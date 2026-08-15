<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavedDesign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SavedDesignController extends Controller
{
    /**
     * List all saved designs for the authenticated user.
     * GET /api/v1/customizer/designs
     */
    public function index(): JsonResponse
    {
        $designs = SavedDesign::where('user_id', Auth::id())
            ->orderBy('updated_at', 'desc')
            ->get()
            ->toArray();

        return response()->json(['success' => true, 'data' => $designs]);
    }

    /**
     * Save a new t-shirt design.
     * POST /api/v1/customizer/designs
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'color' => 'required|string|max:50',
            'size' => 'required|string|max:10',
            'design_id' => 'required|string|max:50',
            'accent_color' => 'nullable|string|max:7',
            'design_data' => 'nullable|array',
        ]);

        $validated['user_id'] = Auth::id();

        $design = SavedDesign::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Design saved successfully',
            'data' => $design->toArray(),
        ], 201);
    }

    /**
     * Get a single saved design.
     * GET /api/v1/customizer/designs/{id}
     */
    public function show(string $id): JsonResponse
    {
        $design = SavedDesign::where('user_id', Auth::id())->findOrFail($id);

        return response()->json(['success' => true, 'data' => $design->toArray()]);
    }

    /**
     * Update a saved design.
     * PUT /api/v1/customizer/designs/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $design = SavedDesign::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'color' => 'sometimes|string|max:50',
            'size' => 'sometimes|string|max:10',
            'design_id' => 'sometimes|string|max:50',
            'accent_color' => 'nullable|string|max:7',
            'design_data' => 'nullable|array',
        ]);

        $design->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Design updated successfully',
            'data' => $design->fresh()->toArray(),
        ]);
    }

    /**
     * Delete a saved design.
     * DELETE /api/v1/customizer/designs/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $design = SavedDesign::where('user_id', Auth::id())->findOrFail($id);
        $design->delete();

        return response()->json([
            'success' => true,
            'message' => 'Design deleted successfully',
        ]);
    }

    /**
     * Upload a custom design image (PNG with transparent background recommended).
     * POST /api/v1/customizer/upload
     */
    public function uploadDesignImage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => 'required|image|mimes:png,jpg,jpeg,gif,webp,svg|max:5120',
        ]);

        $file = $validated['image'];
        $path = $file->store('custom-designs/' . Auth::id(), 'public');

        $url = Storage::url($path);

        return response()->json([
            'success' => true,
            'data' => [
                'url' => $url,
                'path' => $path,
                'filename' => $file->getClientOriginalName(),
            ],
        ]);
    }
}
