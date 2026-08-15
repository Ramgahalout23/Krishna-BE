<?php

namespace App\Services;

use App\Exceptions\AppError;
use App\Jobs\ProcessCampaignJob;
use App\Repositories\MarketingRepository;
use Illuminate\Support\Facades\Log;

class MarketingService
{
    public function __construct(
        protected MarketingRepository $marketingRepository,
        protected EmailService $emailService
    ) {
    }

    /**
     * The list of expected/internal field names for subscriber import.
     */
    public function getSubscriberExpectedFields(): array
    {
        return ['email', 'name', 'phone', 'tags', 'source'];
    }

    /**
     * Build a suggested automatic column mapping for subscriber CSV headers.
     */
    public function suggestSubscriberMapping(array $csvHeaders): array
    {
        $expected = $this->getSubscriberExpectedFields();
        $mapping = [];
        foreach ($csvHeaders as $h) {
            $lower = strtolower(trim($h));
            foreach ($expected as $field) {
                if ($lower === $field) {
                    $mapping[$h] = $field;
                    break;
                }
                // Fuzzy: contains the word or vice versa
                if (str_contains($lower, $field) || str_contains($field, $lower)) {
                    $mapping[$h] = $field;
                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * Remap CSV header row according to a column mapping for subscriber CSVs.
     */
    public function remapSubscriberCSVHeaders(string $csvContent, array $columnMapping): string
    {
        $lines = explode("\n", $csvContent);
        if (empty($lines)) {
            return $csvContent;
        }
        $headers = explode(',', $lines[0]);
        $remapped = [];
        foreach ($headers as $h) {
            $trimmed = trim($h);
            $remapped[] = isset($columnMapping[$trimmed]) ? $columnMapping[$trimmed] : $trimmed;
        }
        $lines[0] = implode(',', $remapped);

        return implode("\n", $lines);
    }

    // ── Subscriber Management ──

    public function getSubscribers(array $params = []): array
    {
        return $this->marketingRepository->findAllSubscribers($params);
    }

    public function getSubscriberById(string $id): array
    {
        $subscriber = $this->marketingRepository->findSubscriberById($id);
        if (! $subscriber) {
            throw AppError::notFound('Subscriber not found');
        }

        return $subscriber->toArray();
    }

    public function createSubscriber(array $data): array
    {
        $existing = $this->marketingRepository->findSubscriberByEmail($data['email']);
        if ($existing) {
            if ($existing->status === 'UNSUBSCRIBED') {
                $updated = $this->marketingRepository->updateSubscriber($existing->id, [
                    'status' => 'ACTIVE',
                    'name' => $data['name'] ?? $existing->name,
                    'tags' => $data['tags'] ?? $existing->tags,
                ]);

                return $updated->toArray();
            }
            if ($existing->status === 'ACTIVE') {
                return $existing->toArray();
            }
            throw AppError::validation('This email cannot be subscribed');
        }

        return $this->marketingRepository->createSubscriber($data)->toArray();
    }

    public function updateSubscriber(string $id, array $data): array
    {
        $subscriber = $this->marketingRepository->findSubscriberById($id);
        if (! $subscriber) {
            throw AppError::notFound('Subscriber not found');
        }

        return $this->marketingRepository->updateSubscriber($id, $data)->toArray();
    }

    public function deleteSubscriber(string $id): array
    {
        $subscriber = $this->marketingRepository->findSubscriberById($id);
        if (! $subscriber) {
            throw AppError::notFound('Subscriber not found');
        }
        $this->marketingRepository->deleteSubscriber($id);

        return ['deleted' => true];
    }

    public function unsubscribeByEmail(string $email): array
    {
        $subscriber = $this->marketingRepository->findSubscriberByEmail($email);
        if (! $subscriber) {
            return ['unsubscribed' => true];
        }

        if ($subscriber->status === 'UNSUBSCRIBED') {
            return ['unsubscribed' => true, 'already_unsubscribed' => true];
        }

        $this->marketingRepository->updateSubscriber($subscriber->id, ['status' => 'UNSUBSCRIBED']);

        return ['unsubscribed' => true];
    }

    public function getSubscriberStats(): array
    {
        return $this->marketingRepository->getSubscriberStats();
    }

    // ── Campaign Management ──

    public function getCampaigns(array $params = []): array
    {
        return $this->marketingRepository->findAllCampaigns($params);
    }

    public function getCampaignById(string $id): array
    {
        $campaign = $this->marketingRepository->findCampaignById($id);
        if (! $campaign) {
            throw AppError::notFound('Campaign not found');
        }

        return $campaign->toArray();
    }

    public function createCampaign(array $data): array
    {
        return $this->marketingRepository->createCampaign($data)->toArray();
    }

    public function updateCampaign(string $id, array $data): array
    {
        $campaign = $this->marketingRepository->findCampaignById($id);
        if (! $campaign) {
            throw AppError::notFound('Campaign not found');
        }

        // Status guards — matches TypeScript logic
        if (in_array($campaign->status, ['SENT', 'SENDING'])) {
            throw AppError::validation('Cannot edit a campaign that has been sent or is sending');
        }

        return $this->marketingRepository->updateCampaign($id, $data)->toArray();
    }

    public function deleteCampaign(string $id): array
    {
        $campaign = $this->marketingRepository->findCampaignById($id);
        if (! $campaign) {
            throw AppError::notFound('Campaign not found');
        }

        // Status guard — matches TypeScript logic
        if ($campaign->status === 'SENDING') {
            throw AppError::validation('Cannot delete a campaign that is currently sending');
        }

        $this->marketingRepository->deleteCampaign($id);

        return ['deleted' => true];
    }

    public function duplicateCampaign(string $campaignId): array
    {
        $source = $this->marketingRepository->findCampaignById($campaignId);
        if (! $source) {
            throw AppError::notFound('Campaign not found');
        }

        return $this->marketingRepository->createCampaign([
            'name' => $source->name.' (Copy)',
            'subject' => $source->subject,
            'preheader' => $source->preheader,
            'from_name' => $source->from_name,
            'from_email' => $source->from_email,
            'content_html' => $source->content_html,
            'content_text' => $source->content_text,
            'type' => $source->type ?: 'EMAIL',
        ])->toArray();
    }

    // ── Send Campaign (Batch Processing — matches TS implementation) ──

    public function sendCampaign(string $campaignId, ?string $testEmail = null): array
    {
        $campaign = $this->marketingRepository->findCampaignById($campaignId);
        if (! $campaign) {
            throw AppError::notFound('Campaign not found');
        }

        if ($campaign->status === 'SENT') {
            throw AppError::validation('Campaign has already been sent');
        }

        if ($testEmail) {
            // Send test email to a single address
            $this->sendSingleCampaignEmail($campaign, $testEmail);

            return ['sent' => true, 'type' => 'test', 'email' => $testEmail];
        }

        // Get all active subscribers
        $subscribersCount = $this->marketingRepository->getActiveSubscribersCount();
        if ($subscribersCount === 0) {
            throw AppError::validation('No active subscribers to send to');
        }

        // Mark campaign as sending
        $this->marketingRepository->updateCampaign($campaignId, ['status' => 'SENDING']);

        // Create recipient records for all active subscribers in batches
        $page = 1;
        $pageSize = 500;
        $totalCreated = 0;

        while (true) {
            $subscribers = $this->marketingRepository->findAllSubscribers([
                'page' => $page,
                'limit' => $pageSize,
                'status' => 'ACTIVE',
            ]);

            if (empty($subscribers['items'])) {
                break;
            }

            $subscriberIds = array_column($subscribers['items'], 'id');
            $created = $this->marketingRepository->createCampaignRecipients($campaignId, $subscriberIds);
            $totalCreated += $created;
            $page++;
        }

        // Dispatch campaign processing to the queue for async handling
        ProcessCampaignJob::dispatch($campaignId);

        return [
            'sent' => false,
            'type' => 'batch',
            'total_recipients' => $totalCreated,
            'message' => "Campaign queued for {$totalCreated} recipients.",
        ];
    }

    private function sendSingleCampaignEmail($campaign, string $toEmail): bool
    {
        try {
            $unsubscribeUrl = url('/unsubscribe?email='.urlencode($toEmail));

            // Append unsubscribe footer (matches TS behavior)
            $htmlWithFooter = $campaign->content_html."
                <div style=\"margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #999; text-align: center;\">
                    <p>You're receiving this because you're subscribed to our newsletter.</p>
                    <p><a href=\"{$unsubscribeUrl}\" style=\"color: #999; text-decoration: underline;\">Unsubscribe</a></p>
                </div>";

            return $this->emailService->sendEmail($toEmail, $campaign->subject, $htmlWithFooter, $campaign->content_text);
        } catch (\Exception $e) {
            Log::error("Failed to send campaign email to {$toEmail}", ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function getCampaignStats(string $campaignId): array
    {
        $campaign = $this->marketingRepository->findCampaignById($campaignId);
        if (! $campaign) {
            throw AppError::notFound('Campaign not found');
        }

        return $this->marketingRepository->updateCampaignStats($campaignId);
    }

    public function getCampaignRecipients(string $campaignId, int $page = 1, int $limit = 50): array
    {
        $campaign = $this->marketingRepository->findCampaignById($campaignId);
        if (! $campaign) {
            throw AppError::notFound('Campaign not found');
        }

        return $this->marketingRepository->getCampaignRecipients($campaignId, $page, $limit);
    }

    // ── CSV Export ──

    public function exportSubscribersCSV(): string
    {
        $subscribers = $this->marketingRepository->findAllSubscribers([
            'page' => 1,
            'limit' => 1000000,
        ]);

        $headers = ['Email', 'Name', 'Status', 'Source', 'Tags', 'Joined', 'Unsubscribed At'];
        $rows = array_map(function ($s) {
            return [
                $this->escapeCSV($s['email']),
                $this->escapeCSV($s['name'] ?? ''),
                $s['status'] ?? '',
                $s['source'] ?? '',
                $this->escapeCSV($s['tags'] ?? ''),
                isset($s['created_at']) ? $s['created_at'] : '',
                isset($s['unsubscribed_at']) ? $s['unsubscribed_at'] : '',
            ];
        }, $subscribers['items']);

        $csvRows = array_merge([implode(',', $headers)], array_map(fn ($r) => implode(',', $r), $rows));

        return implode("\n", $csvRows);
    }

    public function exportCampaignRecipientsCSV(string $campaignId): string
    {
        $campaign = $this->marketingRepository->findCampaignById($campaignId);
        if (! $campaign) {
            throw AppError::notFound('Campaign not found');
        }

        $recipients = $this->marketingRepository->getCampaignRecipients($campaignId, 1, 1000000);

        $headers = ['Email', 'Name', 'Status', 'Sent At', 'Opened At', 'Clicked At', 'Error Message'];
        $rows = array_map(function ($r) {
            $subscriber = $r['subscriber'] ?? [];

            return [
                $this->escapeCSV($subscriber['email'] ?? ''),
                $this->escapeCSV($subscriber['name'] ?? ''),
                $r['status'] ?? '',
                $r['sent_at'] ?? '',
                $r['opened_at'] ?? '',
                $r['clicked_at'] ?? '',
                $this->escapeCSV($r['error_message'] ?? ''),
            ];
        }, $recipients['items']);

        $csvRows = array_merge([implode(',', $headers)], array_map(fn ($r) => implode(',', $r), $rows));

        return implode("\n", $csvRows);
    }

    private function escapeCSV(string $val): string
    {
        if (str_contains($val, ',') || str_contains($val, '"') || str_contains($val, "\n")) {
            return '"'.str_replace('"', '""', $val).'"';
        }

        return $val;
    }

    // ── CSV Import ──

    /**
     * Preview a subscriber CSV import — parse and validate WITHOUT persisting.
     * Accepts optional column_mapping to remap CSV headers before parsing.
     */
    public function previewImportSubscribers(string $csvContent, array $columnMapping = []): array
    {
        // Apply column remapping if provided
        if (! empty($columnMapping)) {
            $csvContent = $this->remapSubscriberCSVHeaders($csvContent, $columnMapping);
        }

        $lines = array_filter(explode("\n", str_replace("\r\n", "\n", $csvContent)), fn ($l) => trim($l) !== '');
        if (count($lines) < 2) {
            throw AppError::validation('CSV must have a header row and at least one data row');
        }

        $headers = array_map('trim', explode(',', strtolower(array_shift($lines))));
        $emailIdx = array_search('email', $headers);
        $nameIdx = array_search('name', $headers);
        $phoneIdx = array_search('phone', $headers);
        $tagsIdx = array_search('tags', $headers);
        $sourceIdx = array_search('source', $headers);

        if ($emailIdx === false) {
            throw AppError::validation('CSV must contain an "email" column');
        }

        $previewRows = [];
        $validCount = 0;
        $warningCount = 0;
        $errorCount = 0;

        foreach ($lines as $i => $line) {
            $rowNum = $i + 2;
            $cols = array_map(fn ($c) => trim(str_replace(['"', "'"], '', $c)), explode(',', $line));
            $email = isset($cols[$emailIdx]) ? strtolower($cols[$emailIdx]) : '';
            $issues = [];

            $name = $nameIdx !== false ? ($cols[$nameIdx] ?? null) : null;
            $phone = $phoneIdx !== false ? ($cols[$phoneIdx] ?? null) : null;
            $tags = $tagsIdx !== false ? ($cols[$tagsIdx] ?? null) : null;
            $rawSource = $sourceIdx !== false ? strtoupper($cols[$sourceIdx] ?? '') : '';
            $validSources = ['SIGNUP', 'IMPORT', 'ADMIN', 'CHECKOUT'];
            $source = ($rawSource && in_array($rawSource, $validSources)) ? $rawSource : 'IMPORT';

            // Validate email
            if (! $email || ! str_contains($email, '@')) {
                $issues[] = ['type' => 'error', 'message' => 'Invalid or missing email'];
            } else {
                // Check for existing subscriber
                $existing = $this->marketingRepository->findSubscriberByEmail($email);
                if ($existing) {
                    $issues[] = ['type' => 'warning', 'message' => "Subscriber already exists (status: {$existing->status})"];
                }
            }

            // Determine row status
            $rowStatus = 'valid';
            foreach ($issues as $issue) {
                if ($issue['type'] === 'error') {
                    $rowStatus = 'error';
                    break;
                }
                if ($issue['type'] === 'warning') {
                    $rowStatus = 'warning';
                }
            }

            if ($rowStatus === 'valid') {
                $validCount++;
            } elseif ($rowStatus === 'warning') {
                $warningCount++;
            } else {
                $errorCount++;
            }

            $previewRows[] = [
                'row_number' => $rowNum,
                'status' => $rowStatus,
                'issues' => $issues,
                'data' => [
                    'email' => $email,
                    'name' => $name ?? '',
                    'phone' => $phone ?? '',
                    'tags' => $tags ?? '',
                    'source' => $source,
                ],
            ];
        }

        // Build suggested mapping from the ORIGINAL header line (not $lines which is now data rows)
        $originalHeaders = array_map('trim', explode(',', $originalHeaderLine));
        $suggestedMapping = $this->suggestSubscriberMapping($originalHeaders);

        return [
            'headers' => $headers,
            'rows' => $previewRows,
            'total_rows' => count($lines),
            'suggested_mapping' => $suggestedMapping,
            'validation' => [
                'valid' => $validCount,
                'warnings' => $warningCount,
                'errors' => $errorCount,
            ],
        ];
    }

    public function importSubscribersFromCSV(string $csvContent, array $options = []): array
    {
        // Apply column remapping if provided
        if (! empty($options['column_mapping'])) {
            $csvContent = $this->remapSubscriberCSVHeaders($csvContent, $options['column_mapping']);
        }

        $lines = array_filter(explode("\n", str_replace("\r\n", "\n", $csvContent)), fn ($l) => trim($l) !== '');
        if (count($lines) < 2) {
            throw AppError::validation('CSV must have a header row and at least one data row');
        }

        $headers = array_map('trim', explode(',', strtolower(array_shift($lines))));
        $emailIdx = array_search('email', $headers);
        $nameIdx = array_search('name', $headers);
        $phoneIdx = array_search('phone', $headers);
        $tagsIdx = array_search('tags', $headers);
        $sourceIdx = array_search('source', $headers);

        if ($emailIdx === false) {
            throw AppError::validation('CSV must contain an "email" column');
        }

        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $errorRows = [];

        foreach ($lines as $i => $line) {
            $cols = array_map(fn ($c) => trim(str_replace(['"', "'"], '', $c)), explode(',', $line));
            $email = isset($cols[$emailIdx]) ? strtolower($cols[$emailIdx]) : '';

            if (! $email || ! str_contains($email, '@')) {
                $errorRows[] = ['row' => $i + 2, 'reason' => 'Invalid email'];
                $errors++;

                continue;
            }

            $name = $nameIdx !== false ? ($cols[$nameIdx] ?? null) : null;
            $phone = $phoneIdx !== false ? ($cols[$phoneIdx] ?? null) : null;
            $tags = $tagsIdx !== false ? ($cols[$tagsIdx] ?? null) : null;
            $rawSource = $sourceIdx !== false ? strtoupper($cols[$sourceIdx] ?? '') : '';
            $validSources = ['SIGNUP', 'IMPORT', 'ADMIN', 'CHECKOUT'];
            $source = ($rawSource && in_array($rawSource, $validSources)) ? $rawSource : ($options['default_source'] ?? 'IMPORT');

            try {
                $existing = $this->marketingRepository->findSubscriberByEmail($email);
                if ($existing) {
                    $skipDuplicates = $options['skip_duplicates'] ?? true;
                    if ($skipDuplicates !== false) {
                        $skipped++;

                        continue;
                    }
                    $this->marketingRepository->updateSubscriber($existing->id, [
                        'name' => $name ?: $existing->name,
                        'tags' => $tags ?: $existing->tags,
                    ]);
                    $imported++;
                } else {
                    $this->marketingRepository->createSubscriber([
                        'email' => $email,
                        'name' => $name,
                        'source' => $source,
                        'tags' => $tags,
                        'metadata' => $phone ? json_encode(['phone' => $phone]) : null,
                    ]);
                    $imported++;
                }
            } catch (\Exception $e) {
                $errorRows[] = ['row' => $i + 2, 'reason' => $e->getMessage() ?: 'Unknown error'];
                $errors++;
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'error_rows' => $errorRows,
            'total' => count($lines),
        ];
    }

    // ── Campaign from Template ──

    public function createCampaignFromTemplate(array $data): array
    {
        $template = \App\Models\CampaignTemplate::findOrFail($data['template_id']);

        return $this->marketingRepository->createCampaign([
            'name' => $data['name'],
            'subject' => $data['subject'],
            'content_html' => $template->content_html ?? '',
            'content_text' => null,
            'type' => 'EMAIL',
            'created_by' => $data['created_by'] ?? $data['createdBy'] ?? null,
        ])->toArray();
    }

    // ── Dashboard Stats ──

    public function getMarketingDashboardStats(): array
    {
        $subscriberStats = $this->marketingRepository->getSubscriberStats();
        $campaigns = $this->marketingRepository->findAllCampaigns([
            'page' => 1,
            'limit' => 5,
        ]);

        $totalCampaigns = $campaigns['total'];
        $recentCampaigns = $campaigns['items'];

        $totalSent = 0;
        $totalOpened = 0;
        $totalClicked = 0;

        foreach ($recentCampaigns as $c) {
            $totalSent += $c['sent_count'] ?? 0;
            $totalOpened += $c['opened_count'] ?? 0;
            $totalClicked += $c['clicked_count'] ?? 0;
        }

        $openRate = $totalSent > 0 ? round(($totalOpened / $totalSent) * 100) : 0;
        $clickRate = $totalOpened > 0 ? round(($totalClicked / $totalOpened) * 100) : 0;

        return [
            'subscribers' => $subscriberStats,
            'campaigns' => [
                'total' => $totalCampaigns,
                'recent' => $recentCampaigns,
            ],
            'engagement' => [
                'total_sent' => $totalSent,
                'total_opened' => $totalOpened,
                'total_clicked' => $totalClicked,
                'open_rate' => $openRate,
                'click_rate' => $clickRate,
            ],
        ];
    }
}
