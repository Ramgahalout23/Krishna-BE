<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Traits\MapsCamelCaseFields;
use App\Services\CategoryService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use MapsCamelCaseFields;

    private array $fieldMappings = [
        'parentId' => 'parent_id',
    ];
    public function __construct(protected CategoryService $categoryService) {}

    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->categoryService->getAll()]);
    }

    public function show(string $id): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->categoryService->getById($id)]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function hierarchy(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->categoryService->getHierarchy()]);
    }

    /**
     * Get flat hierarchical tree.
     * GET /api/v1/categories/tree
     */
    public function tree(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->categoryService->getTree()]);
    }

    /**
     * Get subcategories for a category.
     * GET /api/v1/categories/{categoryId}/subcategories
     */
    public function subcategories(string $categoryId): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->categoryService->getSubcategories($categoryId)]);
    }

    /**
     * Get category stats (product count, etc).
     * GET /api/v1/categories/{categoryId}/stats
     */
    public function categoryStats(string $categoryId): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->categoryService->getCategoryStats($categoryId)]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            // Support both camelCase (frontend) and snake_case (backend) param names
            $input = $this->mapCamelCase($request->all(), $this->fieldMappings);
            $request->replace($input);

            $validated = $request->validate(['name' => 'required|string|max:255', 'parent_id' => 'nullable|exists:categories,id']);
            return response()->json(['success' => true, 'message' => 'Category created', 'data' => $this->categoryService->create($validated)], 201);
        } catch (AppError $e) { return $e->render(); }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            // Support both camelCase (frontend) and snake_case (backend) param names
            $input = $this->mapCamelCase($request->all(), $this->fieldMappings);
            $request->replace($input);

            $validated = $request->validate(['name' => 'sometimes|string|max:255']);
            return response()->json(['success' => true, 'message' => 'Category updated', 'data' => $this->categoryService->update($id, $validated)]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->categoryService->delete($id);
            return response()->json(['success' => true, 'message' => 'Category deleted']);
        } catch (AppError $e) { return $e->render(); }
    }
}
