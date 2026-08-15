<?php

namespace App\Services;

use App\Jobs\RecordEventJob;
use App\Jobs\RecordPageViewJob;
use App\Jobs\RecordSessionJob;
use App\Repositories\TrackingRepository;
use App\Models\UserEvent;

class TrackingService
{
    public function __construct(
        protected TrackingRepository $trackingRepository
    ) {}

    /**
     * Record a page view asynchronously after the response is sent.
     * Returns immediately — the actual DB insert happens in a queued job.
     */
    public function recordPageView(string $url, ?string $userId, ?string $sessionId, ?string $referrer, ?string $title = null, ?string $userAgent = null, ?string $device = null, ?string $source = null, ?string $utmSource = null, ?string $utmMedium = null, ?string $utmCampaign = null, ?string $utmTerm = null, ?string $utmContent = null): array
    {
        RecordPageViewJob::dispatchAfterResponse(
            $url,
            $userId,
            $sessionId,
            $referrer,
            $title,
            $userAgent,
            $device,
            $source,
            $utmSource,
            $utmMedium,
            $utmCampaign,
            $utmTerm,
            $utmContent
        );

        return ['recorded' => true];
    }

    public function createSession(?string $sessionId, ?string $userId, string $ip, string $userAgent, ?string $device = null, ?string $browser = null, ?string $os = null, ?string $referrer = null, ?string $landingPage = null, ?string $source = null, ?string $utmSource = null, ?string $utmMedium = null, ?string $utmCampaign = null, ?string $utmTerm = null, ?string $utmContent = null): array
    {
        RecordSessionJob::dispatchAfterResponse(
            $sessionId,
            $userId,
            $ip,
            $userAgent,
            $device,
            $browser,
            $os,
            $referrer,
            $landingPage,
            $source,
            $utmSource,
            $utmMedium,
            $utmCampaign,
            $utmTerm,
            $utmContent
        );

        return ['recorded' => true, 'session_id' => $sessionId];
    }

    public function endSession(string $sessionId): void
    {
        $this->trackingRepository->endSession($sessionId);
    }

    public function recordEvent(string $sessionId, string $eventType, ?string $eventName = null, ?string $category = null, ?string $label = null, ?string $value = null, ?string $url = null, ?string $metadata = null, ?string $userId = null): array
    {
        RecordEventJob::dispatchAfterResponse(
            $sessionId,
            $eventType,
            $eventName,
            $category,
            $label,
            $value,
            $url,
            $metadata,
            $userId
        );

        return ['recorded' => true];
    }

    public function getPageViewStats(): array
    {
        return $this->trackingRepository->getPageViewsStats();
    }

    public function getActiveSessions(): array
    {
        return ['active_sessions' => $this->trackingRepository->getActiveSessions()];
    }

    public function getTopPages(int $limit = 10): array
    {
        return $this->trackingRepository->getTopPages($limit)->toArray();
    }

    public function getTrafficSourceStats(?string $dateRange = null, ?string $startDate = null, ?string $endDate = null): array
    {
        return $this->trackingRepository->getTrafficSourceStats($dateRange, $startDate, $endDate);
    }

    public function getUtmCampaignStats(?string $dateRange = null, ?string $startDate = null, ?string $endDate = null): array
    {
        return $this->trackingRepository->getUtmCampaignStats($dateRange, $startDate, $endDate);
    }

    // ── Admin Event Methods ──

    public function getEvents(int $page = 1, int $limit = 20): array
    {
        $paginator = UserEvent::with('session')
            ->latest()
            ->paginate($limit, ['*'], 'page', $page);
        return [
            'items' => $paginator->items(),
            'page' => $paginator->currentPage(),
            'limit' => $paginator->perPage(),
            'total' => $paginator->total(),
            'total_pages' => $paginator->lastPage(),
        ];
    }

    public function getEventStats(): array
    {
        return [
            'total_events' => UserEvent::count(),
            'today' => UserEvent::whereDate('created_at', today())->count(),
            'by_type' => UserEvent::selectRaw('event_type, COUNT(*) as count')
                ->groupBy('event_type')
                ->pluck('count', 'event_type')
                ->toArray(),
        ];
    }

    public function getUserJourney(string $userId): array
    {
        $pageViews = $this->trackingRepository->getPageViewsByUser($userId);
        $events = $this->trackingRepository->getEventsByUser($userId);
        $sessions = $this->trackingRepository->getSessionsByUser($userId);

        return [
            'user_id' => $userId,
            'sessions' => $sessions->toArray(),
            'page_views' => $pageViews->toArray(),
            'events' => $events->toArray(),
        ];
    }
}
