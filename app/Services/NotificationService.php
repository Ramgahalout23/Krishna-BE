<?php

namespace App\Services;

use App\Jobs\SendNotificationJob;
use App\Repositories\NotificationRepository;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function __construct(
        protected NotificationRepository $notificationRepository
    ) {}

    public function getUserNotifications(string $userId, int $page = 1, int $limit = 20): array
    {
        $result = $this->notificationRepository->getUserNotifications($userId, $page, $limit);
        $result['unread_count'] = $this->notificationRepository->getUnreadCount($userId);
        return $result;
    }

    public function markAsRead(string $notificationId): void
    {
        $this->notificationRepository->markAsRead($notificationId);
    }

    public function markAllAsRead(string $userId): void
    {
        $this->notificationRepository->markAllAsRead($userId);
    }

    public function create(string $userId, string $type, string $title, string $message, array $data = []): array
    {
        try {
            SendNotificationJob::dispatch($userId, $type, $title, $message, $data);
            Log::info("[NotificationService] Dispatched notification job for user {$userId}: {$type} - {$title}");
            return ['dispatched' => true];
        } catch (\Exception $e) {
            Log::error("[NotificationService] Failed to dispatch notification job: {$e->getMessage()}");
            return $this->createNotificationSync($userId, $type, $title, $message, $data)->toArray();
        }
    }

    /**
     * Create a notification synchronously (called by SendNotificationJob).
     */
    public function createNotificationSync(string $userId, string $type, string $title, string $message, array $data = []): \App\Models\UserNotification
    {
        return $this->notificationRepository->createNotification($userId, $type, $title, $message, $data);
    }

    public function createSystemNotification(string $type, string $title, string $message, array $data = []): array
    {
        try {
            SendNotificationJob::dispatch(null, $type, $title, $message, $data);
            Log::info("[NotificationService] Dispatched system notification: {$type} - {$title}");
            return ['dispatched' => true];
        } catch (\Exception $e) {
            Log::error("[NotificationService] Failed to dispatch system notification: {$e->getMessage()}");
            return $this->createSystemNotificationSync($type, $title, $message, $data)->toArray();
        }
    }

    /**
     * Create a system notification synchronously (called by SendNotificationJob).
     */
    public function createSystemNotificationSync(string $type, string $title, string $message, array $data = []): \App\Models\UserNotification
    {
        return $this->notificationRepository->createSystemNotification($type, $title, $message, $data);
    }

    public function adminGetAll(int $page = 1, int $limit = 20, ?string $search = null): array
    {
        return $this->notificationRepository->adminGetAll($page, $limit, $search);
    }

    public function adminDelete(string $notificationId): void
    {
        $notification = $this->notificationRepository->findById($notificationId);
        if (!$notification) {
            throw new \App\Exceptions\AppError('Notification not found', 404);
        }
        $this->notificationRepository->delete($notificationId);
    }

    public function getUnreadCount(string $userId): int
    {
        return $this->notificationRepository->getUnreadCount($userId);
    }

    public function deleteNotification(string $userId, string $notificationId): void
    {
        $notification = $this->notificationRepository->findById($notificationId);
        if (!$notification) {
            throw new \App\Exceptions\AppError('Notification not found', 404);
        }
        if ($notification->user_id !== $userId) {
            throw new \App\Exceptions\AppError('Unauthorized access', 403);
        }
        $this->notificationRepository->delete($notificationId);
    }

    public function getNotificationsByType(string $userId, string $type, int $page = 1, int $limit = 20): array
    {
        return $this->notificationRepository->getNotificationsByType($userId, $type, $page, $limit);
    }

    public function getNotificationStats(string $userId): array
    {
        return $this->notificationRepository->getNotificationStats($userId);
    }

    public function sendBulkNotification(array $userIds, string $type, string $title, string $message): array
    {
        if (empty($userIds)) {
            throw new \App\Exceptions\AppError('User IDs array cannot be empty', 422);
        }

        try {
            // Dispatch individual notification jobs for each user
            $dispatched = 0;
            foreach ($userIds as $userId) {
                SendNotificationJob::dispatch($userId, $type, $title, $message);
                $dispatched++;
            }
            Log::info("[NotificationService] Dispatched {$dispatched} bulk notification jobs: {$type} - {$title}");
            return ['count' => $dispatched, 'dispatched' => true];
        } catch (\Exception $e) {
            Log::error("[NotificationService] Failed to dispatch bulk notifications: {$e->getMessage()}");
            // Fallback to synchronous creation
            $count = $this->notificationRepository->createBulkNotifications($userIds, $type, $title, $message);
            return ['count' => $count];
        }
    }
}
