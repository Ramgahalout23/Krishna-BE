<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PageService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function __construct(protected PageService $pageService) {}

    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->pageService->getAll()]);
    }

    public function show(string $slug): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->pageService->getBySlug($slug)]);
        } catch (AppError $e) { return $e->render(); }
    }

    // ── Admin Methods ──

    /**
     * Get all pages (including unpublished).
     * GET /api/v1/admin/pages
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $perPage = (int)($request->per_page ?? $request->limit ?? 15);
        $paginated = $this->pageService->getAllPages($perPage);
        return response()->json([
            'success' => true,
            'data' => [
                'pages' => $paginated['data'] ?? [],
                'pagination' => [
                    'page' => $paginated['current_page'] ?? 1,
                    'pages' => $paginated['last_page'] ?? 1,
                    'total' => $paginated['total'] ?? 0,
                    'per_page' => $paginated['per_page'] ?? $perPage,
                ],
            ],
        ]);
    }

    /**
     * Create a new page.
     * POST /api/v1/admin/pages
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'slug' => 'nullable|string|max:255|unique:pages,slug',
                'content' => 'nullable|string',
                'meta_title' => 'nullable|string|max:255',
                'meta_description' => 'nullable|string|max:500',
                'status' => 'nullable|string|in:DRAFT,PUBLISHED',
            ]);
            $page = $this->pageService->createPage($validated);
            return response()->json(['success' => true, 'message' => 'Page created', 'data' => $page], 201);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Update an existing page.
     * PUT /api/v1/admin/pages/{id}
     */
    public function adminUpdate(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'slug' => 'sometimes|string|max:255|unique:pages,slug,' . $id,
                'content' => 'nullable|string',
                'meta_title' => 'nullable|string|max:255',
                'meta_description' => 'nullable|string|max:500',
                'status' => 'nullable|string|in:DRAFT,PUBLISHED',
            ]);
            return response()->json(['success' => true, 'message' => 'Page updated', 'data' => $this->pageService->updatePage($id, $validated)]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Delete a page.
     * DELETE /api/v1/admin/pages/{id}
     */
    public function adminDestroy(string $id): JsonResponse
    {
        try {
            $this->pageService->deletePage($id);
            return response()->json(['success' => true, 'message' => 'Page deleted']);
        } catch (AppError $e) { return $e->render(); }
    }

    // ── Seed Default Templates ──

    /**
     * Seed default sample page templates.
     * POST /api/v1/admin/pages/seed-defaults
     * Creates sample pages if they don't already exist (by slug).
     */
    public function seedDefaults(): JsonResponse
    {
        try {
            $seeder = new \Database\Seeders\PageSeeder();
            $seeder->run();

            $count = \App\Models\Page::count();

            return response()->json([
                'success' => true,
                'message' => "Sample pages seeded successfully. Total pages: {$count}",
                'data' => ['total' => $count],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to seed sample pages: ' . $e->getMessage(),
            ], 500);
        }
    }
}
