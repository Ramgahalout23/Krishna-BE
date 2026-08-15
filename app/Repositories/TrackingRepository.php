<?php

namespace App\Repositories;

use App\Models\PageView;
use App\Models\UserSession;
use App\Models\UserEvent;
use App\Traits\CacheKeyRegistry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class TrackingRepository
{
    use CacheKeyRegistry;

    public function endSession(string $sessionId): void
    {
        UserSession::where('session_id', $sessionId)->update(['end_time' => now(), 'is_active' => false]);
    }

    public function getPageViewsStats(): array
    {
        return $this->cacheWithTracking('tracking_pageview_stats', 300, function () {
            return [
                'total' => PageView::count(),
                'today' => PageView::whereDate('created_at', today())->count(),
                'unique_urls' => PageView::distinct('url')->count('url'),
            ];
        });
    }

    public function getActiveSessions(): int
    {
        return $this->cacheWithTracking('tracking_active_sessions', 60, function () {
            return UserSession::where('is_active', true)
                ->where('start_time', '>=', now()->subHours(2))
                ->count();
        });
    }

    public function getTopPages(int $limit = 10): Collection
    {
        return $this->cacheWithTracking('tracking_top_pages', 300, function () use ($limit) {
            return PageView::select('url')
                ->selectRaw('COUNT(*) as views')
                ->groupBy('url')
                ->orderByDesc('views')
                ->take($limit)
                ->get();
        });
    }

    /**
     * Apply date range filter to a query.
     * Supports presets (today, 7d, 30d) and custom ranges via startDate/endDate.
     */
    private function applyDateRange($query, ?string $dateRange, ?string $startDate = null, ?string $endDate = null): void
    {
        // Custom date range takes precedence
        if ($dateRange === 'custom' && $startDate) {
            try {
                $start = \Carbon\Carbon::parse($startDate)->startOfDay();
                $query->where('created_at', '>=', $start);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('[TrackingRepository] Invalid startDate: ' . $e->getMessage());
            }
            if ($endDate) {
                try {
                    $end = \Carbon\Carbon::parse($endDate)->endOfDay();
                    $query->where('created_at', '<=', $end);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('[TrackingRepository] Invalid endDate: ' . $e->getMessage());
                }
            }
            return;
        }

        if (!$dateRange || $dateRange === 'all') return;

        $start = match ($dateRange) {
            'today' => now()->startOfDay(),
            '7d'    => now()->subDays(7)->startOfDay(),
            '30d'   => now()->subDays(30)->startOfDay(),
            default => null,
        };

        if ($start) {
            $query->where('created_at', '>=', $start);
        }
    }

    /**
     * Get traffic source breakdown stats.
     */
    public function getTrafficSourceStats(?string $dateRange = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $cacheKey = 'tracking_source_stats' . ($dateRange ? '_' . $dateRange : '');
        if ($dateRange === 'custom') $cacheKey .= '_' . ($startDate ?? 'null') . '_' . ($endDate ?? 'null');
        return $this->cacheWithTracking($cacheKey, 300, function () use ($dateRange, $startDate, $endDate) {
            try {
                $query = UserSession::select('source')
                    ->selectRaw('COUNT(*) as count')
                    ->whereNotNull('source');

                $this->applyDateRange($query, $dateRange, $startDate, $endDate);

                $sources = $query->groupBy('source')
                    ->orderByDesc('count')
                    ->get()
                    ->toArray();

                $totalWithSource = array_sum(array_column($sources, 'count'));

                // Count total sessions within date range
                $totalQuery = UserSession::query();
                $this->applyDateRange($totalQuery, $dateRange, $startDate, $endDate);
                $totalSessions = $totalQuery->count();
                $directCount = $totalSessions - $totalWithSource;

                $result = $sources;
                if ($directCount > 0) {
                    $result[] = ['source' => 'direct', 'count' => $directCount];
                }

                usort($result, fn($a, $b) => $b['count'] - $a['count']);

                return $result;
            } catch (\Throwable $e) {
                // Column may not exist yet (migration not run) — return empty
                \Illuminate\Support\Facades\Log::warning('[TrackingRepository] Failed to get traffic source stats: ' . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Get UTM campaign stats.
     */
    public function getUtmCampaignStats(?string $dateRange = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $cacheKey = 'tracking_utm_stats' . ($dateRange ? '_' . $dateRange : '');
        if ($dateRange === 'custom') $cacheKey .= '_' . ($startDate ?? 'null') . '_' . ($endDate ?? 'null');
        return $this->cacheWithTracking($cacheKey, 300, function () use ($dateRange, $startDate, $endDate) {
            try {
                $query = UserSession::select('utm_source', 'utm_medium', 'utm_campaign')
                    ->selectRaw('COUNT(*) as count')
                    ->whereNotNull('utm_source');

                $this->applyDateRange($query, $dateRange, $startDate, $endDate);

                return $query->groupBy('utm_source', 'utm_medium', 'utm_campaign')
                    ->orderByDesc('count')
                    ->take(20)
                    ->get()
                    ->toArray();
            } catch (\Throwable $e) {
                // Column may not exist yet (migration not run) — return empty
                \Illuminate\Support\Facades\Log::warning('[TrackingRepository] Failed to get UTM campaign stats: ' . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Get page views by user for journey.
     */
    public function getPageViewsByUser(string $userId): Collection
    {
        return PageView::where('user_id', $userId)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get events by user through sessions.
     */
    public function getEventsByUser(string $userId): Collection
    {
        return UserEvent::where('user_id', $userId)
            ->with('session')
            ->latest()
            ->get();
    }

    /**
     * Get sessions by user.
     */
    public function getSessionsByUser(string $userId): Collection
    {
        return UserSession::where('user_id', $userId)
            ->orderBy('start_time', 'desc')
            ->get();
    }
}
