<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BrandService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function __construct(protected BrandService $brandService) {}

    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->brandService->getAll()]);
    }

    public function show(string $id): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->brandService->getById($id)]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['name' => 'required|string|max:255']);
            return response()->json(['success' => true, 'message' => 'Brand created', 'data' => $this->brandService->create($validated)], 201);
        } catch (AppError $e) { return $e->render(); }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate(['name' => 'sometimes|string|max:255']);
            return response()->json(['success' => true, 'message' => 'Brand updated', 'data' => $this->brandService->update($id, $validated)]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->brandService->delete($id);
            return response()->json(['success' => true, 'message' => 'Brand deleted']);
        } catch (AppError $e) { return $e->render(); }
    }
}
