<?php

namespace App\Repositories;

use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PaymentRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return Payment::class;
    }

    /**
     * Find payment by order ID.
     */
    public function findByOrder(string $orderId): ?Payment
    {
        return Payment::where('order_id', $orderId)->first();
    }

    /**
     * Get payment by ID.
     */
    public function getPaymentById(string $paymentId): ?Payment
    {
        return Payment::find($paymentId);
    }

    /**
     * Get payment by order ID (alias).
     */
    public function getPaymentByOrderId(string $orderId): ?Payment
    {
        return $this->findByOrder($orderId);
    }

    /**
     * Create payment record.
     */
    public function createPayment(array $data): Payment
    {
        return Payment::create($data);
    }

    /**
     * Get user payments with pagination.
     */
    public function getUserPayments(string $userId, int $page = 1, int $limit = 20): array
    {
        $query = Payment::whereHas('order', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->with(['order' => function ($q) {
            $q->select('id', 'order_number', 'total');
        }]);

        $paginator = $query->latest()->paginate($limit, ['*'], 'page', $page);

        return [
            'items' => $paginator->items(),
            'page' => $paginator->currentPage(),
            'limit' => $paginator->perPage(),
            'total' => $paginator->total(),
            'total_pages' => $paginator->lastPage(),
        ];
    }

    /**
     * Create payment intent (initial payment record).
     */
    public function createPaymentIntent(array $data): Payment
    {
        return Payment::create($data);
    }

    /**
     * Confirm payment (mark as completed).
     */
    public function confirmPayment(string $paymentId, string $transactionId): Payment
    {
        $payment = $this->findByIdOrFail($paymentId);
        $payment->update(['status' => 'COMPLETED', 'transaction_id' => $transactionId]);
        return $payment->fresh();
    }

    /**
     * Update payment status and optionally transaction ID.
     */
    public function updatePaymentStatus(string $paymentId, string $status, ?string $transactionId = null): Payment
    {
        $data = ['status' => $status];
        if ($transactionId !== null) {
            $data['transaction_id'] = $transactionId;
        }
        $payment = $this->findByIdOrFail($paymentId);
        $payment->update($data);
        return $payment->fresh();
    }

    /**
     * Get all payments (admin) with pagination and optional status filter.
     */
    public function getAllPayments(int $page = 1, int $limit = 20, ?string $status = null): array
    {
        $query = Payment::with(['order' => function ($q) {
            $q->select('id', 'order_number');
        }]);

        if ($status) {
            $query->where('status', $status);
        }

        $paginator = $query->latest()->paginate($limit, ['*'], 'page', $page);

        return [
            'items' => $paginator->items(),
            'page' => $paginator->currentPage(),
            'limit' => $paginator->perPage(),
            'total' => $paginator->total(),
            'total_pages' => $paginator->lastPage(),
        ];
    }

    /**
     * Create refund record.
     */
    public function createRefund(array $data): Refund
    {
        return Refund::create($data);
    }

    /**
     * Get refund by ID.
     */
    public function getRefundById(string $refundId): ?Refund
    {
        return Refund::with('payment')->find($refundId);
    }

    /**
     * Get refunds by payment ID.
     */
    public function getRefundsByPaymentId(string $paymentId): Collection
    {
        return Refund::where('payment_id', $paymentId)
            ->latest()
            ->get();
    }

    /**
     * Get refunds for a user.
     */
    public function getUserRefunds(string $userId): Collection
    {
        return Refund::whereHas('payment.order', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->with('payment.order')
            ->latest()
            ->get();
    }

    /**
     * Update refund status.
     */
    public function updateRefundStatus(string $refundId, string $status): Refund
    {
        $refund = Refund::findOrFail($refundId);
        $refund->update(['status' => $status]);
        return $refund->fresh();
    }

    /**
     * Get payment statistics.
     */
    public function getPaymentStats(?string $startDate = null, ?string $endDate = null): array
    {
        $query = Payment::query();

        if ($startDate || $endDate) {
            $query->where(function ($q) use ($startDate, $endDate) {
                if ($startDate) $q->where('created_at', '>=', $startDate);
                if ($endDate) $q->where('created_at', '<=', $endDate);
            });
        }

        $totalPayments = (clone $query)->count();
        $successfulPayments = (clone $query)->where('status', 'COMPLETED')->count();
        $totalRevenue = (float) (clone $query)->where('status', 'COMPLETED')->sum('amount');
        $avgOrderValue = $successfulPayments > 0
            ? round($totalRevenue / $successfulPayments, 2)
            : 0;

        return [
            'total_payments' => $totalPayments,
            'successful_payments' => $successfulPayments,
            'success_rate' => $totalPayments > 0 ? round(($successfulPayments / $totalPayments) * 100, 2) : 0,
            'total_revenue' => $totalRevenue,
            'average_order_value' => $avgOrderValue,
        ];
    }
}
