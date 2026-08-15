<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(protected OrderService $orderService) {}

    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->orderService->getUserOrders(Auth::id(), request()->all())]);
    }

    public function show(string $id): JsonResponse
    {
        try {
            $order = $this->orderService->getOrder($id);
            // Ownership check: users can only see their own orders; admins can see all
            $user = Auth::user();
            if ($user->role === 'CUSTOMER' && isset($order['user_id']) && $order['user_id'] !== $user->id) {
                return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
            }
            return response()->json(['success' => true, 'data' => $order]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'shipping_address_id' => 'required|string',
                'billing_address_id' => 'nullable|string',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|string',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price' => 'required|numeric',
                'shipping_cost' => 'nullable|numeric',
                'discount' => 'nullable|numeric',
                'payment_method' => 'nullable|string',
                'notes' => 'nullable|string',
            ]);

            $order = $this->orderService->createOrder(Auth::id(), $validated);
            return response()->json(['success' => true, 'message' => 'Order created', 'data' => $order], 201);
        } catch (AppError $e) { return $e->render(); }
    }

    public function cancel(string $id, Request $request): JsonResponse
    {
        try {
            $order = $this->orderService->cancelOrder($id, $request->reason);
            return response()->json(['success' => true, 'message' => 'Order cancelled', 'data' => $order]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate(['status' => 'required|string']);
            $order = $this->orderService->updateStatus($id, $validated['status']);
            return response()->json(['success' => true, 'message' => 'Status updated', 'data' => $order]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function tracking(string $orderNumber): JsonResponse
    {
        try {
            $order = $this->orderService->getByOrderNumber($orderNumber);
            return response()->json(['success' => true, 'data' => $order]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function allOrders(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->orderService->getAllOrders($request->all())]);
    }

    /**
     * Admin: Edit order items (add/update/remove items, adjust prices, manage stock).
     * Deletes old items and recreates with audit trail in admin_notes.
     * Automatically restores stock for removed items and deducts stock for new/extra items.
     */
    public function editOrder(Request $request, string $id): JsonResponse
    {
        try {
            $order = \App\Models\Order::with('items.product')->findOrFail($id);

            $validated = $request->validate([
                'items' => 'sometimes|array',
                'items.*.product_id' => 'required_with:items|string',
                'items.*.quantity' => 'required_with:items|integer|min:1',
                'items.*.price' => 'required_with:items|numeric|min:0',
                'items.*.variant_id' => 'nullable|string',
                'subtotal' => 'nullable|numeric|min:0',
                'discount' => 'nullable|numeric|min:0',
                'shipping_cost' => 'nullable|numeric|min:0',
                'tax' => 'nullable|numeric|min:0',
                'admin_notes' => 'nullable|string',
            ]);

            // ── Pre-load products + variants referenced in this edit (avoid N+1 inside transaction) ──
            $productsById = collect();
            $variantsById = collect();

            if (isset($validated['items'])) {
                $editProductIds = collect($validated['items'])
                    ->pluck('product_id')
                    ->merge($order->items->pluck('product_id'))
                    ->filter()->unique()->values()->toArray();

                $editVariantIds = collect($validated['items'])
                    ->whereNotNull('variant_id')
                    ->pluck('variant_id')
                    ->unique()->values()->toArray();

                $productsById = \App\Models\Product::whereIn('id', $editProductIds)->get()->keyBy('id');
                $variantsById = \App\Models\ProductVariant::whereIn('id', $editVariantIds)->get()->keyBy('id');
            }

            DB::transaction(function () use ($order, $validated, $productsById, $variantsById) {
                if (isset($validated['items'])) {
                    // ── Store old items for audit trail & stock restoration ──
                    $oldItemsSummary = $order->items->map(fn($i) => [
                        'product_id' => $i->product_id,
                        'variant_id' => $i->variant_id,
                        'name' => $i->product?->name ?? 'Unknown',
                        'qty' => $i->quantity,
                        'price' => $i->price,
                        'total' => $i->total,
                    ])->toArray();

                    // Build old items lookup for diff calculation: [productId_variantId => quantity]
                    $oldItemsMap = [];
                    foreach ($order->items as $oldItem) {
                        $key = $oldItem->product_id . '_' . ($oldItem->variant_id ?? 'null');
                        $oldItemsMap[$key] = ($oldItemsMap[$key] ?? 0) + $oldItem->quantity;
                    }

                    // Build new items map
                    $newItemsMap = [];
                    foreach ($validated['items'] as $newItem) {
                        $key = $newItem['product_id'] . '_' . ($newItem['variant_id'] ?? 'null');
                        $newItemsMap[$key] = ($newItemsMap[$key] ?? 0) + $newItem['quantity'];

                        // Validate product exists (in-memory from pre-loaded map)
                        $product = $productsById->get($newItem['product_id']);
                        if (!$product) {
                            throw new \Exception("Product {$newItem['product_id']} not found");
                        }
                    }

                    // ── Calculate stock changes ──
                    $variantRestores = [];   // [variantId => quantity to add back]
                    $variantDeductions = []; // [variantId => quantity to deduct]
                    $productRestores = [];   // [productId => quantity to add back]
                    $productDeductions = []; // [productId => quantity to deduct]

                    // Items that were removed or reduced
                    foreach ($oldItemsMap as $key => $oldQty) {
                        $newQty = $newItemsMap[$key] ?? 0;
                        $diff = $oldQty - $newQty;
                        if ($diff > 0) {
                            // Restore stock for removed/reduced items
                            [$pId, $vId] = explode('_', $key, 2);
                            if ($vId !== 'null') {
                                $variantRestores[$vId] = ($variantRestores[$vId] ?? 0) + $diff;
                            } else {
                                $productRestores[$pId] = ($productRestores[$pId] ?? 0) + $diff;
                            }
                        }
                    }

                    // Items that were added or increased
                    foreach ($newItemsMap as $key => $newQty) {
                        $oldQty = $oldItemsMap[$key] ?? 0;
                        $diff = $newQty - $oldQty;
                        if ($diff > 0) {
                            // Deduct stock for new/additional items
                            [$pId, $vId] = explode('_', $key, 2);
                            if ($vId !== 'null') {
                                // Check variant stock (in-memory from pre-loaded map)
                                $variant = $variantsById->get($vId);
                                if (!$variant || $variant->quantity < $diff) {
                                    $variantName = $variant->name ?? 'Variant';
                                    $available = $variant ? $variant->quantity : 0;
                                    throw new \Exception(
                                        "Insufficient variant stock for \"{$variantName}\". Available: {$available}, required extra: {$diff}"
                                    );
                                }
                                $variantDeductions[$vId] = ($variantDeductions[$vId] ?? 0) + $diff;
                            } else {
                                // Check product stock (in-memory from pre-loaded map)
                                $product = $productsById->get($pId);
                                $available = $product ? $product->quantity : 0;
                                if (!$product || $available < $diff) {
                                    $productName = $product->name ?? 'Product';
                                    throw new \Exception(
                                        "Insufficient stock for \"{$productName}\". Available: {$available}, required extra: {$diff}"
                                    );
                                }
                                $productDeductions[$pId] = ($productDeductions[$pId] ?? 0) + $diff;
                            }
                        }
                    }

                    // ── Apply variant stock changes (batch) ──
                    if (!empty($variantRestores)) {
                        $cases = [];
                        $ids = [];
                        foreach ($variantRestores as $vId => $qty) {
                            $eId = str_replace("'", "''", $vId);
                            $cases[] = "WHEN id = '{$eId}' THEN quantity + {$qty}";
                            $ids[] = "'{$eId}'";
                        }
                        DB::statement(
                            'UPDATE product_variants SET quantity = CASE ' . implode(' ', $cases) . ' ELSE quantity END WHERE id IN (' . implode(',', $ids) . ')'
                        );
                    }

                    if (!empty($variantDeductions)) {
                        $cases = [];
                        $ids = [];
                        foreach ($variantDeductions as $vId => $qty) {
                            $eId = str_replace("'", "''", $vId);
                            $cases[] = "WHEN id = '{$eId}' THEN quantity - {$qty}";
                            $ids[] = "'{$eId}'";
                        }
                        DB::statement(
                            'UPDATE product_variants SET quantity = CASE ' . implode(' ', $cases) . ' ELSE quantity END WHERE id IN (' . implode(',', $ids) . ')'
                        );
                    }

                    // ── Apply product stock changes (batch) ──
                    if (!empty($productRestores)) {
                        $cases = [];
                        $ids = [];
                        foreach ($productRestores as $pId => $qty) {
                            $eId = str_replace("'", "''", $pId);
                            $cases[] = "WHEN id = '{$eId}' THEN quantity + {$qty}";
                            $ids[] = "'{$eId}'";
                        }
                        DB::statement(
                            'UPDATE products SET quantity = CASE ' . implode(' ', $cases) . ' ELSE quantity END WHERE id IN (' . implode(',', $ids) . ')'
                        );
                    }

                    if (!empty($productDeductions)) {
                        $cases = [];
                        $ids = [];
                        foreach ($productDeductions as $pId => $qty) {
                            $eId = str_replace("'", "''", $pId);
                            $cases[] = "WHEN id = '{$eId}' THEN quantity - {$qty}";
                            $ids[] = "'{$eId}'";
                        }
                        DB::statement(
                            'UPDATE products SET quantity = CASE ' . implode(' ', $cases) . ' ELSE quantity END WHERE id IN (' . implode(',', $ids) . ')'
                        );
                    }

                    // ── Remove old items and batch-create new ones ──
                    $order->items()->delete();

                    $now = now();
                    $bulkItems = array_map(function ($item) use ($order, $now) {
                        return [
                            'id' => (string) \Illuminate\Support\Str::uuid(),
                            'order_id' => $order->id,
                            'user_id' => $order->user_id,
                            'product_id' => $item['product_id'],
                            'variant_id' => $item['variant_id'] ?? null,
                            'quantity' => $item['quantity'],
                            'price' => $item['price'],
                            'total' => $item['price'] * $item['quantity'],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }, $validated['items']);

                    \App\Models\OrderItem::insert($bulkItems);

                    // ── Build audit trail (in-memory from pre-loaded map) ──
                    $changes = [];
                    foreach ($oldItemsMap as $key => $oldQty) {
                        $newQty = $newItemsMap[$key] ?? 0;
                        if ($oldQty !== $newQty) {
                            [$pId, $vId] = explode('_', $key, 2);
                            $productName = $productsById->get($pId)?->name ?? $pId;
                            $changes[] = "{$productName}: {$oldQty} → {$newQty}";
                        }
                    }
                    foreach ($newItemsMap as $key => $newQty) {
                        if (!isset($oldItemsMap[$key])) {
                            [$pId, $vId] = explode('_', $key, 2);
                            $productName = $productsById->get($pId)?->name ?? $pId;
                            $changes[] = "{$productName}: new (×{$newQty})";
                        }
                    }

                    $changeSummary = empty($changes) ? 'No item qty changes' : implode('; ', $changes);

                    // Check for price changes
                    $priceChanges = [];
                    foreach ($validated['items'] as $newItem) {
                        $oldItem = collect($oldItemsSummary)->firstWhere('product_id', $newItem['product_id']);
                        if ($oldItem && (float)$oldItem['price'] !== (float)$newItem['price']) {
                            $priceChanges[] = "{$oldItem['name']}: ₹{$oldItem['price']} → ₹{$newItem['price']}";
                        }
                    }
                    $priceSummary = empty($priceChanges) ? '' : ' Price changes: ' . implode('; ', $priceChanges);

                    $auditEntry = '[Order edited ' . now()->toDateTimeString() . '] ' .
                        'Admin: ' . (Auth::user()?->email ?? 'unknown') . '. ' .
                        $changeSummary . '.' . $priceSummary;

                    $existingNotes = $order->admin_notes ?? '';
                    $order->admin_notes = $existingNotes ? $existingNotes . "\n" . $auditEntry : $auditEntry;
                }

                // ── Recalculate totals ──
                $itemsTotal = $order->items()->sum(DB::raw('price * quantity'));
                $subtotal = $validated['subtotal'] ?? $itemsTotal;
                $discount = $validated['discount'] ?? $order->discount;
                $shippingCost = $validated['shipping_cost'] ?? $order->shipping_cost;
                $tax = $validated['tax'] ?? $order->tax;
                $total = $subtotal + $tax + $shippingCost - $discount;

                $updateData = [
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'shipping_cost' => $shippingCost,
                    'tax' => $tax,
                    'total' => max(0, $total),
                ];

                if (isset($validated['admin_notes']) && !isset($validated['items'])) {
                    $updateData['admin_notes'] = $validated['admin_notes'];
                }

                $order->update($updateData);
            });

            // Invalidate the cached order response
            Cache::forget('order_' . $id);

            $order->refresh();
            $order->load('items.product.images', 'payment', 'user', 'shippingAddress', 'billingAddress', 'timeline');

            $data = $order->toArray();

            // Map imageUrl onto items for frontend consistency
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as &$item) {
                    $item['imageUrl'] = $item['product']['images'][0]['url'] ?? $item['image_url'] ?? null;
                }
                unset($item);
            }

            return response()->json(['success' => true, 'message' => 'Order updated', 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Public endpoint: Returns recent completed orders with customer name & city
     * for FOMO purchase notifications on the storefront.
     * No sensitive data (email, phone, address) is exposed.
     */
    public function recentOrders(): JsonResponse
    {
        $orders = \App\Models\Order::with(['user', 'shippingAddress', 'items.product'])
            ->whereIn('status', ['DELIVERED', 'CONFIRMED', 'PROCESSING', 'SHIPPED'])
            ->latest()
            ->take(20)
            ->get()
            ->map(fn($order) => [
                'name' => trim(($order->user?->first_name ?? '') . ' ' . ($order->user?->last_name ?? '')),
                'city' => $order->shippingAddress?->city ?? '',
                'product' => $order->items->first()?->product?->name ?? '',
            ])
            ->filter(fn($item) => !empty($item['name']))
            ->values()
            ->toArray();

        return response()->json(['success' => true, 'data' => $orders]);
    }

    public function getRevenueStats(Request $request): JsonResponse
    {
        $startDate = $request->start_date ? new \DateTime($request->start_date) : null;
        $endDate = $request->end_date ? new \DateTime($request->end_date) : null;
        $stats = $this->orderService->getRevenueStats($startDate, $endDate);
        return response()->json(['success' => true, 'data' => $stats]);
    }

    public function getOrderTracking(string $id, Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $tracking = $this->orderService->getOrderTracking($id, $userId);
            return response()->json(['success' => true, 'data' => $tracking]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function subscribeToUpdates(string $id, Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'nullable|email',
                'phone' => 'nullable|string',
                'email_updates' => 'nullable|boolean',
                'sms_updates' => 'nullable|boolean',
            ]);

            $result = $this->orderService->subscribeToUpdates($id, Auth::id(), $validated);
            return response()->json(['success' => true, 'message' => 'Subscribed to order updates', 'data' => $result]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function requestReturn(string $id, Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reason' => 'required|string',
                'items' => 'nullable|array',
            ]);

            $result = $this->orderService->requestReturn($id, Auth::id(), $validated['reason'], $validated['items'] ?? null);
            return response()->json(['success' => true, 'message' => 'Return request submitted', 'data' => $result], 201);
        } catch (AppError $e) { return $e->render(); }
    }
}
