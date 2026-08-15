<?php

namespace App\Services;

use App\Repositories\AdminRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SettingsRepository;
use App\Exceptions\AppError;
use Illuminate\Support\Str;

class AdminService
{
    protected array $validOrderTransitions = [
        'PENDING'    => ['CONFIRMED', 'CANCELLED'],
        'CONFIRMED'  => ['PROCESSING', 'CANCELLED'],
        'PROCESSING' => ['SHIPPED', 'CANCELLED'],
        'SHIPPED'    => ['DELIVERED'],
        'DELIVERED'  => ['RETURNED', 'RETURN_REQUESTED'],
        'CANCELLED'  => [],
        'RETURNED'   => [],
    ];

    public function __construct(
        protected AdminRepository $adminRepository,
        protected OrderRepository $orderRepository,
        protected ProductRepository $productRepository,
        protected SettingsRepository $settingsRepository
    ) {}

    // ── Dashboard ──

    public function getDashboard(): array
    {
        $metrics = $this->adminRepository->getDashboardMetrics();
        $recentOrders = $this->adminRepository->getRecentOrders(5);
        $metrics['recentOrders'] = $recentOrders;
        return $metrics;
    }

    /**
     * Get ALL dashboard data in one consolidated response.
     * This replaces 14+ individual API calls with a single endpoint,
     * dramatically reducing network overhead on dashboard load.
     */
    public function getFullDashboard(?string $startDate = null, ?string $endDate = null): array
    {
        // All data is individually cached (300s TTL) via AdminRepository
        // but fetched together so the frontend only makes 1 HTTP round trip
        $metrics        = $this->adminRepository->getDashboardMetrics($startDate, $endDate);
        $health         = $this->adminRepository->getSystemHealth();
        $logs           = $this->getActivityLogs(8);
        $orders         = $this->adminRepository->getRecentOrders(8);
        $orderStatus    = $this->adminRepository->getOrderStatusDistribution();
        $topProducts    = $this->adminRepository->getProductAnalytics(5);
        $revenueComp    = $this->adminRepository->getRevenueComparison();
        $customerGrowth = $this->adminRepository->getCustomerGrowth(12);
        $hourlyDist     = $this->adminRepository->getHourlyDistribution();
        $paymentTrends  = $this->adminRepository->getPaymentMethodTrends(30);
        $conversion     = $this->adminRepository->getConversionMetrics();
        $dailySales     = $this->adminRepository->getDailySales(14);
        $reviewAnalytics = $this->adminRepository->getReviewAnalytics(30);
        $lowStock       = $this->adminRepository->getLowStockVariants();

        return compact(
            'metrics', 'health', 'logs', 'orders', 'orderStatus',
            'topProducts', 'revenueComp', 'customerGrowth', 'hourlyDist',
            'paymentTrends', 'conversion', 'dailySales', 'reviewAnalytics', 'lowStock'
        );
    }

    public function getRevenueTrends(int $days = 30): array
    {
        return $this->adminRepository->getRevenueTrends($days);
    }

    public function getRecentOrders(int $limit = 5): array
    {
        return $this->adminRepository->getRecentOrders($limit)->toArray();
    }

    public function getActivityLogs(int $limit = 20): array
    {
        return \App\Models\ActivityLog::with('user')->latest()->take($limit)->get()->toArray();
    }

    public function getSystemHealth(): array
    {
        return $this->adminRepository->getSystemHealth();
    }

    // ── Analytics ──

    public function getSalesAnalytics(int $days = 30): array
    {
        return $this->adminRepository->getSalesAnalytics($days);
    }

    public function getProductAnalytics(int $limit = 20): array
    {
        return $this->adminRepository->getProductAnalytics($limit)->toArray();
    }

    public function getUserAnalytics(): array
    {
        return $this->adminRepository->getUserAnalytics();
    }

    public function getOrderStatusDistribution(): array
    {
        return $this->adminRepository->getOrderStatusDistribution();
    }

    public function getPaymentMethodStats(): array
    {
        return $this->adminRepository->getPaymentMethodStats();
    }

    public function getCustomerLifetimeValue(string $userId): array
    {
        return $this->adminRepository->getCustomerLifetimeValue($userId);
    }

    public function getTopCustomers(int $limit = 20): array
    {
        return $this->adminRepository->getTopCustomers($limit)->toArray();
    }

    public function getCategoryPerformance(): array
    {
        return $this->adminRepository->getCategoryPerformance()->toArray();
    }

