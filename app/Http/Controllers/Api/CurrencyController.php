<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CurrencyService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function __construct(protected CurrencyService $currencyService) {}

    /**
     * Public: Get active currencies.
     */
    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->currencyService->getAllActive()]);
    }

    /**
     * Public: Get default currency.
     */
    public function default(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->currencyService->getDefault()]);
    }

    /**
     * Public: Convert amount.
     */
    public function convert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric',
            'to' => 'required|string|size:3',
        ]);

        $result = $this->currencyService->convert($validated['amount'], $validated['to']);
        return response()->json(['success' => true, 'data' => ['amount' => $result, 'currency' => strtoupper($validated['to'])]], 201);
    }

    /**
     * Admin: Get all currencies.
     */
    public function adminIndex(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->currencyService->getAll()]);
    }

    /**
     * Admin: Create/update currency.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string|size:3',
                'name' => 'required|string',
                'symbol' => 'required|string|max:10',
                'exchange_rate' => 'nullable|numeric|min:0',
                'is_default' => 'nullable|boolean',
                'is_active' => 'nullable|boolean',
            ]);

            $currency = $this->currencyService->upsert($validated);
            return response()->json(['success' => true, 'message' => 'Currency saved', 'data' => $currency], 201);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Admin: Delete currency.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->currencyService->delete($id);
            return response()->json(['success' => true, 'message' => 'Currency deleted']);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Admin: Sync exchange rates from live API (Frankfurter / ECB data).
     */
    public function sync(): JsonResponse
    {
        $result = $this->currencyService->syncRates();

        $success = empty($result['errors']);
        $message = $success
            ? "Rates synced: {$result['updated']} updated, {$result['skipped']} unchanged"
            : "Sync completed with " . count($result['errors']) . " error(s)";

        return response()->json([
            'success' => $success,
            'message' => $message,
            'data' => $result,
        ]);
    }
}
