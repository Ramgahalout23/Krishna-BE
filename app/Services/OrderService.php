<?php

namespace App\Services;

use App\Repositories\OrderRepository;
use App\Repositories\CartRepository;
use App\Repositories\ProductRepository;
use App\Exceptions\AppError;
use App\Models\CustomDesign;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OrderService
{
    protected array $validTransitions = [
        'PENDING' => ['CONFIRMED', 'CANCELLED'],
        'CONFIRMED' => ['PROCESSING', 'CANCELLED'],
        'PROCESSING' => ['SHIPPED', 'CANCELLED'],
        'SHIPPED' => ['DELIVERED'],
        'DELIVERED' => ['RETURNED', 'RETURN_REQUESTED'],
        'CANCELLED' => [],
        'RETURNED' => [],
        'RETURN_REQUESTED' => ['RETURNED', 'CANCELLED'],
    ];

    public function __construct(
        protected OrderRepository $orderRepository,
        protected CartRepository $cartRepository
    ) {}

    public function createOrder(string $userId, array $data, ?Collection $preloadedProducts = null, ?Collection $preloadedVariants = null): array
    {
        if (empty($data['items'])) {
            throw AppError::validation('Order must contain at least one item');
        }

        $total = collect($data['items'])->sum(fn($item) => ($item['price'] ?? 0) * ($item['quantity'] ?? 1));
        if ($total <= 0) throw AppError::validation('Invalid order total');

        $orderNumber = 'ORD-' . now()->timestamp . '-' . strtoupper(substr(uniqid(), -4));

        // Resolve default address
        $shippingAddressId = $data['shipping_address_id'];
        if ($shippingAddressId === 'default') {
            $address = $this->orderRepository->getUserDefaultAddress($userId);
            if (!$address) throw AppError::validation('No default shipping address found');
            $shippingAddressId = $address->id;
        }

        // Pre-load variants and products for all items in the order (avoid N+1)
        // Accept pre-loaded data from CheckoutController to eliminate redundant queries
        $productIds = collect($data['items'])->pluck('product_id')->unique()
            ->reject(fn($id) => $id === ProductRepository::CUSTOM_TEE_PRODUCT_ID)
            ->toArray();
        $variantsByProduct = $preloadedVariants ?? ProductVariant::whereIn('product_id', $productIds)
            ->orderBy('quantity', 'desc')
            ->get()
            ->groupBy('product_id');
        $productsById = $preloadedProducts ?? Product::whereIn('id', $productIds)->get()->keyBy('id');

        // Wrap all DB writes in a transaction for atomicity (matching TS CheckoutService)
        $order = DB::transaction(function () use ($data, $userId, $total, $orderNumber, $shippingAddressId, $variantsByProduct, $productsById) {
            $order = $this->orderRepository->create([
                'order_number' => $orderNumber,
                'user_id' => $userId,
                'shipping_address_id' => $shippingAddressId,
                'billing_address_id' => $data['billing_address_id'] ?? null,
                'subtotal' => $total,
                'tax' => $data['tax'] ?? 0,
                'shipping_cost' => $data['shipping_cost'] ?? 0,
                'discount' => $data['discount'] ?? 0,
                'total' => $total + ($data['tax'] ?? 0) + ($data['shipping_cost'] ?? 0) - ($data['discount'] ?? 0),
                'status' => ($data['payment_method'] ?? '') === 'COD' ? 'CONFIRMED' : 'PENDING',
                'confirmed_at' => ($data['payment_method'] ?? '') === 'COD' ? now() : null,
                'notes' => $data['notes'] ?? null,
            ]);

            // Build order items and collect stock deductions for batch processing
            $orderItemsData = [];
            $variantDeductions = [];  // [variantId => quantity]
            $productDeductions = [];  // [productId => quantity]

            foreach ($data['items'] as $itemIndex => $item) {
                $variantId = null;
                $isCustomTee = $item['product_id'] === ProductRepository::CUSTOM_TEE_PRODUCT_ID;

                // Custom tees are print-on-demand — no stock to check or deduct
                if ($isCustomTee) {
                    $orderItemsData[] = [
                        'order_id' => $order->id,
                        'user_id' => $userId,
                        'product_id' => $item['product_id'],
                        'variant_id' => null,
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'total' => ($item['price'] ?? 0) * ($item['quantity'] ?? 1),
                        'item_index' => $itemIndex,
                    ];
                    continue;
                }

                $variants = $variantsByProduct->get($item['product_id']);

                if ($variants && $variants->isNotEmpty()) {
                    // Find variant with sufficient stock
                    $variant = $variants->firstWhere('quantity', '>=', $item['quantity']);
                    if (!$variant) {
                        $totalVariantStock = $variants->sum('quantity');
                        $variantName = $variants->first()->name ?? 'Product';
                        throw AppError::validation(
                            "Insufficient variant stock for \"{$variantName}\". Available across variants: {$totalVariantStock}, requested: {$item['quantity']}"
                        );
                    }
                    $variantId = $variant->id;
                    $variantDeductions[$variant->id] = ($variantDeductions[$variant->id] ?? 0) + $item['quantity'];
                } else {
                    // No variants — fall back to product-level stock (pre-loaded above)
                    $product = $productsById->get($item['product_id']);
                    if (!$product || $product->quantity < $item['quantity']) {
                        $productName = $product ? $product->name : 'Product';
                        $availableStock = $product ? $product->quantity : 0;
                        throw AppError::validation(
                            "Insufficient stock for \"{$productName}\". Available: {$availableStock}, requested: {$item['quantity']}"
                        );
                    }
                    $productDeductions[$item['product_id']] = ($productDeductions[$item['product_id']] ?? 0) + $item['quantity'];
                }

                $orderItemsData[] = [
                    'order_id' => $order->id,
                    'user_id' => $userId,
                    'product_id' => $item['product_id'],
                    'variant_id' => $variantId,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => ($item['price'] ?? 0) * ($item['quantity'] ?? 1),
                    'item_index' => $itemIndex,
                ];
            }

            $this->orderRepository->createOrderItems($orderItemsData);

            // Batch deduct variant stock — single query (parallel equivalent of TS Promise.all)
            if (!empty($variantDeductions)) {
                $cases = [];
                $ids = [];
                foreach ($variantDeductions as $vId => $qty) {
                    $escapedId = str_replace("'", "''", $vId);
                    $cases[] = "WHEN id = '{$escapedId}' THEN quantity - {$qty}";
                    $ids[] = "'{$escapedId}'";
                }
                DB::statement(
                    'UPDATE product_variants SET quantity = CASE ' . implode(' ', $cases) . ' ELSE quantity END WHERE id IN (' . implode(',', $ids) . ')'
                );
            }

            // Batch deduct product stock — single query
            if (!empty($productDeductions)) {
                $cases = [];
                $ids = [];
                foreach ($productDeductions as $pId => $qty) {
                    $escapedId = str_replace("'", "''", $pId);
                    $cases[] = "WHEN id = '{$escapedId}' THEN quantity - {$qty}";
                    $ids[] = "'{$escapedId}'";
                }
                DB::statement(
                    'UPDATE products SET quantity = CASE ' . implode(' ', $cases) . ' ELSE quantity END WHERE id IN (' . implode(',', $ids) . ')'
                );
            }

            // Create payment record if payment method provided
            if (!empty($data['payment_method'])) {
                $this->orderRepository->createPayment([
                    'order_id' => $order->id,
                    'method' => $data['payment_method'],
                    'amount' => $total,
                    'status' => 'PENDING',
                ]);
            }

            // Clear cart within transaction
            $this->cartRepository->clearCart($userId);

            // Bump products cache version so product detail pages refetch fresh stock counts
            Cache::increment('products_cache_version');

            // Clear homepage cached data so featured/new/bestseller lists reflect this new order
            Cache::forget('homepage_all');

            // Clear admin dashboard cache so metrics (total orders, revenue, etc.) reflect this new order immediately
            app(\App\Repositories\AdminRepository::class)->clearDashboardCache();

            return $order;
        });

        // ── Dispatch all post-order processing to a single queue job ──
        // This moves ALL email/SMS/webhook/socket/notification work off the critical path,
        // so the HTTP response returns immediately after the transaction completes.
        \App\Jobs\ProcessOrderAfterCreation::dispatch(
            $order->id,
            $userId,
            $data['items'],
            (float) $total,
            $data['payment_method'] ?? null
        );

        // Return the created order with images loaded (use load() instead of fresh()->load()
        // to avoid an unnecessary SELECT — we just created this order in the transaction)
        $orderArray = $order->load('items.product.images', 'items.variant', 'payment')->toArray();

        // Set imageUrl on each item from product images (lightweight mapping, no extra queries)
        // Custom designs are created AFTER this call by CheckoutController, so they handle
        // their own image injection. But we need imageUrl for real product items.
        if (isset($orderArray['items']) && is_array($orderArray['items'])) {
            foreach ($orderArray['items'] as &$item) {
                $item['imageUrl'] = $item['product']['images'][0]['url'] ?? $item['image_url'] ?? null;
            }
            unset($item);
        }

        // Cache the order so the thank-you page GET /orders/{id} hits warm cache immediately
        Cache::put('order_' . $order->id, $orderArray, now()->addMinutes(3));

        return $orderArray;
    }

    public function getOrder(string $orderId): array
    {
        // Check the cache first — the thank-you page may re-fetch after Razorpay verification
        $cached = Cache::get('order_' . $orderId);
        if ($cached !== null) {
            return $cached;
        }

        $order = $this->orderRepository->findWithDetails($orderId);
        if (!$order) throw AppError::notFound('Order not found');

        $data = $order->toArray();

        // ── Order-level camelCase mapping ──
        $data['orderNumber']      = $data['order_number'] ?? null;
        $data['createdAt']        = $data['created_at'] ?? null;
        $data['updatedAt']        = $data['updated_at'] ?? null;
        $data['shippingCost']     = $data['shipping_cost'] ?? 0;
        $data['couponId']         = $data['coupon_id'] ?? null;
        $data['adminNotes']       = $data['admin_notes'] ?? null;
        $data['userId']           = $data['user_id'] ?? null;
        $data['totalAmount']      = $data['total'] ?? 0;
        $data['paymentMethod']    = $data['payment_method'] ?? null;

        // Timeline timestamp fields
        $data['confirmedAt']        = $data['confirmed_at'] ?? null;
        $data['processingAt']       = $data['processing_at'] ?? null;
        $data['shippedAt']          = $data['shipped_at'] ?? null;
        $data['deliveredAt']        = $data['delivered_at'] ?? null;
        $data['cancelledAt']        = $data['cancelled_at'] ?? null;
        // These columns may not exist in the orders table yet
        $data['returnRequestedAt']  = $data['return_requested_at'] ?? null;
        $data['returnedAt']         = $data['returned_at'] ?? null;

        // ── Customer name ──
        if ($order->relationLoaded('user') && $order->user) {
            $data['customerName'] = trim(($order->user->first_name ?? '') . ' ' . ($order->user->last_name ?? '')) ?: ($order->user->email ?? 'Guest');
        } else {
            $data['customerName'] = 'Guest';
        }

        // ── User nested fields ──
        if (isset($data['user']) && is_array($data['user'])) {
            $data['user']['firstName']   = $data['user']['first_name'] ?? '';
            $data['user']['lastName']    = $data['user']['last_name'] ?? '';
            $data['user']['phoneNumber'] = $data['user']['phone_number'] ?? null;
            $data['user']['createdAt']   = $data['user']['created_at'] ?? null;
        }

        // ── Payment nested fields ──
        if (isset($data['payment']) && is_array($data['payment'])) {
            $data['payment']['transactionId']   = $data['payment']['transaction_id'] ?? null;
            $data['payment']['gatewayResponse'] = $data['payment']['gateway_response'] ?? null;
        }

        // ── Order Items ──
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as &$item) {
                $item['variantId']   = $item['variant_id'] ?? null;
                $item['productName'] = $item['product']['name'] ?? $item['name'] ?? 'Product';
                $item['createdAt']   = $item['created_at'] ?? null;
            }
            unset($item);

            // Map custom design data, imageUrl, size/color, and product.createdAt
            // onto items via shared helper
            $this->mapItemsWithCustomDesigns($data, $order);
        }

        // ── Shipping Address (toArray() keys use the relationship method name = camelCase) ──
        if (isset($data['shippingAddress']) && is_array($data['shippingAddress'])) {
            $data['shippingAddress'] = $this->mapOrderAddress($data['shippingAddress']);
        }

        // ── Billing Address ──
        if (isset($data['billingAddress']) && is_array($data['billingAddress'])) {
            $data['billingAddress'] = $this->mapOrderAddress($data['billingAddress']);
        }

        // ── Timeline ──
        if (isset($data['timeline']) && is_array($data['timeline'])) {
            foreach ($data['timeline'] as &$entry) {
                $entry['createdAt'] = $entry['created_at'] ?? null;
            }
            unset($entry);
        }

        // Cache the fully-mapped response for 3 minutes
        Cache::put('order_' . $orderId, $data, now()->addMinutes(3));

        return $data;
    }

    /**
     * Load custom designs for an order and map onto items by item_index.
     * Used by createOrder() and any method that returns items.
     */
    private function mapItemsWithCustomDesigns(array &$orderArray, $order): void
    {
        if (!isset($orderArray['items']) || !is_array($orderArray['items'])) {
            return;
        }

        // Use eager loaded relation if available, otherwise query
        $customDesigns = $order->relationLoaded('customDesigns')
            ? $order->customDesigns->keyBy('item_index')
            : CustomDesign::where('order_id', $order->id)
                ->get()
                ->keyBy('item_index');

        $this->mapItemsFromCustomDesigns($orderArray['items'], $customDesigns);
    }

    /**
     * Map item fields (imageUrl, size/color) and custom design data onto items
     * using a pre-loaded collection of CustomDesign records keyed by item_index.
     *
     * Used by mapItemsWithCustomDesigns() (single order) and getUserOrders()
     * (batch-loaded designs for multiple orders).
     */
    private function mapItemsFromCustomDesigns(array &$items, Collection $customDesigns): void
    {
        foreach ($items as &$item) {
            $item['imageUrl'] = $item['product']['images'][0]['url'] ?? $item['image_url'] ?? null;
            // Map size/color from variant attributes
            if (!empty($item['variant']) && !empty($item['variant']['attributes'])) {
                $attrs = $item['variant']['attributes'];
                $item['size'] = is_array($attrs) ? ($attrs['size'] ?? $attrs['Size'] ?? null) : null;
                $item['color'] = is_array($attrs) ? ($attrs['color'] ?? $attrs['Color'] ?? null) : null;
            }

            // Map custom design data by item_index
            $idx = $item['item_index'] ?? null;
            if ($idx !== null && $customDesigns->has($idx)) {
                $cd = $customDesigns->get($idx);
                $item['customDesign'] = [
                    'design_file_url' => $cd->design_file_url,
                    'design_notes' => $cd->design_notes,
                    'placement' => $cd->placement,
                    'color' => $cd->color,
                    'size' => $cd->size,
                    'design_filename' => $cd->design_filename,
                ];
                // Set item.image to the front design URL for existing admin frontend
                if ($cd->design_file_url) {
                    $item['image'] = $cd->design_file_url;
                }
            }

            if (isset($item['product']) && is_array($item['product'])) {
                $item['product']['createdAt'] = $item['product']['created_at'] ?? null;
            }
        }
        unset($item);
    }

    /**
     * Map snake_case address fields to camelCase.
     */
    private function mapOrderAddress(array $addr): array
    {
        $addr['firstName']    = $addr['first_name'] ?? '';
        $addr['lastName']     = $addr['last_name'] ?? '';
        $addr['phoneNumber']  = $addr['phone_number'] ?? null;
        $addr['addressLine1'] = $addr['address_line1'] ?? '';
        $addr['addressLine2'] = $addr['address_line2'] ?? '';
        $addr['zipCode']      = $addr['zip_code'] ?? '';
        $addr['isDefault']    = $addr['is_default'] ?? false;
        return $addr;
    }

    public function getByOrderNumber(string $orderNumber): array
    {
        $order = $this->orderRepository->findByOrderNumber($orderNumber);
        if (!$order) throw AppError::notFound('Order not found');

        $data = $order->toArray();

        // Map imageUrl, size/color, and custom design data onto items via shared helper
        $this->mapItemsWithCustomDesigns($data, $order);

        return $data;
    }

    public function getUserOrders(string $userId, array $filters = []): array
    {
        $paginator = $this->orderRepository->getUserOrders($userId, $filters);
        $result = $paginator->toArray();

        // ── Batch-load custom designs for ALL orders in this page with a single query ──
        $orderIds = [];
        if (isset($result['data']) && is_array($result['data'])) {
            foreach ($result['data'] as $order) {
                if (isset($order['id'])) {
                    $orderIds[] = $order['id'];
                }
            }
        }
        $allCustomDesigns = !empty($orderIds)
            ? CustomDesign::whereIn('order_id', $orderIds)->get()->groupBy('order_id')
            : collect();

        // Map snake_case DB fields to camelCase for frontend and add imageUrl to items
        if (isset($result['data']) && is_array($result['data'])) {
            $result['data'] = array_map(function ($order) use ($allCustomDesigns) {
                $order['createdAt']   = $order['created_at'] ?? null;
                $order['totalAmount'] = $order['total'] ?? 0;
                $order['orderNumber'] = $order['order_number'] ?? null;

                // Look up pre-loaded custom designs for this order, keyed by item_index
                $customDesigns = $allCustomDesigns->get($order['id'])?->keyBy('item_index') ?? collect();

                // Map imageUrl, size/color, productName, productId, and custom design
                // data onto items via shared helper (uses pre-loaded designs)
                if (isset($order['items']) && is_array($order['items'])) {
                    foreach ($order['items'] as &$item) {
                        $item['productName'] = $item['product']['name'] ?? $item['name'] ?? null;
                        $item['productId']   = $item['product_id'] ?? null;
                    }
                    unset($item);

                    // Map imageUrl, size/color, customDesign, and product.createdAt
                    $this->mapItemsFromCustomDesigns($order['items'], $customDesigns);
                }

                return $order;
            }, $result['data']);
        }

        return $result;
    }

    public function getAllOrders(array $filters = []): array
    {
        $paginator = $this->orderRepository->findMany($filters);

        // Transform each order to include camelCase fields matching frontend expectations
        $transformed = collect($paginator->items())->map(function ($order) {
            $data = $order->toArray();
            $data['customerName'] = $order->relationLoaded('user') && $order->user
                ? trim(($order->user->first_name ?? '') . ' ' . ($order->user->last_name ?? '')) ?: ($order->user->email ?? 'Guest')
                : 'Guest';
            $data['createdAt']   = $data['created_at'] ?? null;
            $data['updatedAt']   = $data['updated_at'] ?? null;
            $data['userId']      = $data['user_id'] ?? null;
            $data['totalAmount'] = $data['total'] ?? 0;

            // Map imageUrl, size/color, and custom design image onto items via shared helper
            $this->mapItemsWithCustomDesigns($data, $order);

            return $data;
        });

        return [
            'orders'     => $transformed->toArray(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'pages' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
            ],
        ];
    }

    public function updateStatus(string $orderId, string $newStatus): array
    {
        $order = $this->orderRepository->findByIdOrFail($orderId);

        if (!isset($this->validTransitions[$order->status]) || !in_array($newStatus, $this->validTransitions[$order->status])) {
            throw AppError::validation("Cannot transition from {$order->status} to {$newStatus}");
        }

        $updated = $this->orderRepository->updateStatus($orderId, $newStatus);

        // Invalidate cache AFTER the DB update to avoid a race where stale data is re-cached
        Cache::forget('order_' . $orderId);

        // ── Update sold_count when order reaches or leaves a completed status ──
        $soldStatuses = ['DELIVERED', 'SHIPPED', 'COMPLETED'];
        $enteringSold = in_array($newStatus, $soldStatuses);
        $leavingSold = in_array($order->status, $soldStatuses) && !in_array($newStatus, $soldStatuses);

        if ($enteringSold || $leavingSold) {
            $productIds = \App\Models\OrderItem::where('order_id', $orderId)
                ->where('product_id', '!=', ProductRepository::CUSTOM_TEE_PRODUCT_ID)
                ->pluck('product_id')
                ->unique()
                ->toArray();
            if (!empty($productIds)) {
                app(\App\Repositories\ProductRepository::class)->batchUpdateProductSoldCount($productIds);
            }
        }

        // ── Dispatch webhook + notifications asynchronously ──
        \App\Jobs\DispatchWebhookJob::dispatch('order.status_updated', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'previous_status' => $order->status,
            'new_status' => $newStatus,
            'user_id' => $order->user_id,
        ]);

        // Queue SMS + email notifications so the admin response returns immediately
        \App\Jobs\ProcessOrderStatusUpdate::dispatch(
            $orderId,
            $newStatus,
            $order->status
        );

        $updatedArray = $updated->load('items.product.images', 'items.variant')->toArray();

        $this->mapItemsWithCustomDesigns($updatedArray, $updated);

        return $updatedArray;
    }

    public function cancelOrder(string $orderId, ?string $reason = null): array
    {
        $order = $this->orderRepository->findByIdOrFail($orderId);

        if (!in_array($order->status, ['PENDING', 'CONFIRMED'])) {
            throw AppError::validation('Only pending or confirmed orders can be cancelled');
        }

        // Restore stock when cancelling — batch restoration
        // Eager load items to avoid N+1 query
        $order->load('items.product.images');
        $orderItems = $order->items;
        $variantRestores = [];  // [variantId => quantity]
        $productRestores = [];  // [productId => quantity]

        foreach ($orderItems as $item) {
            // Custom tees are print-on-demand — no stock to restore
            if ($item->product_id === ProductRepository::CUSTOM_TEE_PRODUCT_ID) {
                continue;
            }
            if ($item->variant_id) {
                $variantRestores[$item->variant_id] = ($variantRestores[$item->variant_id] ?? 0) + $item->quantity;
            } else {
                $productRestores[$item->product_id] = ($productRestores[$item->product_id] ?? 0) + $item->quantity;
            }
        }

        // Batch restore variant stock — single query
        if (!empty($variantRestores)) {
            $cases = [];
            $ids = [];
            foreach ($variantRestores as $vId => $qty) {
                $escapedId = str_replace("'", "''", $vId);
                $cases[] = "WHEN id = '{$escapedId}' THEN quantity + {$qty}";
                $ids[] = "'{$escapedId}'";
            }
            DB::statement(
                'UPDATE product_variants SET quantity = CASE ' . implode(' ', $cases) . ' ELSE quantity END WHERE id IN (' . implode(',', $ids) . ')'
            );
        }

        // Batch restore product stock — single query
        if (!empty($productRestores)) {
            $cases = [];
            $ids = [];
            foreach ($productRestores as $pId => $qty) {
                $escapedId = str_replace("'", "''", $pId);
                $cases[] = "WHEN id = '{$escapedId}' THEN quantity + {$qty}";
                $ids[] = "'{$escapedId}'";
            }
            DB::statement(
                'UPDATE products SET quantity = CASE ' . implode(' ', $cases) . ' ELSE quantity END WHERE id IN (' . implode(',', $ids) . ')'
            );
        }

        $updated = $this->orderRepository->update($orderId, [
            'status' => 'CANCELLED',
            'notes' => $reason,
        ]);

        // Invalidate product + order caches so stock reflects restoration
        Cache::forget('order_' . $orderId);
        Cache::increment('products_cache_version');

        // ── Webhook: order.cancelled (queued) ──
        \App\Jobs\DispatchWebhookJob::dispatch('order.cancelled', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'user_id' => $order->user_id,
            'reason' => $reason,
        ]);

        // Queue SMS + email cancellation notifications (async)
        \App\Jobs\ProcessOrderStatusUpdate::dispatch(
            $orderId,
            'CANCELLED',
            $order->status
        );

        // Load items with images and variant on the fresh instance and map for consistency
        $updated->load('items.product.images', 'items.variant');
        $updatedArray = $updated->toArray();

        $this->mapItemsWithCustomDesigns($updatedArray, $updated);

        return $updatedArray;
    }

    public function getRevenueStats(?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        return $this->orderRepository->getRevenueStats($startDate, $endDate);
    }

    public function getOrderTracking(string $orderId, string $userId): array
    {
        $order = $this->orderRepository->findWithDetails($orderId);
        if (!$order) throw AppError::notFound('Order not found');

        if ($order->user_id !== $userId) {
            throw AppError::forbidden('You do not have permission to view this order');
        }

        $shipping = $order->shipping;

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'estimated_delivery' => $shipping?->estimated_delivery,
            'tracking_number' => $shipping?->tracking_number,
            'carrier' => $shipping?->carrier,
            'timeline' => array_values(array_filter([
                ['status' => 'ORDER_PLACED', 'date' => $order->created_at, 'description' => 'Order placed'],
                ['status' => 'PROCESSING', 'date' => $order->processing_at, 'description' => 'Order being prepared'],
                ['status' => 'SHIPPED', 'date' => $order->shipped_at, 'description' => 'Order shipped'],
                ['status' => 'DELIVERED', 'date' => $order->delivered_at, 'description' => 'Order delivered'],
            ], fn($t) => !is_null($t['date']))),
        ];
    }

    public function getTotalOrdersCount(): int
    {
        return $this->orderRepository->getTotalOrdersCount();
    }

    public function getPublicTracking(string $orderNumber): array
    {
        $order = $this->orderRepository->findByOrderNumber($orderNumber);
        if (!$order) throw AppError::notFound('Order not found');

        $shipping = $order->shipping;

        $items = $order->items->map(function ($item) {
            return [
                'product_id' => $item->product_id,
                'name' => $item->product?->name,
                'quantity' => $item->quantity,
                'price' => (float) $item->price,
                'total' => (float) $item->total,
                'image_url' => $item->product?->images?->first()?->url,
            ];
        })->toArray();

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'subtotal' => (float) $order->subtotal,
            'total' => (float) $order->total,
            'estimated_delivery' => $shipping?->estimated_delivery,
            'tracking_number' => $shipping?->tracking_number,
            'carrier' => $shipping?->carrier,
            'items' => $items,
            'timeline' => array_values(array_filter([
                ['status' => 'ORDER_PLACED', 'date' => $order->created_at, 'description' => 'Order placed'],
                ['status' => 'PROCESSING', 'date' => $order->processing_at, 'description' => 'Order being prepared'],
                ['status' => 'SHIPPED', 'date' => $order->shipped_at, 'description' => 'Order shipped'],
                ['status' => 'DELIVERED', 'date' => $order->delivered_at, 'description' => 'Order delivered'],
            ], fn($t) => !is_null($t['date']))),
        ];
    }

    public function subscribeToUpdates(string $orderId, string $userId, array $data): array
    {
        $order = $this->orderRepository->findByIdOrFail($orderId);

        if ($order->user_id !== $userId) {
            throw AppError::forbidden('You do not have permission to modify this order');
        }

        $subscriptionData = [
            'subscribed_at' => now()->toIso8601String(),
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email_updates' => $data['email_updates'] ?? true,
            'sms_updates' => $data['sms_updates'] ?? false,
        ];

        $this->orderRepository->update($orderId, ['admin_notes' => json_encode($subscriptionData)]);

        return ['order_id' => $orderId, 'subscribed' => true];
    }

    public function requestReturn(string $orderId, string $userId, string $reason, ?array $items = null): array
    {
        $order = $this->orderRepository->findByIdOrFail($orderId);

        if ($order->user_id !== $userId) {
            throw AppError::forbidden('You do not have permission to request a return for this order');
        }

        $daysSinceDelivery = $order->delivered_at
            ? (int) now()->diffInDays($order->delivered_at)
            : 999;

        if ($daysSinceDelivery > 30) {
            throw AppError::validation('Return period expired (30 days)');
        }

        if ($order->status === 'RETURNED') {
            throw AppError::validation('Order already returned');
        }

        if ($order->status === 'RETURN_REQUESTED') {
            throw AppError::validation('Return already requested for this order');
        }

        // Validate items belong to this order
        $orderItems = $order->items()->pluck('product_id')->toArray();
        if ($items !== null) {
            $invalidItems = array_diff($items, $orderItems);
            if (!empty($invalidItems)) {
                throw AppError::validation('Some items do not belong to this order');
            }
        }

        // Create return request record
        $order->returnRequests()->create([
            'user_id' => $userId,
            'reason' => $reason,
            'status' => 'PENDING',
            'description' => $items ? implode(', ', $items) : null,
        ]);

        // Update order status
        $this->orderRepository->updateStatus($orderId, 'RETURN_REQUESTED');

        // ── Webhook: order.return_requested (queued) ──
        \App\Jobs\DispatchWebhookJob::dispatch('order.return_requested', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'user_id' => $userId,
            'reason' => $reason,
        ]);

        return ['order_id' => $orderId, 'reason' => $reason, 'status' => 'PENDING', 'message' => 'Return request submitted'];
    }

    // All post-order notification logic has been moved to queued jobs:
    // - ProcessOrderAfterCreation (for new orders: email, SMS, webhooks, socket, events)
    // - ProcessOrderStatusUpdate (for status changes: SMS, email)
}
