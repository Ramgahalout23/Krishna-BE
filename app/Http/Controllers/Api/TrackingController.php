<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TrackingController extends Controller
{
    public function __construct(protected TrackingService $trackingService) {}

    /**
     * Record a page view.
     * Accepts both camelCase (frontend) and snake_case field names.
     */
    public function pageView(Request $request): JsonResponse
    {
        $sessionId = $request->sessionId ?? $request->session_id;
        $userId = $request->userId ?? $request->user_id ?? Auth::id();

        $view = $this->trackingService->recordPageView(
            $request->url,
            $userId,
            $sessionId,
            $request->referrer,
            $request->title,
            $request->userAgent ?? $request->user_agent,
            $request->device,
            $request->source,
            $request->utmSource ?? $request->utm_source,
            $request->utmMedium ?? $request->utm_medium,
            $request->utmCampaign ?? $request->utm_campaign,
            $request->utmTerm ?? $request->utm_term,
            $request->utmContent ?? $request->utm_content
        );
        return response()->json(['success' => true, 'data' => $view]);
    }

    /**
     * Create a tracking session.
     * Accepts both camelCase (frontend) and snake_case field names.
     * Handles both authenticated and guest users.
     */
    public function createSession(Request $request): JsonResponse
    {
        $sessionId = $request->sessionId ?? $request->session_id;
        $userId = $request->userId ?? $request->user_id ?? Auth::id();

        $session = $this->trackingService->createSession(
            $sessionId,
            $userId,
            $request->ip(),
            $request->userAgent ?? $request->user_agent ?? $request->header('User-Agent', 'Unknown'),
            $request->device,
            $request->browser,
            $request->os,
            $request->referrer,
            $request->landingPage ?? $request->landing_page,
            $request->source,
            $request->utmSource ?? $request->utm_source,
            $request->utmMedium ?? $request->utm_medium,
            $request->utmCampaign ?? $request->utm_campaign,
            $request->utmTerm ?? $request->utm_term,
            $request->utmContent ?? $request->utm_content
        );
        return response()->json(['success' => true, 'data' => $session]);
    }

    public function endSession(Request $request, string $sessionId): JsonResponse
    {
        $this->trackingService->endSession($sessionId);
        return response()->json(['success' => true, 'message' => 'Session ended']);
    }

    /**
     * Record a tracking event.
     * Accepts both camelCase (frontend) and snake_case field names.
     * Stores rich event data (event_name, category, label, value, url, metadata) as separate columns.
     */
    public function recordEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required_without:sessionId|string',
            'sessionId' => 'required_without:session_id|string',
            'event_type' => 'required_without:eventType|string',
            'eventType' => 'required_without:event_type|string',
        ]);

        $sessionId = $request->sessionId ?? $request->session_id;
        $eventType = $request->eventType ?? $request->event_type;
        $userId = $request->userId ?? $request->user_id ?? Auth::id();

        // If rich event_data JSON string is provided, parse it for individual fields
        $eventData = $request->event_data ?? $request->eventData;
        $parsedData = null;
        if ($eventData && is_string($eventData)) {
            $decoded = json_decode($eventData, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $parsedData = $decoded;
            }
        }

        $event = $this->trackingService->recordEvent(
            $sessionId,
            $eventType,
            $parsedData['eventName'] ?? $request->event_name ?? $parsedData['eventName'] ?? null,
            $parsedData['category'] ?? $request->category ?? null,
            $parsedData['label'] ?? $request->label ?? null,
            $parsedData['value'] ?? $request->value ?? null,
            $parsedData['url'] ?? $request->url ?? null,
            $parsedData['metadata'] ?? $request->metadata ? (is_array($request->metadata) ? json_encode($request->metadata) : $request->metadata) : null,
            $userId
        );
        return response()->json(['success' => true, 'data' => $event], 201);
    }

    public function adminPageViews(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->trackingService->getPageViewStats()]);
    }

    public function pageViewStats(): JsonResponse
    {
        return $this->adminPageViews();
    }

    public function activeSessions(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->trackingService->getActiveSessions()]);
    }

    public function sessionStats(): JsonResponse
    {
        return $this->activeSessions();
    }

    public function dashboard(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [
            'page_views' => $this->trackingService->getPageViewStats(),
            'active_sessions' => $this->trackingService->getActiveSessions(),
            'top_pages' => $this->trackingService->getTopPages(),
        ]]);
    }

    // ── Admin Event & Journey Methods ──

    public function getEvents(Request $request): JsonResponse
    {
        $page = $request->page ?? 1;
        $limit = $request->limit ?? 20;
        return response()->json(['success' => true, 'data' => $this->trackingService->getEvents((int)$page, (int)$limit)]);
    }

    public function getEventStats(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->trackingService->getEventStats()]);
    }

    public function trafficSources(Request $request): JsonResponse
    {
        $dateRange = $request->query('dateRange', 'all');
        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');
        $sources = $this->trackingService->getTrafficSourceStats($dateRange, $startDate, $endDate);
        $utmStats = $this->trackingService->getUtmCampaignStats($dateRange, $startDate, $endDate);
        return response()->json(['success' => true, 'data' => [
            'sources' => $sources,
            'utm_campaigns' => $utmStats,
        ]]);
    }

    public function getUserJourney(string $userId): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->trackingService->getUserJourney($userId)]);
    }
}
