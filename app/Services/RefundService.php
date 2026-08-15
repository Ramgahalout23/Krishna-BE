<?php

namespace App\Services;

use App\Models\Refund;
use App\Models\RefundRequest;
use App\Models\ReturnRequest;
use App\Models\Order;
use App\Exceptions\AppError;
use Illuminate\Support\Facades\DB;

class RefundService
{
    /**
     * Acceptable return reasons as per company policy.
     */
    const ACCEPTABLE_REASONS = ['wrong_size', 'defective', 'wrong_item', 'not_delivered'];

    /**
     * Non-acceptable reasons that should be flagged.
     */
    const NON_ACCEPTABLE_REASONS = ['changed_mind', 'worn_item', 'missing_tags', 'sale_item'];

    /**
     * Return window in days.
     */
    const RETURN_WINDOW_DAYS = 7;

    /**
     * Create a refund request for an order item.
     */
    public function createRefundRequest(string $orderId, string $userId, array $data): RefundRequest
    {
        $order = Order::where('id', $orderId)->where('user_id', $userId)->firstOrFail();

        if (!in_array($order->status, ['DELIVERED', 'SHIPPED'])) {
            throw AppError::validation('Refunds are only available for delivered or shipped orders');
        }

        return RefundRequest::create([
            'order_id' => $orderId,
            'user_id' => $userId,
            'reason' => $data['reason'],
            'description' => $data['description'] ?? null,
            'status' => 'PENDING',
        ]);
    }

    /**
     * Create a return request with policy validation.
     */
    public function createReturnRequest(string $orderId, string $userId, array $data): ReturnRequest
    {
        $order = Order::where('id', $orderId)->where('user_id', $userId)->firstOrFail();

        if (!in_array($order->status, ['DELIVERED', 'SHIPPED'])) {
            throw AppError::validation('Returns are only available for delivered or shipped orders');
        }

        // Check 7-day return window
        if ($order->delivered_at) {
            $daysSinceDelivery = now()->diffInDays($order->delivered_at);
            if ($daysSinceDelivery > self::RETURN_WINDOW_DAYS) {
                throw AppError::validation('Return window has expired. Returns must be requested within ' . self::RETURN_WINDOW_DAYS . ' days of delivery.');
            }
        }

        // Validate reason against policy
        $reason = $data['reason'] ?? '';
        if (in_array($reason, self::NON_ACCEPTABLE_REASONS)) {
            throw AppError::validation('This reason is not eligible for return per our policy. Please contact support for assistance.');
        }

        // Determine if order was on sale/discount
        $wasOnSale = !empty($data['was_on_sale']) || (isset($order->discount) && $order->discount > 0);
        if ($wasOnSale && $reason !== 'defective') {
            throw AppError::validation('Sale and discounted items are not eligible for return unless defective.');
        }

        // Validate return_type against reason
        $returnType = $data['return_type'] ?? 'exchange';
        if ($reason === 'wrong_size' && !in_array($returnType, ['exchange', 'replacement'])) {
            throw AppError::validation('Size issues: we offer exchange or replacement. Cash refunds are not available for size exchanges per policy.');
        }
        if (in_array($reason, ['defective', 'wrong_item']) && !in_array($returnType, ['exchange', 'replacement', 'refund'])) {
            throw AppError::validation('For defective or wrong items, we offer exchange, replacement, or refund.');
        }

        return ReturnRequest::create([
            'order_id' => $orderId,
            'user_id' => $userId,
            'reason' => $reason,
            'description' => $data['description'] ?? null,
            'return_type' => $returnType,
            'refund_amount' => $data['refund_amount'] ?? null,
            'status' => 'PENDING',
        ]);
    }

    /**
     * Get user's refund requests.
     */
    public function getUserRefundRequests(string $userId): array
    {
        return RefundRequest::where('user_id', $userId)
            ->with('order')
            ->latest()
            ->get()
            ->toArray();
    }

    /**
     * Get user's return requests.
     */
    public function getUserReturnRequests(string $userId): array
    {
        return ReturnRequest::where('user_id', $userId)
            ->with('order')
            ->latest()
            ->get()
            ->toArray();
    }

    /**
     * Get user's refunds (processed).
     */
    public function getUserRefunds(string $userId): array
    {
        return Refund::where('user_id', $userId)
            ->with('payment')
            ->latest()
            ->get()
            ->toArray();
    }

