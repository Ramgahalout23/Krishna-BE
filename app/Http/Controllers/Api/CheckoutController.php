<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CheckoutService;
use App\Exceptions\AppError;
use App\Models\Address;
use App\Models\CustomDesign;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\OrderService;
use App\Repositories\ProductRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function __construct(
        protected CheckoutService $checkoutService,
        protected \App\Services\FlashSaleService $flashSaleService,
        protected \App\Services\VolumeDiscountService $volumeDiscountService
    ) {}

    public function summary(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->checkoutService->getSummary(Auth::id())]);
    }

    public function calculateShipping(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->checkoutService->calculateShipping(Auth::id())]);
    }

    public function applyCoupon(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['code' => 'required|string', 'subtotal' => 'required|numeric']);
            $result = $this->checkoutService->applyCoupon($validated['code'], $validated['subtotal']);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function removeCoupon(): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Coupon removed']);
    }

    /**
     * Calculate volume/bundle discounts for given cart items.
     * POST /api/v1/checkout/volume-discount
     *
     * Accepts items array and returns the applicable volume discount
     * based on item quantities (Buy 2 = 5%, Buy 3 = 10%, Buy 4+ = 15%).
     * This is a public endpoint so the frontend can display savings before checkout.
     */
    public function calculateVolumeDiscount(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.productId' => 'required|string',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price' => 'required|numeric|min:0',
                'items.*.name' => 'nullable|string',
            ]);

            // Build plain items array matching the service format
            $plainItems = array_map(function ($item) {
                return [
                    'product_id'   => $item['productId'],
                    'product_name' => $item['name'] ?? 'Product',
                    'quantity'     => (int) $item['quantity'],
                    'price'        => (float) $item['price'],
                ];
            }, $validated['items']);

            $result = $this->volumeDiscountService->calculateDiscount($plainItems);

            return response()->json([
                'success' => true,
                'data' => [
                    'total_discount' => $result['total_discount'],
                    'items_discount' => $result['items_discount'],
                    'applied_tiers'  => $result['applied_tiers'],
                    'tiers'          => \App\Services\VolumeDiscountService::TIERS,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        }
    }


    /**
     * Process checkout — supports both authenticated users and guest checkout.
     * POST /api/v1/checkout
     *
     * All users send items directly in the request body (productId, quantity).
     * Product prices are looked up from the database to prevent price tampering.
     * Guest users: a user account is created on-the-fly for referential integrity.
     */
    public function checkout(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $isGuest = !$user;

            // ── Validation rules ──
            $rules = [
                'items' => 'required|array|min:1',
                'items.*.productId' => 'required|string',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price' => 'nullable|numeric|min:0',
                'items.*.isCustom' => 'nullable|boolean',
                'items.*.customDesign' => 'nullable|array',
                'items.*.customDesign.designFile' => 'nullable|string',
                'items.*.customDesign.designNotes' => 'nullable|string',
                'items.*.customDesign.placement' => 'nullable|string',
                'items.*.customDesign.color' => 'nullable|array',
                'items.*.customDesign.color.name' => 'nullable|string',
                'items.*.customDesign.color.hex' => 'nullable|string',
                'items.*.customDesign.serverUrl' => 'nullable|string',
                'items.*.customDesign.path' => 'nullable|string',

                'shippingAddress.firstName' => 'required|string|max:255',
                'shippingAddress.lastName' => 'required|string|max:255',
                'shippingAddress.addressLine1' => 'required|string|max:255',
                'shippingAddress.addressLine2' => 'nullable|string|max:255',
                'shippingAddress.city' => 'required|string|max:255',
                'shippingAddress.state' => 'nullable|string|max:255',
                'shippingAddress.zipCode' => 'nullable|string|max:20',
                'shippingAddress.country' => 'nullable|string|max:100',
                'shippingAddress.phone' => 'required|string|max:20',
                'shippingAddress.email' => 'nullable|email|max:255',
                'paymentMethod' => 'nullable|string',
                'couponCode' => 'nullable|string',
                'shippingMethod' => 'nullable|string',
                'notes' => 'nullable|string',
            ];

            // Guest-only validation fields
            if ($isGuest) {
                $rules['createAccount'] = 'nullable|boolean';
                $rules['password'] = 'nullable|string|min:8';
            }

            $validated = $request->validate($rules);

            // ── Resolve or create a user (guests get an on-the-fly account) ──
            if ($isGuest) {
                $result = $this->resolveGuestUser($validated);
                $user = $result['user'];
                // Login the guest user so Auth::id() works downstream
                Auth::login($user);
            }

            // ── Create an Address record from the inline shipping data ──
            $sa = $validated['shippingAddress'];
            $address = Address::create([
                'user_id' => $user->id,
                'type' => 'HOME',
                'first_name' => $sa['firstName'],
                'last_name' => $sa['lastName'],
                'phone_number' => $sa['phone'],
                'address_line1' => $sa['addressLine1'],
                'address_line2' => $sa['addressLine2'] ?? null,
                'city' => $sa['city'],
                'state' => $sa['state'] ?? null,
                'zip_code' => $sa['zipCode'] ?? null,
                'country' => $sa['country'] ?? 'India',
            ]);

            // ── Build order items from request ──
            // Separate real products (look up from DB) and custom items (use submitted price)
            $customItemKeys = [];
            $realProductIds = [];
            foreach ($validated['items'] as $idx => $item) {
                if (!empty($item['isCustom']) || $item['productId'] === ProductRepository::CUSTOM_TEE_PRODUCT_ID) {
                    $customItemKeys[] = $idx;
                } else {
                    $realProductIds[] = $item['productId'];
                }
            }

            // ── Pre-load real products AND variants in a single query each to avoid N+1 ──
            // Products are used for price validation; variants for stock checking
            // Both are passed into createOrder() to eliminate redundant DB queries
            $preloadedProducts = Product::whereIn('id', $realProductIds)->get()->keyBy('id');
            $preloadedVariants = !empty($realProductIds)
                ? ProductVariant::whereIn('product_id', $realProductIds)
                    ->orderBy('quantity', 'desc')
                    ->get()
                    ->groupBy('product_id')
                : collect();

            $orderItems = [];
            $subtotal = 0;
            foreach ($validated['items'] as $idx => $item) {
                if (in_array($idx, $customItemKeys)) {
                    // Custom T-Shirt: use the price submitted from the frontend
                    // (the frontend computes BASE_PRICE + CUSTOM_DESIGN_FEE)
                    $price = (float) ($item['price'] ?? 699);
                    $orderItems[] = [
                        'product_id' => ProductRepository::CUSTOM_TEE_PRODUCT_ID,
                        'quantity' => (int) $item['quantity'],
                        'price' => $price,
                    ];
                    $subtotal += $price * (int) $item['quantity'];
                } else {
                    // Real product: look up from database to prevent price tampering
                    $product = $preloadedProducts->get($item['productId']);
                    if (!$product) {
                        return response()->json([
                            'success' => false,
                            'message' => "Product '{$item['productId']}' not found",
                        ], 422);
                    }
                    $price = (float) $product->price;
                    $orderItems[] = [
                        'product_id' => $product->id,
                        'quantity' => (int) $item['quantity'],
                        'price' => $price,
                    ];
                    $subtotal += $price * (int) $item['quantity'];
                }
            }
            $shippingCost = $subtotal >= 499 ? 0 : 50;

            // ── Auto-apply flash sale discounts ──
            $flashSaleResult = $this->flashSaleService->getApplicableDiscounts($orderItems);
            $discount = $flashSaleResult['total_discount'];

            // ── Auto-apply volume/bundle discounts (stacked on top of flash sales) ──
            $volumeDiscountResult = $this->volumeDiscountService->calculateDiscount($orderItems);
            $volumeDiscount = $volumeDiscountResult['total_discount'];
            $discount += $volumeDiscount;

            // ── Create the order directly via OrderService (bypass OrderController::store) ──
            $orderService = app(OrderService::class);
            $orderArray = $orderService->createOrder($user->id, [
                'shipping_address_id' => $address->id,
                'items' => $orderItems,
                'shipping_cost' => $shippingCost,
                'discount' => $discount,
                'payment_method' => $validated['paymentMethod'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ], $preloadedProducts, $preloadedVariants);

            $createdOrderId = $orderArray['id'] ?? null;

            // ── Create CustomDesign records directly (bypass CustomDesignController::store) ──
            if ($createdOrderId && !empty($customItemKeys)) {
                $itemsFromResponse = $orderArray['items'] ?? [];

                foreach ($customItemKeys as $itemIndex) {
                    $item = $validated['items'][$itemIndex];
                    $customDesign = $item['customDesign'] ?? [];

                    // Map the item index to the actual order_item UUID for a reliable FK relationship
                    $orderItemId = $itemsFromResponse[$itemIndex]['id'] ?? null;

                    // Store back design URL in design_notes as JSON if placement is 'both'
                    $backDesignUrl = $customDesign['backServerUrl'] ?? $customDesign['backUrl'] ?? null;
                    $backDesignPath = $customDesign['backPath'] ?? null;
                    $notes = $customDesign['designNotes'] ?? null;
                    $placement = $customDesign['placement'] ?? null;

                    if ($placement === 'both' && $backDesignUrl) {
                        $designNotesPayload = json_encode([
                            'text' => $notes,
                            'backDesignUrl' => $backDesignUrl,
                            'backDesignPath' => $backDesignPath,
                        ]);
                    } else {
                        $designNotesPayload = $notes;
                    }

                    CustomDesign::create([
                        'order_id' => $createdOrderId,
                        'item_index' => $itemIndex,
                        'order_item_id' => $orderItemId,
                        'design_file_url' => $customDesign['serverUrl'] ?? null,
                        'design_file_path' => $customDesign['path'] ?? null,
                        'design_filename' => $customDesign['designFile'] ?? null,
                        'color' => $customDesign['color']['name'] ?? $item['color'] ?? null,
                        'size' => $item['size'] ?? null,
                        'quantity' => (int) $item['quantity'],
                        'placement' => $placement ?? 'Front',
                        'price' => (float) ($item['price'] ?? 699),
                        'design_notes' => $designNotesPayload,
                        'user_id' => $user->id,
                        'customer_name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'Guest',
                        'customer_email' => $user->email ?? '',
                        'status' => 'PENDING_REVIEW',
                    ]);
                }

                // Reload custom designs from DB and inject into the order array + update cache
                $customDesigns = CustomDesign::where('order_id', $createdOrderId)
                    ->get()
                    ->keyBy('item_index');

                if (isset($orderArray['items']) && is_array($orderArray['items'])) {
                    foreach ($orderArray['items'] as &$itemRef) {
                        $idx = $itemRef['item_index'] ?? null;
                        if ($idx !== null && $customDesigns->has($idx)) {
                            $cd = $customDesigns->get($idx);
                            $itemRef['customDesign'] = [
                                'design_file_url' => $cd->design_file_url,
                                'design_notes' => $cd->design_notes,
                                'placement' => $cd->placement,
                                'color' => $cd->color,
                                'size' => $cd->size,
                                'design_filename' => $cd->design_filename,
                            ];
                            if ($cd->design_file_url) {
                                $itemRef['image'] = $cd->design_file_url;
                            }
                        }
                    }
                    unset($itemRef);
                }

                // Update the cached order data to include custom designs
                Cache::put('order_' . $createdOrderId, $orderArray, now()->addMinutes(3));
            }

            // ── Build the JSON response ──
            $response = response()->json([
                'success' => true,
                'message' => 'Order created',
                'data' => $orderArray,
            ], 201);

            // ── Always return a Sanctum token for guest checkout users ──
            if ($isGuest) {
                $token = $user->createToken('checkout-token');
                $plainTextToken = $token->plainTextToken;

                $responseData = $response->getData();
                if (isset($responseData->data)) {
                    $responseData->data->accountCreated = !empty($validated['createAccount']);
                    $responseData->data->tokens = [
                        'accessToken' => $plainTextToken,
                        'refreshToken' => null,
                    ];
                    if (!empty($validated['createAccount'])) {
                        $responseData->data->user = $user->toArray();
                    }
                }
                $response->setData($responseData);
            }

            return $response;
        } catch (AppError $e) {
            return $e->render();
        }
    }

    /**
     * Resolve a guest user for checkout.
     *
     * - If an email is provided and matches an existing user, returns that user
     *   (no account created).
     * - If createAccount is true and a password is provided, creates a full user
     *   with the real password.
     * - Otherwise creates a minimal guest user with a random password.
     *
     * @return array{user: User, created: bool}
     */
    private function resolveGuestUser(array $validated): array
    {
        $email = $validated['shippingAddress']['email'] ?? null;
        $firstName = $validated['shippingAddress']['firstName'] ?? 'Guest';
        $lastName = $validated['shippingAddress']['lastName'] ?? '';
        $phone = $validated['shippingAddress']['phone'] ?? '';
        $createAccount = !empty($validated['createAccount']);
        $password = $validated['password'] ?? null;

        // If email is provided and the user already exists, reuse the account
        if ($email) {
            $existing = User::where('email', $email)->first();
            if ($existing) {
                return ['user' => $existing, 'created' => false];
            }
        }

        // Decide password: use the provided one if creating account, else random
        $hashedPassword = $createAccount && $password
            ? Hash::make($password)
            : Hash::make(Str::random(32));

        // Generate a placeholder email if none was provided
        $userEmail = $email ?? 'guest_' . Str::random(12) . '@checkout.local';

        $user = User::create([
            'first_name'  => $firstName,
            'last_name'   => $lastName,
            'email'       => $userEmail,
            'password'    => $hashedPassword,
            'phone_number'=> $phone,
            'role'        => 'CUSTOMER',
            'is_email_verified' => false,
            'is_active'   => true,
            'is_blocked'  => false,
        ]);

        return ['user' => $user, 'created' => true];
    }
}
