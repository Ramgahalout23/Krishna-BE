<?php

namespace App\Repositories;

use App\Models\Subscriber;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Illuminate\Support\Facades\DB;

class MarketingRepository
{
    // ── Subscribers ──

    public function findAllSubscribers(array $params = []): array
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 20;

        $query = Subscriber::query();

        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }
        if (!empty($params['source'])) {
            $query->where('source', $params['source']);
        }
        if (!empty($params['search'])) {
            $search = $params['search'];
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $paginator = $query->latest()->paginate($limit, ['*'], 'page', $page);

        return [
            'items' => $paginator->items(),
            'page' => $paginator->currentPage(),
            'limit' => $paginator->perPage(),
            'total' => $paginator->total(),
            'total_pages' => $paginator->lastPage(),
        ];
    }

    public function findSubscriberById(string $id): ?Subscriber
    {
        return Subscriber::withCount('campaignRecipients')->find($id);
    }

    public function findSubscriberByEmail(string $email): ?Subscriber
    {
        return Subscriber::where('email', $email)->first();
    }

    public function createSubscriber(array $data): Subscriber
    {
        return Subscriber::create([
            'email' => $data['email'],
            'name' => $data['name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'source' => $data['source'] ?? 'SIGNUP',
            'status' => $data['status'] ?? 'ACTIVE',
            'tags' => $data['tags'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    public function updateSubscriber(string $id, array $data): Subscriber
    {
        $subscriber = Subscriber::findOrFail($id);
        $updateData = [];

        if (array_key_exists('name', $data)) $updateData['name'] = $data['name'];
        if (array_key_exists('status', $data)) $updateData['status'] = $data['status'];
        if (array_key_exists('tags', $data)) $updateData['tags'] = $data['tags'];
        if (array_key_exists('source', $data)) $updateData['source'] = $data['source'];
        if (array_key_exists('phone', $data)) $updateData['phone'] = $data['phone'];
        if (array_key_exists('metadata', $data)) $updateData['metadata'] = $data['metadata'];

        if (($data['status'] ?? null) === 'UNSUBSCRIBED') {
            $updateData['unsubscribed_at'] = now();
        }

        $subscriber->update($updateData);
        return $subscriber->fresh();
    }

    public function deleteSubscriber(string $id): void
    {
        Subscriber::findOrFail($id)->delete();
    }

    public function getSubscriberStats(): array
    {
        $total = Subscriber::count();
        $active = Subscriber::where('status', 'ACTIVE')->count();
        $unsubscribed = Subscriber::where('status', 'UNSUBSCRIBED')->count();
        $bounced = Subscriber::where('status', 'BOUNCED')->count();
        $recentSignups = Subscriber::where('created_at', '>=', now()->subDays(30))->count();

        return [
            'total' => $total,
            'active' => $active,
            'subscribed' => $active,
            'unsubscribed' => $unsubscribed,
            'bounced' => $bounced,
            'recent_signups' => $recentSignups,
        ];
    }

    public function getActiveSubscribersCount(): int
    {
        return Subscriber::where('status', 'ACTIVE')->count();
    }

    // ── Campaigns ──

    public function findAllCampaigns(array $params = []): array
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 20;

        $query = Campaign::withCount('recipients');

        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }
        if (!empty($params['type'])) {
            $query->where('type', $params['type']);
        }
        if (!empty($params['search'])) {
            $search = $params['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        $paginator = $query->latest()->paginate($limit, ['*'], 'page', $page);

        return [
            'items' => $paginator->items(),
            'page' => $paginator->currentPage(),
            'limit' => $paginator->perPage(),
            'total' => $paginator->total(),
            'total_pages' => $paginator->lastPage(),
        ];
    }

    public function findCampaignById(string $id): ?Campaign
    {
        return Campaign::withCount('recipients')->find($id);
    }

    public function createCampaign(array $data): Campaign
    {
        $campaignData = [
            'name' => $data['name'],
            'subject' => $data['subject'],
            'preheader' => $data['preheader'] ?? null,
            'from_name' => $data['from_name'] ?? $data['fromName'] ?? null,
            'from_email' => $data['from_email'] ?? $data['fromEmail'] ?? null,
            'content_html' => $data['content_html'] ?? $data['contentHtml'] ?? '',
            'content_text' => $data['content_text'] ?? $data['contentText'] ?? null,
            'type' => $data['type'] ?? 'EMAIL',
            'status' => !empty($data['scheduled_at']) ? 'SCHEDULED' : 'DRAFT',
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'created_by' => $data['created_by'] ?? $data['createdBy'] ?? null,
        ];

        return Campaign::create($campaignData);
    }

    public function updateCampaign(string $id, array $data): Campaign
    {
        $campaign = Campaign::findOrFail($id);
        $updateData = [];

        foreach (['name', 'subject', 'preheader', 'from_name', 'from_email', 'content_html', 'content_text', 'type', 'status', 'scheduled_at', 'sent_at'] as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }
        // Also check camelCase versions
        if (array_key_exists('fromName', $data)) $updateData['from_name'] = $data['fromName'];
        if (array_key_exists('fromEmail', $data)) $updateData['from_email'] = $data['fromEmail'];
        if (array_key_exists('contentHtml', $data)) $updateData['content_html'] = $data['contentHtml'];
        if (array_key_exists('contentText', $data)) $updateData['content_text'] = $data['contentText'];
        if (array_key_exists('createdBy', $data)) $updateData['created_by'] = $data['createdBy'];
        if (array_key_exists('scheduledAt', $data)) $updateData['scheduled_at'] = $data['scheduledAt'];

        $campaign->update($updateData);
        return $campaign->fresh();
    }

    public function markCampaignAsSent(string $campaignId): void
    {
        Campaign::where('id', $campaignId)->update(['sent_at' => now()]);
    }

    public function deleteCampaign(string $id): void
    {
        Campaign::findOrFail($id)->delete();
    }

    // ── Campaign Recipients ──

    public function getCampaignRecipients(string $campaignId, int $page = 1, int $limit = 50): array
    {
        $query = CampaignRecipient::with(['subscriber' => fn($q) => $q->select('id', 'email', 'name')])
            ->where('campaign_id', $campaignId);

        $paginator = $query->latest()->paginate($limit, ['*'], 'page', $page);

        return [
            'items' => $paginator->items(),
            'page' => $paginator->currentPage(),
            'limit' => $paginator->perPage(),
            'total' => $paginator->total(),
            'total_pages' => $paginator->lastPage(),
        ];
    }

    public function createCampaignRecipients(string $campaignId, array $subscriberIds): int
    {
        $data = array_map(fn($id) => [
            'campaign_id' => $campaignId,
            'subscriber_id' => $id,
            'status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ], $subscriberIds);

        CampaignRecipient::insert($data);

        Campaign::where('id', $campaignId)->update([
            'total_recipients' => count($subscriberIds),
        ]);

        return count($subscriberIds);
    }

    public function updateRecipientStatus(string $recipientId, string $status, array $extra = []): void
    {
        $data = array_merge(['status' => $status], $extra);
        CampaignRecipient::where('id', $recipientId)->update($data);
    }

    public function getCampaignAggregatedStats(string $campaignId): array
    {
        $baseQuery = CampaignRecipient::where('campaign_id', $campaignId);

        return [
            'sent' => (clone $baseQuery)->where('status', 'SENT')->count(),
            'opened' => (clone $baseQuery)->whereNotNull('opened_at')->count(),
            'clicked' => (clone $baseQuery)->whereNotNull('clicked_at')->count(),
            'bounced' => (clone $baseQuery)->where('status', 'BOUNCED')->count(),
            'unsubscribed' => (clone $baseQuery)->where('status', 'UNSUBSCRIBED')->count(),
            'failed' => (clone $baseQuery)->where('status', 'FAILED')->count(),
            'complained' => (clone $baseQuery)->where('status', 'COMPLAINED')->count(),
        ];
    }

    public function updateCampaignStats(string $campaignId): array
    {
        $stats = $this->getCampaignAggregatedStats($campaignId);

        Campaign::where('id', $campaignId)->update([
            'sent_count' => $stats['sent'] + $stats['opened'] + $stats['clicked'],
            'opened_count' => $stats['opened'],
            'clicked_count' => $stats['clicked'],
            'bounced_count' => $stats['bounced'],
            'unsubscribed_count' => $stats['unsubscribed'],
            'complained_count' => $stats['complained'],
            'failed_count' => $stats['failed'],
        ]);

        return $stats;
    }

    public function findWhatsAppRecipients(array $params = []): array
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 20;

        $query = Subscriber::where('status', 'ACTIVE')->whereNotNull('phone');

        if (!empty($params['search'])) {
            $search = $params['search'];
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $paginator = $query->select('id', 'name', 'email', 'phone')
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
}