    /**
     * Admin: approve a refund request.
     */
    public function approveRefundRequest(string $refundRequestId, string $adminId, ?string $adminResponse = null): RefundRequest
    {
        return DB::transaction(function () use ($refundRequestId, $adminId, $adminResponse) {
            $request = RefundRequest::findOrFail($refundRequestId);

            if ($request->status !== 'PENDING') {
                throw AppError::validation('Refund request has already been processed');
            }

            $request->update([
                'status' => 'APPROVED',
                'admin_response' => $adminResponse,
            ]);

            return $request->fresh();
        });
    }

    /**
     * Admin: reject a refund request.
     */
    public function rejectRefundRequest(string $refundRequestId, string $adminId, ?string $adminResponse = null): RefundRequest
    {
        $request = RefundRequest::findOrFail($refundRequestId);

        if ($request->status !== 'PENDING') {
            throw AppError::validation('Refund request has already been processed');
        }

        $request->update([
            'status' => 'REJECTED',
            'admin_response' => $adminResponse,
        ]);

        return $request->fresh();
    }

    /**
     * Admin: approve a return request and optionally process refund to wallet.
     */
    public function approveReturnRequest(string $returnRequestId, string $adminId, ?string $adminResponse = null, ?string $resolution = null): ReturnRequest
    {
        return DB::transaction(function () use ($returnRequestId, $adminId, $adminResponse, $resolution) {
            $request = ReturnRequest::findOrFail($returnRequestId);

            if ($request->status !== 'PENDING') {
                throw AppError::validation('Return request has already been processed');
            }

            $updateData = [
                'status' => 'APPROVED',
                'admin_response' => $adminResponse,
                'processed_at' => now(),
            ];

            if ($resolution) {
                $updateData['resolution'] = $resolution;
            }

            $request->update($updateData);

            return $request->fresh();
        });
    }

    /**
     * Admin: reject a return request.
     */
    public function rejectReturnRequest(string $returnRequestId, string $adminId, ?string $adminResponse = null): ReturnRequest
    {
        $request = ReturnRequest::findOrFail($returnRequestId);

        if ($request->status !== 'PENDING') {
            throw AppError::validation('Return request has already been processed');
        }

        $request->update([
            'status' => 'REJECTED',
            'admin_response' => $adminResponse,
        ]);

        return $request->fresh();
    }

    /**
     * Admin: complete a return and process refund.
     */
    public function completeReturn(string $returnRequestId, string $adminId): ReturnRequest
    {
        return DB::transaction(function () use ($returnRequestId, $adminId) {
            $request = ReturnRequest::findOrFail($returnRequestId);

            if ($request->status !== 'APPROVED') {
                throw AppError::validation('Return must be approved before completing');
            }

            // Refund processing placeholder (implement payment gateway refund here if needed)
            if ($request->refund_amount > 0) {
                Log::info("[RefundService] Refund of {$request->refund_amount} for return #{$request->id} ready to process.");
            }

            $request->update([
                'status' => 'COMPLETED',
                'processed_at' => now(),
            ]);

            return $request->fresh();
        });
    }

    /**
     * Admin: get a single return request with full details.
     */
    public function getReturnRequestDetail(string $id): ?ReturnRequest
    {
        return ReturnRequest::with([
            'user',
            'order.items',
            'order.shippingAddress',
            'order.billingAddress',
            'order.payment',
            'order.shipping',
        ])->find($id);
    }

    /**
     * Admin: list all refund requests.
     */
    public function getAllRefundRequests(array $filters = []): array
    {
        $query = RefundRequest::with(['user', 'order']);
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        return $query->latest()->paginate($filters['per_page'] ?? 20)->toArray();
    }

    /**
     * Admin: list all return requests.
     */
    public function getAllReturnRequests(array $filters = []): array
    {
        $query = ReturnRequest::with(['user', 'order']);
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        return $query->latest()->paginate($filters['per_page'] ?? 20)->toArray();
    }

    /**
     * Admin: list all processed refunds.
     */
    public function getAllRefunds(array $filters = []): array
    {
        $query = Refund::with(['user', 'payment']);
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        return $query->latest()->paginate($filters['per_page'] ?? 20)->toArray();
    }

    /**
     * Process a refund for a payment (creates Refund record).
     */
    public function processPaymentRefund(string $paymentId, string $userId, float $amount, string $reason): Refund
    {
        return DB::transaction(function () use ($paymentId, $userId, $amount, $reason) {
            $refund = Refund::create([
                'payment_id' => $paymentId,
                'user_id' => $userId,
                'amount' => $amount,
                'reason' => $reason,
                'status' => 'PENDING',
            ]);

            return $refund;
        });
    }
}
