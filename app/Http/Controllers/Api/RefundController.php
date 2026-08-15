<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RefundService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RefundController extends Controller
{
    public function __construct(
        protected RefundService $refundService
    ) {}

    /**
     * Create a refund request.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'order_id' => 'required|string',
                'reason' => 'required|string|max:255',
                'description' => 'nullable|string',
            ]);

            $refundRequest = $this->refundService->createRefundRequest(
                $validated['order_id'],
                Auth::id(),
                $validated
            );

            return response()->json([
                'success' => true,
                'message' => 'Refund request created',
                'data' => $refundRequest,
            ], 201);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Get user's refund requests.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->refundService->getUserRefundRequests(Auth::id()),
        ]);
    }

    /**
     * Get user's processed refunds.
     */
    public function refunds(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->refundService->getUserRefunds(Auth::id()),
        ]);
    }

    // ── Admin Routes ──

    /**
     * Admin: list all refund requests.
     */
    public function adminIndex(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->refundService->getAllRefundRequests($request->all()),
        ]);
    }

    /**
     * Admin: approve refund request.
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        try {
            $refundRequest = $this->refundService->approveRefundRequest(
                $id,
                Auth::id(),
                $request->input('admin_response')
            );

            return response()->json([
                'success' => true,
                'message' => 'Refund request approved',
                'data' => $refundRequest,
            ]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Admin: reject refund request.
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        try {
            $refundRequest = $this->refundService->rejectRefundRequest(
                $id,
                Auth::id(),
                $request->input('admin_response')
            );

            return response()->json([
                'success' => true,
                'message' => 'Refund request rejected',
                'data' => $refundRequest,
            ]);
        } catch (AppError $e) { return $e->render(); }
    }
}
