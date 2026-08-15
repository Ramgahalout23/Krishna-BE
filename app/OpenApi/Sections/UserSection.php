<?php

namespace App\OpenApi\Sections;

class UserSection
{
    /**
     * $ref to a named response schema (single entity).
     */
    private static function single(string $ref): array
    {
        return ['content' => ['application/json' => ['schema' => ['$ref' => $ref]]]];
    }

    /**
     * $ref to SuccessWithMessage (success + message + optional data).
     */
    private static function swm(): array
    {
        return ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/SuccessWithMessage']]]];
    }

    /**
     * $ref to MessageResponse (success + message only).
     */
    private static function msg(): array
    {
        return ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/MessageResponse']]]];
    }

    private static function param(string $n, string $d): array
    {
        return ['name' => $n, 'in' => 'path', 'required' => true, 'description' => $d, 'schema' => ['type' => 'string']];
    }

    public static function paths(): array
    {
        return [
            // ── Wishlist ──
            '/wishlist' => [
                'get' => ['summary' => 'List wishlist items', 'tags' => ['Wishlist'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/WishlistResponse')]],
                'post' => ['summary' => 'Add item to wishlist', 'tags' => ['Wishlist'], 'security' => [['bearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['product_id' => ['type' => 'string']]]]]], 'responses' => ['201' => self::swm()]],
                'delete' => ['summary' => 'Clear wishlist', 'tags' => ['Wishlist'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::msg()]],
            ],
            '/wishlist/bulk' => [
                'post' => ['summary' => 'Bulk add items to wishlist', 'tags' => ['Wishlist'], 'security' => [['bearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['product_ids' => ['type' => 'array', 'items' => ['type' => 'string']]]]]]], 'responses' => ['201' => self::swm()]],
            ],
            '/wishlist/count' => [
                'get' => ['summary' => 'Get wishlist item count', 'tags' => ['Wishlist'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/GenericDataResponse')]],
            ],
            '/wishlist/share' => [
                'post' => ['summary' => 'Share wishlist (generate token)', 'tags' => ['Wishlist'], 'security' => [['bearerAuth' => []]], 'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => []]]]], 'responses' => ['200' => self::msg()]],
                'delete' => ['summary' => 'Unshare wishlist', 'tags' => ['Wishlist'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::msg()]],
                'get' => ['summary' => 'Get wishlist share status', 'tags' => ['Wishlist'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/GenericDataResponse')]],
            ],

            // ── Payments ──
            '/wishlist/{productId}' => [
                'delete' => ['summary' => 'Remove item from wishlist', 'tags' => ['Wishlist'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::param('productId', 'Product UUID')], 'responses' => ['200' => self::msg()]],
            ],
            '/wishlist/{productId}/move-to-cart' => [
                'post' => ['summary' => 'Move wishlist item to cart', 'tags' => ['Wishlist'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::param('productId', 'Product UUID')], 'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => []]]]], 'responses' => ['200' => self::msg()]],
            ],
            '/wishlist/check/{productId}' => [
                'get' => ['summary' => 'Check if product is in wishlist', 'tags' => ['Wishlist'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::param('productId', 'Product UUID')], 'responses' => ['200' => self::single('#/components/schemas/GenericDataResponse')]],
            ],
            '/shared-wishlist/{token}' => [
                'get' => ['summary' => 'View shared wishlist by token', 'tags' => ['Wishlist'], 'parameters' => [self::param('token', 'Share token')], 'responses' => ['200' => self::single('#/components/schemas/WishlistResponse')]],
            ],
            '/payments/methods' => [
                'get' => ['summary' => 'Get available payment methods', 'tags' => ['Payments'], 'responses' => ['200' => self::single('#/components/schemas/PaymentMethodListResponse')]],
            ],
            '/payments/callback' => [
                'get' => ['summary' => 'Handle payment gateway callback', 'tags' => ['Payments'], 'responses' => ['200' => self::swm()]],
            ],
            '/payments/webhook' => [
                'post' => ['summary' => 'Razorpay webhook handler', 'tags' => ['Payments'], 'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => []]]]], 'responses' => ['200' => self::swm()]],
            ],
            '/payments/create-payment-intent' => [
                'post' => ['summary' => 'Create payment intent', 'tags' => ['Payments'], 'security' => [['bearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/CreatePaymentIntentRequest']]]], 'responses' => ['200' => self::single('#/components/schemas/GenericDataResponse')]],
            ],
            '/payments/confirm' => [
                'post' => ['summary' => 'Confirm payment', 'tags' => ['Payments'], 'security' => [['bearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ConfirmPaymentRequest']]]], 'responses' => ['200' => self::swm()]],
            ],
            '/payments/initiate' => [
                'post' => ['summary' => 'Initiate a payment', 'tags' => ['Payments'], 'security' => [['bearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/InitiatePaymentRequest']]]], 'responses' => ['200' => self::swm()]],
            ],
            '/payments/{paymentId}' => [
                'get' => ['summary' => 'Get payment details', 'tags' => ['Payments'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::param('paymentId', 'Payment UUID')], 'responses' => ['200' => self::single('#/components/schemas/PaymentResponse')]],
            ],
            '/payments/razorpay/create-order' => [
                'post' => ['summary' => 'Create Razorpay order', 'tags' => ['Payments'], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/RazorpayCreateOrderRequest']]]], 'responses' => ['200' => self::single('#/components/schemas/GenericDataResponse')]],
            ],
            '/payments/razorpay/verify' => [
                'post' => ['summary' => 'Verify Razorpay payment', 'tags' => ['Payments'], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/RazorpayVerifyRequest']]]], 'responses' => ['200' => self::swm()]],
            ],
            '/payments/custom/initiate' => [
                'post' => ['summary' => 'Initiate custom gateway payment', 'tags' => ['Payments'], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/CustomGatewayRequest']]]], 'responses' => ['200' => self::swm()]],
            ],
            '/payments' => [
                'get' => ['summary' => 'List user payments', 'tags' => ['Payments'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/PaymentListResponse')]],
            ],
            '/payments/{paymentId}/verify' => [
                'post' => ['summary' => 'Verify a payment', 'tags' => ['Payments'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::param('paymentId', 'Payment UUID')], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/VerifyPaymentRequest']]]], 'responses' => ['200' => self::swm()]],
            ],
            '/payments/refunds/list' => [
                'get' => ['summary' => 'List user refunds', 'tags' => ['Payments'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/GenericListResponse')]],
            ],
            '/payments/{paymentId}/refund' => [
                'post' => ['summary' => 'Request refund for payment', 'tags' => ['Payments'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::param('paymentId', 'Payment UUID')], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/RefundPaymentRequest']]]], 'responses' => ['200' => self::swm()]],
            ],

            // ── Addresses (User) ──
            '/addresses' => [
                'get' => ['summary' => 'List user addresses', 'tags' => ['Addresses'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/AddressListResponse')]],
                'post' => ['summary' => 'Create new address', 'tags' => ['Addresses'], 'security' => [['bearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/CreateAddressRequest']]]], 'responses' => ['201' => self::single('#/components/schemas/AddressCreatedResponse'), '422' => ['description' => 'Validation error']]],
            ],
            '/addresses/{id}' => [
                'get' => ['summary' => 'Get address details', 'tags' => ['Addresses'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::param('id', 'Address UUID')], 'responses' => ['200' => self::single('#/components/schemas/AddressResponse')]],
                'put' => ['summary' => 'Update address', 'tags' => ['Addresses'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::param('id', 'Address UUID')], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/CreateAddressRequest']]]], 'responses' => ['200' => self::swm(), '422' => ['description' => 'Validation error']]],
                'delete' => ['summary' => 'Delete address', 'tags' => ['Addresses'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::param('id', 'Address UUID')], 'responses' => ['200' => self::msg()]],
            ],

            // ── User Profile ──
            '/user-profile' => [
                'get' => ['summary' => 'Get user profile', 'tags' => ['User Profile'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/UserResponse')]],
                'put' => ['summary' => 'Update user profile', 'tags' => ['User Profile'], 'security' => [['bearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/UpdateProfileRequest']]]], 'responses' => ['200' => self::single('#/components/schemas/ProfileUpdatedResponse')]],
            ],
            '/user-profile/stats' => [
                'get' => ['summary' => 'Get user statistics', 'tags' => ['User Profile'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/GenericDataResponse')]],
            ],
            '/user-profile/orders' => [
                'get' => ['summary' => 'Get user orders', 'tags' => ['User Profile'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/OrderListResponse')]],
            ],
            '/user-profile/wallet' => [
                'get' => ['summary' => 'Get wallet info', 'tags' => ['User Profile'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/WalletResponse')]],
            ],
            '/user-profile/loyalty' => [
                'get' => ['summary' => 'Get loyalty info', 'tags' => ['User Profile'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/LoyaltyResponse')]],
            ],
            '/user-profile/addresses/default' => [
                'get' => ['summary' => 'Get default address', 'tags' => ['User Profile'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/AddressResponse')]],
            ],
            '/user-profile/addresses' => [
                'get' => ['summary' => 'List profile addresses', 'tags' => ['User Profile'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/AddressListResponse')]],
                'post' => ['summary' => 'Add new address', 'tags' => ['User Profile'], 'security' => [['bearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/CreateAddressRequest']]]], 'responses' => ['201' => self::single('#/components/schemas/AddressCreatedResponse')]],
            ],
            '/user-profile/addresses/{addressId}' => [
                'get' => ['summary' => 'Get address details', 'tags' => ['User Profile'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::param('addressId', 'Address UUID')], 'responses' => ['200' => self::single('#/components/schemas/AddressResponse')]],
                'put' => ['summary' => 'Update address', 'tags' => ['User Profile'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::param('addressId', 'Address UUID')], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/CreateAddressRequest']]]], 'responses' => ['200' => self::swm()]],
                'delete' => ['summary' => 'Delete address', 'tags' => ['User Profile'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::param('addressId', 'Address UUID')], 'responses' => ['200' => self::msg()]],
            ],
            '/user-profile/addresses/{addressId}/set-default' => [
                'post' => ['summary' => 'Set address as default', 'tags' => ['User Profile'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::param('addressId', 'Address UUID')], 'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => []]]]], 'responses' => ['200' => self::msg()]],
            ],

            // ── Wallet ──
            '/wallet' => ['get' => ['summary' => 'Show wallet details', 'tags' => ['Wallet'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/WalletResponse')]]],
            '/wallet/balance' => ['get' => ['summary' => 'Get wallet balance', 'tags' => ['Wallet'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/GenericDataResponse')]]],
            '/wallet/transactions' => ['get' => ['summary' => 'Get wallet transactions', 'tags' => ['Wallet'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/WalletTransactionListResponse')]]],
            '/wallet/recharge' => ['post' => ['summary' => 'Recharge wallet', 'tags' => ['Wallet'], 'security' => [['bearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/RechargeWalletRequest']]]], 'responses' => ['200' => self::swm()]]],

            // ── Loyalty ──
            '/loyalty' => ['get' => ['summary' => 'Show loyalty points', 'tags' => ['Loyalty'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/LoyaltyResponse')]]],
            '/loyalty/balance' => ['get' => ['summary' => 'Get loyalty balance', 'tags' => ['Loyalty'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/GenericDataResponse')]]],
            '/loyalty/history' => ['get' => ['summary' => 'Get loyalty history', 'tags' => ['Loyalty'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/GenericListResponse')]]],
            '/loyalty/info' => ['get' => ['summary' => 'Get loyalty program info', 'tags' => ['Loyalty'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/GenericDataResponse')]]],

            // ── Notifications ──
            '/notifications' => ['get' => ['summary' => 'List notifications (paginated)', 'tags' => ['Notifications'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/NotificationListResponse')]]],
            '/notifications/unread' => ['get' => ['summary' => 'Get unread notifications', 'tags' => ['Notifications'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/NotificationListResponse')]]],
            '/notifications/stats' => ['get' => ['summary' => 'Get notification stats', 'tags' => ['Notifications'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/GenericDataResponse')]]],
            '/notifications/read-all' => ['put' => ['summary' => 'Mark all notifications as read', 'tags' => ['Notifications'], 'security' => [['bearerAuth' => []]], 'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => []]]]], 'responses' => ['200' => self::msg()]]],

            // ── Tickets ──
            '/notifications/{id}' => ['delete' => ['summary' => 'Delete notification', 'tags' => ['Notifications'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::param('id', 'Notification UUID')], 'responses' => ['200' => self::msg()]]],
            '/notifications/type/{type}' => ['get' => ['summary' => 'Get notifications by type', 'tags' => ['Notifications'], 'security' => [['bearerAuth' => []]], 'parameters' => [['name' => 'type', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]], 'responses' => ['200' => self::single('#/components/schemas/NotificationListResponse')]]],
            '/notifications/{id}/read' => ['put' => ['summary' => 'Mark notification as read', 'tags' => ['Notifications'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::param('id', 'Notification UUID')], 'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => []]]]], 'responses' => ['200' => self::msg()]]],
            '/tickets' => [
                'get' => ['summary' => 'List support tickets', 'tags' => ['Tickets'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/TicketListResponse')]],
                'post' => ['summary' => 'Create support ticket', 'tags' => ['Tickets'], 'security' => [['bearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['subject' => ['type' => 'string'], 'description' => ['type' => 'string'], 'category' => ['type' => 'string'], 'priority' => ['type' => 'string', 'enum' => ['LOW', 'MEDIUM', 'HIGH', 'URGENT']]]]]]], 'responses' => ['201' => self::single('#/components/schemas/TicketCreatedResponse'), '422' => ['description' => 'Validation error']]],
            ],
            '/tickets/{id}' => ['get' => ['summary' => 'Get ticket details', 'tags' => ['Tickets'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::param('id', 'Ticket UUID')], 'responses' => ['200' => self::single('#/components/schemas/TicketResponse')]]],
            '/tickets/{id}/messages' => ['post' => ['summary' => 'Add message to ticket', 'tags' => ['Tickets'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::param('id', 'Ticket UUID')], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['message' => ['type' => 'string']]]]]], 'responses' => ['201' => self::swm()]]],

            // ── Chat ──
            '/chat/init' => ['post' => ['summary' => 'Initialize a chat session', 'tags' => ['Chat'], 'security' => [['bearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ChatInitRequest']]]], 'responses' => ['200' => self::single('#/components/schemas/ChatInitResponse')]]],
            '/chat/{ticketId}/messages' => [
                'post' => ['summary' => 'Send chat message', 'tags' => ['Chat'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::param('ticketId', 'Ticket UUID')], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/SendChatMessageRequest']]]], 'responses' => ['201' => self::swm()]],
                'get' => ['summary' => 'Get chat messages', 'tags' => ['Chat'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::param('ticketId', 'Ticket UUID')], 'responses' => ['200' => self::single('#/components/schemas/ChatMessageListResponse')]],
            ],
            '/chat/{ticketId}/typing' => ['post' => ['summary' => 'Send typing indicator', 'tags' => ['Chat'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::param('ticketId', 'Ticket UUID')], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/TypingIndicatorRequest']]]], 'responses' => ['200' => self::msg()]]],

            // ── Refunds & Returns ──
            '/refund-requests' => [
                'post' => ['summary' => 'Create refund request', 'tags' => ['Refunds & Returns'], 'security' => [['bearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/CreateRefundRequest']]]], 'responses' => ['201' => self::swm()]],
                'get' => ['summary' => 'List refund requests', 'tags' => ['Refunds & Returns'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/RefundRequestListResponse')]],
            ],
            '/refunds' => ['get' => ['summary' => 'List user refunds', 'tags' => ['Refunds & Returns'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/GenericListResponse')]]],
            '/return-requests' => [
                'post' => ['summary' => 'Create return request', 'tags' => ['Refunds & Returns'], 'security' => [['bearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/CreateReturnRequest']]]], 'responses' => ['201' => self::swm()]],
                'get' => ['summary' => 'List return requests', 'tags' => ['Refunds & Returns'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/GenericListResponse')]],
            ],

            // ── SMS ──
            '/sms/status' => ['get' => ['summary' => 'Check SMS service status', 'tags' => ['SMS'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/GenericDataResponse')]]],

            // ── Variants ──
            '/variants/{id}' => ['get' => ['summary' => 'Get variant details', 'tags' => ['Product Variants'], 'parameters' => [self::param('id', 'Variant UUID')], 'responses' => ['200' => self::single('#/components/schemas/VariantResponse')]]],

            // ── Abandoned Carts ──
            '/abandoned-carts' => [
                'post' => ['summary' => 'Record abandoned cart', 'tags' => ['Abandoned Carts'], 'security' => [['bearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/RecordAbandonedCartRequest']]]], 'responses' => ['201' => self::swm()]],
                'get' => ['summary' => 'List user abandoned carts', 'tags' => ['Abandoned Carts'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => self::single('#/components/schemas/AbandonedCartListResponse')]],
            ],
            '/abandoned-carts/{id}' => ['get' => ['summary' => 'Get abandoned cart details', 'tags' => ['Abandoned Carts'], 'security' => [['bearerAuth' => []]], 'parameters' => [self::param('id', 'Cart UUID')], 'responses' => ['200' => self::single('#/components/schemas/GenericDataResponse')]]],

            // ── Comments ──
        
];
    }
}