    public function getDailySales(int $days = 30): array
    {
        return $this->adminRepository->getDailySales($days);
    }

    public function getHourlyDistribution(): array
    {
        return $this->adminRepository->getHourlyDistribution();
    }

    public function getRevenueComparison(): array
    {
        return $this->adminRepository->getRevenueComparison();
    }

    public function getCustomerGrowth(int $months = 12): array
    {
        return $this->adminRepository->getCustomerGrowth($months);
    }

    public function getConversionMetrics(): array
    {
        return $this->adminRepository->getConversionMetrics();
    }

    public function getPaymentMethodTrends(int $days = 30): array
    {
        return $this->adminRepository->getPaymentMethodTrends($days);
    }

    public function getOrderRevenueStats(): array
    {
        return $this->adminRepository->getOrderRevenueStats();
    }

    // ── Review Analytics ──

    public function getReviewAnalytics(int $days = 30): array
    {
        return $this->adminRepository->getReviewAnalytics($days);
    }

    /**
     * Get ALL analytics data in one consolidated response.
     * Replaces 15 individual API calls with a single endpoint,
     * dramatically reducing network overhead on the Analytics page load.
     */
    public function getFullAnalytics(?string $startDate = null, ?string $endDate = null, int $days = 30): array
    {
        $sales               = $this->adminRepository->getSalesAnalytics($days);
        $revenueTrends       = $this->adminRepository->getRevenueTrends($days);
        $categoryPerformance = $this->adminRepository->getCategoryPerformance()->toArray();
        $orderStatus         = $this->adminRepository->getOrderStatusDistribution();
        $paymentMethods      = $this->adminRepository->getPaymentMethodStats();
        $topCustomers        = $this->adminRepository->getTopCustomers(20)->toArray();
        $dailySales          = $this->adminRepository->getDailySales($days);
        $hourlyDistribution  = $this->adminRepository->getHourlyDistribution();
        $revenueComparison   = $this->adminRepository->getRevenueComparison();
        $customerGrowth      = $this->adminRepository->getCustomerGrowth(12);
        $conversionMetrics   = $this->adminRepository->getConversionMetrics();
        $paymentTrends       = $this->adminRepository->getPaymentMethodTrends($days);
        $topProducts         = $this->adminRepository->getProductAnalytics(20)->toArray();
        $userAnalytics       = $this->adminRepository->getUserAnalytics();
        $dashboardSummary    = $this->getDashboardSummary($startDate, $endDate);

        return compact(
            'sales', 'revenueTrends', 'categoryPerformance', 'orderStatus',
            'paymentMethods', 'topCustomers', 'dailySales', 'hourlyDistribution',
            'revenueComparison', 'customerGrowth', 'conversionMetrics',
            'paymentTrends', 'topProducts', 'userAnalytics', 'dashboardSummary'
        );
    }

    public function getDashboardSummary(?string $startDate = null, ?string $endDate = null): array
    {
        $metrics = $this->adminRepository->getDashboardMetrics($startDate, $endDate);
        $recentOrders = $this->adminRepository->getRecentOrders(5);
        $recentUsers = $this->adminRepository->getRecentUsers(5);
        $revenueTrends = $this->adminRepository->getRevenueTrends(7);
        $orderStatusDist = $this->adminRepository->getOrderStatusDistribution();

        return compact('metrics', 'recentOrders', 'recentUsers', 'revenueTrends', 'orderStatusDist');
    }

    // ── Staff ──

    public function getStaff(array $filters = []): array
    {
        $staff = $this->adminRepository->getStaff($filters);
        return $staff->toArray();
    }

