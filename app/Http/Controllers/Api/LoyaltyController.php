<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LoyaltyService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoyaltyController extends Controller
{
    public function __construct(
        protected LoyaltyService $loyaltyService
    ) {}

    /**
     * Get loyalty points with transactions.
     */
    public function show(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->loyaltyService->getPoints(Auth::id()),
            ]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Get point balance only.
     */
    public function balance(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'points' => $this->loyaltyService->getBalance(Auth::id()),
                'tier' => $this->loyaltyService->getTier($this->loyaltyService->getBalance(Auth::id())),
                'money_value' => $this->loyaltyService->pointsToMoney($this->loyaltyService->getBalance(Auth::id())),
            ],
        ]);
    }

    /**
     * Get transaction history.
     */
    public function history(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->loyaltyService->getTransactionHistory(Auth::id(), $request->input('limit', 20)),
            ]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Get tier and conversion info.
     */
    public function info(): JsonResponse
    {
        $points = $this->loyaltyService->getBalance(Auth::id());
        return response()->json([
            'success' => true,
            'data' => [
                'current_points' => $points,
                'tier' => $this->loyaltyService->getTier($points),
                'conversion_rate' => $this->loyaltyService->getConversionRate(),
                'points_to_money' => $this->loyaltyService->pointsToMoney($points),
                'next_tier_points' => $this->getNextTierPoints($points),
            ],
        ]);
    }

    // ── Admin Routes ──

    /**
     * Admin: get all loyalty records.
     */
    public function adminIndex(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->loyaltyService->getAll($request->all()),
        ]);
    }

    /**
     * Admin: adjust points for a user.
     */
    public function adjust(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|string',
                'points' => 'required|integer',
                'reason' => 'required|string|max:255',
            ]);

            $loyalty = $this->loyaltyService->adjustPoints(
                $validated['user_id'],
                $validated['points'],
                $validated['reason'],
                Auth::id()
            );

            return response()->json([
                'success' => true,
                'message' => 'Loyalty points adjusted successfully',
                'data' => $loyalty,
            ]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Admin: get loyalty for a specific user.
     */
    public function adminShow(string $userId): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->loyaltyService->getPoints($userId),
            ]);
        } catch (AppError $e) { return $e->render(); }
    }

    private function getNextTierPoints(int $currentPoints): array
    {
        $tiers = [
            'BRONZE' => 0,
            'SILVER' => 500,
            'GOLD' => 2000,
            'PLATINUM' => 5000,
            'DIAMOND' => 10000,
        ];

        $nextTier = null;
        $nextTierPoints = 0;
        foreach ($tiers as $tier => $minPoints) {
            if ($currentPoints < $minPoints) {
                $nextTier = $tier;
                $nextTierPoints = $minPoints;
                break;
            }
        }

        return [
            'next_tier' => $nextTier,
            'points_needed' => $nextTier ? $nextTierPoints - $currentPoints : 0,
            'current_tier' => $this->loyaltyService->getTier($currentPoints),
        ];
    }
}
