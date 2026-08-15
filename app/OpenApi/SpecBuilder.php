<?php

namespace App\OpenApi;

use App\OpenApi\Sections\HealthSection;
use App\OpenApi\Sections\AuthSection;
use App\OpenApi\Sections\ShopSection;
use App\OpenApi\Sections\CartSection;
use App\OpenApi\Sections\UserSection;
use App\OpenApi\Sections\SystemSection;
use App\OpenApi\Sections\AdminSection;

class SpecBuilder
{
    /**
     * Build the complete OpenAPI specification by merging all domain sections.
     */
    public static function build(): array
    {
        $paths = [];

        $sections = [
            HealthSection::class,
            AuthSection::class,
            ShopSection::class,
            CartSection::class,
            UserSection::class,
            SystemSection::class,
            AdminSection::class,
        ];

        foreach ($sections as $section) {
            $paths = array_merge($paths, $section::paths());
        }

        // Sort paths so literal segments precede parameterized ({...}) siblings.
        // This ensures correct path matching in Swagger UI / OpenAPI tooling.
        uksort($paths, function (string $a, string $b): int {
            $segA = explode('/', trim($a, '/'));
            $segB = explode('/', trim($b, '/'));

            $minLen = min(count($segA), count($segB));
            for ($i = 0; $i < $minLen; $i++) {
                $isParamA = $segA[$i] !== '' && $segA[$i][0] === '{';
                $isParamB = $segB[$i] !== '' && $segB[$i][0] === '{';

                if ($isParamA && !$isParamB) {
                    return 1; // parameterized after literal
                }
                if (!$isParamA && $isParamB) {
                    return -1; // literal before parameterized
                }

                $cmp = strcmp($segA[$i], $segB[$i]);
                if ($cmp !== 0) {
                    return $cmp;
                }
            }

            return count($segA) - count($segB); // shorter paths first
        });

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'LUXE E-Commerce API',
                'description' => 'Complete RESTful API for the LUXE e-commerce platform. Supports products, orders, cart, auth, admin, marketing, SEO, inventory, and more.',
                'version' => '1.0.0',
                'contact' => ['email' => 'support@luxe.com'],
            ],
            'servers' => [
                ['url' => '/api/v1', 'description' => 'API v1'],
            ],
            'paths' => $paths,
            'components' => self::components(),
            'security' => [['bearerAuth' => []]],
        ];
    }

    /**
     * Reusable component schemas used across all API endpoints.
     */
    private static function components(): array
    {
        return [
            'securitySchemes' => [
                'bearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'Sanctum',
                    'description' => 'Enter your Sanctum token: \"Bearer <token>\"',
                ],
            ],
            'schemas' => [
                // ── Response Envelopes ──
                'SuccessResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'success' => ['type' => 'boolean', 'example' => true],
                        'data' => ['nullable' => true, 'description' => 'Response payload (object or array)'],
                    ],
                ],
                'SuccessWithMessage' => [
                    'type' => 'object',
                    'properties' => [
                        'success' => ['type' => 'boolean', 'example' => true],
                        'message' => ['type' => 'string', 'example' => 'Operation successful'],
                        'data' => ['type' => 'object', 'nullable' => true],
                    ],
                ],
                'MessageResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'success' => ['type' => 'boolean', 'example' => true],
                        'message' => ['type' => 'string', 'example' => 'Operation completed'],
                    ],
                ],
                'ErrorResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'success' => ['type' => 'boolean', 'example' => false],
                        'message' => ['type' => 'string', 'example' => 'Something went wrong'],
                    ],
                ],
                'ValidationError' => [
                    'type' => 'object',
                    'properties' => [
                        'success' => ['type' => 'boolean', 'example' => false],
                        'message' => ['type' => 'string', 'example' => 'Validation failed'],
                        'errors' => [
                            'type' => 'object',
                            'example' => ['email' => ['The email field is required.']],
                        ],
                    ],
                ],
                'NotFoundError' => [
                    'type' => 'object',
                    'properties' => [
                        'success' => ['type' => 'boolean', 'example' => false],
                        'message' => ['type' => 'string', 'example' => 'Resource not found'],
                    ],
                ],

                // ── Pagination ──
                'Pagination' => [
                    'type' => 'object',
                    'properties' => [
                        'page' => ['type' => 'integer', 'example' => 1],
                        'pages' => ['type' => 'integer', 'example' => 5],
                        'total' => ['type' => 'integer', 'example' => 100],
                        'per_page' => ['type' => 'integer', 'example' => 20],
                    ],
                ],

                // ── Auth ──
                'User' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid', 'example' => '550e8400-e29b-41d4-a716-446655440000'],
                        'first_name' => ['type' => 'string', 'example' => 'John'],
                        'last_name' => ['type' => 'string', 'example' => 'Doe'],
                        'email' => ['type' => 'string', 'format' => 'email', 'example' => 'john@example.com'],
                        'role' => ['type' => 'string', 'enum' => ['CUSTOMER', 'ADMIN', 'MANAGER', 'SUPER_ADMIN'], 'example' => 'CUSTOMER'],
                        'phone_number' => ['type' => 'string', 'nullable' => true, 'example' => '+919876543210'],
                        'avatar' => ['type' => 'string', 'nullable' => true],
                        'is_email_verified' => ['type' => 'boolean', 'example' => false],
                        'is_active' => ['type' => 'boolean', 'example' => true],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],
                'AuthToken' => [
                    'type' => 'object',
                    'properties' => [
                        'token' => ['type' => 'string', 'example' => '1|abc123def456'],
                        'user' => ['$ref' => '#/components/schemas/User'],
                    ],
                ],
                'RegisterRequest' => [
                    'type' => 'object',
                    'required' => ['first_name', 'last_name', 'email', 'password'],
                    'properties' => [
                        'first_name' => ['type' => 'string', 'example' => 'John'],
                        'last_name' => ['type' => 'string', 'example' => 'Doe'],
                        'email' => ['type' => 'string', 'format' => 'email', 'example' => 'john@example.com'],
                        'password' => ['type' => 'string', 'format' => 'password', 'minLength' => 8, 'example' => 'securePass123'],
                        'phone_number' => ['type' => 'string', 'nullable' => true, 'example' => '+919876543210'],
                    ],
                ],
                'LoginRequest' => [
                    'type' => 'object',
                    'required' => ['email', 'password'],
                    'properties' => [
                        'email' => ['type' => 'string', 'format' => 'email', 'example' => 'john@example.com'],
                        'password' => ['type' => 'string', 'format' => 'password', 'example' => 'securePass123'],
                    ],
                ],
                'UpdateProfileRequest' => [
                    'type' => 'object',
                    'properties' => [
                        'first_name' => ['type' => 'string', 'example' => 'John'],
                        'last_name' => ['type' => 'string', 'example' => 'Doe'],
                        'phone_number' => ['type' => 'string', 'nullable' => true],
                        'avatar' => ['type' => 'string', 'nullable' => true],
                    ],
                ],
                'ChangePasswordRequest' => [
                    'type' => 'object',
                    'required' => ['current_password', 'new_password'],
                    'properties' => [
                        'current_password' => ['type' => 'string', 'format' => 'password', 'example' => 'oldPass123'],
                        'new_password' => ['type' => 'string', 'format' => 'password', 'minLength' => 8, 'example' => 'newPass456'],
                    ],
                ],
                'ForgotPasswordRequest' => [
                    'type' => 'object',
                    'required' => ['email'],
                    'properties' => [
                        'email' => ['type' => 'string', 'format' => 'email', 'example' => 'john@example.com'],
                    ],
                ],
                'VerifyEmailRequest' => [
                    'type' => 'object',
                    'required' => ['email', 'token'],
                    'properties' => [
                        'email' => ['type' => 'string', 'format' => 'email'],
                        'token' => ['type' => 'string'],
                    ],
                ],
                'ResetPasswordRequest' => [
                    'type' => 'object',
                    'required' => ['email', 'token', 'password'],
                    'properties' => [
                        'email' => ['type' => 'string', 'format' => 'email'],
                        'token' => ['type' => 'string', 'example' => 'reset-token-here'],
                        'password' => ['type' => 'string', 'format' => 'password', 'minLength' => 8],
                        'password_confirmation' => ['type' => 'string', 'format' => 'password'],
                    ],
                ],
                'SendOtpRequest' => [
                    'type' => 'object',
                    'required' => ['email'],
                    'properties' => ['email' => ['type' => 'string', 'format' => 'email']],
                ],
                'VerifyOtpRequest' => [
                    'type' => 'object',
                    'required' => ['email', 'otp'],
                    'properties' => [
                        'email' => ['type' => 'string', 'format' => 'email'],
                        'otp' => ['type' => 'string', 'minLength' => 6, 'maxLength' => 6, 'example' => '123456'],
                    ],
                ],

                // ── Products ──
                'Product' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'name' => ['type' => 'string', 'example' => 'Classic White T-Shirt'],
                        'slug' => ['type' => 'string', 'example' => 'classic-white-t-shirt'],
                        'description' => ['type' => 'string', 'nullable' => true],
                        'short_description' => ['type' => 'string', 'nullable' => true],
                        'price' => ['type' => 'number', 'format' => 'float', 'example' => 29.99],
                        'old_price' => ['type' => 'number', 'format' => 'float', 'nullable' => true],
                        'cost' => ['type' => 'number', 'format' => 'float', 'nullable' => true],
                        'sku' => ['type' => 'string', 'example' => 'TSH-WHT-001'],
                        'barcode' => ['type' => 'string', 'nullable' => true],
                        'quantity' => ['type' => 'integer', 'example' => 100],
                        'status' => ['type' => 'string', 'enum' => ['DRAFT', 'PUBLISHED', 'ARCHIVED'], 'example' => 'PUBLISHED'],
                        'badge' => ['type' => 'string', 'nullable' => true, 'example' => 'NEW'],
                        'rating' => ['type' => 'number', 'format' => 'float', 'example' => 4.5],
                        'is_featured' => ['type' => 'boolean', 'example' => false],
                        'category_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                        'brand_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                        'images' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],
                'CreateProductRequest' => [
                    'type' => 'object',
                    'required' => ['name', 'price'],
                    'properties' => [
                        'name' => ['type' => 'string', 'maxLength' => 255],
                        'description' => ['type' => 'string', 'nullable' => true],
                        'short_description' => ['type' => 'string', 'nullable' => true],
                        'price' => ['type' => 'number', 'format' => 'float', 'minimum' => 0],
                        'old_price' => ['type' => 'number', 'format' => 'float', 'nullable' => true],
                        'sku' => ['type' => 'string', 'nullable' => true],
                        'quantity' => ['type' => 'integer', 'minimum' => 0, 'example' => 0],
                        'category_id' => ['type' => 'string', 'nullable' => true],
                        'status' => ['type' => 'string', 'enum' => ['DRAFT', 'PUBLISHED', 'ARCHIVED']],
                        'is_featured' => ['type' => 'boolean'],
                    ],
                ],
                'UpdateProductRequest' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'maxLength' => 255],
                        'description' => ['type' => 'string', 'nullable' => true],
                        'price' => ['type' => 'number', 'format' => 'float', 'minimum' => 0],
                        'sku' => ['type' => 'string', 'nullable' => true],
                        'status' => ['type' => 'string', 'enum' => ['DRAFT', 'PUBLISHED', 'ARCHIVED']],
                    ],
                ],

                // ── Categories ──
                'Category' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'name' => ['type' => 'string', 'example' => 'Men\'s Clothing'],
                        'slug' => ['type' => 'string', 'example' => 'mens-clothing'],
                        'description' => ['type' => 'string', 'nullable' => true],
                        'image' => ['type' => 'string', 'nullable' => true],
                        'parent_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                        'is_active' => ['type' => 'boolean', 'example' => true],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],
                'CreateCategoryRequest' => [
                    'type' => 'object',
                    'required' => ['name'],
                    'properties' => [
                        'name' => ['type' => 'string', 'maxLength' => 255],
                        'description' => ['type' => 'string', 'nullable' => true],
                        'parent_id' => ['type' => 'string', 'nullable' => true],
                        'is_active' => ['type' => 'boolean'],
                    ],
                ],

                // ── Cart & Checkout ──
                'CartItem' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'product_id' => ['type' => 'string', 'format' => 'uuid'],
                        'variant_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                        'product' => ['$ref' => '#/components/schemas/Product'],
                        'quantity' => ['type' => 'integer', 'example' => 2],
                        'price' => ['type' => 'number', 'format' => 'float'],
                        'total' => ['type' => 'number', 'format' => 'float'],
                    ],
                ],
                'Cart' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'items' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/CartItem']],
                        'subtotal' => ['type' => 'number', 'format' => 'float'],
                        'total' => ['type' => 'number', 'format' => 'float'],
                        'item_count' => ['type' => 'integer'],
                    ],
                ],
                'AddToCartRequest' => [
                    'type' => 'object',
                    'required' => ['product_id', 'quantity'],
                    'properties' => [
                        'product_id' => ['type' => 'string', 'format' => 'uuid'],
                        'variant_id' => ['type' => 'string', 'nullable' => true],
                        'quantity' => ['type' => 'integer', 'minimum' => 1, 'example' => 1],
                    ],
                ],

                // ── Orders ──
                'Order' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'order_number' => ['type' => 'string', 'example' => 'ORD-20240601-001'],
                        'user_id' => ['type' => 'string', 'format' => 'uuid'],
                        'total' => ['type' => 'number', 'format' => 'float'],
                        'subtotal' => ['type' => 'number', 'format' => 'float'],
                        'shipping_cost' => ['type' => 'number', 'format' => 'float'],
                        'tax' => ['type' => 'number', 'format' => 'float'],
                        'discount' => ['type' => 'number', 'format' => 'float'],
                        'status' => ['type' => 'string', 'enum' => ['PENDING', 'CONFIRMED', 'PROCESSING', 'SHIPPED', 'DELIVERED', 'CANCELLED', 'RETURNED']],
                        'payment_method' => ['type' => 'string', 'nullable' => true],
                        'payment_status' => ['type' => 'string'],
                        'items' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/OrderItem']],
                        'shipping_address' => ['$ref' => '#/components/schemas/Address'],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],
                'OrderItem' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'product_id' => ['type' => 'string', 'format' => 'uuid'],
                        'name' => ['type' => 'string'],
                        'quantity' => ['type' => 'integer'],
                        'price' => ['type' => 'number', 'format' => 'float'],
                        'total' => ['type' => 'number', 'format' => 'float'],
                    ],
                ],

                // ── Address ──
                'Address' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'label' => ['type' => 'string', 'nullable' => true, 'example' => 'Home'],
                        'full_name' => ['type' => 'string'],
                        'phone' => ['type' => 'string'],
                        'address_line1' => ['type' => 'string'],
                        'address_line2' => ['type' => 'string', 'nullable' => true],
                        'city' => ['type' => 'string'],
                        'state' => ['type' => 'string'],
                        'postal_code' => ['type' => 'string'],
                        'country' => ['type' => 'string', 'example' => 'India'],
                        'is_default' => ['type' => 'boolean'],
                    ],
                ],
                'CreateAddressRequest' => [
                    'type' => 'object',
                    'required' => ['address_line1', 'city', 'state', 'postal_code', 'country'],
                    'properties' => [
                        'label' => ['type' => 'string'],
                        'full_name' => ['type' => 'string'],
                        'phone' => ['type' => 'string'],
                        'address_line1' => ['type' => 'string'],
                        'address_line2' => ['type' => 'string', 'nullable' => true],
                        'city' => ['type' => 'string'],
                        'state' => ['type' => 'string'],
                        'postal_code' => ['type' => 'string'],
                        'country' => ['type' => 'string'],
                        'is_default' => ['type' => 'boolean'],
                    ],
                ],

                // ── Reviews ──
                'Review' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'product_id' => ['type' => 'string', 'format' => 'uuid'],
                        'user_id' => ['type' => 'string', 'format' => 'uuid'],
                        'user' => ['$ref' => '#/components/schemas/User'],
                        'rating' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
                        'title' => ['type' => 'string', 'nullable' => true],
                        'comment' => ['type' => 'string'],
                        'is_verified' => ['type' => 'boolean'],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],
                'CreateReviewRequest' => [
                    'type' => 'object',
                    'required' => ['product_id', 'rating', 'comment'],
                    'properties' => [
                        'product_id' => ['type' => 'string', 'format' => 'uuid'],
                        'rating' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
                        'title' => ['type' => 'string'],
                        'comment' => ['type' => 'string'],
                    ],
                ],

                // ── Coupons ──
                'Coupon' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'code' => ['type' => 'string', 'example' => 'SAVE20'],
                        'discount_type' => ['type' => 'string', 'enum' => ['PERCENTAGE', 'FIXED', 'FREE_SHIPPING']],
                        'discount_value' => ['type' => 'number', 'format' => 'float'],
                        'min_order_value' => ['type' => 'number', 'format' => 'float', 'nullable' => true],
                        'max_discount' => ['type' => 'number', 'format' => 'float', 'nullable' => true],
                        'is_active' => ['type' => 'boolean'],
                        'expiry_date' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                    ],
                ],
                'ValidateCouponRequest' => [
                    'type' => 'object',
                    'required' => ['code'],
                    'properties' => ['code' => ['type' => 'string', 'example' => 'SAVE20']],
                ],

                // ── Banners ──
                'Banner' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'title' => ['type' => 'string'],
                        'subtitle' => ['type' => 'string', 'nullable' => true],
                        'image_url' => ['type' => 'string'],
                        'link_url' => ['type' => 'string', 'nullable' => true],
                        'type' => ['type' => 'string', 'example' => 'hero'],
                        'position' => ['type' => 'integer'],
                        'is_active' => ['type' => 'boolean'],
                    ],
                ],

                // ── Payments ──
                'Payment' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'order_id' => ['type' => 'string', 'format' => 'uuid'],
                        'method' => ['type' => 'string'],
                        'amount' => ['type' => 'number', 'format' => 'float'],
                        'status' => ['type' => 'string', 'enum' => ['PENDING', 'COMPLETED', 'FAILED', 'REFUNDED']],
                        'transaction_id' => ['type' => 'string', 'nullable' => true],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],

                // ── Shipping ──
                'Shipping' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'order_id' => ['type' => 'string', 'format' => 'uuid'],
                        'carrier' => ['type' => 'string', 'nullable' => true],
                        'tracking_number' => ['type' => 'string', 'nullable' => true],
                        'status' => ['type' => 'string'],
                        'cost' => ['type' => 'number', 'format' => 'float'],
                        'estimated_delivery' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                    ],
                ],
                'CalculateShippingRequest' => [
                    'type' => 'object',
                    'required' => ['address', 'items'],
                    'properties' => [
                        'address' => ['type' => 'string'],
                        'items' => ['type' => 'array', 'items' => ['type' => 'object']],
                    ],
                ],

                // ── Wishlist ──
                'WishlistItem' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'product_id' => ['type' => 'string', 'format' => 'uuid'],
                        'product' => ['$ref' => '#/components/schemas/Product'],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],

                // ── Notifications ──
                'Notification' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'type' => ['type' => 'string', 'example' => 'order'],
                        'title' => ['type' => 'string'],
                        'message' => ['type' => 'string'],
                        'data' => ['type' => 'object', 'nullable' => true],
                        'read_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],

                // ── Tickets ──
                'Ticket' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'ticket_number' => ['type' => 'string'],
                        'subject' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                        'category' => ['type' => 'string', 'nullable' => true],
                        'priority' => ['type' => 'string', 'enum' => ['LOW', 'MEDIUM', 'HIGH', 'URGENT']],
                        'status' => ['type' => 'string', 'enum' => ['OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED']],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],

                // ── SEO ──
                'SeoData' => [
                    'type' => 'object',
                    'properties' => [
                        'meta_title' => ['type' => 'string', 'nullable' => true],
                        'meta_description' => ['type' => 'string', 'nullable' => true],
                        'meta_keywords' => ['type' => 'string', 'nullable' => true],
                        'og_image' => ['type' => 'string', 'nullable' => true],
                    ],
                ],

                // ── Product Variants ──
                'ProductVariant' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'product_id' => ['type' => 'string', 'format' => 'uuid'],
                        'name' => ['type' => 'string'],
                        'sku' => ['type' => 'string'],
                        'attributes' => ['type' => 'object', 'example' => ['color' => 'Red', 'size' => 'M']],
                        'price' => ['type' => 'number', 'format' => 'float'],
                        'quantity' => ['type' => 'integer'],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],

                // ── Wallet & Loyalty ──
                'Wallet' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'user_id' => ['type' => 'string', 'format' => 'uuid'],
                        'balance' => ['type' => 'number', 'format' => 'float', 'example' => 500.00],
                    ],
                ],
                'LoyaltyPoints' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'user_id' => ['type' => 'string', 'format' => 'uuid'],
                        'points' => ['type' => 'integer', 'example' => 250],
                        'tier' => ['type' => 'string', 'enum' => ['BRONZE', 'SILVER', 'GOLD', 'PLATINUM']],
                    ],
                ],

                // ── CMS Pages ──
                'Page' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'title' => ['type' => 'string'],
                        'slug' => ['type' => 'string'],
                        'content' => ['type' => 'string'],
                        'is_published' => ['type' => 'boolean'],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],

                // ── Promotions ──
                'Promotion' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'title' => ['type' => 'string'],
                        'description' => ['type' => 'string', 'nullable' => true],
                        'type' => ['type' => 'string'],
                        'discount' => ['type' => 'number', 'format' => 'float'],
                        'status' => ['type' => 'string'],
                        'start_date' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                        'end_date' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                        'is_active' => ['type' => 'boolean'],
                    ],
                ],

                // ── Brands ──
                'Brand' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'name' => ['type' => 'string'],
                        'slug' => ['type' => 'string'],
                        'description' => ['type' => 'string', 'nullable' => true],
                        'logo' => ['type' => 'string', 'nullable' => true],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],

                // ── Settings ──
                'Setting' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'key' => ['type' => 'string'],
                        'value' => ['type' => 'string'],
                        'module' => ['type' => 'string'],
                    ],
                ],

                // ── Tax ──
                'TaxRate' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'name' => ['type' => 'string'],
                        'rate' => ['type' => 'number', 'format' => 'float'],
                        'type' => ['type' => 'string', 'enum' => ['PERCENTAGE', 'FIXED']],
                        'is_active' => ['type' => 'boolean'],
                    ],
                ],

                // ── Marketing ──
                'Subscriber' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'email' => ['type' => 'string', 'format' => 'email'],
                        'name' => ['type' => 'string', 'nullable' => true],
                        'status' => ['type' => 'string', 'enum' => ['ACTIVE', 'UNSUBSCRIBED']],
                        'source' => ['type' => 'string'],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],
                'Campaign' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'name' => ['type' => 'string'],
                        'subject' => ['type' => 'string'],
                        'status' => ['type' => 'string', 'enum' => ['DRAFT', 'SENDING', 'SENT', 'FAILED']],
                        'sent_count' => ['type' => 'integer'],
                        'opened_count' => ['type' => 'integer'],
                        'clicked_count' => ['type' => 'integer'],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],

                // ═══════════════════════════════════════════════
                //  ADMIN RESPONSE SCHEMAS
                //  Admin endpoints return paginated lists with entity-specific key names
                // ═══════════════════════════════════════════════

                'AdminPaginatedData' => [
                    'type' => 'object',
                    'properties' => [
                        'items' => ['type' => 'array', 'items' => ['type' => 'object'], 'description' => 'Entity list (key name varies per endpoint)'],
                        'pagination' => ['$ref' => '#/components/schemas/Pagination'],
                    ],
                ],
                'AnalyticsResponse' => [
                    'allOf' => [['$ref' => '#/components/schemas/SuccessResponse'], ['properties' => ['data' => ['type' => 'object', 'description' => 'Analytics data']]]],
                    'example' => ['success' => true, 'data' => ['total_revenue' => 125000, 'total_orders' => 342, 'avg_order_value' => 365.50]],
                ],
                'SystemHealthResponse' => [
                    'allOf' => [['$ref' => '#/components/schemas/SuccessResponse'], ['properties' => ['data' => ['type' => 'object', 'properties' => ['status' => ['type' => 'string'], 'uptime' => ['type' => 'integer'], 'memory' => ['type' => 'string']]]]]],
                    'example' => ['success' => true, 'data' => ['status' => 'healthy', 'uptime' => 86400, 'memory' => '128MB']],
                ],
                'AdminUserListResponse' => [
                    'allOf' => [['$ref' => '#/components/schemas/SuccessResponse'], ['properties' => ['data' => ['type' => 'object', 'properties' => ['users' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/User']], 'pagination' => ['$ref' => '#/components/schemas/Pagination']]]]]],
                    'example' => ['success' => true, 'data' => ['users' => [['id' => '550e...', 'email' => 'john@example.com', 'role' => 'CUSTOMER']], 'pagination' => ['page' => 1, 'pages' => 5, 'total' => 100, 'per_page' => 20]]],
                ],
                'AdminProductListResponse' => [
                    'allOf' => [['$ref' => '#/components/schemas/SuccessResponse'], ['properties' => ['data' => ['type' => 'object', 'properties' => ['products' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Product']], 'pagination' => ['$ref' => '#/components/schemas/Pagination']]]]]],
                    'example' => ['success' => true, 'data' => ['products' => [['id' => '550e...', 'name' => 'Classic White T-Shirt', 'price' => 29.99]], 'pagination' => ['page' => 1, 'pages' => 5, 'total' => 100, 'per_page' => 20]]],
                ],
                'AdminOrderListResponse' => [
                    'allOf' => [['$ref' => '#/components/schemas/SuccessResponse'], ['properties' => ['data' => ['type' => 'object', 'properties' => ['orders' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Order']], 'pagination' => ['$ref' => '#/components/schemas/Pagination']]]]]],
                    'example' => ['success' => true, 'data' => ['orders' => [['id' => '550e...', 'total' => 129.99, 'status' => 'PENDING']], 'pagination' => ['page' => 1, 'pages' => 3, 'total' => 50, 'per_page' => 20]]],
                ],
                'AdminCategoryListResponse' => [
                    'allOf' => [['$ref' => '#/components/schemas/SuccessResponse'], ['properties' => ['data' => ['type' => 'object', 'properties' => ['categories' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Category']], 'pagination' => ['$ref' => '#/components/schemas/Pagination']]]]]],
                ],
                'AdminBrandListResponse' => [
                    'allOf' => [['$ref' => '#/components/schemas/SuccessResponse'], ['properties' => ['data' => ['type' => 'object', 'properties' => ['brands' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Brand']], 'pagination' => ['$ref' => '#/components/schemas/Pagination']]]]]],
                ],
                'AdminCouponListResponse' => [
                    'allOf' => [['$ref' => '#/components/schemas/SuccessResponse'], ['properties' => ['data' => ['type' => 'object', 'properties' => ['coupons' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Coupon']], 'pagination' => ['$ref' => '#/components/schemas/Pagination']]]]]],
                ],
                'AdminBannerListResponse' => [
                    'allOf' => [['$ref' => '#/components/schemas/SuccessResponse'], ['properties' => ['data' => ['type' => 'object', 'properties' => ['banners' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Banner']], 'pagination' => ['$ref' => '#/components/schemas/Pagination']]]]]],
                ],
                'AdminReviewListResponse' => [
                    'allOf' => [['$ref' => '#/components/schemas/SuccessResponse'], ['properties' => ['data' => ['type' => 'object', 'properties' => ['reviews' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Review']], 'pagination' => ['$ref' => '#/components/schemas/Pagination']]]]]],
                ],
                'AdminTicketListResponse' => [
                    'allOf' => [['$ref' => '#/components/schemas/SuccessResponse'], ['properties' => ['data' => ['type' => 'object', 'properties' => ['tickets' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Ticket']], 'pagination' => ['$ref' => '#/components/schemas/Pagination']]]]]],
                ],
                'AdminSubscriberListResponse' => [
                    'allOf' => [['$ref' => '#/components/schemas/SuccessResponse'], ['properties' => ['data' => ['type' => 'object', 'properties' => ['subscribers' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Subscriber']], 'pagination' => ['$ref' => '#/components/schemas/Pagination']]]]]],
                ],
                'AdminCampaignListResponse' => [
                    'allOf' => [['$ref' => '#/components/schemas/SuccessResponse'], ['properties' => ['data' => ['type' => 'object', 'properties' => ['campaigns' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Campaign']], 'pagination' => ['$ref' => '#/components/schemas/Pagination']]]]]],
                ],
                'AdminAdListResponse' => [
                    'allOf' => [['$ref' => '#/components/schemas/SuccessResponse'], ['properties' => ['data' => ['type' => 'object', 'properties' => ['ads' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Campaign']], 'pagination' => ['$ref' => '#/components/schemas/Pagination']]]]]],
                ],
                'AdminNotificationListResponse' => [
                    'allOf' => [['$ref' => '#/components/schemas/SuccessResponse'], ['properties' => ['data' => ['type' => 'object', 'properties' => ['notifications' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Notification']], 'pagination' => ['$ref' => '#/components/schemas/Pagination']]]]]],
                ],
                'AdminInventoryListResponse' => [
                    'allOf' => [['$ref' => '#/components/schemas/SuccessResponse'], ['properties' => ['data' => ['type' => 'object', 'properties' => ['inventory' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/ProductVariant']], 'pagination' => ['$ref' => '#/components/schemas/Pagination']]]]]],
                ],
                'AdminAbandonedCartListResponse' => [
                    'allOf' => [['$ref' => '#/components/schemas/SuccessResponse'], ['properties' => ['data' => ['type' => 'object', 'properties' => ['carts' => ['type' => 'array', 'items' => ['type' => 'object']], 'pagination' => ['$ref' => '#/components/schemas/Pagination']]]]]],
                ],
                'AdminExportListResponse' => [
                    'allOf' => [['$ref' => '#/components/schemas/SuccessResponse'], ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/ExportJob']]]]],
                ],
                'ExportJob' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'type' => ['type' => 'string', 'enum' => ['users', 'orders', 'products']],
                        'status' => ['type' => 'string', 'enum' => ['pending', 'processing', 'completed', 'failed']],
                        'file_name' => ['type' => 'string', 'nullable' => true],
                        'error_message' => ['type' => 'string', 'nullable' => true],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                        'completed_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    ],
                ],
                'FailedJob' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'connection' => ['type' => 'string'],
                        'queue' => ['type' => 'string'],
                        'failed_at' => ['type' => 'string', 'format' => 'date-time'],
                        'exception' => ['type' => 'string', 'description' => 'Full exception trace'],
                    ],
                ],
                'AdminFailedJobListResponse' => [
                    'allOf' => [['$ref' => '#/components/schemas/SuccessResponse'], ['properties' => ['data' => ['type' => 'object', 'properties' => ['items' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/FailedJob']], 'total' => ['type' => 'integer'], 'page' => ['type' => 'integer'], 'per_page' => ['type' => 'integer'], 'total_pages' => ['type' => 'integer']]]]]],
                ],

                // ═══════════════════════════════════════════════
                //  NAMED RESPONSE SCHEMAS
                //  These wrap envelope + data for Swagger UI
                //  to show proper merged examples instead of raw allOf.
                // ═══════════════════════════════════════════════

                // ── Auth Responses ──
                'LoginResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessWithMessage'],
                        ['properties' => ['data' => ['$ref' => '#/components/schemas/AuthToken']]],
                    ],
                    'example' => [
                        'success' => true,
                        'message' => 'Login successful',
                        'data' => [
                            'token' => '1|abc123def456',
                            'user' => [
                                'id' => '550e8400-e29b-41d4-a716-446655440000',
                                'first_name' => 'John',
                                'last_name' => 'Doe',
                                'email' => 'john@example.com',
                                'role' => 'CUSTOMER',
                            ],
                        ],
                    ],
                ],
                'RegisterResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessWithMessage'],
                        ['properties' => ['data' => ['$ref' => '#/components/schemas/AuthToken']]],
                    ],
                    'example' => [
                        'success' => true,
                        'message' => 'User registered successfully',
                        'data' => [
                            'token' => '1|abc123def456',
                            'user' => [
                                'id' => '550e8400-e29b-41d4-a716-446655440000',
                                'first_name' => 'John',
                                'last_name' => 'Doe',
                                'email' => 'john@example.com',
                                'role' => 'CUSTOMER',
                            ],
                        ],
                    ],
                ],
                'UserResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['$ref' => '#/components/schemas/User']]],
                    ],
                    'example' => [
                        'success' => true,
                        'data' => [
                            'id' => '550e8400-e29b-41d4-a716-446655440000',
                            'first_name' => 'John',
                            'last_name' => 'Doe',
                            'email' => 'john@example.com',
                            'role' => 'CUSTOMER',
                        ],
                    ],
                ],
                'ProfileUpdatedResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessWithMessage'],
                        ['properties' => ['data' => ['$ref' => '#/components/schemas/User']]],
                    ],
                    'example' => [
                        'success' => true,
                        'message' => 'Profile updated',
                        'data' => [
                            'id' => '550e8400-e29b-41d4-a716-446655440000',
                            'first_name' => 'John',
                            'last_name' => 'Doe',
                            'email' => 'john@example.com',
                            'role' => 'CUSTOMER',
                        ],
                    ],
                ],

                // ── Product Responses ──
                'ProductResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['$ref' => '#/components/schemas/Product']]],
                    ],
                    'example' => [
                        'success' => true,
                        'data' => [
                            'id' => '550e8400-e29b-41d4-a716-446655440000',
                            'name' => 'Classic White T-Shirt',
                            'price' => 29.99,
                            'quantity' => 100,
                            'status' => 'PUBLISHED',
                        ],
                    ],
                ],
                'ProductCreatedResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessWithMessage'],
                        ['properties' => ['data' => ['$ref' => '#/components/schemas/Product']]],
                    ],
                    'example' => [
                        'success' => true,
                        'message' => 'Product created',
                        'data' => [
                            'id' => '550e8400-e29b-41d4-a716-446655440000',
                            'name' => 'Classic White T-Shirt',
                            'price' => 29.99,
                        ],
                    ],
                ],
                'ProductListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Product']]]],
                    ],
                    'example' => [
                        'success' => true,
                        'data' => [['id' => '...', 'name' => 'Classic White T-Shirt', 'price' => 29.99]],
                    ],
                ],

                // ── Category Responses ──
                'CategoryResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['$ref' => '#/components/schemas/Category']]],
                    ],
                ],
                'CategoryListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Category']]]],
                    ],
                ],

                // ── Cart Responses ──
                'CartResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['$ref' => '#/components/schemas/Cart']]],
                    ],
                ],
                'CartItemAddedResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessWithMessage'],
                        ['properties' => ['data' => ['$ref' => '#/components/schemas/CartItem']]],
                    ],
                ],

                // ── Address Created Response (create endpoints return message + Address data) ──
                'AddressCreatedResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessWithMessage'],
                        ['properties' => ['data' => ['$ref' => '#/components/schemas/Address']]],
                    ],
                ],

                // ── Ticket Created Response ──
                'TicketCreatedResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessWithMessage'],
                        ['properties' => ['data' => ['$ref' => '#/components/schemas/Ticket']]],
                    ],
                ],

                // ── Order Responses ──
                'OrderResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['$ref' => '#/components/schemas/Order']]],
                    ],
                ],
                'OrderListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Order']]]],
                    ],
                ],

                // ── Address Responses ──
                'AddressResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['$ref' => '#/components/schemas/Address']]],
                    ],
                ],
                'AddressListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Address']]]],
                    ],
                ],

                // ── Review Responses ──
                'ReviewResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['$ref' => '#/components/schemas/Review']]],
                    ],
                ],
                'ReviewListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Review']]]],
                    ],
                ],

                // ── Coupon Responses ──
                'CouponResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['$ref' => '#/components/schemas/Coupon']]],
                    ],
                ],
                'CouponListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Coupon']]]],
                    ],
                ],

                // ── Banner Responses ──
                'BannerResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['$ref' => '#/components/schemas/Banner']]],
                    ],
                ],
                'BannerListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Banner']]]],
                    ],
                ],

                // ── Other Entity Responses ──
                'PaymentResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['$ref' => '#/components/schemas/Payment']]],
                    ],
                ],
                'PaymentListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Payment']]]],
                    ],
                ],
                'ShippingResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['$ref' => '#/components/schemas/Shipping']]],
                    ],
                ],
                'ShippingListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Shipping']]]],
                    ],
                ],
                'WishlistResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/WishlistItem']]]],
                    ],
                ],
                'NotificationResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['$ref' => '#/components/schemas/Notification']]],
                    ],
                ],
                'NotificationListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Notification']]]],
                    ],
                ],
                'TicketResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['$ref' => '#/components/schemas/Ticket']]],
                    ],
                ],
                'TicketListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Ticket']]]],
                    ],
                ],
                'VariantResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['$ref' => '#/components/schemas/ProductVariant']]],
                    ],
                ],
                'VariantListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/ProductVariant']]]],
                    ],
                ],
                'WalletResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['$ref' => '#/components/schemas/Wallet']]],
                    ],
                ],
                'LoyaltyResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['$ref' => '#/components/schemas/LoyaltyPoints']]],
                    ],
                ],
                'PromotionListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Promotion']]]],
                    ],
                ],
                'BrandResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['$ref' => '#/components/schemas/Brand']]],
                    ],
                ],
                'BrandListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Brand']]]],
                    ],
                ],
                'PageResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['$ref' => '#/components/schemas/Page']]],
                    ],
                ],
                'PageListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Page']]]],
                    ],
                ],
                'SettingResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['$ref' => '#/components/schemas/Setting']]],
                    ],
                ],
                'SettingListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Setting']]]],
                    ],
                ],
                'SeoDataResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['$ref' => '#/components/schemas/SeoData']]],
                    ],
                ],

                // ── Generic Responses for endpoints without specific schemas ──
                'GenericDataResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'object', 'description' => 'Response payload object', 'nullable' => true]]],
                    ],
                    'example' => ['success' => true, 'data' => ['key' => 'value']],
                ],
                'GenericListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['type' => 'object', 'description' => 'List item'], 'description' => 'Array of items']]],
                    ],
                    'example' => ['success' => true, 'data' => [['id' => 1, 'name' => 'Example']]],
                ],

                // ── Payment Method ──
                'PaymentMethod' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'name' => ['type' => 'string', 'example' => 'Credit Card'],
                        'code' => ['type' => 'string', 'example' => 'razorpay'],
                        'is_active' => ['type' => 'boolean', 'example' => true],
                    ],
                ],
                'PaymentMethodListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/PaymentMethod']]]],
                    ],
                ],

                // ── Activity Log ──
                'ActivityLog' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'user_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                        'action' => ['type' => 'string', 'example' => 'product.created'],
                        'description' => ['type' => 'string', 'nullable' => true],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],
                'ActivityLogListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/ActivityLog']]]],
                    ],
                ],

                // ── Dashboard Metrics ──
                'DashboardMetrics' => [
                    'type' => 'object',
                    'properties' => [
                        'total_revenue' => ['type' => 'number', 'format' => 'float', 'example' => 125000],
                        'total_orders' => ['type' => 'integer', 'example' => 342],
                        'total_users' => ['type' => 'integer', 'example' => 1520],
                        'total_products' => ['type' => 'integer', 'example' => 890],
                        'avg_order_value' => ['type' => 'number', 'format' => 'float', 'example' => 365.50],
                        'conversion_rate' => ['type' => 'number', 'format' => 'float', 'example' => 3.2],
                    ],
                ],
                'DashboardMetricsResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['$ref' => '#/components/schemas/DashboardMetrics']]],
                    ],
                    'example' => ['success' => true, 'data' => ['total_revenue' => 125000, 'total_orders' => 342, 'total_users' => 1520, 'total_products' => 890, 'avg_order_value' => 365.50, 'conversion_rate' => 3.2]],
                ],

                // ── Wallet Transaction ──
                'WalletTransaction' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'type' => ['type' => 'string', 'enum' => ['CREDIT', 'DEBIT']],
                        'amount' => ['type' => 'number', 'format' => 'float'],
                        'description' => ['type' => 'string', 'nullable' => true],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],
                'WalletTransactionListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/WalletTransaction']]]],
                    ],
                ],

                // ── Chat Message ──
                'ChatMessage' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'sender_id' => ['type' => 'string', 'format' => 'uuid'],
                        'message' => ['type' => 'string'],
                        'type' => ['type' => 'string', 'enum' => ['TEXT', 'IMAGE', 'FILE']],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],
                'ChatInitResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'object', 'properties' => [
                            'ticket_id' => ['type' => 'string', 'format' => 'uuid'],
                            'session_id' => ['type' => 'string'],
                        ]]]],
                    ],
                ],
                'ChatMessageListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/ChatMessage']]]],
                    ],
                ],

                // ── Reel ──
                'Reel' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'title' => ['type' => 'string'],
                        'video_url' => ['type' => 'string'],
                        'thumbnail' => ['type' => 'string', 'nullable' => true],
                        'is_active' => ['type' => 'boolean'],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],
                'ReelListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Reel']]]],
                    ],
                ],

                // ── Curated Look ──
                'CuratedLook' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'title' => ['type' => 'string'],
                        'description' => ['type' => 'string', 'nullable' => true],
                        'image' => ['type' => 'string', 'nullable' => true],
                        'is_active' => ['type' => 'boolean'],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],
                'CuratedLookListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/CuratedLook']]]],
                    ],
                ],

                // ── Currency ──
                'Currency' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'code' => ['type' => 'string', 'example' => 'USD'],
                        'name' => ['type' => 'string', 'example' => 'US Dollar'],
                        'symbol' => ['type' => 'string', 'example' => '$'],
                        'exchange_rate' => ['type' => 'number', 'format' => 'float'],
                        'is_default' => ['type' => 'boolean'],
                    ],
                ],
                'CurrencyListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Currency']]]],
                    ],
                ],
                'CurrencyConversionResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'object', 'properties' => [
                            'from' => ['type' => 'string'],
                            'to' => ['type' => 'string'],
                            'amount' => ['type' => 'number', 'format' => 'float'],
                            'converted_amount' => ['type' => 'number', 'format' => 'float'],
                            'rate' => ['type' => 'number', 'format' => 'float'],
                        ]]]],
                    ],
                ],

                // ── Translations ──
                'Translation' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'key' => ['type' => 'string'],
                        'value' => ['type' => 'string'],
                        'locale' => ['type' => 'string', 'example' => 'en'],
                        'group' => ['type' => 'string'],
                    ],
                ],
                'TranslationListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Translation']]]],
                    ],
                ],
                'LanguageListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                            'code' => ['type' => 'string', 'example' => 'en'],
                            'name' => ['type' => 'string', 'example' => 'English'],
                            'is_default' => ['type' => 'boolean'],
                        ]]]]],
                    ],
                ],

                // ── Tax ──
                'TaxCalculationResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'object', 'properties' => [
                            'subtotal' => ['type' => 'number', 'format' => 'float'],
                            'tax_amount' => ['type' => 'number', 'format' => 'float'],
                            'total' => ['type' => 'number', 'format' => 'float'],
                            'breakdown' => ['type' => 'array', 'items' => ['type' => 'object'], 'nullable' => true],
                        ]]]],
                    ],
                ],

                // ── Abandoned Cart ──
                'AbandonedCart' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'user_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                        'item_count' => ['type' => 'integer'],
                        'total' => ['type' => 'number', 'format' => 'float'],
                        'last_active' => ['type' => 'string', 'format' => 'date-time'],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],
                'AbandonedCartListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AbandonedCart']]]],
                    ],
                ],

                // ── Refund Request ──
                'RefundRequest' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'order_id' => ['type' => 'string', 'format' => 'uuid'],
                        'amount' => ['type' => 'number', 'format' => 'float'],
                        'reason' => ['type' => 'string', 'nullable' => true],
                        'status' => ['type' => 'string', 'enum' => ['PENDING', 'APPROVED', 'REJECTED', 'COMPLETED']],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],
                'RefundRequestListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/RefundRequest']]]],
                    ],
                ],

                // ── Staff ──
                'Staff' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'first_name' => ['type' => 'string'],
                        'last_name' => ['type' => 'string'],
                        'email' => ['type' => 'string', 'format' => 'email'],
                        'role' => ['type' => 'string', 'enum' => ['ADMIN', 'MANAGER', 'SUPPORT']],
                        'is_active' => ['type' => 'boolean'],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],
                'StaffListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Staff']]]],
                    ],
                ],

                // ── Low Stock ──
                'LowStockItem' => [
                    'type' => 'object',
                    'properties' => [
                        'product_id' => ['type' => 'string', 'format' => 'uuid'],
                        'product_name' => ['type' => 'string'],
                        'sku' => ['type' => 'string'],
                        'current_quantity' => ['type' => 'integer'],
                        'reorder_point' => ['type' => 'integer'],
                    ],
                ],
                'LowStockListResponse' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/LowStockItem']]]],
                    ],
                ],

                // ═══════════════════════════════════════════════
                //  REQUEST BODY SCHEMAS (Admin)
                // ═══════════════════════════════════════════════

                'ManageUserRequest' => [
                    'type' => 'object',
                    'required' => ['action'],
                    'properties' => [
                        'action' => ['type' => 'string', 'enum' => ['block', 'unblock', 'flag', 'unflag']],
                        'reason' => ['type' => 'string', 'nullable' => true],
                    ],
                ],
                'StaffCreateRequest' => [
                    'type' => 'object',
                    'required' => ['first_name', 'last_name', 'email', 'password'],
                    'properties' => [
                        'first_name' => ['type' => 'string'],
                        'last_name' => ['type' => 'string'],
                        'email' => ['type' => 'string', 'format' => 'email'],
                        'password' => ['type' => 'string', 'format' => 'password', 'minLength' => 8],
                        'role' => ['type' => 'string', 'enum' => ['ADMIN', 'MANAGER', 'SUPPORT']],
                    ],
                ],
                'StaffUpdateRequest' => [
                    'type' => 'object',
                    'properties' => [
                        'first_name' => ['type' => 'string'],
                        'last_name' => ['type' => 'string'],
                        'email' => ['type' => 'string', 'format' => 'email'],
                        'role' => ['type' => 'string', 'enum' => ['ADMIN', 'MANAGER', 'SUPPORT']],
                        'is_active' => ['type' => 'boolean'],
                    ],
                ],
                'OrderStatusUpdateRequest' => [
                    'type' => 'object',
                    'required' => ['status'],
                    'properties' => [
                        'status' => ['type' => 'string', 'enum' => ['PENDING', 'CONFIRMED', 'PROCESSING', 'SHIPPED', 'DELIVERED', 'CANCELLED', 'RETURNED']],
                        'notes' => ['type' => 'string', 'nullable' => true],
                    ],
                ],
                'EditOrderRequest' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'nullable' => true],
                        'payment_status' => ['type' => 'string', 'nullable' => true],
                        'notes' => ['type' => 'string', 'nullable' => true],
                    ],
                ],
                'BrandCreateRequest' => [
                    'type' => 'object',
                    'required' => ['name'],
                    'properties' => [
                        'name' => ['type' => 'string', 'maxLength' => 255],
                        'description' => ['type' => 'string', 'nullable' => true],
                        'logo' => ['type' => 'string', 'nullable' => true],
                    ],
                ],
                'BannerCreateRequest' => [
                    'type' => 'object',
                    'required' => ['title', 'image_url'],
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'subtitle' => ['type' => 'string', 'nullable' => true],
                        'image_url' => ['type' => 'string'],
                        'link_url' => ['type' => 'string', 'nullable' => true],
                        'type' => ['type' => 'string'],
                        'position' => ['type' => 'integer'],
                    ],
                ],
                'BannerReorderRequest' => [
                    'type' => 'object',
                    'required' => ['order'],
                    'properties' => [
                        'order' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                            'id' => ['type' => 'string'],
                            'position' => ['type' => 'integer'],
                        ]]],
                    ],
                ],
                'CouponCreateRequest' => [
                    'type' => 'object',
                    'required' => ['code', 'discount_type', 'discount_value'],
                    'properties' => [
                        'code' => ['type' => 'string'],
                        'discount_type' => ['type' => 'string', 'enum' => ['PERCENTAGE', 'FIXED', 'FREE_SHIPPING']],
                        'discount_value' => ['type' => 'number', 'format' => 'float'],
                        'min_order_value' => ['type' => 'number', 'format' => 'float', 'nullable' => true],
                        'max_discount' => ['type' => 'number', 'format' => 'float', 'nullable' => true],
                        'is_active' => ['type' => 'boolean', 'default' => true],
                        'expiry_date' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                        'usage_limit' => ['type' => 'integer', 'nullable' => true],
                    ],
                ],
                'CouponUpdateRequest' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => ['type' => 'string'],
                        'discount_type' => ['type' => 'string', 'enum' => ['PERCENTAGE', 'FIXED', 'FREE_SHIPPING']],
                        'discount_value' => ['type' => 'number', 'format' => 'float'],
                        'min_order_value' => ['type' => 'number', 'format' => 'float', 'nullable' => true],
                        'max_discount' => ['type' => 'number', 'format' => 'float', 'nullable' => true],
                        'is_active' => ['type' => 'boolean'],
                        'expiry_date' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                    ],
                ],
                'BulkGenerateCouponsRequest' => [
                    'type' => 'object',
                    'required' => ['count', 'discount_type', 'discount_value'],
                    'properties' => [
                        'count' => ['type' => 'integer', 'minimum' => 1, 'example' => 10],
                        'discount_type' => ['type' => 'string', 'enum' => ['PERCENTAGE', 'FIXED', 'FREE_SHIPPING']],
                        'discount_value' => ['type' => 'number', 'format' => 'float'],
                        'prefix' => ['type' => 'string', 'nullable' => true],
                        'expiry_date' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                    ],
                ],
                'InventoryBatchUpdateRequest' => [
                    'type' => 'object',
                    'required' => ['items'],
                    'properties' => [
                        'items' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                            'id' => ['type' => 'string'],
                            'quantity' => ['type' => 'integer', 'minimum' => 0],
                        ], 'required' => ['id', 'quantity']]],
                    ],
                ],
                'AddStockRequest' => [
                    'type' => 'object',
                    'required' => ['product_id', 'quantity'],
                    'properties' => [
                        'product_id' => ['type' => 'string'],
                        'variant_id' => ['type' => 'string', 'nullable' => true],
                        'quantity' => ['type' => 'integer', 'minimum' => 1],
                        'notes' => ['type' => 'string', 'nullable' => true],
                    ],
                ],
                'ReduceStockRequest' => [
                    'type' => 'object',
                    'required' => ['product_id', 'quantity'],
                    'properties' => [
                        'product_id' => ['type' => 'string'],
                        'variant_id' => ['type' => 'string', 'nullable' => true],
                        'quantity' => ['type' => 'integer', 'minimum' => 1],
                        'reason' => ['type' => 'string', 'nullable' => true],
                    ],
                ],
                'UpdateStockRequest' => [
                    'type' => 'object',
                    'required' => ['quantity'],
                    'properties' => [
                        'quantity' => ['type' => 'integer', 'minimum' => 0],
                        'notes' => ['type' => 'string', 'nullable' => true],
                    ],
                ],
                'SendNotificationRequest' => [
                    'type' => 'object',
                    'required' => ['title', 'message'],
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'message' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                        'user_id' => ['type' => 'string', 'nullable' => true],
                    ],
                ],
                'BulkNotificationRequest' => [
                    'type' => 'object',
                    'required' => ['title', 'message', 'user_ids'],
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'message' => ['type' => 'string'],
                        'type' => ['type' => 'string', 'nullable' => true],
                        'user_ids' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1],
                    ],
                ],
                'TicketStatusUpdateRequest' => [
                    'type' => 'object',
                    'required' => ['status'],
                    'properties' => [
                        'status' => ['type' => 'string', 'enum' => ['OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED']],
                        'notes' => ['type' => 'string', 'nullable' => true],
                    ],
                ],
                'TicketUpdateRequest' => [
                    'type' => 'object',
                    'properties' => [
                        'subject' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                        'category' => ['type' => 'string', 'nullable' => true],
                        'priority' => ['type' => 'string', 'enum' => ['LOW', 'MEDIUM', 'HIGH', 'URGENT']],
                        'status' => ['type' => 'string', 'enum' => ['OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED']],
                    ],
                ],
                'AdminAddTicketMessageRequest' => [
                    'type' => 'object',
                    'required' => ['message'],
                    'properties' => [
                        'message' => ['type' => 'string'],
                        'type' => ['type' => 'string', 'enum' => ['TEXT', 'IMAGE', 'FILE']],
                    ],
                ],
                'PromotionCreateRequest' => [
                    'type' => 'object',
                    'required' => ['title', 'type', 'discount'],
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'description' => ['type' => 'string', 'nullable' => true],
                        'type' => ['type' => 'string'],
                        'discount' => ['type' => 'number', 'format' => 'float'],
                        'is_active' => ['type' => 'boolean', 'default' => true],
                        'start_date' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                        'end_date' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                    ],
                ],
                'SettingCreateRequest' => [
                    'type' => 'object',
                    'required' => ['key', 'value'],
                    'properties' => [
                        'key' => ['type' => 'string'],
                        'value' => ['type' => 'string'],
                        'module' => ['type' => 'string', 'nullable' => true],
                    ],
                ],
                'PageCreateRequest' => [
                    'type' => 'object',
                    'required' => ['title', 'content'],
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'content' => ['type' => 'string'],
                        'slug' => ['type' => 'string', 'nullable' => true],
                        'is_published' => ['type' => 'boolean', 'default' => true],
                    ],
                ],
                'ExportDispatchRequest' => [
                    'type' => 'object',
                    'required' => ['type'],
                    'properties' => [
                        'type' => ['type' => 'string', 'enum' => ['users', 'orders', 'products']],
                        'format' => ['type' => 'string', 'default' => 'csv'],
                        'filters' => ['type' => 'object', 'nullable' => true],
                    ],
                ],
                'BulkDeleteProductsRequest' => [
                    'type' => 'object',
                    'required' => ['ids'],
                    'properties' => [
                        'ids' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1],
                    ],
                ],
                'SubscriberCreateRequest' => [
                    'type' => 'object',
                    'required' => ['email'],
                    'properties' => [
                        'email' => ['type' => 'string', 'format' => 'email'],
                        'name' => ['type' => 'string', 'nullable' => true],
                        'source' => ['type' => 'string', 'nullable' => true],
                    ],
                ],
                'CampaignCreateRequest' => [
                    'type' => 'object',
                    'required' => ['name', 'subject'],
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'subject' => ['type' => 'string'],
                        'content' => ['type' => 'string', 'nullable' => true],
                        'status' => ['type' => 'string', 'enum' => ['DRAFT', 'SENDING', 'SENT']],
                        'scheduled_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    ],
                ],

                // ═══════════════════════════════════════════════
                //  REQUEST BODY SCHEMAS (User & System)
                // ═══════════════════════════════════════════════

                'RechargeWalletRequest' => [
                    'type' => 'object',
                    'required' => ['amount'],
                    'properties' => [
                        'amount' => ['type' => 'number', 'format' => 'float', 'minimum' => 1],
                        'payment_method' => ['type' => 'string', 'nullable' => true],
                    ],
                ],
                'CreateRefundRequest' => [
                    'type' => 'object',
                    'required' => ['order_id', 'reason'],
                    'properties' => [
                        'order_id' => ['type' => 'string'],
                        'reason' => ['type' => 'string'],
                        'amount' => ['type' => 'number', 'format' => 'float', 'nullable' => true],
                    ],
                ],
                'CreateReturnRequest' => [
                    'type' => 'object',
                    'required' => ['order_id', 'reason'],
                    'properties' => [
                        'order_id' => ['type' => 'string'],
                        'reason' => ['type' => 'string'],
                        'items' => ['type' => 'array', 'items' => ['type' => 'object'], 'nullable' => true],
                    ],
                ],
                'CurrencyConversionRequest' => [
                    'type' => 'object',
                    'required' => ['from', 'to', 'amount'],
                    'properties' => [
                        'from' => ['type' => 'string', 'example' => 'USD'],
                        'to' => ['type' => 'string', 'example' => 'EUR'],
                        'amount' => ['type' => 'number', 'format' => 'float'],
                    ],
                ],
                'TaxCalculationRequest' => [
                    'type' => 'object',
                    'required' => ['amount'],
                    'properties' => [
                        'amount' => ['type' => 'number', 'format' => 'float'],
                        'address' => ['type' => 'string', 'nullable' => true],
                        'product_ids' => ['type' => 'array', 'items' => ['type' => 'string'], 'nullable' => true],
                    ],
                ],
                'TrackingPageViewRequest' => [
                    'type' => 'object',
                    'required' => ['url'],
                    'properties' => [
                        'url' => ['type' => 'string'],
                        'title' => ['type' => 'string', 'nullable' => true],
                        'referrer' => ['type' => 'string', 'nullable' => true],
                        'session_id' => ['type' => 'string', 'nullable' => true],
                    ],
                ],
                'TrackingSessionRequest' => [
                    'type' => 'object',
                    'properties' => [
                        'user_id' => ['type' => 'string', 'nullable' => true],
                        'source' => ['type' => 'string', 'nullable' => true],
                        'data' => ['type' => 'object', 'nullable' => true],
                    ],
                ],
                'TrackingEventRequest' => [
                    'type' => 'object',
                    'required' => ['event', 'data'],
                    'properties' => [
                        'event' => ['type' => 'string'],
                        'data' => ['type' => 'object'],
                        'session_id' => ['type' => 'string', 'nullable' => true],
                    ],
                ],
                'ChatInitRequest' => [
                    'type' => 'object',
                    'required' => ['message'],
                    'properties' => [
                        'subject' => ['type' => 'string', 'nullable' => true],
                        'message' => ['type' => 'string'],
                        'category' => ['type' => 'string', 'nullable' => true],
                    ],
                ],
                'SendChatMessageRequest' => [
                    'type' => 'object',
                    'required' => ['message'],
                    'properties' => [
                        'message' => ['type' => 'string'],
                        'type' => ['type' => 'string', 'enum' => ['TEXT', 'IMAGE', 'FILE']],
                    ],
                ],
                'TypingIndicatorRequest' => [
                    'type' => 'object',
                    'properties' => [
                        'is_typing' => ['type' => 'boolean', 'default' => true],
                    ],
                ],
                'CreateCommentRequest' => [
                    'type' => 'object',
                    'required' => ['order_item_id', 'comment'],
                    'properties' => [
                        'order_item_id' => ['type' => 'string'],
                        'comment' => ['type' => 'string'],
                        'rating' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5, 'nullable' => true],
                    ],
                ],
                'RazorpayCreateOrderRequest' => [
                    'type' => 'object',
                    'required' => ['amount'],
                    'properties' => [
                        'amount' => ['type' => 'number', 'format' => 'float'],
                        'currency' => ['type' => 'string', 'default' => 'INR'],
                        'order_id' => ['type' => 'string', 'nullable' => true],
                        'notes' => ['type' => 'object', 'nullable' => true],
                    ],
                ],
                'RazorpayVerifyRequest' => [
                    'type' => 'object',
                    'required' => ['razorpay_payment_id', 'razorpay_order_id', 'razorpay_signature'],
                    'properties' => [
                        'razorpay_payment_id' => ['type' => 'string'],
                        'razorpay_order_id' => ['type' => 'string'],
                        'razorpay_signature' => ['type' => 'string'],
                    ],
                ],
                'RecordAbandonedCartRequest' => [
                    'type' => 'object',
                    'required' => ['items', 'total'],
                    'properties' => [
                        'items' => ['type' => 'array', 'items' => ['type' => 'object']],
                        'total' => ['type' => 'number', 'format' => 'float'],
                        'email' => ['type' => 'string', 'format' => 'email', 'nullable' => true],
                    ],
                ],
                'CreatePaymentIntentRequest' => [
                    'type' => 'object',
                    'required' => ['amount'],
                    'properties' => [
                        'amount' => ['type' => 'number', 'format' => 'float'],
                        'currency' => ['type' => 'string', 'default' => 'INR'],
                        'order_id' => ['type' => 'string', 'nullable' => true],
                        'payment_method' => ['type' => 'string', 'nullable' => true],
                    ],
                ],
                'ConfirmPaymentRequest' => [
                    'type' => 'object',
                    'required' => ['payment_intent_id'],
                    'properties' => [
                        'payment_intent_id' => ['type' => 'string'],
                        'payment_method' => ['type' => 'string', 'nullable' => true],
                    ],
                ],
                'InitiatePaymentRequest' => [
                    'type' => 'object',
                    'required' => ['order_id', 'payment_method'],
                    'properties' => [
                        'order_id' => ['type' => 'string'],
                        'payment_method' => ['type' => 'string'],
                        'amount' => ['type' => 'number', 'format' => 'float', 'nullable' => true],
                    ],
                ],
                'VerifyPaymentRequest' => [
                    'type' => 'object',
                    'properties' => [
                        'razorpay_payment_id' => ['type' => 'string'],
                        'razorpay_order_id' => ['type' => 'string'],
                        'razorpay_signature' => ['type' => 'string'],
                    ],
                ],
                'RefundPaymentRequest' => [
                    'type' => 'object',
                    'required' => ['reason'],
                    'properties' => [
                        'reason' => ['type' => 'string'],
                        'amount' => ['type' => 'number', 'format' => 'float', 'nullable' => true],
                    ],
                ],
                'CustomGatewayRequest' => [
                    'type' => 'object',
                    'required' => ['order_id', 'gateway'],
                    'properties' => [
                        'order_id' => ['type' => 'string'],
                        'gateway' => ['type' => 'string'],
                        'amount' => ['type' => 'number', 'format' => 'float', 'nullable' => true],
                    ],
                ],
                'MarketingSubscribeRequest' => [
                    'type' => 'object',
                    'required' => ['email'],
                    'properties' => [
                        'email' => ['type' => 'string', 'format' => 'email'],
                        'name' => ['type' => 'string', 'nullable' => true],
                        'source' => ['type' => 'string', 'nullable' => true],
                    ],
                ],
                'WebhookProcessedResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'example' => 'processed'],
                    ],
                ],
            ],
        ];
    }
}
