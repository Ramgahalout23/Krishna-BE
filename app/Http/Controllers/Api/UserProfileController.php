<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Traits\MapsCamelCaseFields;
use App\Services\UserProfileService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserProfileController extends Controller
{
    use MapsCamelCaseFields;
    public function __construct(protected UserProfileService $userProfileService) {}

    public function show(): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->userProfileService->getProfile(Auth::id())]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function orders(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->userProfileService->getOrders(Auth::id())]);
    }

    public function wallet(): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->userProfileService->getWallet(Auth::id())]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function loyalty(): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->userProfileService->getLoyalty(Auth::id())]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Get user profile stats (order count, total spent, etc).
     * GET /api/v1/user-profile/stats
     */
    public function stats(): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->userProfileService->getStats(Auth::id())]);
        } catch (AppError $e) { return $e->render(); }
    }

    // ── Address Management under /user-profile/addresses ──

    public function addresses(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->userProfileService->getAddresses(Auth::id())]);
    }

    public function defaultAddress(): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->userProfileService->getDefaultAddress(Auth::id())]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function addAddress(Request $request): JsonResponse
    {
        try {
            $input = $this->mapCamelCase($request->all(), [
                'firstName' => 'first_name',
                'lastName' => 'last_name',
                'phoneNumber' => 'phone_number',
                'addressLine1' => 'address_line1',
                'addressLine2' => 'address_line2',
                'zipCode' => 'zip_code',
                'isDefault' => 'is_default',
            ]);
            $request->replace($input);

            $validated = $request->validate([
                'type' => 'nullable|string|in:SHIPPING,BILLING,BOTH',
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'phone_number' => 'required|string|max:20',
                'address_line1' => 'required|string|max:255',
                'address_line2' => 'nullable|string|max:255',
                'city' => 'required|string|max:255',
                'state' => 'required|string|max:255',
                'zip_code' => 'required|string|max:20',
                'country' => 'required|string|max:255',
                'is_default' => 'boolean',
            ]);
            $address = $this->userProfileService->addAddress(Auth::id(), $validated);
            return response()->json(['success' => true, 'message' => 'Address added', 'data' => $address], 201);
        } catch (AppError $e) { return $e->render(); }
    }

    public function updateAddress(Request $request, string $addressId): JsonResponse
    {
        try {
            $input = $this->mapCamelCase($request->all(), [
                'firstName' => 'first_name',
                'lastName' => 'last_name',
                'phoneNumber' => 'phone_number',
                'addressLine1' => 'address_line1',
                'addressLine2' => 'address_line2',
                'zipCode' => 'zip_code',
                'isDefault' => 'is_default',
            ]);
            $request->replace($input);

            $validated = $request->validate([
                'type' => 'nullable|string|in:SHIPPING,BILLING,BOTH',
                'first_name' => 'sometimes|string|max:255',
                'last_name' => 'sometimes|string|max:255',
                'phone_number' => 'sometimes|string|max:20',
                'address_line1' => 'sometimes|string|max:255',
                'address_line2' => 'nullable|string|max:255',
                'city' => 'sometimes|string|max:255',
                'state' => 'sometimes|string|max:255',
                'zip_code' => 'sometimes|string|max:20',
                'country' => 'sometimes|string|max:255',
                'is_default' => 'boolean',
            ]);
            return response()->json(['success' => true, 'message' => 'Address updated', 'data' => $this->userProfileService->updateAddress(Auth::id(), $addressId, $validated)]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function deleteAddress(string $addressId): JsonResponse
    {
        try {
            $this->userProfileService->deleteAddress(Auth::id(), $addressId);
            return response()->json(['success' => true, 'message' => 'Address deleted']);
        } catch (AppError $e) { return $e->render(); }
    }

    public function showAddress(string $addressId): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->userProfileService->getAddress(Auth::id(), $addressId)]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function setDefaultAddress(string $addressId): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'message' => 'Default address updated', 'data' => $this->userProfileService->setDefaultAddress(Auth::id(), $addressId)]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Upload a user avatar image.
     * POST /api/v1/uploads/avatar
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
            ]);

            $result = app(\App\Services\StorageDriverService::class)->storeFile($request->file('file'), 'uploads/avatars');

            return response()->json([
                'success' => true,
                'data' => ['url' => $result['url'], 'path' => $result['path']],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
