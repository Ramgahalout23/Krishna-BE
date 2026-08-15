<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminService;
use Illuminate\Http\Request;

/**
 * @deprecated Blade admin views are no longer used (admin is React SPA).
 *             This controller is kept for reference only.
 */
class AdminMarketingController extends Controller
{
    public function __construct(
        protected AdminService $adminService
    ) {
        $this->middleware('auth');
        $this->middleware('admin');
    }

    // ── Coupons ──
    public function couponsIndex()
    {
        $coupons = $this->adminService->getCoupons();
        return view('admin.coupons.index', compact('coupons'));
    }

    public function couponsCreate()
    {
        return view('admin.coupons.form');
    }

    public function couponsStore(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:coupons,code',
            'type' => 'nullable|string|in:PERCENTAGE,FIXED,FREE_SHIPPING',
            'discount_type' => 'nullable|string',
            'discount_value' => 'nullable|numeric|min:0',
            'min_order_value' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:0',
            'usage_per_user' => 'nullable|integer|min:0',
            'start_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);

        $this->adminService->createCoupon($validated);
        return redirect()->route('admin.coupons.index')
            ->with('success', 'Coupon created successfully');
    }

    public function couponsEdit(string $id)
    {
        $coupon = $this->adminService->getCouponById($id);
        abort_unless($coupon, 404, 'Coupon not found');
        return view('admin.coupons.form', compact('coupon'));
    }

    public function couponsUpdate(Request $request, string $id)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:coupons,code,' . $id,
            'type' => 'nullable|string|in:PERCENTAGE,FIXED,FREE_SHIPPING',
            'discount_value' => 'nullable|numeric|min:0',
            'min_order_value' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:0',
            'start_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ]);

        $this->adminService->updateCoupon($id, $validated);
        return redirect()->route('admin.coupons.index')
            ->with('success', 'Coupon updated successfully');
    }

    public function couponsDestroy(string $id)
    {
        $this->adminService->deleteCoupon($id);
        return redirect()->route('admin.coupons.index')
            ->with('success', 'Coupon deleted successfully');
    }

    // ── Banners ──
    public function bannersIndex()
    {
        $banners = $this->adminService->getBanners();
        return view('admin.banners.index', compact('banners'));
    }

    public function bannersCreate()
    {
        return view('admin.banners.form');
    }

    public function bannersStore(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'image_url' => 'nullable|string',
            'link_url' => 'nullable|string',
            'type' => 'nullable|string|max:50',
            'position' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $this->adminService->createBanner($validated);
        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner created successfully');
    }

    public function bannersEdit(string $id)
    {
        $banner = $this->adminService->getBannerById($id);
        abort_unless($banner, 404, 'Banner not found');
        return view('admin.banners.form', compact('banner'));
    }

    public function bannersUpdate(Request $request, string $id)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'image_url' => 'nullable|string',
            'link_url' => 'nullable|string',
            'type' => 'nullable|string|max:50',
            'position' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $this->adminService->updateBanner($id, $validated);
        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner updated successfully');
    }

    public function bannersDestroy(string $id)
    {
        $this->adminService->deleteBanner($id);
        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner deleted successfully');
    }

    // ── Promotions ──
    public function promotionsIndex()
    {
        $promotions = $this->adminService->getPromotions();
        return view('admin.promotions.index', compact('promotions'));
    }

    public function promotionsCreate()
    {
        return view('admin.promotions.form');
    }

    public function promotionsStore(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'discount' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'status' => 'nullable|string',
        ]);

        $this->adminService->createPromotion($validated);
        return redirect()->route('admin.promotions.index')
            ->with('success', 'Promotion created successfully');
    }

    public function promotionsEdit(string $id)
    {
        $promotion = $this->adminService->getPromotionById($id);
        abort_unless($promotion, 404, 'Promotion not found');
        return view('admin.promotions.form', compact('promotion'));
    }

    public function promotionsUpdate(Request $request, string $id)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'discount' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'status' => 'nullable|string',
        ]);

        $this->adminService->updatePromotion($id, $validated);
        return redirect()->route('admin.promotions.index')
            ->with('success', 'Promotion updated successfully');
    }

    public function promotionsDestroy(string $id)
    {
        $this->adminService->deletePromotion($id);
        return redirect()->route('admin.promotions.index')
            ->with('success', 'Promotion deleted successfully');
    }
}
