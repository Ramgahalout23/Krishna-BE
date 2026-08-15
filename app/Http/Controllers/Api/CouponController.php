<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Traits\MapsCamelCaseFields;
use App\Services\CouponService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    use MapsCamelCaseFields;

    private array $fieldMappings = [
        'discountType' => 'discount_type',
        'discountValue' => 'discount_value',
        'minPurchase' => 'min_order_value',
        'maxUses' => 'usage_limit',
        'expiresAt' => 'expiry_date',
        'isActive' => 'is_active',
    ];

    public function __construct(
        protected CouponService $couponService
    ) {}

    public function validateCoupon(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['code' => 'required|string']);
            $coupon = $this->couponService->validateCoupon($validated['code']);
            return response()->json(['success' => true, 'data' => $coupon]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->couponService->getAll()]);
    }

    public function show(string $id): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->couponService->getById($id)]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            // Map camelCase payload from frontend to snake_case
            $input = $this->mapCamelCase($request->all(), $this->fieldMappings);

            $validated = validator($input, [
                'code' => 'required|string|unique:coupons',
                'discount_type' => 'required|string',
                'discount_value' => 'required|numeric',
                'type' => 'nullable|string',
            ])->validate();

            return response()->json(['success' => true, 'message' => 'Coupon created', 'data' => $this->couponService->create($validated)], 201);
        } catch (AppError $e) { return $e->render(); } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            // Map camelCase payload from frontend to snake_case
            $input = $this->mapCamelCase($request->all(), $this->fieldMappings);

            $validated = validator($input, [
                'code' => 'sometimes|string|unique:coupons,code,'.$id,
            ])->validate();

            return response()->json(['success' => true, 'message' => 'Coupon updated', 'data' => $this->couponService->update($id, $validated)]);
        } catch (AppError $e) { return $e->render(); } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->couponService->delete($id);
            return response()->json(['success' => true, 'message' => 'Coupon deleted']);
        } catch (AppError $e) { return $e->render(); }
    }

    public function toggle(string $id): JsonResponse
    {
        try {
            $coupon = $this->couponService->getById($id);
            $updated = $this->couponService->update($id, ['is_active' => !$coupon->is_active]);
            return response()->json([
                'success' => true,
                'message' => 'Coupon ' . ($updated['is_active'] ?? ($updated->is_active ?? false) ? 'activated' : 'deactivated'),
                'data' => $updated,
            ]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function analytics(string $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->couponService->getAnalytics($id)]);
    }

    public function usageHistory(string $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->couponService->getUsageHistory($id)]);
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $result = $this->couponService->getAll($request->all(), $request->input('page', 1), $request->input('limit', 10));

        // Map snake_case DB fields to camelCase expected by frontend
        $items = collect($result['data'])->map(function ($coupon) {
            $coupon['discountType'] = $coupon['type'] ?? null;
            $coupon['discountValue'] = $coupon['discount_value'] ?? null;
            $coupon['minPurchase'] = $coupon['min_order_value'] ?? null;
            $coupon['minOrderValue'] = $coupon['min_order_value'] ?? null;
            $coupon['maxUses'] = $coupon['usage_limit'] ?? null;
            $coupon['usageLimit'] = $coupon['usage_limit'] ?? null;
            $coupon['expiresAt'] = $coupon['expiry_date'] ?? null;
            $coupon['expiryDate'] = $coupon['expiry_date'] ?? null;
            $coupon['isActive'] = $coupon['is_active'] ?? true;
            $coupon['active'] = $coupon['is_active'] ?? true;
            $coupon['usedCount'] = $coupon['usage_count'] ?? 0;
            $coupon['usageCount'] = $coupon['usage_count'] ?? 0;
            $coupon['value'] = $coupon['discount_value'] ?? null;
            return $coupon;
        })->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'coupons' => $items,
                'pagination' => $result['pagination'],
            ],
        ]);
    }

    public function bulkGenerate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'count' => 'required|integer|min:1|max:100',
                'discount_value' => 'required|numeric|min:0',
                'type' => 'nullable|string',
                'max_uses' => 'nullable|integer|min:1',
            ]);

            $coupons = [];
            for ($i = 0; $i < $validated['count']; $i++) {
                $code = strtoupper(\Illuminate\Support\Str::random(8));
            // Derive discount_type from the coupon type
            $discountType = match (strtoupper($validated['type'] ?? 'FLAT')) {
                'PERCENTAGE' => 'PERCENTAGE',
                default => 'FLAT',
            };
            $coupon = $this->couponService->create([
                'code' => $code,
                'discount_value' => $validated['discount_value'],
                'discount_type' => $discountType,
                'type' => strtoupper($validated['type'] ?? 'FLAT'),
                'max_uses' => $validated['max_uses'] ?? 1,
            ]);
                $coupons[] = $coupon;
            }

            return response()->json(['success' => true, 'message' => $validated['count'] . ' coupons generated', 'data' => $coupons], 201);
        } catch (AppError $e) { return $e->render(); }
    }


    public function getByCode(string $code): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->couponService->validateCoupon($code)]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function apply(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['code' => 'required|string']);
            $coupon = $this->couponService->validateCoupon($validated['code']);
            $coupon['applied'] = true;
            return response()->json(['success' => true, 'data' => $coupon]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function getAutoApply(): JsonResponse
    {
        $coupons = $this->couponService->getAll(['is_active' => true]);
        return response()->json(['success' => true, 'data' => $coupons]);
    }

    public function getBest(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['cart_total' => 'required|numeric|min:0']);
            $coupons = $this->couponService->getAll(['is_active' => true, 'sort_by' => 'discount_value', 'sort_order' => 'desc']);
            return response()->json(['success' => true, 'data' => $coupons]);
        } catch (AppError $e) { return $e->render(); }
    }
}
