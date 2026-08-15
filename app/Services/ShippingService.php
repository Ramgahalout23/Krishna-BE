<?php

namespace App\Services;

use App\Repositories\ShippingRepository;
use App\Exceptions\AppError;
use App\Models\Shipping;
use App\Models\ShippingZone;
use App\Models\ShippingRate;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;

class ShippingService
{
    public function __construct(
        protected ShippingRepository $shippingRepository
    ) {}

    public function getProviders(): array
    {
        return [
            ['id' => 'standard', 'name' => 'Standard Shipping', 'estimated_days' => '5-7 business days'],
            ['id' => 'express', 'name' => 'Express Shipping', 'estimated_days' => '2-3 business days'],
            ['id' => 'overnight', 'name' => 'Overnight Shipping', 'estimated_days' => '1 business day'],
        ];
    }

    public function getZones(): array
    {
        return Cache::remember('shipping_zones', 3600, function () {
            $zones = $this->shippingRepository->getAllZones()->toArray();
            // Map snake_case fields and add 'regions' from 'countries' for frontend compat
            return array_map(function ($zone) {
                $zone['countries'] = $zone['countries'] ?? [];
                $zone['states']    = $zone['states'] ?? [];
                // Frontend reads z.regions but model has countries column
                $zone['regions']   = $zone['countries'];
                $zone['createdAt'] = $zone['created_at'] ?? null;
                $zone['updatedAt'] = $zone['updated_at'] ?? null;
                return $zone;
            }, $zones);
        });
    }

    public function getShippingMethods(): array
    {
        return $this->getProviders();
    }

    public function calculate(string $zoneId, float $weight = 0, float $subtotal = 0): array
    {
        $rate = $this->shippingRepository->calculateRate($zoneId, $weight, $subtotal);
        if (!$rate) throw AppError::validation('No shipping rate available for this zone');

        return ['rate' => $rate, 'zone_id' => $zoneId, 'estimated_days' => '5-7 business days'];
    }

    /**
     * Track a shipment by tracking number.
     */
    public function trackShipment(string $trackingNumber): array
    {
        $shipping = $this->shippingRepository->findByTrackingNumber($trackingNumber);
        if (!$shipping) throw AppError::notFound('Shipment not found');

        return [
            'tracking_number' => $shipping->tracking_number,
            'carrier' => $shipping->carrier,
            'status' => $shipping->status ?? 'IN_TRANSIT',
            'estimated_delivery' => $shipping->estimated_delivery,
            'current_location' => $shipping->current_location ?? null,
            'events' => $shipping->tracking_events ?? [],
        ];
    }

    // ── Zone CRUD ──

    public function createZone(array $data): array
    {
        $result = ShippingZone::create($data)->toArray();
        Cache::forget('shipping_zones');
        return $result;
    }

    public function updateZone(string $id, array $data): array
    {
        $zone = ShippingZone::findOrFail($id);
        $zone->update($data);
        Cache::forget('shipping_zones');
        return $zone->fresh()->toArray();
    }

    public function deleteZone(string $id): void
    {
        ShippingZone::findOrFail($id)->delete();
        Cache::forget('shipping_zones');
    }

    public function getAllZonesPaginated(int $page = 1, int $limit = 20): array
    {
        $paginator = ShippingZone::with('rates')->paginate($limit, ['*'], 'page', $page);
        return [
            'items' => $paginator->items(),
            'page' => $paginator->currentPage(),
            'limit' => $paginator->perPage(),
            'total' => $paginator->total(),
            'total_pages' => $paginator->lastPage(),
        ];
    }

    // ── Rate CRUD ──

    public function createRate(array $data): array
    {
        return ShippingRate::create($data)->toArray();
    }

    public function updateRate(string $id, array $data): array
    {
        $rate = ShippingRate::findOrFail($id);
        $rate->update($data);
        return $rate->fresh()->toArray();
    }

    public function deleteRate(string $id): void
    {
        ShippingRate::findOrFail($id)->delete();
    }

    // ── Shipping CRUD ──

    public function createShipping(array $data): array
    {
        // Verify order exists
        $order = Order::find($data['order_id'] ?? null);
        if (!$order) throw AppError::notFound('Order not found');

        return Shipping::create($data)->toArray();
    }

    public function updateShipping(string $id, array $data): array
    {
        $shipping = Shipping::findOrFail($id);
        $shipping->update($data);
        return $shipping->fresh()->toArray();
    }

    public function getShipping(string $id): array
    {
        $shipping = Shipping::with('order')->find($id);
        if (!$shipping) throw AppError::notFound('Shipping not found');
        $data = $shipping->toArray();
        $data['orderId']         = $data['order_id'] ?? null;
        $data['trackingNumber']  = $data['tracking_number'] ?? null;
        $data['createdAt']       = $data['created_at'] ?? null;
        $data['updatedAt']       = $data['updated_at'] ?? null;
        $data['estimatedDelivery'] = $data['estimated_delivery'] ?? null;
        $data['actualDelivery']  = $data['actual_delivery'] ?? null;
        return $data;
    }

    public function getShippingByOrder(string $orderId): array
    {
        $shipping = $this->shippingRepository->findByOrder($orderId);
        if (!$shipping) throw AppError::notFound('Shipping not found for this order');
        return $shipping->load('order')->toArray();
    }

    public function getAllShippings(int $page = 1, int $limit = 20, ?string $search = null): array
    {
        $query = Shipping::with('order');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('tracking_number', 'like', "%{$search}%")
                  ->orWhere('carrier', 'like', "%{$search}%");
            });
        }
        $paginator = $query->latest()->paginate($limit, ['*'], 'page', $page);

        // Map each item to include camelCase fields expected by frontend
        $mapped = collect($paginator->items())->map(function ($shipping) {
            $data = $shipping->toArray();
            $data['orderId']         = $data['order_id'] ?? null;
            $data['trackingNumber']  = $data['tracking_number'] ?? null;
            $data['createdAt']       = $data['created_at'] ?? null;
            $data['updatedAt']       = $data['updated_at'] ?? null;
            $data['estimatedDelivery'] = $data['estimated_delivery'] ?? null;
            $data['actualDelivery']  = $data['actual_delivery'] ?? null;
            return $data;
        })->toArray();

        return [
            'items' => $mapped,
            'page' => $paginator->currentPage(),
            'limit' => $paginator->perPage(),
            'total' => $paginator->total(),
            'total_pages' => $paginator->lastPage(),
        ];
    }

    public function getUserShipments(string $userId, int $page = 1, int $limit = 20): array
    {
        $paginator = Shipping::whereHas('order', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->with('order')->latest()->paginate($limit, ['*'], 'page', $page);
        return [
            'items' => $paginator->items(),
            'page' => $paginator->currentPage(),
            'limit' => $paginator->perPage(),
            'total' => $paginator->total(),
            'total_pages' => $paginator->lastPage(),
        ];
    }

    public function getShipmentsByStatus(string $status, int $page = 1, int $limit = 20, ?string $search = null): array
    {
        $query = Shipping::with('order')->where('status', $status);
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('tracking_number', 'like', "%{$search}%")
                  ->orWhere('carrier', 'like', "%{$search}%");
            });
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

    public function getShippingStats(): array
    {
        return [
            'total_shipments' => Shipping::count(),
            'in_transit' => Shipping::where('status', 'IN_TRANSIT')->count(),
            'delivered' => Shipping::where('status', 'DELIVERED')->count(),
            'pending' => Shipping::where('status', 'PENDING')->count(),
        ];
    }
}
