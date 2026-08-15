<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Traits\MapsCamelCaseFields;
use App\Services\ShippingService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    use MapsCamelCaseFields;

    private array $fieldMappings = [
        'orderId' => 'order_id',
        'trackingNumber' => 'tracking_number',
        'estimatedDelivery' => 'estimated_delivery',
    ];
    public function __construct(protected ShippingService $shippingService) {}

    public function providers(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->shippingService->getProviders()]);
    }

    public function zones(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->shippingService->getZones()]);
    }

    public function calculate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['zone_id' => 'required|string', 'weight' => 'nullable|numeric', 'subtotal' => 'nullable|numeric']);
            return response()->json(['success' => true, 'data' => $this->shippingService->calculate($validated['zone_id'], $validated['weight'] ?? 0, $validated['subtotal'] ?? 0)]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Track a shipment by tracking number.
     * GET /api/v1/shipping/tracking/{trackingNumber}
     */
    public function trackShipment(string $trackingNumber): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->shippingService->trackShipment($trackingNumber)]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    // ── User Routes ──

    public function methods(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->shippingService->getShippingMethods()]);
    }

    public function getShippingByOrder(string $orderId): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->shippingService->getShippingByOrder($orderId)]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function getUserShipments(Request $request): JsonResponse
    {
        $page = $request->page ?? 1;
        $limit = $request->limit ?? 20;
        $userId = $request->user()?->id;
        return response()->json(['success' => true, 'data' => $this->shippingService->getUserShipments($userId, (int)$page, (int)$limit)]);
    }

    public function show(string $id): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->shippingService->getShipping($id)]);
        } catch (AppError $e) { return $e->render(); }
    }

    // ── Admin Zone CRUD ──

    public function createZone(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['name' => 'required|string|max:255']);
            return response()->json(['success' => true, 'data' => $this->shippingService->createZone($validated)], 201);
        } catch (AppError $e) { return $e->render(); }
    }

    public function updateZone(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate(['name' => 'sometimes|string|max:255']);
            return response()->json(['success' => true, 'data' => $this->shippingService->updateZone($id, $validated)]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function deleteZone(string $id): JsonResponse
    {
        try {
            $this->shippingService->deleteZone($id);
            return response()->json(['success' => true, 'message' => 'Zone deleted']);
        } catch (AppError $e) { return $e->render(); }
    }

    public function zonesList(Request $request): JsonResponse
    {
        $page = $request->page ?? 1;
        $limit = $request->limit ?? 20;
        return response()->json(['success' => true, 'data' => $this->shippingService->getAllZonesPaginated((int)$page, (int)$limit)]);
    }

    // ── Admin Rate CRUD ──

    public function createRate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'shipping_zone_id' => 'required|string',
                'name' => 'required|string|max:255',
                'base_rate' => 'required|numeric|min:0',
            ]);
            return response()->json(['success' => true, 'data' => $this->shippingService->createRate($validated)], 201);
        } catch (AppError $e) { return $e->render(); }
    }

    public function updateRate(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'base_rate' => 'sometimes|numeric|min:0',
            ]);
            return response()->json(['success' => true, 'data' => $this->shippingService->updateRate($id, $validated)]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function deleteRate(string $id): JsonResponse
    {
        try {
            $this->shippingService->deleteRate($id);
            return response()->json(['success' => true, 'message' => 'Rate deleted']);
        } catch (AppError $e) { return $e->render(); }
    }

    // ── Admin Shipping CRUD ──

    public function createShipping(Request $request): JsonResponse
    {
        try {
            $data = $this->mapCamelCase($request->all(), $this->fieldMappings);

            $validated = validator($data, [
                'order_id' => 'required|string',
                'carrier' => 'required|string|max:255',
                'tracking_number' => 'nullable|string',
            ])->validate();

            return response()->json(['success' => true, 'data' => $this->shippingService->createShipping($validated)], 201);
        } catch (AppError $e) { return $e->render(); } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }
    }

    public function updateShipping(Request $request, string $id): JsonResponse
    {
        try {
            $data = $this->mapCamelCase($request->all(), $this->fieldMappings);

            $validated = validator($data, [
                'carrier' => 'sometimes|string|max:255',
                'tracking_number' => 'nullable|string',
                'status' => 'nullable|string',
                'estimated_delivery' => 'nullable|date',
            ])->validate();

            return response()->json(['success' => true, 'data' => $this->shippingService->updateShipping($id, $validated)]);
        } catch (AppError $e) { return $e->render(); } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }
    }

    public function getAllShippings(Request $request): JsonResponse
    {
        $page = $request->page ?? 1;
        $limit = $request->limit ?? 20;
        $search = $request->search;
        return response()->json(['success' => true, 'data' => $this->shippingService->getAllShippings((int)$page, (int)$limit, $search)]);
    }

    public function getShipmentsByStatus(Request $request): JsonResponse
    {
        $status = $request->status ?? 'IN_TRANSIT';
        $page = $request->page ?? 1;
        $limit = $request->limit ?? 20;
        return response()->json(['success' => true, 'data' => $this->shippingService->getShipmentsByStatus($status, (int)$page, (int)$limit)]);
    }

    public function getShippingStats(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->shippingService->getShippingStats()]);
    }
}
