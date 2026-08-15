<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdminService;
use App\Models\ExportJob;
use App\Jobs\GenerateExportJob;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    public function __construct(
        protected AdminService $adminService
    ) {}

    public function users(Request $request): JsonResponse
    {
        $users = $this->adminService->getUsers($request->only(['search', 'role']));
        return response()->json(['success' => true, 'data' => $users]);
    }

    public function userDetail(string $id): JsonResponse
    {
        try {
            $user = $this->adminService->getUserById($id);
            return response()->json(['success' => true, 'data' => $user]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function manageUser(Request $request, string $id): JsonResponse
    {
        try {
            $input = $request->only(['role', 'is_active', 'is_blocked', 'action']);

            // Handle frontend's 'action' param (block/unblock) mapping to is_blocked
            if (isset($input['action']) && !isset($input['is_blocked'])) {
                if ($input['action'] === 'block') {
                    $input['is_blocked'] = true;
                } elseif ($input['action'] === 'unblock') {
                    $input['is_blocked'] = false;
                }
            }

            $user = $this->adminService->manageUser($id, $input);
            return response()->json(['success' => true, 'message' => 'User updated', 'data' => $user]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function updateUserRole(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate(['role' => 'required|string|in:CUSTOMER,MANAGER,ADMIN,SUPER_ADMIN']);
            $user = $this->adminService->manageUser($id, ['role' => $validated['role']]);
            return response()->json(['success' => true, 'message' => 'User role updated', 'data' => $user]);
        } catch (AppError $e) { return $e->render(); }
    }

    // ── Staff ──

    public function staff(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getStaff($request->only(['search', 'role']))]);
    }

    public function staffCreate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|max:255',
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'role' => 'nullable|string|in:ADMIN,MANAGER,SUPER_ADMIN,SUPPORT_AGENT,FINANCE',
                'password' => 'nullable|string|min:8',
            ]);
            $staff = $this->adminService->createStaff($validated);
            return response()->json(['success' => true, 'message' => 'Staff created', 'data' => $staff], 201);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function staffUpdate(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'role' => 'nullable|string|in:ADMIN,MANAGER,SUPER_ADMIN,SUPPORT_AGENT,FINANCE',
                'is_active' => 'nullable|boolean',
            ]);
            $staff = $this->adminService->updateStaff($id, $validated);
            return response()->json(['success' => true, 'message' => 'Staff updated', 'data' => $staff]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    // ── Export Users ──

    /**
     * Export users as CSV download.
     * GET /api/v1/admin/users/export
     * Accepts optional `columns` query param: comma-separated column keys to include.
     */
    public function exportUsers(Request $request): \Illuminate\Http\Response
    {
        try {
            $exportJob = ExportJob::create([
                'user_id' => $request->user()->id,
                'type' => 'users',
                'status' => 'pending',
                'filters' => $request->only(['search', 'role', 'status']),
                'columns' => $request->has('columns') ? array_map('trim', explode(',', $request->input('columns'))) : [],
            ]);
            GenerateExportJob::dispatch($exportJob->id);

            return response()->json([
                'success' => true,
                'message' => 'Export queued for processing',
                'data' => [
                    'id' => $exportJob->id,
                    'type' => $exportJob->type,
                    'status' => 'queued',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
