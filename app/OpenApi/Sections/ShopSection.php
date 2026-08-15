<?php

namespace App\OpenApi\Sections;

class ShopSection
{
    /** Named response $ref helpers — Swagger UI shows proper merged examples */
    private static function singleData(string $refSchema): array
    {
        return ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/' . $refSchema . 'Response']]]];
    }

    private static function listData(string $refSchema): array
    {
        return ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/' . $refSchema . 'ListResponse']]]];
    }

    private static function validationError(): array
    {
        return ['description' => 'Validation error', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ValidationError']]]];
    }

    private static function notFound(): array
    {
        return ['description' => 'Resource not found', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/NotFoundError']]]];
    }

    private static function pathParam(string $name, string $description, array $extra = []): array
    {
        return array_merge(['name' => $name, 'in' => 'path', 'required' => true, 'description' => $description, 'schema' => ['type' => 'string']], $extra);
    }

    /**
     * For response schemas with inline data shape (no named response schema).
     */
    private static function inlineData(array $dataSchema): array
    {
        return [
            'content' => ['application/json' => [
                'schema' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/SuccessResponse'],
                        ['properties' => ['data' => $dataSchema]],
                    ],
                ],
            ]],
        ];
    }

    private static function successWithMessage(): array
    {
        return ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/SuccessWithMessage']]]];
    }

    private static function message(): array
    {
        return ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/MessageResponse']]]];
    }

