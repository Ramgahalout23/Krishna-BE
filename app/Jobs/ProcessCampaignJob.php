<?php

namespace App\Jobs;

use App\Repositories\MarketingRepository;
use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30; // seconds between retries
    public int $timeout = 600; // 10 minutes — campaigns can be large

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected string $campaignId
    ) {}

    /**
     * Execute the job.
     * Processes all pending recipients for a campaign,
     * sending each one an email via EmailService.
     */
    public function handle(MarketingRepository $marketingRepository, EmailService $emailService): void
    {
        $campaign = $marketingRepository->findCampaignById($this->campaignId);
        if (!$campaign) {
            Log::warning("[ProcessCampaignJob] Campaign {$this->campaignId} not found");
            return;
        }

        Log::info("[ProcessCampaignJob] Starting campaign {$this->campaignId}: {$campaign->subject}");

        try {
            $page = 1;
            $pageSize = 100;
            $totalSent = 0;
            $totalFailed = 0;

            while (true) {
                $recipients = $marketingRepository->getCampaignRecipients($this->campaignId, $page, $pageSize);
                if (empty($recipients['items'])) break;

                foreach ($recipients['items'] as $recipient) {
                    try {
                        $reloadedCampaign = $marketingRepository->findCampaignById($this->campaignId);
                        if (!$reloadedCampaign) break 2;

                        $subscriberEmail = $recipient['subscriber']['email'] ?? null;
                        if (!$subscriberEmail) {
                            $totalFailed++;
                            continue;
                        }

                        $unsubscribeUrl = url('/unsubscribe?email=' . urlencode($subscriberEmail));

                        // Append unsubscribe footer (matches MarketingService logic)
                        $htmlWithFooter = $reloadedCampaign->content_html . "\n                <div style=\"margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #999; text-align: center;\">\n                    <p>You're receiving this because you're subscribed to our newsletter.</p>\n                    <p><a href=\"{$unsubscribeUrl}\" style=\"color: #999; text-decoration: underline;\">Unsubscribe</a></p>\n                </div>";

                        $success = $emailService->sendEmail(
                            $subscriberEmail,
                            $reloadedCampaign->subject,
                            $htmlWithFooter,
                            $reloadedCampaign->content_text
                        );

                        if ($success) {
                            $marketingRepository->updateRecipientStatus($recipient['id'], 'SENT', [
                                'sent_at' => now(),
                            ]);
                            $totalSent++;
                        } else {
                            $marketingRepository->updateRecipientStatus($recipient['id'], 'FAILED', [
                                'error_message' => 'Email send failed',
                            ]);
                            $totalFailed++;
                        }
                    } catch (\Exception $e) {
                        $marketingRepository->updateRecipientStatus($recipient['id'], 'FAILED', [
                            'error_message' => $e->getMessage() ?: 'Unknown error',
                        ]);
                        $totalFailed++;
                    }
                }
                $page++;
            }

            // Update campaign stats & final status
            $marketingRepository->updateCampaignStats($this->campaignId);
            $status = $totalSent > 0 ? 'SENT' : 'FAILED';
            $marketingRepository->updateCampaign($this->campaignId, ['status' => $status]);
            if ($status === 'SENT') {
                $marketingRepository->markCampaignAsSent($this->campaignId);
            }

            Log::info("[ProcessCampaignJob] Campaign {$this->campaignId} completed: {$totalSent} sent, {$totalFailed} failed");
        } catch (\Exception $error) {
            Log::error("[ProcessCampaignJob] Campaign processing error for {$this->campaignId}", [
                'error' => $error->getMessage(),
            ]);
            $marketingRepository->updateCampaign($this->campaignId, ['status' => 'FAILED']);
            throw $error; // Re-throw so the queue worker retries
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("[ProcessCampaignJob] Permanently failed after {$this->tries} attempts for campaign {$this->campaignId}: {$exception->getMessage()}");
    }
}
