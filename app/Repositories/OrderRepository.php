<?php

namespace App\Repositories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Address;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return Order::class;
    }

    public function findWithDetails(string $id): ?Order
    {
        return Order::select([
                'id', 'order_number', 'user_id', 'subtotal', 'tax', 'shipping_cost', 'discount', 'total',
                'status', 'coupon_id', 'admin_notes', 'notes',
                'shipping_address_id', 'billing_address_id',
                'created_at', 'updated_at',
                'confirmed_at', 'processing_at', 'shipped_at', 'delivered_at', 'cancelled_at',
            ])
            ->with([
                'items' => function ($q) {
                    $q->select('id', 'order_id', 'product_id', 'variant_id', 'quantity', 'price', 'total', 'item_index', 'created_at');
                },
                'items.product' => function ($q) {
                    $q->select('id', 'name', 'created_at');
                },
                'items.product.images' => function ($q) {
                    $q->select('id', 'product_id', 'url');
                },
                'items.variant' => function ($q) {
                    $q->select('id', 'name', 'attributes');
                },
                'user' => function ($q) {
                    $q->select(['id', 'first_name', 'last_name', 'email', 'phone_number', 'created_at']);
                },
                'payment' => function ($q) {
                    $q->select('id', 'order_id', 'method', 'amount', 'status', 'transaction_id', 'gateway_response', 'created_at');
                },
                'timeline' => function ($q) {
                    $q->select('id', 'order_id', 'status', 'description', 'created_at');
                },
                'shippingAddress' => function ($q) {
                    $q->select('id', 'first_name', 'last_name', 'phone_number', 'address_line1', 'address_line2', 'city', 'state', 'zip_code', 'country', 'is_default');
                },
                'billingAddress' => function ($q) {
                    $q->select('id', 'first_name', 'last_name', 'phone_number', 'address_line1', 'address_line2', 'city', 'state', 'zip_code', 'country', 'is_default');
                },
                'customDesigns' => function ($q) {
                    $q->select('id', 'order_id', 'item_index', 'design_file_url', 'design_notes', 'placement', 'color', 'size', 'design_filename');
                },
            ])
            ->find($id);
    }

    public function findByOrderNumber(string $orderNumber): ?Order
    {
        return Order::select([
                'id', 'order_number', 'user_id', 'subtotal', 'tax', 'shipping_cost', 'discount', 'total',
                'status', 'coupon_id', 'admin_notes', 'notes',
                'shipping_address_id', 'billing_address_id',
                'created_at', 'updated_at',
                'confirmed_at', 'processing_at', 'shipped_at', 'delivered_at', 'cancelled_at',
            ])
            ->with([
                'items' => function ($q) {
                    $q->select('id', 'order_id', 'product_id', 'variant_id', 'quantity', 'price', 'total', 'item_index', 'created_at');
                },
                'items.product' => function ($q) {
                    $q->select('id', 'name', 'created_at');
                },
                'items.product.images' => function ($q) {
                    $q->select('id', 'product_id', 'url');
                },
                'items.variant' => function ($q) {
                    $q->select('id', 'name', 'attributes');
                },
                'user' => function ($q) {
                    $q->select(['id', 'first_name', 'last_name', 'email', 'phone_number', 'created_at']);
                },
                'payment' => function ($q) {
                    $q->select('id', 'order_id', 'method', 'amount', 'status', 'transaction_id', 'gateway_response', 'created_at');
                },
                'shippingAddress' => function ($q) {
                    $q->select('id', 'first_name', 'last_name', 'phone_number', 'address_line1', 'address_line2', 'city', 'state', 'zip_code', 'country', 'is_default');
                },
            ])
            ->where('order_number', $orderNumber)
            ->first();
    }

    public function getUserOrders(string $userId, array $filters = []): LengthAwarePaginator
    {
        $query = Order::select('id', 'order_number', 'status', 'total', 'created_at')
            ->with([
                'items' => function ($q) {
                    $q->select('id', 'order_id', 'product_id', 'variant_id', 'quantity', 'price', 'total', 'item_index', 'created_at');
                },
                'items.product' => function ($q) {
                    $q->select('id', 'name');
                },
                'items.product.images' => function ($q) {
                    $q->select('id', 'product_id', 'url');
                },
                'items.variant' => function ($q) {
                    $q->select('id', 'name', 'attributes');
                },
            ])
            ->where('user_id', $userId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = $filters['per_page'] ?? 15;
        return $query->latest()->paginate($perPage);
    }

    public function findMany(array $filters = []): LengthAwarePaginator
    {
        $query = Order::select('id', 'order_number', 'user_id', 'total', 'status', 'created_at', 'updated_at')
            ->with([
                'user' => function ($q) {
                    $q->select(['id', 'first_name', 'last_name', 'email']);
                },
                'items' => function ($q) {
                    $q->select('id', 'order_id', 'product_id', 'variant_id', 'quantity', 'price', 'total', 'item_index', 'created_at');
                },
                'items.product' => function ($q) {
                    $q->select('id', 'name');
                },
                'items.product.images' => function ($q) {
                    $q->select('id', 'product_id', 'url');
                },
                'items.variant' => function ($q) {
                    $q->select('id', 'name', 'attributes');
                },
            ]);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('created_at', [$filters['start_date'], $filters['end_date']]);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('email', 'like', "%{$search}%")
                         ->orWhere('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = $filters['per_page'] ?? 15;
        return $query->latest()->paginate($perPage);
    }

    public function createOrderItems(array $items): void
    {
        // Bulk insert with timestamps — avoids N+1 individual inserts
        $now = now();
        $bulkData = array_map(function ($item) use ($now) {
            return array_merge($item, [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, $items);

        OrderItem::insert($bulkData);
    }

    public function createPayment(array $data): Payment
    {
        return Payment::create($data);
    }

    public function getUserDefaultAddress(string $userId): ?Address
    {
        return Address::where('user_id', $userId)->where('is_default', true)->first();
    }

    public function updateStatus(string $id, string $status): Order
    {
        $order = $this->findByIdOrFail($id);
        $statusField = strtolower($status) . '_at';
        $updateData = ['status' => $status];
        if (in_array($statusField, (new Order)->getFillable())) {
            $updateData[$statusField] = now();
        }
        $order->update($updateData);
        return $order->fresh();
    }

    public function getRevenueStats(?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = Order::whereIn('status', ['DELIVERED', 'CONFIRMED']);

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        return [
            'total_revenue' => $query->sum('total'),
            'total_orders' => $query->count(),
            'avg_order_value' => $query->avg('total') ?? 0,
        ];
    }

    public function getTotalOrdersCount(): int
    {
        return Order::count();
    }
}
