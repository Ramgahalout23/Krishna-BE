<?php

namespace App\Repositories;

use App\Models\UserNotification;
use App\Traits\CacheKeyRegistry;
use Illuminate\Support\Facades\Cache;

class NotificationRepository extends BaseRepository
{
    use CacheKeyRegistry;
    protected function modelClass(): string
    {
        return UserNotification::class;
    }

    /**
     * Generate a cache key for user-specific notification queries.
     */
    private function userCacheKey(string $userId, string $suffix): string
    {
        return "notifications:{$userId}:{$suffix}";
    }

    /**
     * Clear all notification caches for a given user.
     * Called when notifications are created, marked as read, or deleted.
     */
    private function clearUserCache(string $userId): void
    {
        $this->clearTrackedCache();
    }

    public function getUserNotifications(string $userId, int $page = 1, int $limit = 20): array
    {
        // Cache the paginated result per user (keyed by page+limit too)
        $cacheKey = $this->userCacheKey($userId, "list:{$page}:{$limit}");

        return $this->cacheWithTracking($cacheKey, 30, function () use ($userId, $page, $limit) {
            $query = UserNotification::where('user_id', $userId);
            $paginator = $query->latest()->paginate($limit, ['*'], 'page', $page);

            return [
                'notifications' => $paginator->items(),
                'page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'total' => $paginator->total(),
                'total_pages' => $paginator->lastPage(),
            ];
        });
    }

    public function getUnreadCount(string $userId): int
    {
        return $this->cacheWithTracking($this->userCacheKey($userId, 'unread_count'), 120, function () use ($userId) {
            return UserNotification::where('user_id', $userId)
                ->where('is_read', false)
                ->count();
        });
    }

    public function markAsRead(string $notificationId): void
    {
        $notification = UserNotification::find($notificationId);
        if ($notification) {
            $userId = $notification->user_id;
            $notification->update(['is_read' => true]);
            $this->clearUserCache($userId);
        }
    }

    public function markAllAsRead(string $userId): void
    {
        UserNotification::where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $this->clearUserCache($userId);
    }

    public function createNotification(string $userId, string $type, string $title, string $message, array $data = []): UserNotification
    {
        $notification = UserNotification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);

        $this->clearUserCache($userId);
        return $notification;
    }

    public function adminGetAll(int $page = 1, int $limit = 20, ?string $search = null): array
    {
        $query = UserNotification::with('user:id,first_name,last_name,email');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%");
            });
        }

        $paginator = $query->latest()->paginate($limit, ['*'], 'page', $page);

        // Map snake_case DB fields to camelCase expected by frontend
        $items = collect($paginator->items())->map(function ($notification) {
            $notification->createdAt = $notification->created_at;
            $notification->targetAudience = $notification->user_id ? 'SPECIFIC_USER' : 'ALL';
            return $notification;
        })->toArray();

        return [
            'items' => $items,
            'page' => $paginator->currentPage(),
            'limit' => $paginator->perPage(),
            'total' => $paginator->total(),
            'total_pages' => $paginator->lastPage(),
        ];
    }

    public function createSystemNotification(string $type, string $title, string $message, array $data = []): UserNotification
    {
        return UserNotification::create([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);
    }

    public function getNotificationsByType(string $userId, string $type, int $page = 1, int $limit = 20): array
    {
        $cacheKey = $this->userCacheKey($userId, "by_type:{$type}:{$page}:{$limit}");

        return $this->cacheWithTracking($cacheKey, 30, function () use ($userId, $type, $page, $limit) {
            $query = UserNotification::where('user_id', $userId)
                ->where('type', $type);

            $paginator = $query->latest()->paginate($limit, ['*'], 'page', $page);

            return [
                'items' => $paginator->items(),
                'page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'total' => $paginator->total(),
                'total_pages' => $paginator->lastPage(),
            ];
        });
    }

    public function getNotificationStats(string $userId): array
    {
        return $this->cacheWithTracking($this->userCacheKey($userId, 'stats'), 30, function () use ($userId) {
            $total = UserNotification::where('user_id', $userId)->count();
            $unread = UserNotification::where('user_id', $userId)->where('is_read', false)->count();
            $byType = UserNotification::where('user_id', $userId)
                ->selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray();

            return [
                'total' => $total,
                'unread' => $unread,
                'read' => $total - $unread,
                'by_type' => $byType,
            ];
        });
    }

    public function deleteAllUserNotifications(string $userId): void
    {
        UserNotification::where('user_id', $userId)->delete();
        $this->clearUserCache($userId);
    }

    public function createBulkNotifications(array $userIds, string $type, string $title, string $message): int
    {
        $notifications = [];
        foreach ($userIds as $userId) {
            $notifications[] = [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        UserNotification::insert($notifications);

        // Clear cache for all affected users
        foreach ($userIds as $userId) {
            $this->clearUserCache($userId);
        }

        return count($notifications);
    }
}