    public static function paths(): array
    {
        return [
            // ── Products ──
            '/products' => [
                'get' => [
                    'summary' => 'List all products (paginated, filterable)',
                    'tags' => ['Products'],
                    'parameters' => [
                        ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 1]],
                        ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 20]],
                        ['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string']],
                        ['name' => 'category_id', 'in' => 'query', 'schema' => ['type' => 'string']],
                        ['name' => 'min_price', 'in' => 'query', 'schema' => ['type' => 'number']],
                        ['name' => 'max_price', 'in' => 'query', 'schema' => ['type' => 'number']],
                        ['name' => 'sort', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['price_asc', 'price_desc', 'newest', 'popular']]],
                    ],
                    'responses' => ['200' => self::listData('Product')],
                ],
            ],
            '/products/featured' => [
                'get' => ['summary' => 'Get featured products', 'tags' => ['Products'], 'parameters' => [['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 8]]], 'responses' => ['200' => self::listData('Product')]],
            ],
            '/products/new-arrivals' => [
                'get' => ['summary' => 'Get new arrival products', 'tags' => ['Products'], 'parameters' => [['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 8]]], 'responses' => ['200' => self::listData('Product')]],
            ],
            '/products/best-sellers' => [
                'get' => ['summary' => 'Get best selling products', 'tags' => ['Products'], 'parameters' => [['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 8]]], 'responses' => ['200' => self::listData('Product')]],
            ],
            '/products/search' => [
                'get' => [
                    'summary' => 'Search products by query',
                    'tags' => ['Products'],
                    'parameters' => [
                        ['name' => 'q', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string']],
                        ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 10]],
                    ],
                    'responses' => ['200' => self::listData('Product')],
                ],
            ],
            '/products/brand' => [
                'get' => ['summary' => 'List all brands', 'tags' => ['Products'], 'responses' => ['200' => self::listData('Brand')]],
            ],
            '/products/category/{categoryId}' => [
                'get' => [
                    'summary' => 'Get products by category',
                    'tags' => ['Products'],
                    'parameters' => [self::pathParam('categoryId', 'Category UUID')],
                    'responses' => ['200' => self::listData('Product')],
                ],
            ],
            '/products/{productId}/availability' => [
                'get' => [
                    'summary' => 'Check product stock availability',
                    'tags' => ['Products'],
                    'parameters' => [self::pathParam('productId', 'Product UUID'), ['name' => 'quantity', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 1]]],
                    'responses' => ['200' => self::inlineData(['type' => 'object', 'properties' => ['available' => ['type' => 'boolean']]])],
                ],
            ],
            '/products/{id}' => [
                'get' => [
                    'summary' => 'Get single product details',
                    'tags' => ['Products'],
                    'parameters' => [self::pathParam('id', 'Product UUID')],
                    'responses' => [
                        '200' => self::singleData('Product'),
                        '404' => self::notFound(),
                    ],
                ],
            ],
            '/products/{productId}/variants/attributes' => [
                'get' => [
                    'summary' => 'Get variant by attributes',
                    'tags' => ['Products'],
                    'parameters' => [self::pathParam('productId', 'Product UUID')],
                    'responses' => ['200' => self::singleData('Variant')],
                ],
            ],
            '/products/{productId}/variants' => [
                'get' => [
                    'summary' => 'Get variants for a product',
                    'tags' => ['Products'],
                    'parameters' => [self::pathParam('productId', 'Product UUID')],
                    'responses' => ['200' => self::listData('Variant')],
                ],
            ],

            // ── Categories ──
            '/categories' => [
                'get' => ['summary' => 'List all categories', 'tags' => ['Categories'], 'responses' => ['200' => self::listData('Category')]],
            ],
            '/categories/hierarchy' => [
                'get' => ['summary' => 'Get category hierarchy tree', 'tags' => ['Categories'], 'responses' => ['200' => self::singleData('Category')]],
            ],
            '/categories/tree' => [
                'get' => ['summary' => 'Get category tree (nested)', 'tags' => ['Categories'], 'responses' => ['200' => self::singleData('Category')]],
            ],
            '/categories/{categoryId}/subcategories' => [
                'get' => ['summary' => 'Get subcategories of a category', 'tags' => ['Categories'], 'parameters' => [self::pathParam('categoryId', 'Category UUID')], 'responses' => ['200' => self::listData('Category')]],
            ],
            '/categories/{categoryId}/stats' => [
                'get' => ['summary' => 'Get category statistics', 'tags' => ['Categories'], 'parameters' => [self::pathParam('categoryId', 'Category UUID')], 'responses' => ['200' => self::singleData('Category')]],
            ],
            '/categories/{id}' => [
                'get' => ['summary' => 'Get single category', 'tags' => ['Categories'], 'parameters' => [self::pathParam('id', 'Category UUID')], 'responses' => ['200' => self::singleData('Category'), '404' => self::notFound()]],
            ],

            // ── Banners ──
            '/banners' => [
                'get' => ['summary' => 'Get active banners', 'tags' => ['Banners'], 'responses' => ['200' => self::listData('Banner')]],
            ],
            '/banners/homepage' => [
                'get' => ['summary' => 'Get homepage banners', 'tags' => ['Banners'], 'responses' => ['200' => self::listData('Banner')]],
            ],
            '/banners/hero' => [
                'get' => ['summary' => 'Get hero banners', 'tags' => ['Banners'], 'responses' => ['200' => self::listData('Banner')]],
            ],
            '/banners/sale' => [
                'get' => ['summary' => 'Get sale banners', 'tags' => ['Banners'], 'responses' => ['200' => self::listData('Banner')]],
            ],
            '/banners/category' => [
                'get' => ['summary' => 'Get category banners', 'tags' => ['Banners'], 'responses' => ['200' => self::listData('Banner')]],
            ],
            '/banners/popup' => [
                'get' => ['summary' => 'Get popup banners', 'tags' => ['Banners'], 'responses' => ['200' => self::listData('Banner')]],
            ],
            '/banners/{id}' => [
                'get' => ['summary' => 'Get single banner', 'tags' => ['Banners'], 'parameters' => [self::pathParam('id', 'Banner UUID')], 'responses' => ['200' => self::singleData('Banner')]],
            ],

            // ── Reviews ──
            '/reviews/homepage' => [
                'get' => ['summary' => 'Get homepage reviews/testimonials', 'tags' => ['Reviews'], 'responses' => ['200' => self::listData('Review')]],
            ],
            '/reviews/product/{productId}' => [
                'get' => ['summary' => 'Get product reviews', 'tags' => ['Reviews'], 'parameters' => [self::pathParam('productId', 'Product UUID')], 'responses' => ['200' => self::listData('Review')]],
            ],
            '/reviews/stats/{productId}' => [
                'get' => ['summary' => 'Get review stats for a product', 'tags' => ['Reviews'], 'parameters' => [self::pathParam('productId', 'Product UUID')], 'responses' => ['200' => self::singleData('Review')]],
            ],
            '/reviews/verified/{productId}' => [
                'get' => ['summary' => 'Get verified purchase reviews', 'tags' => ['Reviews'], 'parameters' => [self::pathParam('productId', 'Product UUID')], 'responses' => ['200' => self::listData('Review')]],
            ],
            '/reviews/user' => [
                'get' => ['summary' => 'Get current user reviews', 'tags' => ['Reviews'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::listData('Review')]],
            ],
            '/reviews' => [
                'post' => [
                    'summary' => 'Create a review',
                    'tags' => ['Reviews'],
                    'security' => [['bearerAuth' => []]],
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/CreateReviewRequest']]]],
                    'responses' => [
                        '201' => ['description' => 'Review created', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/SuccessWithMessage']]]],
                        '422' => self::validationError(),
                    ],
                ],
            ],
            '/reviews/{id}' => [
                'get' => ['summary' => 'Get single review', 'tags' => ['Reviews'], 'parameters' => [self::pathParam('id', 'Review UUID')], 'responses' => ['200' => self::singleData('Review')]],
                'put' => ['summary' => 'Update own review', 'tags' => ['Reviews'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::pathParam('id', 'Review UUID')], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/CreateReviewRequest']]]], 'responses' => ['200' => ['description' => 'Review updated', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/SuccessWithMessage']]]], '422' => self::validationError()]],
                'delete' => ['summary' => 'Delete own review', 'tags' => ['Reviews'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::pathParam('id', 'Review UUID')], 'responses' => ['200' => self::message()]],
            ],
            '/reviews/{id}/helpful' => [
                'post' => ['summary' => 'Mark review as helpful', 'tags' => ['Reviews'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::pathParam('id', 'Review UUID')], 'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => []]]]], 'responses' => ['200' => self::message()]],
            ],
            '/reviews/{id}/unhelpful' => [
                'post' => ['summary' => 'Mark review as unhelpful', 'tags' => ['Reviews'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::pathParam('id', 'Review UUID')], 'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => []]]]], 'responses' => ['200' => self::message()]],
            ],

            // ── Coupons ──
            '/coupons' => [
                'get' => ['summary' => 'List active coupons', 'tags' => ['Coupons'], 'responses' => ['200' => self::listData('Coupon')]],
            ],
            '/coupons/auto-apply/list' => [
                'get' => ['summary' => 'Get auto-apply coupons', 'tags' => ['Coupons'], 'responses' => ['200' => self::listData('Coupon')]],
            ],
            '/coupons/best' => [
                'post' => ['summary' => 'Get best applicable coupon', 'tags' => ['Coupons'], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ValidateCouponRequest']]]], 'responses' => ['200' => self::singleData('Coupon')]],
            ],
            '/coupons/validate' => [
                'post' => ['summary' => 'Validate a coupon code', 'tags' => ['Coupons'], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ValidateCouponRequest']]]], 'responses' => ['200' => self::singleData('Coupon')]],
            ],
            '/coupons/apply' => [
                'post' => ['summary' => 'Apply a coupon to cart', 'tags' => ['Coupons'], 'security' => [['bearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ValidateCouponRequest']]]], 'responses' => ['200' => self::message()]],
            ],
            '/coupons/remove' => [
                'delete' => ['summary' => 'Remove coupon from cart', 'tags' => ['Coupons'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::message()]],
            ],

            // ── Shipping ──
            '/coupons/{code}' => [
                'get' => ['summary' => 'Get coupon by code', 'tags' => ['Coupons'], 'parameters' => [self::pathParam('code', 'Coupon code')], 'responses' => ['200' => self::singleData('Coupon')]],
            ],
            '/shipping/methods' => [
                'get' => ['summary' => 'Get available shipping methods', 'tags' => ['Shipping'], 'responses' => ['200' => self::listData('Shipping')]],
            ],
            '/shipping/providers' => [
                'get' => ['summary' => 'Get shipping providers', 'tags' => ['Shipping'], 'responses' => ['200' => self::listData('Shipping')]],
            ],
            '/shipping/zones' => [
                'get' => ['summary' => 'Get shipping zones', 'tags' => ['Shipping'], 'responses' => ['200' => self::listData('Shipping')]],
            ],
            '/shipping/calculate' => [
                'post' => ['summary' => 'Calculate shipping cost', 'tags' => ['Shipping'], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/CalculateShippingRequest']]]], 'responses' => ['200' => self::singleData('Shipping')]],
            ],
            '/shipping/tracking/{trackingNumber}' => [
                'get' => ['summary' => 'Track shipment by tracking number', 'tags' => ['Shipping'], 'parameters' => [self::pathParam('trackingNumber', 'Tracking number')], 'responses' => ['200' => self::singleData('Shipping')]],
            ],
            '/shipping/track/{trackingId}' => [
                'get' => ['summary' => 'Track shipment by tracking ID', 'tags' => ['Shipping'], 'parameters' => [self::pathParam('trackingId', 'Tracking ID')], 'responses' => ['200' => self::singleData('Shipping')]],
            ],
            '/shipping/order/{orderId}' => [
                'get' => ['summary' => 'Get shipping for an order', 'tags' => ['Shipping'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::pathParam('orderId', 'Order UUID')], 'responses' => ['200' => self::singleData('Shipping')]],
            ],
            '/shipping/my-shipments' => [
                'get' => ['summary' => 'Get user shipments', 'tags' => ['Shipping'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::listData('Shipping')]],
            ],
            '/shipping/{shippingId}' => [
                'get' => ['summary' => 'Get shipping details', 'tags' => ['Shipping'], 'parameters' => [self::pathParam('shippingId', 'Shipping UUID')], 'responses' => ['200' => self::singleData('Shipping')]],
            ],
        
];
    }
}
