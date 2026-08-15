<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RefundService;
use App\Services\EmailService;
use App\Services\NotificationService;
use App\Services\NotificationTemplateService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ReturnController extends Controller
{
    public function __construct(
        protected RefundService $refundService,
        protected EmailService $emailService,
        protected NotificationService $notificationService,
        protected NotificationTemplateService $notificationTemplateService
    ) {}

    /**
     * Create a return request.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'order_id' => 'required|string',
                'reason' => 'required|string|max:255',
                'return_type' => 'nullable|string|in:exchange,replacement,refund,other',
                'description' => 'nullable|string',
                'refund_amount' => 'nullable|numeric|min:0',
            ]);

            $returnRequest = $this->refundService->createReturnRequest(
                $validated['order_id'],
                Auth::id(),
                $validated
            );

            return response()->json([
                'success' => true,
                'message' => 'Return request created',
                'data' => $returnRequest,
            ], 201);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Get user's return requests.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->refundService->getUserReturnRequests(Auth::id()),
        ]);
    }

    // ── Admin Routes ──

    /**
     * Admin: list all return requests.
     */
    public function adminIndex(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->refundService->getAllReturnRequests($request->all()),
        ]);
    }

    /**
     * Admin: get a single return request with full details.
     */
    public function show(string $id): JsonResponse
    {
        $returnRequest = $this->refundService->getReturnRequestDetail($id);

        if (!$returnRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Return request not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $returnRequest,
        ]);
    }

    /**
     * Admin: approve return request.
     * Sends email + in-app notification to the customer.
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'admin_response' => 'nullable|string',
                'resolution' => 'nullable|string|in:exchange,replacement,refund',
            ]);

            $returnRequest = $this->refundService->approveReturnRequest(
                $id,
                Auth::id(),
                $validated['admin_response'] ?? null,
                $validated['resolution'] ?? null
            );

            // Send notifications
            $this->sendReturnApprovedNotifications($returnRequest);

            return response()->json([
                'success' => true,
                'message' => 'Return request approved',
                'data' => $returnRequest,
            ]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Admin: reject return request.
     * Sends email + in-app notification to the customer.
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        try {
            $returnRequest = $this->refundService->rejectReturnRequest(
                $id,
                Auth::id(),
                $request->input('admin_response')
            );

            // Send notifications
            $this->sendReturnRejectedNotifications($returnRequest);

            return response()->json([
                'success' => true,
                'message' => 'Return request rejected',
                'data' => $returnRequest,
            ]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Admin: complete return and process refund to wallet.
     * Sends email + in-app notification to the customer.
     */
    public function complete(string $id): JsonResponse
    {
        try {
            $returnRequest = $this->refundService->completeReturn($id, Auth::id());

            // Send notifications
            $this->sendReturnCompletedNotifications($returnRequest);

            return response()->json([
                'success' => true,
                'message' => 'Return completed and refund processed',
                'data' => $returnRequest,
            ]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Admin: list all processed refunds.
     */
    public function allRefunds(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->refundService->getAllRefunds($request->all()),
        ]);
    }

    // ── Notification Helpers ──

    /**
     * Send email + in-app notification when a return is approved.
     */
    private function sendReturnApprovedNotifications($returnRequest): void
    {
        try {
            $returnRequest->load('user');
            $user = $returnRequest->user;
            if (!$user || !$user->email) return;

            $userName = $user->firstName ?? $user->name ?? 'Customer';
            $requestId = substr($returnRequest->id, 0, 8);
            $resolution = $returnRequest->resolution ?? $returnRequest->return_type ?? '';
            $adminResponse = $returnRequest->admin_response;

            // Send email
            $this->emailService->sendReturnApproved(
                $user->email,
                $userName,
                $requestId,
                $resolution,
                $adminResponse
            );

            // Send in-app notification
            $rendered = $this->notificationTemplateService->renderTemplate('notif_return_approved', [
                'customerName' => $userName,
                'requestId' => $requestId,
                'resolution' => $resolution,
            ]);
            if ($rendered['rendered']) {
                $this->notificationService->create(
                    $user->id,
                    'return_approved',
                    $rendered['title'],
                    $rendered['message'],
                    ['return_request_id' => $returnRequest->id]
                );
            }
        } catch (\Exception $e) {
            Log::error("[ReturnController] Failed to send approved notifications: {$e->getMessage()}");
        }
    }

    /**
     * Send email + in-app notification when a return is rejected.
     */
    private function sendReturnRejectedNotifications($returnRequest): void
    {
        try {
            $returnRequest->load('user');
            $user = $returnRequest->user;
            if (!$user || !$user->email) return;

            $userName = $user->firstName ?? $user->name ?? 'Customer';
            $requestId = substr($returnRequest->id, 0, 8);
            $adminResponse = $returnRequest->admin_response;

            // Send email
            $this->emailService->sendReturnRejected(
                $user->email,
                $userName,
                $requestId,
                $adminResponse
            );

            // Send in-app notification
            $rendered = $this->notificationTemplateService->renderTemplate('notif_return_rejected', [
                'customerName' => $userName,
                'requestId' => $requestId,
            ]);
            if ($rendered['rendered']) {
                $this->notificationService->create(
                    $user->id,
                    'return_rejected',
                    $rendered['title'],
                    $rendered['message'],
                    ['return_request_id' => $returnRequest->id]
                );
            }
        } catch (\Exception $e) {
            Log::error("[ReturnController] Failed to send rejected notifications: {$e->getMessage()}");
        }
    }

    /**
     * Send email + in-app notification when a return is completed and refund processed.
     */
    private function sendReturnCompletedNotifications($returnRequest): void
    {
        try {
            $returnRequest->load('user');
            $user = $returnRequest->user;
            if (!$user || !$user->email) return;

            $userName = $user->firstName ?? $user->name ?? 'Customer';
            $requestId = substr($returnRequest->id, 0, 8);
            $refundAmount = $returnRequest->refund_amount > 0
                ? number_format((float) $returnRequest->refund_amount, 2)
                : '';

            // Send email
            $this->emailService->sendReturnCompleted(
                $user->email,
                $userName,
                $requestId,
                $refundAmount
            );

            // Send in-app notification
            $rendered = $this->notificationTemplateService->renderTemplate('notif_return_completed', [
                'customerName' => $userName,
                'requestId' => $requestId,
                'refundAmount' => $refundAmount,
            ]);
            if ($rendered['rendered']) {
                $this->notificationService->create(
                    $user->id,
                    'return_completed',
                    $rendered['title'],
                    $rendered['message'],
                    ['return_request_id' => $returnRequest->id]
                );
            }
        } catch (\Exception $e) {
            Log::error("[ReturnController] Failed to send completed notifications: {$e->getMessage()}");
        }
    }
}
