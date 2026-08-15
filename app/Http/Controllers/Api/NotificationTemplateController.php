<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exceptions\AppError;
use App\Services\NotificationTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationTemplateController extends Controller
{
    public function __construct(
        protected NotificationTemplateService $notificationTemplateService
    ) {}

    public function listTemplates(): JsonResponse
    {
        $templates = $this->notificationTemplateService->getAllTemplates();
        return response()->json(['success' => true, 'data' => $templates]);
    }

    public function getTemplate(string $id): JsonResponse
    {
        $template = $this->notificationTemplateService->getTemplate($id);
        if (!$template) {
            return response()->json(['success' => false, 'message' => 'Template not found'], 404);
        }
        return response()->json(['success' => true, 'data' => $template]);
    }

    public function updateTemplate(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'mode' => 'nullable|in:DEFAULT,CUSTOM',
                'active' => 'nullable|boolean',
                'template' => 'nullable|string',
                'title' => 'nullable|string',
                'message' => 'nullable|string',
            ]);

            $template = $this->notificationTemplateService->updateTemplate($id, $validated);
            return response()->json(['success' => true, 'message' => 'Template updated', 'data' => $template]);
        } catch (AppError $e) {
            return $e->render();
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function toggleTemplate(string $id): JsonResponse
    {
        try {
            $result = $this->notificationTemplateService->toggleTemplate($id);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function previewTemplate(string $id, Request $request): JsonResponse
    {
        try {
            $sampleData = $request->input('data', []);
            $rendered = $this->notificationTemplateService->renderTemplate($id, $sampleData);
            return response()->json(['success' => true, 'data' => $rendered]);
        } catch (AppError $e) {
            return $e->render();
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
