<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Traits\MapsCamelCaseFields;
use App\Services\BannerService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    use MapsCamelCaseFields;

    private array $fieldMappings = [
        'imageUrl' => 'image_url',
        'linkUrl' => 'link_url',
        'displayMode' => 'display_mode',
        'isActive' => 'is_active',
    ];
    public function __construct(protected BannerService $bannerService) {}

    // ── Public User-Facing Endpoints ──

    /**
     * Get active banners, optionally filtered by type query param.
     * GET /api/v1/banners?type=HERO
     */
    public function getActiveBanners(Request $request): JsonResponse
    {
        $type = $request->query('type');
        return response()->json(['success' => true, 'data' => $this->bannerService->getActiveBanners($type)]);
    }

    /**
     * Get homepage banners grouped by type.
     * GET /api/v1/banners/homepage?device=mobile|desktop
     */
    public function getHomepageBanners(Request $request): JsonResponse
    {
        $device = $request->query('device', 'desktop');
        return response()->json(['success' => true, 'data' => $this->bannerService->getHomepageBanners($device)]);
    }

    /**
     * Get hero banners.
     * GET /api/v1/banners/hero?device=mobile|desktop
     */
    public function getHeroBanners(Request $request): JsonResponse
    {
        $device = $request->query('device', 'desktop');
        return response()->json(['success' => true, 'data' => $this->bannerService->getHeroBanners($device)]);
    }

    /**
     * Get sale banners.
     * GET /api/v1/banners/sale?device=mobile|desktop
     */
    public function getSaleBanners(Request $request): JsonResponse
    {
        $device = $request->query('device', 'desktop');
        return response()->json(['success' => true, 'data' => $this->bannerService->getSaleBanners($device)]);
    }

    /**
     * Get category banners.
     * GET /api/v1/banners/category?device=mobile|desktop
     */
    public function getCategoryBanners(Request $request): JsonResponse
    {
        $device = $request->query('device', 'desktop');
        return response()->json(['success' => true, 'data' => $this->bannerService->getCategoryBanners($device)]);
    }

    /**
     * Get popup banners.
     * GET /api/v1/banners/popup
     */
    public function getPopupBanners(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->bannerService->getPopupBanners()]);
    }

    /**
     * Get banner by ID.
     */
    public function show(string $id): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->bannerService->getById($id)]);
        } catch (AppError $e) { return $e->render(); }
    }

    // ── Admin Endpoints ──

    /**
     * Get all banners with pagination (Admin).
     */
    public function adminIndex(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->bannerService->getAllBanners($request->all())]);
    }

    /**
     * Create banner (Admin).
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Map camelCase payload from frontend to snake_case
            $input = $this->mapCamelCase($request->all(), $this->fieldMappings);

            $validated = validator($input, [
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'image_url' => 'nullable|string',
                'link_url' => 'nullable|string',
                'type' => 'nullable|string|in:HERO,SALE,CATEGORY,POPUP,FEATURED,NEW_ARRIVAL',
                'position' => 'nullable|integer',
                'is_active' => 'nullable|boolean',
                'display_mode' => 'nullable|string|in:DEFAULT,IMAGE_ONLY,TITLE_ONLY,VIDEO',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'show_on_mobile' => 'nullable|boolean',
                'show_on_desktop' => 'nullable|boolean',
                'background_color' => 'nullable|string',
                'text_color' => 'nullable|string',
                'button_text' => 'nullable|string',
                'button_link' => 'nullable|string',
            ])->validate();
            $validated['created_by'] = $request->user()?->id;
            return response()->json(['success' => true, 'message' => 'Banner created', 'data' => $this->bannerService->create($validated)], 201);
        } catch (AppError $e) { return $e->render(); } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }
    }

    /**
     * Update banner (Admin).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            // Map camelCase payload from frontend to snake_case
            $input = $this->mapCamelCase($request->all(), $this->fieldMappings);

            $validated = validator($input, [
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'image_url' => 'nullable|string',
                'link_url' => 'nullable|string',
                'type' => 'nullable|string|in:HERO,SALE,CATEGORY,POPUP,FEATURED,NEW_ARRIVAL',
                'position' => 'nullable|integer',
                'is_active' => 'nullable|boolean',
                'display_mode' => 'nullable|string|in:DEFAULT,IMAGE_ONLY,TITLE_ONLY,VIDEO',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'show_on_mobile' => 'nullable|boolean',
                'show_on_desktop' => 'nullable|boolean',
                'background_color' => 'nullable|string',
                'text_color' => 'nullable|string',
                'button_text' => 'nullable|string',
                'button_link' => 'nullable|string',
            ])->validate();
            return response()->json(['success' => true, 'data' => $this->bannerService->update($id, $validated)]);
        } catch (AppError $e) { return $e->render(); } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }
    }

    /**
     * Delete banner (Admin).
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->bannerService->delete($id);
            return response()->json(['success' => true, 'message' => 'Banner deleted']);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Toggle banner active status (Admin).
     * PATCH /api/v1/admin/banners/{id}/toggle
     */
    public function toggleStatus(string $id): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->bannerService->toggleStatus($id)]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Reorder banners (Admin).
     * PATCH /api/v1/admin/banners/reorder
     */
    public function reorder(Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            // Frontend sends { order: [...] }, backend expects { banner_ids: [...] }
            $ids = $data['banner_ids'] ?? $data['order'] ?? [];

            if (empty($ids)) {
                throw AppError::validation('Banner IDs are required');
            }

            $this->bannerService->reorder($ids);
            return response()->json(['success' => true, 'message' => 'Banners reordered']);
        } catch (AppError $e) { return $e->render(); }
    }
}
