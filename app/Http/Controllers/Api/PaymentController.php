<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use App\Services\RazorpayService;
use App\Services\CustomGatewayService;
use App\Exceptions\AppError;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
        protected RazorpayService $razorpayService,
        protected CustomGatewayService $customGatewayService
    ) {}

    // ── Public Routes ──

    public function getPaymentMethods(): JsonResponse
    {
        $methods = [
            ['id' => 'RAZORPAY', 'name' => 'Razorpay', 'description' => 'Pay via UPI, Card, NetBanking or Wallet', 'active' => true],
            ['id' => 'COD', 'name' => 'Cash on Delivery', 'description' => 'Pay when you receive your package', 'active' => true],
        ];
        return response()->json(['success' => true, 'data' => $methods]);
    }

    public function createRazorpayOrder(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'order_id' => 'required|string',
                'amount' => 'required|numeric|min:1',
                'currency' => 'nullable|string|size:3',
            ]);

            $order = $this->razorpayService->createOrder(
                $validated['order_id'],
                $validated['amount'],
                $validated['currency'] ?? 'INR'
            );

            return response()->json(['success' => true, 'data' => $order]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function verifyRazorpayPayment(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'order_id' => 'required|string',
                'razorpay_order_id' => 'required|string',
                'razorpay_payment_id' => 'required|string',
                'razorpay_signature' => 'required|string',
            ]);

            $result = $this->razorpayService->verifyPayment(
                $validated['order_id'],
                $validated['razorpay_payment_id'],
                $validated['razorpay_order_id'],
                $validated['razorpay_signature']
            );

            return response()->json(['success' => true, 'message' => 'Payment verified', 'data' => $result]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function initiateCustomGateway(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'order_id' => 'required|string',
                'amount' => 'required|numeric',
                'gateway' => 'required|string',
                'return_url' => 'nullable|string',
            ]);

            $user = $request->user();
            $result = $this->customGatewayService->initiatePayment(
                $validated['gateway'],
                $validated['order_id'],
                $validated['amount'],
                $user?->id ?? 'guest',
                $validated['return_url'] ?? null
            );

            return response()->json(['success' => true, 'data' => $result]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function handleGatewayCallback(Request $request): JsonResponse
    {
        try {
            $this->customGatewayService->processWebhook($request->all());
            // Payment status change affects dashboard metrics — clear cache
            app(\App\Repositories\AdminRepository::class)->clearDashboardCache();
            return response()->json(['success' => true, 'message' => 'Gateway callback processed']);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    // ── Authenticated Routes ──

    public function initiatePayment(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'order_id' => 'required|string',
                'method' => 'required|string',
                'amount' => 'required|numeric',
            ]);
            return response()->json(['success' => true, 'data' => $this->paymentService->createPaymentIntent($validated)]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function verifyPayment(Request $request, string $paymentId): JsonResponse
    {
        try {
            $validated = $request->validate(['transaction_id' => 'required|string']);
            return response()->json(['success' => true, 'data' => $this->paymentService->confirm($paymentId, $validated['transaction_id'])]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json(['success' => true, 'data' => $this->paymentService->getUserPayments($user->id)]);
    }

    public function getUserRefunds(Request $request): JsonResponse
    {
        $user = $request->user();
        $refunds = Refund::where('user_id', $user->id)->latest()->get();
        return response()->json(['success' => true, 'data' => $refunds]);
    }

    public function show(string $orderId): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->paymentService->getByOrder($orderId)]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function getPaymentDetails(Request $request, string $paymentId): JsonResponse
    {
        try {
            $payment = Payment::with('order')->findOrFail($paymentId);
            $user = $request->user();
            if ($user && $user->role !== 'ADMIN' && $user->role !== 'SUPER_ADMIN' && $payment->order->user_id !== $user->id) {
                return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
            }
            return response()->json(['success' => true, 'data' => $payment]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }

    public function createPaymentIntent(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'order_id' => 'required|string',
                'method' => 'required|string',
                'amount' => 'required|numeric',
            ]);
            return response()->json(['success' => true, 'data' => $this->paymentService->createPaymentIntent($validated)]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function confirm(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'payment_id' => 'required|string',
                'transaction_id' => 'required|string',
            ]);
            return response()->json(['success' => true, 'message' => 'Payment confirmed', 'data' => $this->paymentService->confirm($validated['payment_id'], $validated['transaction_id'])]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function processRefund(Request $request, string $paymentId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'reason' => 'nullable|string',
            ]);
            $payment = Payment::findOrFail($paymentId);
            $refund = Refund::create([
                'payment_id' => $paymentId,
                'user_id' => $request->user()->id,
                'amount' => $validated['amount'],
                'reason' => $validated['reason'] ?? '',
                'status' => 'PENDING',
            ]);
            return response()->json(['success' => true, 'data' => $refund], 201);
        } catch (AppError $e) { return $e->render(); }
    }

    // ── Admin Routes ──

    public function getAllPayments(Request $request): JsonResponse
    {
        // Accept both `per_page` and `limit` — the admin UI sends `limit`.
        $perPage = (int) ($request->input('per_page') ?? $request->input('limit') ?? 20);
        $query = Payment::with('order');

        if ($request->filled('status') && strtoupper($request->status) !== 'ALL') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('order_id', 'like', "%{$search}%")
                  ->orWhere('transaction_id', 'like', "%{$search}%");
            });
        }

        $paginator = $query->latest()->paginate(max(1, $perPage));

        // Map each payment to include camelCase fields expected by frontend
        $paginator->getCollection()->transform(function ($payment) {
            $payment->orderId       = $payment->order_id;
            $payment->transactionId = $payment->transaction_id;
            $payment->createdAt     = $payment->created_at;
            $payment->updatedAt     = $payment->updated_at;
            $payment->paymentMethod = $payment->method;
            return $payment;
        });

        return response()->json(['success' => true, 'data' => $paginator]);
    }

    public function getPaymentStats(Request $request): JsonResponse
    {
        // One pass over `payments` instead of six separate aggregate queries.
        $row = DB::selectOne("
            SELECT
                COUNT(*) AS total_count,
                SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed_count,
                SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) AS failed_count,
                COALESCE(SUM(CASE WHEN status = 'COMPLETED' THEN amount ELSE 0 END), 0) AS total_amount,
                COALESCE(SUM(CASE WHEN status = 'PENDING' THEN amount ELSE 0 END), 0) AS pending_amount
            FROM payments
        ");

        $totalCount    = (int) ($row->total_count ?? 0);
        $completedCnt  = (int) ($row->completed_count ?? 0);
        $pendingCnt    = (int) ($row->pending_count ?? 0);
        $failedCnt     = (int) ($row->failed_count ?? 0);
        $totalAmount   = (float) ($row->total_amount ?? 0);
        $pendingAmount = (float) ($row->pending_amount ?? 0);
        // Refunds are tracked via the Refund model (separate table), not Payment status
        $refundedTotal = (float) \App\Models\Refund::where('status', 'APPROVED')->sum('amount');

        return response()->json(['success' => true, 'data' => [
            // Original keys (backward compat)
            'total'        => $totalCount,
            'completed'    => $completedCnt,
            'pending'      => $pendingCnt,
            'failed'       => $failedCnt,
            'total_amount' => $totalAmount,
            // Frontend-expected camelCase keys
            'totalProcessed' => $totalAmount,
            'totalPending'   => $pendingAmount,
            'totalRefunded'  => $refundedTotal,
            'successRate'    => $totalCount > 0 ? round(($completedCnt / $totalCount) * 100, 1) : 0,
        ]]);
    }

    public function approveRefund(Request $request, string $refundId): JsonResponse
    {
        try {
            $refund = Refund::findOrFail($refundId);
            $refund->update(['status' => 'APPROVED', 'processed_at' => now()]);
            return response()->json(['success' => true, 'data' => $refund]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function rejectRefund(Request $request, string $refundId): JsonResponse
    {
        try {
            $refund = Refund::findOrFail($refundId);
            $refund->update(['status' => 'REJECTED']);
            return response()->json(['success' => true, 'data' => $refund]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function webhook(Request $request): JsonResponse
    {
        try {
            $this->customGatewayService->processWebhook($request->all());
            // Payment/order status changes via webhook affect dashboard metrics — clear cache
            app(\App\Repositories\AdminRepository::class)->clearDashboardCache();
            return response()->json(['success' => true, 'message' => 'Webhook processed']);
        } catch (AppError $e) {
            return $e->render();
        }
    }
}
