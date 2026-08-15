<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ShippingController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\TrackingController;
use App\Http\Controllers\Api\AbandonedCartController;
use App\Http\Controllers\Api\UtilityController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\SeoController;
use App\Http\Controllers\Api\ProductVariantController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\TaxController;
use App\Http\Controllers\Api\MarketingController;
use App\Http\Controllers\Api\CampaignTemplateController;
use App\Http\Controllers\Api\AdCampaignController;
use App\Http\Controllers\Api\AIController;
use App\Http\Controllers\Api\EmailTemplateController;
use App\Http\Controllers\Api\NotificationTemplateController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\LoyaltyController;
use App\Http\Controllers\Api\RefundController;
use App\Http\Controllers\Api\ReturnController;
use App\Http\Controllers\Api\SMSController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\CuratedLookController;
use App\Http\Controllers\Api\ReelController;
use App\Http\Controllers\Api\BroadcastingController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\TranslationController;
use App\Http\Controllers\Api\RecentlyViewedController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\DocsController;
use App\Http\Controllers\Api\HomepageController;
use App\Http\Controllers\Api\AppInitController;
use App\Http\Controllers\Api\SavedDesignController;
use App\Http\Controllers\Api\CustomDesignController;

Route::prefix('v1')->group(function () {
    // Consolidated app initialization — replaces 8+ individual API calls
    Route::get('/app-init', AppInitController::class);

    // ── Consolidated Homepage Endpoint (replaces 15+ separate API calls) ──
    Route::get('/homepage', HomepageController::class);


    // ── OpenAPI Documentation Routes ──
    Route::get('/docs/json', [DocsController::class, 'json']);
    Route::get('/docs', [DocsController::class, 'ui']);

    Route::get('/health', function () {
        return response()->json(['status' => 'healthy', 'timestamp' => now()]);
    });

    Route::get('/health/status', function () {
        return response()->json(['status' => 'healthy', 'timestamp' => now(), 'version' => '1.0.0']);
    });

    // ── Public Broadcasting Config ──
    Route::get('/broadcasting/config', [BroadcastingController::class, 'config']);

    // ── Public Currency Routes ──
    Route::get('/currencies', [CurrencyController::class, 'index']);
    Route::get('/currencies/default', [CurrencyController::class, 'default']);
    Route::post('/currencies/convert', [CurrencyController::class, 'convert']);

    // ── Public Translation Routes ──
    Route::get('/translations', [TranslationController::class, 'index']);
    Route::get('/translations/languages', [TranslationController::class, 'languages']);

    // ── Public Payment Routes ──
    Route::get('/payments/methods', [PaymentController::class, 'getPaymentMethods']);
    Route::post('/payments/razorpay/create-order', [PaymentController::class, 'createRazorpayOrder']);
    Route::post('/payments/razorpay/verify', [PaymentController::class, 'verifyRazorpayPayment']);
    Route::post('/payments/custom/initiate', [PaymentController::class, 'initiateCustomGateway']);
    Route::get('/payments/callback', [PaymentController::class, 'handleGatewayCallback']);

    // ── Public Marketing Routes ──
    Route::post('/marketing/subscribe', [MarketingController::class, 'publicSubscribe']);
    Route::post('/marketing/unsubscribe', [MarketingController::class, 'publicUnsubscribe']);

    // ── Public Tax Routes ──
    Route::post('/tax/calculate', [TaxController::class, 'calculateTax']);

    // ── Public Product Variant Routes ──
    Route::get('/products/{productId}/variants/attributes', [ProductVariantController::class, 'getVariantByAttributes']);
    Route::get('/products/{productId}/variants', [ProductVariantController::class, 'byProduct']);
    Route::get('/variants/{id}', [ProductVariantController::class, 'show']);

    // ── Email Service Health ──
    Route::get('/email/health', [UtilityController::class, 'emailPreview']);

    // ── Public Auth Routes (rate-limited to prevent abuse) ──
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
        Route::post('/send-otp', [AuthController::class, 'sendOtp'])->middleware('throttle:5,1');
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:10,1');
        Route::post('/send-verification', [AuthController::class, 'sendVerification'])->middleware('throttle:3,1');
        Route::post('/verify-email', [AuthController::class, 'verifyEmail'])->middleware('throttle:5,1');

        // OAuth Routes (rate-limited to prevent abuse of OAuth flow initiation)
        Route::get('/oauth/status', [AuthController::class, 'oauthStatus'])->middleware('throttle:10,1');
        Route::get('/{provider}', [AuthController::class, 'redirectToProvider'])->where('provider', 'google|facebook')->middleware('throttle:10,1');
        Route::get('/{provider}/callback', [AuthController::class, 'handleProviderCallback'])->where('provider', 'google|facebook')->middleware('throttle:10,1');
    });

    // ── Authenticated Auth Routes (must be after public auth to not conflict) ──
    Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
        Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
        Route::post('/refresh-oauth', [AuthController::class, 'refreshOAuth'])->middleware('admin');
    });

    // Public Product Routes
    Route::get('/products/featured', [ProductController::class, 'featured']);
    Route::get('/products/new-arrivals', [ProductController::class, 'newArrivals']);
    Route::get('/products/best-sellers', [ProductController::class, 'bestSellers']);
    Route::get('/products/search', [ProductController::class, 'search']);
    Route::get('/products/brand', [BrandController::class, 'index']);
    Route::get('/products/category/{categoryId}', [ProductController::class, 'byCategory']);
    Route::get('/products/{productId}/availability', [ProductController::class, 'checkAvailability']);
    Route::get('/products/{id}/related', [ProductController::class, 'related']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::get('/products', [ProductController::class, 'index']);

    Route::get('/categories/hierarchy', [CategoryController::class, 'hierarchy']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{id}', [CategoryController::class, 'show']);

    // Public Banner Routes (specific filter endpoints BEFORE generic /{id})
    Route::get('/banners/homepage', [BannerController::class, 'getHomepageBanners']);
    Route::get('/banners/hero', [BannerController::class, 'getHeroBanners']);
    Route::get('/banners/sale', [BannerController::class, 'getSaleBanners']);
    Route::get('/banners/category', [BannerController::class, 'getCategoryBanners']);
    Route::get('/banners/popup', [BannerController::class, 'getPopupBanners']);
    Route::get('/banners', [BannerController::class, 'getActiveBanners']);
    Route::get('/banners/{id}', [BannerController::class, 'show']);

    // Public Review Routes
    Route::get('/reviews/homepage', [ReviewController::class, 'homepage']);
    Route::get('/reviews/store', [ReviewController::class, 'storeReviews']);
    Route::post('/reviews/store', [ReviewController::class, 'storeStoreReview']);
    Route::get('/reviews/product/{productId}', [ReviewController::class, 'productReviews']);
    Route::get('/reviews/stats/{productId}', [ReviewController::class, 'stats']);
    Route::get('/reviews/verified/{productId}', [ReviewController::class, 'verifiedReviews']);
    Route::get('/reviews/user', [ReviewController::class, 'userReviews'])->middleware('auth:sanctum');
    Route::get('/reviews/{id}', [ReviewController::class, 'show']);

    // Public Category Routes (specific BEFORE generic {id})
    Route::get('/categories/tree', [CategoryController::class, 'tree']);
    Route::get('/categories/{categoryId}/subcategories', [CategoryController::class, 'subcategories']);
    Route::get('/categories/{categoryId}/stats', [CategoryController::class, 'categoryStats']);

    // Public Coupon Routes (specific routes before generic)
    Route::get('/coupons', [CouponController::class, 'index']);
    Route::get('/coupons/auto-apply/list', [CouponController::class, 'getAutoApply']);
    Route::post('/coupons/best', [CouponController::class, 'getBest']);
    Route::get('/coupons/{code}', [CouponController::class, 'getByCode']);
    Route::post('/coupons/validate', [CouponController::class, 'validateCoupon']);
    Route::post('/coupons/apply', [CouponController::class, 'apply']);

    // Public Shipping Routes
    Route::get('/shipping/methods', [ShippingController::class, 'methods']);
    Route::get('/shipping/providers', [ShippingController::class, 'providers']);
    Route::get('/shipping/zones', [ShippingController::class, 'zones']);
    Route::post('/shipping/calculate', [ShippingController::class, 'calculate']);
    Route::get('/shipping/tracking/{trackingNumber}', [ShippingController::class, 'trackShipment']);
    Route::get('/shipping/track/{trackingId}', [ShippingController::class, 'trackShipment']);

    // Public Promotion Routes
    Route::get('/promotions', [PromotionController::class, 'index']);
    Route::get('/store-offers', [PromotionController::class, 'storeOffers']);

    // Public Shared Wishlist Route
    Route::get('/shared-wishlist/{token}', [WishlistController::class, 'viewShared']);

    // Public Tracking Routes
    Route::post('/tracking/pageview', [TrackingController::class, 'pageView']);
    Route::post('/tracking/session', [TrackingController::class, 'createSession']);
    Route::patch('/tracking/session/{sessionId}/end', [TrackingController::class, 'endSession']);
    Route::post('/tracking/event', [TrackingController::class, 'recordEvent']);
    Route::post('/payments/webhook', [PaymentController::class, 'webhook']);

    // Public Recent Orders (for FOMO purchase notifications on storefront)
    Route::get('/orders/recent', [OrderController::class, 'recentOrders']);

    // Public Settings
    Route::get('/settings', [SettingsController::class, 'index']);
    Route::get('/settings/maintenance', [SettingsController::class, 'getMaintenanceStatus']);
    Route::get('/settings/404', [SettingsController::class, 'getCustom404']);
    Route::get('/settings/{key}', [SettingsController::class, 'show']);

    // Public Pages
    Route::get('/pages', [PageController::class, 'index']);
    Route::get('/pages/{slug}', [PageController::class, 'show']);

    // Public SEO (specific routes BEFORE generic {entityType}/{entityId} routes)
    Route::get('/seo/global', [SeoController::class, 'globalSEO']);
    Route::get('/seo/sitemap', [SeoController::class, 'sitemap']);
    Route::get('/seo/robots', [SeoController::class, 'robotsTxt']);
    Route::get('/seo/robots/raw', [SeoController::class, 'robotsTxtRaw']);
    Route::get('/seo/sitemap/raw', [SeoController::class, 'sitemapRaw']);
    Route::get('/seo/pages/{slug}', [SeoController::class, 'show']);
    Route::get('/seo/{entityType}/{entityId}', [SeoController::class, 'showEntitySEO']);

    // Public Reels
    Route::get('/reels', [ReelController::class, 'index']);

    // Authenticated Reel Likes (inside the auth:sanctum group we add the like/unlike routes)
    // These are placed here for reference — they are nested inside the auth group below

    // Public Curated Looks
    Route::get('/curated-looks', [CuratedLookController::class, 'index']);

    // Public Inventory Check (matches TypeScript GET /inventory/:productId/check)
    Route::get('/inventory/{productId}/check', [InventoryController::class, 'check']);

    // ── Public Checkout Routes (supports guest checkout) ──
    Route::post('/checkout', [CheckoutController::class, 'checkout']);
    Route::post('/checkout/volume-discount', [CheckoutController::class, 'calculateVolumeDiscount']);
    Route::post('/checkout/coupon', [CheckoutController::class, 'applyCoupon']);
    Route::delete('/checkout/coupon', [CheckoutController::class, 'removeCoupon']);

    // ── Public Custom Design Upload (supports guest users uploading designs before checkout) ──
    Route::post('/custom-designs/upload', [CustomDesignController::class, 'uploadDesignImage']);

    // ── Authenticated User Routes ──
    Route::middleware('auth:sanctum')->group(function () {
        // ── SMS Routes ──
        Route::get('/sms/status', [SMSController::class, 'status']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

        Route::get('/cart', [CartController::class, 'index']);
        Route::post('/cart', [CartController::class, 'addItem']);
        Route::post('/cart/items', [CartController::class, 'addItem']);
        Route::patch('/cart/{itemId}', [CartController::class, 'updateItem']);
        Route::delete('/cart/{itemId}', [CartController::class, 'removeItem']);
        Route::delete('/cart', [CartController::class, 'clear']);
        Route::post('/cart/validate', [CartController::class, 'validateCart']);

        Route::get('/checkout/summary', [CheckoutController::class, 'summary']);
        Route::post('/checkout/shipping', [CheckoutController::class, 'calculateShipping']);

        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
        Route::patch('/orders/{id}/cancel', [OrderController::class, 'cancel']);
        Route::get('/orders/track-by-number/{orderNumber}', [OrderController::class, 'tracking']);

        // Invoice routes must be BEFORE generic /orders/{id}
        Route::get('/orders/{orderId}/invoice', [InvoiceController::class, 'show']);
        Route::get('/orders/{orderId}/invoice/download', [InvoiceController::class, 'download']);

        // ── Order Tracking (authenticated user) ──
        Route::get('/orders/{orderId}/tracking', [OrderController::class, 'getOrderTracking']);
        Route::post('/orders/{orderId}/subscribe-updates', [OrderController::class, 'subscribeToUpdates']);                        Route::post('/orders/{orderId}/return', [OrderController::class, 'requestReturn']);

                        // ── Recently Viewed Products ──
                        Route::get('/recently-viewed', [RecentlyViewedController::class, 'index']);
                        Route::post('/recently-viewed', [RecentlyViewedController::class, 'store']);

                        // ── Saved T-Shirt Designs (Customizer) ──
                        Route::get('/customizer/designs', [SavedDesignController::class, 'index']);
                        Route::post('/customizer/designs', [SavedDesignController::class, 'store']);
                        Route::get('/customizer/designs/{id}', [SavedDesignController::class, 'show']);
                        Route::put('/customizer/designs/{id}', [SavedDesignController::class, 'update']);
                        Route::delete('/customizer/designs/{id}', [SavedDesignController::class, 'destroy']);
                        Route::post('/customizer/upload', [SavedDesignController::class, 'uploadDesignImage']);

                        // ── Custom Design Orders ──
                        Route::get('/custom-designs/user', [CustomDesignController::class, 'userDesigns']);
                        Route::post('/custom-designs', [CustomDesignController::class, 'store']);

        // ── Reel Likes (authenticated) ──
        Route::post('/reels/{id}/like', [ReelController::class, 'like']);
        Route::delete('/reels/{id}/like', [ReelController::class, 'unlike']);
        Route::get('/reels/{id}/liked', [ReelController::class, 'liked']);

        // ── Cart Merge ──
        Route::post('/cart/merge', [CartController::class, 'mergeCart']);

        // ── Notifications (authenticated user) ──
        Route::get('/notifications/unread', [NotificationController::class, 'getUnreadNotifications']);
        Route::delete('/notifications/{id}', [NotificationController::class, 'deleteNotification']);
        Route::get('/notifications/type/{type}', [NotificationController::class, 'getNotificationsByType']);
        Route::get('/notifications/stats', [NotificationController::class, 'getNotificationStats']);

        // ── Wishlist Sharing (must be BEFORE generic /{productId} routes to avoid route shadowing) ──
        Route::post('/wishlist/share', [WishlistController::class, 'share']);
        Route::delete('/wishlist/share', [WishlistController::class, 'unshare']);
        Route::get('/wishlist/share', [WishlistController::class, 'shareStatus']);

        Route::get('/wishlist', [WishlistController::class, 'index']);
        Route::post('/wishlist', [WishlistController::class, 'store']);
        Route::delete('/wishlist/{productId}', [WishlistController::class, 'destroy']);
        Route::delete('/wishlist', [WishlistController::class, 'clearWishlist']);
        Route::post('/wishlist/bulk', [WishlistController::class, 'bulkAdd']);
        Route::post('/wishlist/{productId}/move-to-cart', [WishlistController::class, 'moveToCart']);
        Route::get('/wishlist/check/{productId}', [WishlistController::class, 'check']);
        Route::get('/wishlist/count', [WishlistController::class, 'count']);

        Route::post('/reviews', [ReviewController::class, 'store']);
        Route::put('/reviews/{id}', [ReviewController::class, 'update']);
        Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);
        Route::post('/reviews/{id}/helpful', [ReviewController::class, 'markHelpful']);
        Route::post('/reviews/{id}/unhelpful', [ReviewController::class, 'markUnhelpful']);

        // ── Review Image Uploads (user-facing) ──
        Route::post('/uploads/review-image', [ReviewController::class, 'uploadReviewImage']);
        Route::post('/uploads/review-images', [ReviewController::class, 'uploadReviewImages']);

        // ── Avatar Upload (user-facing) ──
        Route::post('/uploads/avatar', [UserProfileController::class, 'uploadAvatar']);

        Route::get('/addresses', [AddressController::class, 'index']);
        Route::post('/addresses', [AddressController::class, 'store']);
        Route::get('/addresses/{id}', [AddressController::class, 'show']);
        Route::put('/addresses/{id}', [AddressController::class, 'update']);
        Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);

        Route::post('/coupons/apply', [CouponController::class, 'validateCoupon']);
        Route::delete('/coupons/remove', [CheckoutController::class, 'removeCoupon']);

        Route::get('/payments', [PaymentController::class, 'index']);
        Route::get('/payments/{paymentId}', [PaymentController::class, 'getPaymentDetails']);
        Route::post('/payments/create-payment-intent', [PaymentController::class, 'createPaymentIntent']);
        Route::post('/payments/confirm', [PaymentController::class, 'confirm']);
        Route::post('/payments/initiate', [PaymentController::class, 'initiatePayment']);
        Route::post('/payments/{paymentId}/verify', [PaymentController::class, 'verifyPayment']);
        Route::get('/payments/refunds/list', [PaymentController::class, 'getUserRefunds']);
        Route::post('/payments/{paymentId}/refund', [PaymentController::class, 'processRefund']);

        Route::get('/user-profile', [UserProfileController::class, 'show']);
        Route::put('/user-profile', [AuthController::class, 'updateProfile']);
        Route::get('/user-profile/stats', [UserProfileController::class, 'stats']);
        Route::get('/user-profile/orders', [UserProfileController::class, 'orders']);
        Route::get('/user-profile/wallet', [UserProfileController::class, 'wallet']);
        Route::get('/user-profile/loyalty', [UserProfileController::class, 'loyalty']);
        // ── User Profile Addresses (specific before generic) ──
        Route::get('/user-profile/addresses/default', [UserProfileController::class, 'defaultAddress']);
        Route::get('/user-profile/addresses', [UserProfileController::class, 'addresses']);
        Route::post('/user-profile/addresses', [UserProfileController::class, 'addAddress']);
        Route::get('/user-profile/addresses/{addressId}', [UserProfileController::class, 'showAddress']);
        Route::put('/user-profile/addresses/{addressId}', [UserProfileController::class, 'updateAddress']);
        Route::delete('/user-profile/addresses/{addressId}', [UserProfileController::class, 'deleteAddress']);
        Route::post('/user-profile/addresses/{addressId}/set-default', [UserProfileController::class, 'setDefaultAddress']);

        // ── Wallet Routes (User) ──
        Route::get('/wallet', [WalletController::class, 'show']);
        Route::get('/wallet/balance', [WalletController::class, 'balance']);
        Route::get('/wallet/transactions', [WalletController::class, 'transactions']);
        Route::post('/wallet/recharge', [WalletController::class, 'recharge']);

        // ── Loyalty Routes (User) ──
        Route::get('/loyalty', [LoyaltyController::class, 'show']);
        Route::get('/loyalty/balance', [LoyaltyController::class, 'balance']);
        Route::get('/loyalty/history', [LoyaltyController::class, 'history']);
        Route::get('/loyalty/info', [LoyaltyController::class, 'info']);

        // ── Refund Routes (User) ──
        Route::post('/refund-requests', [RefundController::class, 'store']);
        Route::get('/refund-requests', [RefundController::class, 'index']);
        Route::get('/refunds', [RefundController::class, 'refunds']);

        // ── Return Routes (User) ──
        Route::post('/return-requests', [ReturnController::class, 'store']);
        Route::get('/return-requests', [ReturnController::class, 'index']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::put('/notifications/{id}/read', [NotificationController::class, 'markRead']);
        Route::put('/notifications/read-all', [NotificationController::class, 'markAllRead']);

        Route::get('/tickets', [SupportTicketController::class, 'index']);
        Route::post('/tickets', [SupportTicketController::class, 'store']);
        Route::get('/tickets/{id}', [SupportTicketController::class, 'show']);
        Route::post('/tickets/{id}/messages', [SupportTicketController::class, 'addMessage']);
        Route::post('/abandoned-carts', [AbandonedCartController::class, 'store']);
        Route::get('/abandoned-carts', [AbandonedCartController::class, 'userCarts']);
        Route::get('/abandoned-carts/{id}', [AbandonedCartController::class, 'show']);

        // ── Shipping (User) ──
        Route::get('/shipping/order/{orderId}', [ShippingController::class, 'getShippingByOrder']);
        Route::get('/shipping/my-shipments', [ShippingController::class, 'getUserShipments']);
        Route::get('/shipping/{shippingId}', [ShippingController::class, 'show']);

        // ── Chat Routes ──
        Route::post('/chat/init', [ChatController::class, 'init']);
        Route::post('/chat/{ticketId}/messages', [ChatController::class, 'sendMessage']);
        Route::post('/chat/{ticketId}/typing', [ChatController::class, 'sendTyping']);
        Route::get('/chat/{ticketId}/messages', [ChatController::class, 'getMessages']);
    });

    // ── Admin Shipping Routes (inside admin middleware group) ──
    Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
        require __DIR__.'/admin/shipping.php';
    });

    // ── Admin Routes ──
    Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
        require __DIR__.'/admin/dashboard.php';
        require __DIR__.'/admin/users.php';
        require __DIR__.'/admin/catalog.php';
        require __DIR__.'/admin/orders.php';
        require __DIR__.'/admin/marketing.php';
        require __DIR__.'/admin/content.php';
        require __DIR__.'/admin/system.php';

        // ── Custom Designs (admin management) ──
        Route::get('/custom-designs/stats', [CustomDesignController::class, 'stats']);
        Route::get('/custom-designs', [CustomDesignController::class, 'index']);
        Route::get('/custom-designs/{id}', [CustomDesignController::class, 'show']);
        Route::patch('/custom-designs/{id}/status', [CustomDesignController::class, 'updateStatus']);
        Route::patch('/custom-designs/{id}/notes', [CustomDesignController::class, 'updateNotes']);
    });

});

// ── Sitemap XML (outside v1 prefix, at root) ──
Route::get('/sitemap.xml', function () {
    $url = url('/');
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    $xml .= "  <url><loc>{$url}</loc><priority>1.0</priority></url>\n";
    $xml .= "  <url><loc>{$url}/products</loc><priority>0.9</priority></url>\n";
    $xml .= "  <url><loc>{$url}/categories</loc><priority>0.8</priority></url>\n";
    $xml .= '</urlset>';
    return response($xml, 200)->header('Content-Type', 'application/xml');
});

Route::get('/', function () {
    return response()->json(['message' => 'E-Commerce API v1', 'version' => '1.0.0']);
});
