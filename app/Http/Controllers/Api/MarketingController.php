<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AppError;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessSubscriberImportJob;
use App\Services\MarketingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MarketingController extends Controller
{
    public function __construct(
        protected MarketingService $marketingService
    ) {
    }

    // ── Public Routes ──

    public function publicSubscribe(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'nullable|email|max:255',
                'name' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
            ]);

            if (! ($validated['email'] ?? null) && ! ($validated['phone'] ?? null)) {
                return response()->json(['success' => false, 'message' => 'Email or phone number is required.'], 422);
            }

            $data = [
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'name' => $validated['name'] ?? null,
                'source' => ($validated['phone'] ?? null) ? 'PHONE_LEAD' : 'SIGNUP',
            ];

            $subscriber = $this->marketingService->createSubscriber($data);

            return response()->json(['success' => true, 'message' => 'Subscribed successfully', 'data' => $subscriber], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function publicUnsubscribe(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['email' => 'required|email']);
            $result = $this->marketingService->unsubscribeByEmail($validated['email']);

            return response()->json(['success' => true, 'message' => 'Unsubscribed successfully', 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // ── Admin Dashboard ──

    public function getDashboard(Request $request): JsonResponse
    {
        try {
            $stats = $this->marketingService->getMarketingDashboardStats();

            return response()->json(['success' => true, 'data' => $stats]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── Subscriber Routes ──

    public function getSubscribers(Request $request): JsonResponse
    {
        try {
            $result = $this->marketingService->getSubscribers([
                'page' => (int) $request->input('page', 1),
                'limit' => (int) $request->input('per_page', 20),
                'status' => $request->input('status'),
                'search' => $request->input('search'),
                'source' => $request->input('source'),
            ]);

            return response()->json([
                'success' => true,
                'data' => $result['items'],
                'meta' => [
                    'page' => $result['page'],
                    'limit' => $result['limit'],
                    'total' => $result['total'],
                    'total_pages' => $result['total_pages'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getSubscriberStats(Request $request): JsonResponse
    {
        try {
            $stats = $this->marketingService->getSubscriberStats();

            return response()->json(['success' => true, 'data' => $stats]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getSubscriberById(string $id): JsonResponse
    {
        try {
            $subscriber = $this->marketingService->getSubscriberById($id);

            return response()->json(['success' => true, 'data' => $subscriber]);
        } catch (AppError $e) {
            return $e->render();
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function createSubscriber(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|max:255',
                'name' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'source' => 'nullable|string|max:50',
                'tags' => 'nullable|string',
            ]);

            $subscriber = $this->marketingService->createSubscriber($validated);

            return response()->json(['success' => true, 'data' => $subscriber], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function updateSubscriber(Request $request, string $id): JsonResponse
    {
        try {
            $subscriber = $this->marketingService->updateSubscriber($id, $request->only(['name', 'phone', 'status', 'source', 'tags']));

            return response()->json(['success' => true, 'data' => $subscriber]);
        } catch (AppError $e) {
            return $e->render();
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteSubscriber(string $id): JsonResponse
    {
        try {
            $this->marketingService->deleteSubscriber($id);

            return response()->json(['success' => true, 'message' => 'Subscriber deleted']);
        } catch (AppError $e) {
            return $e->render();
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function exportSubscribersCSV(Request $request): JsonResponse
    {
        try {
            $csv = $this->marketingService->exportSubscribersCSV();

            return response()->json(['success' => true, 'data' => ['csv' => $csv, 'count' => substr_count($csv, "\n") - 1]]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function previewImportSubscribersCSV(Request $request): JsonResponse
    {
        try {
            if (! $request->hasFile('file')) {
                return response()->json(['success' => false, 'message' => 'CSV file required'], 422);
            }

            $csvContent = file_get_contents($request->file('file')->getRealPath());
            $columnMapping = $request->input('column_mapping');
            $mapping = $columnMapping ? (is_string($columnMapping) ? json_decode($columnMapping, true) : $columnMapping) : [];

            $preview = $this->marketingService->previewImportSubscribers($csvContent, $mapping ?: []);

            return response()->json([
                'success' => true,
                'data' => $preview,
            ]);
        } catch (AppError $e) {
            return $e->render();
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function importSubscribersCSV(Request $request): JsonResponse
    {
        try {
            if (! $request->hasFile('file')) {
                return response()->json(['success' => false, 'message' => 'CSV file required'], 422);
            }

            $csvContent = file_get_contents($request->file('file')->getRealPath());

            // Apply column mapping if provided — remap headers before saving
            $columnMapping = $request->input('column_mapping');
            $mapping = $columnMapping ? (is_string($columnMapping) ? json_decode($columnMapping, true) : $columnMapping) : [];
            if (! empty($mapping)) {
                $csvContent = $this->marketingService->remapSubscriberCSVHeaders($csvContent, $mapping);
            }

            $importId = ProcessSubscriberImportJob::generateImportId();
            $csvFilePath = "imports/subscribers/{$importId}.csv";
            Storage::disk('local')->put($csvFilePath, $csvContent);

            ProcessSubscriberImportJob::dispatch($csvFilePath, $importId);

            return response()->json([
                'success' => true,
                'message' => 'Import queued for processing.',
                'data' => [
                    'import_id' => $importId,
                    'status' => 'queued',
                ],
            ], 202);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Check the status of a subscriber import job.
     */
    public function subscriberImportStatus(string $importId): JsonResponse
    {
        $status = ProcessSubscriberImportJob::getStatus($importId);
        $result = in_array($status, ['completed', 'completed_with_errors', 'failed']) ? ProcessSubscriberImportJob::getResult($importId) : null;

        return response()->json([
            'success' => true,
            'data' => [
                'import_id' => $importId,
                'status' => $status,
                'result' => $result,
            ],
        ]);
    }

    // ── Campaign Routes ──

    public function getCampaigns(Request $request): JsonResponse
    {
        try {
            $result = $this->marketingService->getCampaigns([
                'page' => (int) $request->input('page', 1),
                'limit' => (int) $request->input('per_page', 20),
                'status' => $request->input('status'),
                'type' => $request->input('type'),
                'search' => $request->input('search'),
            ]);

            return response()->json([
                'success' => true,
                'data' => $result['items'],
                'meta' => [
                    'page' => $result['page'],
                    'limit' => $result['limit'],
                    'total' => $result['total'],
                    'total_pages' => $result['total_pages'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getCampaignById(string $id): JsonResponse
    {
        try {
            $campaign = $this->marketingService->getCampaignById($id);

            return response()->json(['success' => true, 'data' => $campaign]);
        } catch (AppError $e) {
            return $e->render();
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function createCampaign(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'subject' => 'required|string|max:255',
                'preheader' => 'nullable|string|max:255',
                'from_name' => 'nullable|string|max:255',
                'from_email' => 'nullable|email|max:255',
                'content_html' => 'required|string',
                'content_text' => 'nullable|string',
                'type' => 'nullable|string|max:50',
                'status' => 'nullable|in:DRAFT,SCHEDULED,SENT',
                'scheduled_at' => 'nullable|date',
            ]);

            $campaign = $this->marketingService->createCampaign($validated);

            return response()->json(['success' => true, 'data' => $campaign], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function updateCampaign(Request $request, string $id): JsonResponse
    {
        try {
            $campaign = $this->marketingService->updateCampaign($id, $request->only([
                'name', 'subject', 'preheader', 'from_name', 'from_email',
                'content_html', 'content_text', 'type', 'status', 'scheduled_at',
            ]));

            return response()->json(['success' => true, 'data' => $campaign]);
        } catch (AppError $e) {
            return $e->render();
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteCampaign(string $id): JsonResponse
    {
        try {
            $this->marketingService->deleteCampaign($id);

            return response()->json(['success' => true, 'message' => 'Campaign deleted']);
        } catch (AppError $e) {
            return $e->render();
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function cloneCampaign(string $id): JsonResponse
    {
        try {
            $campaign = $this->marketingService->duplicateCampaign($id);

            return response()->json(['success' => true, 'data' => $campaign], 201);
        } catch (AppError $e) {
            return $e->render();
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function sendCampaign(Request $request, string $id): JsonResponse
    {
        try {
            $result = $this->marketingService->sendCampaign($id, $request->input('test_email'));

            return response()->json(['success' => true, 'message' => 'Campaign send initiated', 'data' => $result]);
        } catch (AppError $e) {
            return $e->render();
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getCampaignStats(string $id): JsonResponse
    {
        try {
            $stats = $this->marketingService->getCampaignStats($id);

            return response()->json(['success' => true, 'data' => $stats]);
        } catch (AppError $e) {
            return $e->render();
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getCampaignRecipients(string $id, Request $request): JsonResponse
    {
        try {
            $result = $this->marketingService->getCampaignRecipients(
                $id,
                (int) $request->input('page', 1),
                (int) $request->input('per_page', 50),
            );

            return response()->json([
                'success' => true,
                'data' => $result['items'],
                'meta' => [
                    'page' => $result['page'],
                    'limit' => $result['limit'],
                    'total' => $result['total'],
                    'total_pages' => $result['total_pages'],
                ],
            ]);
        } catch (AppError $e) {
            return $e->render();
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function exportCampaignRecipientsCSV(string $id): JsonResponse
    {
        try {
            $csv = $this->marketingService->exportCampaignRecipientsCSV($id);

            return response()->json(['success' => true, 'data' => ['csv' => $csv, 'count' => substr_count($csv, "\n") - 1]]);
        } catch (AppError $e) {
            return $e->render();
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function createCampaignFromTemplate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'template_id' => 'required|string',
                'name' => 'required|string|max:255',
                'subject' => 'required|string|max:255',
            ]);

            $campaign = $this->marketingService->createCampaignFromTemplate($validated);

            return response()->json(['success' => true, 'data' => $campaign], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
