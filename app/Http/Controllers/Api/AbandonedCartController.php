<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Traits\MapsCamelCaseFields;
use App\Jobs\SendAbandonedCartReminderJob;
use App\Services\AbandonedCartService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AbandonedCartController extends Controller
{
    use MapsCamelCaseFields;
    public function __construct(protected AbandonedCartService $abandonedCartService) {}

    public function all(Request $request): JsonResponse
    {
        $result = $this->abandonedCartService->getAll($request->all());
        // $result is from paginator->toArray(): { data: [...], current_page, last_page, per_page, total }
        return response()->json([
            'success' => true,
            'data' => [
                'carts' => $result['data'] ?? [],
                'pagination' => [
                    'page' => $result['current_page'] ?? 1,
                    'pages' => $result['last_page'] ?? 1,
                    'total' => $result['total'] ?? 0,
                    'per_page' => $result['per_page'] ?? 15,
                ],
            ],
        ]);
    }

    /**
     * Get abandoned cart by ID.
     * GET /api/v1/admin/abandoned-carts/{id} (admin — no ownership check)
     * GET /api/v1/abandoned-carts/{id} (user — with ownership check)
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            // Admins can view any cart; regular users can only view their own
            $userId = ($user && $user->isAdmin()) ? null : ($user->id ?? Auth::id());
            return response()->json(['success' => true, 'data' => $this->abandonedCartService->getById($id, $userId)]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Delete an abandoned cart (Admin).
     * DELETE /api/v1/admin/abandoned-carts/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->abandonedCartService->delete($id);
            return response()->json(['success' => true, 'message' => 'Abandoned cart deleted']);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Get user abandoned carts (User).
     * POST /api/v1/abandoned-carts
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $input = $this->mapCamelCase($request->all(), [
                'cartData' => 'cart_data',
                'sessionId' => 'session_id',
            ]);

            $data = validator($input, [
                'cart_data' => 'nullable|array',
                'session_id' => 'nullable|string',
            ])->validate();

            $cart = $this->abandonedCartService->create(Auth::id(), $data);
            return response()->json(['success' => true, 'data' => $cart], 201);
        } catch (AppError $e) { return $e->render(); } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }
    }

    public function userCarts(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->abandonedCartService->getUserCarts(Auth::id())]);
    }

    public function stats(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->abandonedCartService->getStats()]);
    }

    public function sendReminder(string $id): JsonResponse
    {
        try {
            SendAbandonedCartReminderJob::dispatch($id);
            return response()->json(['success' => true, 'message' => 'Reminder queued for processing'], 202);
        } catch (AppError $e) { return $e->render(); }
    }
}