    public function getStaffUserModel(string $id): \App\Models\User
    {
        $user = \App\Models\User::find($id);
        if (!$user || !in_array($user->role, ['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'SUPPORT_AGENT', 'FINANCE'])) {
            throw AppError::notFound('Staff member not found');
        }
        return $user;
    }

    public function createStaff(array $data): \App\Models\User
    {
        if (empty($data['email'])) {
            throw AppError::validation('Email is required');
        }

        $existing = \App\Models\User::where('email', $data['email'])->first();
        if ($existing) {
            throw AppError::conflict('User with this email already exists');
        }

        return $this->adminRepository->createStaff($data);
    }

    public function updateStaff(string $id, array $data): \App\Models\User
    {
        $this->getStaffUserModel($id); // Verify exists
        return $this->adminRepository->updateStaff($id, $data);
    }

    // ── Backups ──

    public function getBackupSettings(): array
    {
        return $this->adminRepository->getBackupSettings();
    }

    public function updateBackupSettings(array $data): array
    {
        return $this->adminRepository->updateBackupSettings($data);
    }

    public function createBackup(): array
    {
        return $this->adminRepository->createBackup();
    }

    public function listBackups(): array
    {
        return $this->adminRepository->listBackups();
    }

    public function deleteBackup(string $filename): void
    {
        $this->adminRepository->deleteBackup($filename);
    }

    public function getBackupPath(string $filename): ?string
    {
        return $this->adminRepository->getBackupPath($filename);
    }

    // ── Products ──

    public function getProducts(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->adminRepository->getProducts($filters);
    }

    public function getProductById(string $id): array
    {
        $product = $this->adminRepository->getProductById($id);
        if (!$product) {
            throw AppError::notFound('Product not found');
        }
        return $product->toArray();
    }

    public function getProductFormData(): array
    {
        return $this->adminRepository->getCategoriesAndBrands();
    }

    public function createProduct(array $data): \App\Models\Product
    {
        if (empty($data['name'])) {
            throw AppError::validation('Product name is required');
        }

        $data['slug'] = Str::slug($data['name']) . '-' . Str::random(6);
        $data['status'] = $data['status'] ?? 'DRAFT';

        return $this->adminRepository->createProduct($data);
    }

    public function updateProduct(string $id, array $data): \App\Models\Product
    {
        return $this->adminRepository->updateProduct($id, $data);
    }

    public function deleteProduct(string $id): void
    {
        // Product existence is verified by the repository via findOrFail
        $this->adminRepository->deleteProduct($id);
    }

    public function getProductModel(string $id): \App\Models\Product
    {
        $product = $this->adminRepository->getProductById($id);
        if (!$product) {
            throw AppError::notFound('Product not found');
        }
        return $product;
    }

    // ── Orders ──

    public function getOrders(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->adminRepository->getOrders($filters);
    }

    public function getOrderById(string $id): array
    {
        $order = $this->adminRepository->getOrderById($id);
        if (!$order) {
            throw AppError::notFound('Order not found');
        }
        return $order->toArray();
    }

    public function getOrderModel(string $id): \App\Models\Order
    {
        $order = $this->adminRepository->getOrderById($id);
        if (!$order) {
            throw AppError::notFound('Order not found');
        }
        return $order;
    }

    public function updateOrderStatus(string $id, string $newStatus): \App\Models\Order
    {
        $order = \App\Models\Order::findOrFail($id);

        if (!isset($this->validOrderTransitions[$order->status]) ||
            !in_array($newStatus, $this->validOrderTransitions[$order->status])) {
            throw AppError::validation(
                "Cannot transition from {$order->status} to {$newStatus}"
            );
        }

        return $this->adminRepository->updateOrderStatus($id, $newStatus);
    }

    // ── Users ──

    public function getUsers(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->adminRepository->getUsers($filters);
    }

    public function getUserById(string $id): array
    {
        $user = $this->adminRepository->getUserById($id);
        if (!$user) {
            throw AppError::notFound('User not found');
        }
        return $user->toArray();
    }

    public function getUserModel(string $id): \App\Models\User
    {
        $user = $this->adminRepository->getUserById($id);
        if (!$user) {
            throw AppError::notFound('User not found');
        }
        return $user;
    }

    public function manageUser(string $id, array $data): \App\Models\User
    {
        $updateData = [];
        if (isset($data['role'])) {
            $updateData['role'] = $data['role'];
        }
        if (isset($data['is_active'])) {
            $updateData['is_active'] = (bool) $data['is_active'];
        }
        if (isset($data['is_blocked'])) {
            $updateData['is_blocked'] = (bool) $data['is_blocked'];
        }

        return $this->adminRepository->updateUser($id, $updateData);
    }

    // ── Coupons ──

    public function getCoupons(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->adminRepository->getCoupons($filters);
    }

    public function getCouponById(string $id): ?\App\Models\Coupon
    {
        return $this->adminRepository->getCouponById($id);
    }

    public function createCoupon(array $data): \App\Models\Coupon
    {
        if (empty($data['code'])) {
            throw AppError::validation('Coupon code is required');
        }

        $existing = $this->adminRepository->getCouponByCode($data['code']);
        if ($existing) {
            throw AppError::conflict("Coupon code '{$data['code']}' already exists");
        }

        $data['is_active'] = isset($data['is_active']);
        return $this->adminRepository->createCoupon($data);
    }

    public function updateCoupon(string $id, array $data): \App\Models\Coupon
    {
        $coupon = $this->adminRepository->getCouponById($id);
        if (!$coupon) {
            throw AppError::notFound('Coupon not found');
        }

        if (!empty($data['code']) && $data['code'] !== $coupon->code) {
            $existing = $this->adminRepository->getCouponByCode($data['code'], $id);
            if ($existing) {
                throw AppError::conflict("Coupon code '{$data['code']}' already exists");
            }
        }

        return $this->adminRepository->updateCoupon($id, $data);
    }

    // ── Categories ──

    public function getCategories(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        return $this->adminRepository->getCategories($filters);
    }

    public function getCategoriesPaginated(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->adminRepository->getCategoriesPaginated($filters);
    }

    public function createCategory(array $data): \App\Models\Category
    {
        if (empty($data['name'])) {
            throw AppError::validation('Category name is required');
        }
        $data['slug'] = Str::slug($data['name']);
        $data['is_active'] = $data['is_active'] ?? true;
        return $this->adminRepository->createCategory($data);
    }

    public function getCategoryById(string $id): ?\App\Models\Category
    {
        return $this->adminRepository->getCategoryById($id);
    }

    public function updateCategory(string $id, array $data): \App\Models\Category
    {
        $category = $this->adminRepository->getCategoryById($id);
        if (!$category) {
            throw AppError::notFound('Category not found');
        }
        if (!empty($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        return $this->adminRepository->updateCategory($id, $data);
    }

    public function deleteCategory(string $id): void
    {
        $this->adminRepository->deleteCategory($id);
    }

    // ── Brands ──

    public function getBrands(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        return $this->adminRepository->getBrands($filters);
    }

    public function getBrandsPaginated(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->adminRepository->getBrandsPaginated($filters);
    }

    public function createBrand(array $data): \App\Models\Brand
    {
        if (empty($data['name'])) {
            throw AppError::validation('Brand name is required');
        }
        $data['slug'] = Str::slug($data['name']);
        return $this->adminRepository->createBrand($data);
    }

    public function getBrandById(string $id): ?\App\Models\Brand
    {
        return $this->adminRepository->getBrandById($id);
    }

    public function updateBrand(string $id, array $data): \App\Models\Brand
    {
        $brand = $this->adminRepository->getBrandById($id);
        if (!$brand) {
            throw AppError::notFound('Brand not found');
        }
        if (!empty($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        return $this->adminRepository->updateBrand($id, $data);
    }

    public function deleteBrand(string $id): void
    {
        $this->adminRepository->deleteBrand($id);
    }

    // ── Banners ──

    public function getBanners(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->adminRepository->getBanners();
    }

    public function createBanner(array $data): \App\Models\Banner
    {
        $data['is_active'] = $data['is_active'] ?? true;
        $data['position'] = $data['position'] ?? 0;
        return $this->adminRepository->createBanner($data);
    }

    public function getBannerById(string $id): ?\App\Models\Banner
    {
        return $this->adminRepository->getBannerById($id);
    }

    public function updateBanner(string $id, array $data): \App\Models\Banner
    {
        $banner = $this->adminRepository->getBannerById($id);
        if (!$banner) {
            throw AppError::notFound('Banner not found');
        }
        return $this->adminRepository->updateBanner($id, $data);
    }

    public function deleteBanner(string $id): void
    {
        $this->adminRepository->deleteBanner($id);
    }

    // ── Reviews ──

    public function getReviews(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->adminRepository->getReviews($filters);
    }

    public function moderateReview(string $id, bool $isApproved): \App\Models\Review
    {
        return $this->adminRepository->moderateReview($id, $isApproved);
    }

    // ── Tickets ──

    public function getTickets(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->adminRepository->getTickets($filters);
    }

    public function getTicketById(string $id): ?\App\Models\SupportTicket
    {
        return $this->adminRepository->getTicketById($id);
    }

    public function getTicketModel(string $id): \App\Models\SupportTicket
    {
        $ticket = $this->adminRepository->getTicketById($id);
        if (!$ticket) {
            throw AppError::notFound('Ticket not found');
        }
        return $ticket;
    }

    public function updateTicketStatus(string $id, string $status): \App\Models\SupportTicket
    {
        return $this->adminRepository->updateTicketStatus($id, $status);
    }

    // ── Promotions ──

    public function getPromotions(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->adminRepository->getPromotions($filters);
    }

    public function createPromotion(array $data): \App\Models\Promotion
    {
        return $this->adminRepository->createPromotion($data);
    }

    public function getPromotionById(string $id): ?\App\Models\Promotion
    {
        return $this->adminRepository->getPromotionById($id);
    }

    public function updatePromotion(string $id, array $data): \App\Models\Promotion
    {
        $promotion = $this->adminRepository->getPromotionById($id);
        if (!$promotion) {
            throw AppError::notFound('Promotion not found');
        }
        return $this->adminRepository->updatePromotion($id, $data);
    }

    public function deletePromotion(string $id): void
    {
        $this->adminRepository->deletePromotion($id);
    }

    // ── Abandoned Carts ──

    public function getAbandonedCarts(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->adminRepository->getAbandonedCarts($filters);
    }

    public function getAbandonedCartById(string $id): ?\App\Models\AbandonedCart
    {
        return $this->adminRepository->getAbandonedCartById($id);
    }

    public function getAbandonedCartModel(string $id): \App\Models\AbandonedCart
    {
        $cart = $this->adminRepository->getAbandonedCartById($id);
        if (!$cart) {
            throw AppError::notFound('Abandoned cart not found');
        }
        return $cart;
    }

    public function getAbandonedCartStats(): array
    {
        return $this->adminRepository->getAbandonedCartStats();
    }

    // ── Inventory ──

    public function getInventory(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->adminRepository->getInventory($filters);
    }

    public function getInventoryByProduct(string $productId): ?\App\Models\Inventory
    {
        return $this->adminRepository->getInventoryByProduct($productId);
    }

    public function addStock(string $productId, int $quantity, string $reason = ''): \App\Models\Inventory
    {
        return $this->adminRepository->addStock($productId, $quantity, $reason);
    }

    public function reduceStock(string $productId, int $quantity, string $reason = ''): \App\Models\Inventory
    {
        return $this->adminRepository->reduceStock($productId, $quantity, $reason);
    }

    public function getInventoryMovement(string $productId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->adminRepository->getInventoryMovement($productId);
    }

    public function getLowStockProducts(int $threshold = 5): \Illuminate\Database\Eloquent\Collection
    {
        return $this->adminRepository->getLowStockProducts($threshold);
    }

    // ── Notifications ──

    public function getNotifications(): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->adminRepository->getNotifications();
    }

    // ── Product Variants ──

    public function getVariantsByProduct(string $productId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->adminRepository->getVariantsByProduct($productId);
    }

    public function createVariant(array $data): \App\Models\ProductVariant
    {
        return $this->adminRepository->createVariant($data);
    }

    public function updateVariant(string $id, array $data): \App\Models\ProductVariant
    {
        return $this->adminRepository->updateVariant($id, $data);
    }

    public function deleteVariant(string $id): void
    {
        $this->adminRepository->deleteVariant($id);
    }

    // ── CSV Generation ──

    /**
     * Generate a CSV string from a paginated user list.
     */
    public function generateUsersCsv(\Illuminate\Pagination\LengthAwarePaginator $users): string
    {
        $headers = ['ID', 'First Name', 'Last Name', 'Email', 'Role', 'Phone', 'Email Verified', 'Active', 'Blocked', 'Created At'];
        $rows = collect($users->items())->map(fn($u) => [
            $u->id,
            $u->first_name ?? $u->firstName ?? '',
            $u->last_name ?? $u->lastName ?? '',
            $u->email ?? '',
            $u->role ?? '',
            $u->phone_number ?? $u->phone ?? '',
            $u->is_email_verified ?? $u->emailVerified ?? false ? 'Yes' : 'No',
            $u->is_active ?? true ? 'Yes' : 'No',
            $u->is_blocked ?? $u->blocked ?? false ? 'Yes' : 'No',
            $u->created_at?->format('Y-m-d H:i:s') ?? $u->createdAt ?? '',
        ])->toArray();

        $csv = implode(',', array_map(fn($h) => '"' . str_replace('"', '""', $h) . '"', $headers)) . "\n";
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', (string)($v ?? '')) . '"', $row)) . "\n";
        }
        return $csv;
    }

    // ── Settings ──

    public function updateSettings(array $data): array
    {
        foreach ($data as $key => $value) {
            if (!is_null($value)) {
                $this->settingsRepository->setValue($key, $value);
            }
        }
        return $this->settingsRepository->getAllAsArray();
    }

    // ── Coupon Delete ──

    public function deleteCoupon(string $id): void
    {
        $this->adminRepository->deleteCoupon($id);
    }


}
