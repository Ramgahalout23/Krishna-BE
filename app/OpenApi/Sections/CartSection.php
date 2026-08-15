<?php

namespace App\OpenApi\Sections;

class CartSection
{
    /**
     * $ref to a named response schema (single entity).
     */
    private static function single(string $ref): array
    {
        return ['content' => ['application/json' => ['schema' => ['$ref' => $ref]]]];
    }

    /**
     * $ref to MessageResponse (success + message only, no data).
     */
    private static function msg(): array
    {
        return ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/MessageResponse']]]];
    }

    /**
     * $ref to SuccessWithMessage (success + message + optional data).
     */
    private static function swm(): array
    {
        return ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/SuccessWithMessage']]]];
    }

    private static function param(string $name, string $description): array
    {
        return ['name' => $name, 'in' => 'path', 'required' => true, 'description' => $description, 'schema' => ['type' => 'string']];
    }

    public static function paths(): array
    {
        return [
            // ── Cart ──
            '/cart' => [
                'get' => [
                    'summary' => 'Get current cart contents',
                    'tags' => ['Cart'],
                    'security' => [['bearerAuth' => []]],
                    'responses' => ['200' => self::single('#/components/schemas/CartResponse')],
                ],
                'post' => [
                    'summary' => 'Add item to cart',
                    'tags' => ['Cart'],
                    'security' => [['bearerAuth' => []]],
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/AddToCartRequest']]]],
                    'responses' => ['201' => self::single('#/components/schemas/CartItemAddedResponse'), '422' => ['description' => 'Validation error']],
                ],
                'delete' => [
                    'summary' => 'Clear entire cart',
                    'tags' => ['Cart'],
                    'security' => [['bearerAuth' => []]],
                    'responses' => ['200' => self::msg()],
                ],
            ],
            '/cart/items' => [
                'post' => [
                    'summary' => 'Add item(s) to cart (alias)',
                    'tags' => ['Cart'],
                    'security' => [['bearerAuth' => []]],
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/AddToCartRequest']]]],
                    'responses' => ['201' => self::single('#/components/schemas/CartItemAddedResponse'), '422' => ['description' => 'Validation error']],
                ],
            ],
            '/cart/validate' => [
                'post' => [
                    'summary' => 'Validate cart items (stock check)',
                    'tags' => ['Cart'],
                    'security' => [['bearerAuth' => []]],
                    'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => []]]]],
                    'responses' => ['200' => self::single('#/components/schemas/CartResponse')],
                ],
            ],
            '/cart/merge' => [
                'post' => [
                    'summary' => 'Merge guest cart with user cart',
                    'tags' => ['Cart'],
                    'security' => [['bearerAuth' => []]],
                    'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['session_id' => ['type' => 'string', 'nullable' => true]]]]]],
                    'responses' => ['200' => self::single('#/components/schemas/CartResponse')],
                ],
            ],

            // ── Checkout ──
            '/cart/{itemId}' => [
                'patch' => [
                    'summary' => 'Update cart item quantity',
                    'tags' => ['Cart'],
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [self::param('itemId', 'Cart Item UUID')],
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['quantity'], 'properties' => ['quantity' => ['type' => 'integer', 'minimum' => 1]]]]]],
                    'responses' => ['200' => self::swm(), '422' => ['description' => 'Validation error']],
                ],
                'delete' => [
                    'summary' => 'Remove item from cart',
                    'tags' => ['Cart'],
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [self::param('itemId', 'Cart Item UUID')],
                    'responses' => ['200' => self::msg()],
                ],
            ],
            '/checkout' => [
                'post' => [
                    'summary' => 'Process checkout (supports guest)',
                    'tags' => ['Checkout'],
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => [
                        'address_id' => ['type' => 'string'],
                        'payment_method' => ['type' => 'string'],
                        'coupon_code' => ['type' => 'string'],
                    ]]]]],
                    'responses' => [
                        '200' => self::single('#/components/schemas/OrderResponse'),
                        '422' => ['description' => 'Validation error'],
                    ],
                ],
            ],
            '/checkout/coupon' => [
                'post' => ['summary' => 'Apply coupon at checkout', 'tags' => ['Checkout'], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ValidateCouponRequest']]]], 'responses' => ['200' => self::swm()]],
                'delete' => ['summary' => 'Remove coupon at checkout', 'tags' => ['Checkout'], 'responses' => ['200' => self::msg()]],
            ],
            '/checkout/summary' => [
                'get' => ['summary' => 'Get checkout summary', 'tags' => ['Checkout'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/GenericDataResponse')]],
            ],
            '/checkout/shipping' => [
                'post' => ['summary' => 'Calculate shipping for checkout', 'tags' => ['Checkout'], 'security' => [['bearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['address_id' => ['type' => 'string'], 'items' => ['type' => 'array', 'items' => ['type' => 'object']]]]]]], 'responses' => ['200' => self::single('#/components/schemas/GenericListResponse')]],
            ],

            // ── Orders ──
            '/orders' => [
                'get' => ['summary' => 'List user orders', 'tags' => ['Orders'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/OrderListResponse')]],
                'post' => ['summary' => 'Create new order', 'tags' => ['Orders'], 'security' => [['bearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['address_id' => ['type' => 'string'], 'payment_method' => ['type' => 'string'], 'coupon_code' => ['type' => 'string', 'nullable' => true], 'notes' => ['type' => 'string', 'nullable' => true]]]]]], 'responses' => ['201' => self::single('#/components/schemas/OrderResponse')]],
            ],
            '/orders/{id}' => [
                'get' => ['summary' => 'Get order details', 'tags' => ['Orders'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::param('id', 'Order UUID')], 'responses' => ['200' => self::single('#/components/schemas/OrderResponse'), '404' => ['description' => 'Order not found']]],
            ],
            '/orders/{id}/cancel' => [
                'patch' => ['summary' => 'Cancel an order', 'tags' => ['Orders'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::param('id', 'Order UUID')], 'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['reason' => ['type' => 'string', 'nullable' => true]]]]]], 'responses' => ['200' => self::msg()]],
            ],
            '/orders/track-by-number/{orderNumber}' => [
                'get' => ['summary' => 'Track order by order number', 'tags' => ['Orders'], 'security' => [['bearerAuth' => []]], 'parameters' => [['name' => 'orderNumber', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]], 'responses' => ['200' => self::single('#/components/schemas/OrderResponse')]],
            ],
            '/orders/{orderId}/invoice' => [
                'get' => ['summary' => 'Get order invoice', 'tags' => ['Orders'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::param('orderId', 'Order UUID')], 'responses' => ['200' => self::single('#/components/schemas/GenericDataResponse')]],
            ],
            '/orders/{orderId}/invoice/download' => [
                'get' => ['summary' => 'Download order invoice PDF', 'tags' => ['Orders'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::param('orderId', 'Order UUID')], 'responses' => ['200' => ['description' => 'PDF download', 'content' => ['application/pdf' => ['schema' => ['type' => 'string', 'format' => 'binary']]]]]],
            ],
            '/orders/{orderId}/tracking' => [
                'get' => ['summary' => 'Get order tracking timeline', 'tags' => ['Orders'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::param('orderId', 'Order UUID')], 'responses' => ['200' => self::single('#/components/schemas/OrderResponse')]],
            ],
            '/orders/{orderId}/subscribe-updates' => [
                'post' => ['summary' => 'Subscribe to order updates', 'tags' => ['Orders'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::param('orderId', 'Order UUID')], 'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['email' => ['type' => 'string', 'format' => 'email', 'nullable' => true], 'sms' => ['type' => 'boolean']]]]]], 'responses' => ['200' => self::msg()]],
            ],
            '/orders/{orderId}/return' => [
                'post' => ['summary' => 'Request return for order', 'tags' => ['Orders'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::param('orderId', 'Order UUID')], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['reason' => ['type' => 'string'], 'items' => ['type' => 'array', 'items' => ['type' => 'object']], 'notes' => ['type' => 'string', 'nullable' => true]]]]]], 'responses' => ['200' => self::msg()]],
            ],
        
];
    }
}
