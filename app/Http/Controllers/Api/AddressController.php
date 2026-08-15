<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AddressService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AddressController extends Controller
{
    public function __construct(protected AddressService $addressService) {}

    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->addressService->getUserAddresses(Auth::id())]);
    }

    public function show(string $id): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->addressService->getById($id, Auth::id())]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'phone_number' => 'required|string|max:20',
                'address_line1' => 'required|string|max:255',
                'address_line2' => 'nullable|string|max:255',
                'city' => 'required|string|max:100',
                'state' => 'required|string|max:100',
                'zip_code' => 'required|string|max:20',
                'country' => 'required|string|max:100',
                'is_default' => 'nullable|boolean',
                'type' => 'nullable|string|in:HOME,WORK,OTHER',
            ]);
            $address = $this->addressService->create(Auth::id(), $validated);
            return response()->json(['success' => true, 'message' => 'Address created', 'data' => $address], 201);
        } catch (AppError $e) { return $e->render(); }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'address_line1' => 'sometimes|string|max:255',
                'city' => 'sometimes|string|max:100',
                'state' => 'sometimes|string|max:100',
                'is_default' => 'nullable|boolean',
            ]);
            $address = $this->addressService->update($id, Auth::id(), $validated);
            return response()->json(['success' => true, 'message' => 'Address updated', 'data' => $address]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->addressService->delete($id, Auth::id());
            return response()->json(['success' => true, 'message' => 'Address deleted']);
        } catch (AppError $e) { return $e->render(); }
    }
}
