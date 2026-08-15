<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WalletService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    public function __construct(
        protected WalletService $walletService
    ) {}

    /**
     * Get wallet details with transactions.
     */
    public function show(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->walletService->getWallet(Auth::id()),
            ]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Get wallet balance only.
     */
    public function balance(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['balance' => $this->walletService->getBalance(Auth::id())],
        ]);
    }

    /**
     * Get wallet transactions.
     */
    public function transactions(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->walletService->getTransactions(Auth::id(), $request->input('limit', 20)),
            ]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Recharge wallet (user).
     */
    public function recharge(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'reason' => 'nullable|string|max:255',
            ]);

            $wallet = $this->walletService->recharge(
                Auth::id(),
                $validated['amount'],
                $validated['reason'] ?? 'Wallet recharge'
            );

            return response()->json([
                'success' => true,
                'message' => 'Wallet recharged successfully',
                'data' => $wallet,
            ]);
        } catch (AppError $e) { return $e->render(); }
    }

    // ── Admin Routes ──

    /**
     * Admin: get all wallets.
     */
    public function adminIndex(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->walletService->getAllWallets($request->all()),
        ]);
    }

    /**
     * Admin: adjust user wallet balance.
     */
    public function adjust(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|string',
                'amount' => 'required|numeric',
                'reason' => 'required|string|max:255',
            ]);

            $wallet = $this->walletService->adjustWallet(
                $validated['user_id'],
                $validated['amount'],
                $validated['reason'],
                Auth::id()
            );

            return response()->json([
                'success' => true,
                'message' => 'Wallet adjusted successfully',
                'data' => $wallet,
            ]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Admin: get wallet for a specific user.
     */
    public function adminShow(string $userId): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->walletService->getWallet($userId),
            ]);
        } catch (AppError $e) { return $e->render(); }
    }
}
