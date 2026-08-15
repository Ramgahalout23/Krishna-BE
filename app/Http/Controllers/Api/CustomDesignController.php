<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomDesign;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomDesignController extends Controller
{
    /**
     * Admin: List all custom designs with pagination, search, and status filter.
     * GET /api/v1/admin/custom-designs
     */
    public function index(Request $request): JsonResponse
    {
        $query = CustomDesign::with(['order', 'user'])
            ->orderBy('created_at', 'desc');

        // Status filter
        if ($request->filled('status') && $request->status !== 'ALL') {
            $query->where('status', $request->status);
        }

        // Search across order info
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_email', 'like', "%{$search}%")
                  ->orWhere('design_notes', 'like', "%{$search}%")
                  ->orWhere('design_filename', 'like', "%{$search}%")
                  ->orWhere('order_id', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->input('limit', 50), 100);
        $page = (int) $request->input('page', 1);

        $total = $query->count();
        $designs = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => $designs,
            'pagination' => [
                'page' => $page,
                'pages' => max(1, (int) ceil($total / $perPage)),
                'total' => $total,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Admin: Get a single custom design by ID.
     * GET /api/v1/admin/custom-designs/{id}
     */
    public function show(string $id): JsonResponse
    {
        $design = CustomDesign::with(['order.items', 'user', 'reviewer'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $design->toArray()]);
    }

    /**
     * Admin: Update custom design status.
     * PATCH /api/v1/admin/custom-designs/{id}/status
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|max:30',
        ]);

        $design = CustomDesign::findOrFail($id);
        $newStatus = strtoupper($validated['status']);

        if (!CustomDesign::isValidTransition($design->status, $newStatus)) {
            return response()->json([
                'success' => false,
                'message' => "Invalid status transition from {$design->status} to {$newStatus}",
            ], 422);
        }

        $updates = ['status' => $newStatus, 'reviewed_by' => Auth::id()];
        // Only set reviewed_at on the first review (from PENDING_REVIEW)
        if ($design->status === CustomDesign::STATUS_PENDING_REVIEW) {
            $updates['reviewed_at'] = now();
        }
        $design->update($updates);

        return response()->json([
            'success' => true,
            'message' => "Design status updated to {$newStatus}",
            'data' => $design->fresh()->toArray(),
        ]);
    }

    /**
     * Admin: Update admin notes for a custom design.
     * PATCH /api/v1/admin/custom-designs/{id}/notes
     */
    public function updateNotes(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'admin_notes' => 'nullable|string',
        ]);

        $design = CustomDesign::findOrFail($id);
        $design->update(['admin_notes' => $validated['admin_notes'] ?? '']);

        return response()->json([
            'success' => true,
            'message' => 'Notes saved',
            'data' => $design->fresh()->toArray(),
        ]);
    }

    /**
     * Admin: Get status counts for the stats dashboard.
     * GET /api/v1/admin/custom-designs/stats
     */
    public function stats(): JsonResponse
    {
        $statuses = [
            CustomDesign::STATUS_PENDING_REVIEW,
            CustomDesign::STATUS_APPROVED,
            CustomDesign::STATUS_IN_PRODUCTION,
            CustomDesign::STATUS_SHIPPED,
            CustomDesign::STATUS_COMPLETED,
            CustomDesign::STATUS_REJECTED,
        ];

        $counts = [];
        $total = 0;
        foreach ($statuses as $status) {
            $count = CustomDesign::where('status', $status)->count();
            $counts[$status] = $count;
            $total += $count;
        }
        $counts['ALL'] = $total;

        return response()->json([
            'success' => true,
            'data' => $counts,
        ]);
    }

    /**
     * Auth: Upload a custom design image.
     * POST /api/v1/custom-designs/upload
     */
    public function uploadDesignImage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => 'required|image|mimes:png,jpg,jpeg,gif,webp|max:10240', // max 10MB
        ]);

        $file = $validated['image'];
        $folder = 'custom-designs/' . (Auth::id() ?? 'guest');
        $path = $file->store($folder, 'public');

        $url = Storage::url($path);

        return response()->json([
            'success' => true,
            'data' => [
                'url' => $url,
                'path' => $path,
                'filename' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ],
        ]);
    }

    /**
     * Serve a custom design image by its ID.
     * GET /api/v1/custom-designs/{id}/image
     */
    public function serveImage(string $id): \Illuminate\Http\Response
    {
        $design = CustomDesign::findOrFail($id);

        if (!$design->design_file_path || !Storage::disk('public')->exists($design->design_file_path)) {
            abort(404, 'Image not found');
        }

        $fileContents = Storage::disk('public')->get($design->design_file_path);
        $mime = $design->design_mime ?? 'image/png';

        return response($fileContents, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . ($design->design_filename ?? 'design.png') . '"',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Store a new custom design record (called when order is placed with custom design).
     * POST /api/v1/custom-designs
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|string|exists:orders,id',
            'item_index' => 'required|integer|min:0',
            'order_item_id' => 'nullable|string|exists:order_items,id',
            'design_file_path' => 'nullable|string',
            'design_file_url' => 'nullable|string',
            'design_filename' => 'nullable|string|max:255',
            'design_mime' => 'nullable|string|max:50',
            'design_file_size' => 'nullable|integer',
            'color' => 'nullable|string|max:50',
            'size' => 'nullable|string|max:10',
            'quantity' => 'nullable|integer|min:1',
            'placement' => 'nullable|string|max:50',
            'price' => 'nullable|numeric|min:0',
            'design_notes' => 'nullable|string',
        ]);

        // Idempotency: use order_item_id (explicit FK) when available, fall back to item_index
        $existing = null;
        if (!empty($validated['order_item_id'])) {
            $existing = CustomDesign::where('order_id', $validated['order_id'])
                ->where('order_item_id', $validated['order_item_id'])
                ->first();
        }
        if (!$existing) {
            $existing = CustomDesign::where('order_id', $validated['order_id'])
                ->where('item_index', $validated['item_index'])
                ->first();
        }
        if ($existing) {
            return response()->json([
                'success' => true,
                'data' => $existing->toArray(),
            ]);
        }

        // Attach order metadata
        $order = Order::with('user')->findOrFail($validated['order_id']);
        $user = $order->user;
        $validated['user_id'] = $order->user_id;
        $validated['customer_name'] = $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'Guest' : 'Guest';
        $validated['customer_email'] = $user?->email ?? '';
        $validated['status'] = CustomDesign::STATUS_PENDING_REVIEW;

        $design = CustomDesign::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Custom design recorded',
            'data' => $design->toArray(),
        ], 201);
    }

    /**
     * Get a list of custom designs for the authenticated user.
     * GET /api/v1/custom-designs/user
     */
    public function userDesigns(): JsonResponse
    {
        $designs = CustomDesign::where('user_id', Auth::id())
            ->with('order')
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();

        return response()->json(['success' => true, 'data' => $designs]);
    }
}
