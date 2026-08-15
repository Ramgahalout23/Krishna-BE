<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exceptions\AppError;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct(protected NotificationService $notificationService) {}

    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->notificationService->getUserNotifications(Auth::id())]);
    }

    public function markRead(string $id): JsonResponse
    {
        $this->notificationService->markAsRead($id);
        return response()->json(['success' => true, 'message' => 'Marked as read']);
    }

    public function markAllRead(): JsonResponse
    {
        $this->notificationService->markAllAsRead(Auth::id());
        return response()->json(['success' => true, 'message' => 'All marked as read']);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'title' => 'required|string',
            'message' => 'required|string',
            'user_id' => 'nullable|string', // Optional: send to a specific user; omit for system-wide
        ]);

        if (!empty($validated['user_id'])) {
            // Send to a specific user
            $notification = $this->notificationService->create($validated['user_id'], $validated['type'], $validated['title'], $validated['message']);
        } else {
            // Create system-wide notification (no user_id — frontend can display these for all users)
            $notification = $this->notificationService->createSystemNotification($validated['type'], $validated['title'], $validated['message']);
        }

        return response()->json(['success' => true, 'data' => $notification], 201);
    }

    public function adminGetAll(Request $request): JsonResponse
    {
        $page = $request->page ?? 1;
        $limit = $request->limit ?? 20;
        $search = $request->search;
        $result = $this->notificationService->adminGetAll((int) $page, (int) $limit, $search);
        return response()->json(['success' => true, 'data' => $result]);
    }

    public function adminDelete(string $id): JsonResponse
    {
        try {
            $this->notificationService->adminDelete($id);
            return response()->json(['success' => true, 'message' => 'Notification deleted']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }

    public function getUnreadNotifications(): JsonResponse
    {
        $count = $this->notificationService->getUnreadCount(Auth::id());
        return response()->json([
            'success' => true,
            'data' => ['count' => $count],
        ]);
    }

    public function deleteNotification(string $id): JsonResponse
    {
        try {
            $this->notificationService->deleteNotification(Auth::id(), $id);
            return response()->json(['success' => true, 'message' => 'Notification deleted']);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function getNotificationsByType(string $type, Request $request): JsonResponse
    {
        $page = $request->page ?? 1;
        $limit = $request->limit ?? 20;
        $result = $this->notificationService->getNotificationsByType(Auth::id(), $type, (int) $page, (int) $limit);
        return response()->json(['success' => true, 'data' => $result]);
    }

    public function getNotificationStats(): JsonResponse
    {
        $stats = $this->notificationService->getNotificationStats(Auth::id());
        return response()->json(['success' => true, 'data' => $stats]);
    }

    public function sendBulkNotification(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_ids' => 'required|array|min:1',
                'user_ids.*' => 'required|string',
                'type' => 'required|string',
                'title' => 'required|string',
                'message' => 'required|string',
            ]);

            $result = $this->notificationService->sendBulkNotification(
                $validated['user_ids'],
                $validated['type'],
                $validated['title'],
                $validated['message']
            );
            return response()->json(['success' => true, 'message' => 'Bulk notifications sent', 'data' => $result]);
        } catch (AppError $e) {
            return $e->render();
        }
    }
}
