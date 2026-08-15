<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\DatabaseSeedJob;
use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class UtilityController extends Controller
{
    public function __construct(
        protected EmailService $emailService
    ) {}

    public function emailPreview(): JsonResponse
    {
        try {
            $html = $this->emailService->generatePreviewHtml();
            return response()->json(['success' => true, 'data' => ['html' => $html]]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function emailTest(Request $request): JsonResponse
    {
        $validated = $request->validate(['to' => 'required|email']);
        $sent = $this->emailService->sendEmail(
            $validated['to'],
            'Test Email from THREVOLT',
            '<h1>Test Email</h1><p>This is a test email from THREVOLT admin panel.</p>'
        );
        if ($sent) {
            return response()->json(['success' => true, 'message' => 'Test email sent to ' . $validated['to']]);
        }
        return response()->json(['success' => false, 'message' => 'Failed to send test email'], 500);
    }

    public function uploadFile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:jpeg,png,jpg,gif,webp,svg,pdf,doc,docx,xls,xlsx,csv,txt,zip|max:10240',
        ]);
        $file = $validated['file'];
        $result = app(\App\Services\StorageDriverService::class)->storeFile($file, 'uploads');
        return response()->json([
            'success' => true,
            'data' => [
                'url' => $result['url'],
                'path' => $result['path'],
                'filename' => $file->getClientOriginalName(),
                'mimetype' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]
        ]);
    }

    public function uploadMultiple(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'files' => 'required|array',
            'files.*' => 'required|file|mimes:jpeg,png,jpg,gif,webp,svg,pdf,doc,docx,xls,xlsx,csv,txt,zip|max:10240',
        ]);
        $results = app(\App\Services\StorageDriverService::class)->storeFiles($validated['files'], 'uploads');
        $uploaded = [];
        foreach ($results as $i => $result) {
            $file = $request->file('files')[$i];
            $uploaded[] = [
                'url' => $result['url'],
                'path' => $result['path'],
                'filename' => $file->getClientOriginalName(),
                'mimetype' => $file->getMimeType(),
                'size' => $file->getSize(),
            ];
        }
        return response()->json(['success' => true, 'data' => ['files' => $uploaded]]);
    }

    public function cacheClear(): JsonResponse
    {
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');

        // Clear translations cache
        \Illuminate\Support\Facades\Cache::forget('app_init');
        \Illuminate\Support\Facades\Cache::forget('translations_en_frontend');
        \Illuminate\Support\Facades\Cache::forget('translations_fr_frontend');
        \Illuminate\Support\Facades\Cache::forget('translations_hi_frontend');
        \Illuminate\Support\Facades\Cache::forget('languages_active');
        \Illuminate\Support\Facades\Cache::forget('language_default');
        \Illuminate\Support\Facades\Cache::forget('languages_admin');

        return response()->json(['success' => true, 'message' => 'All caches cleared successfully']);
    }

    public function databaseSeed(Request $request): JsonResponse
    {
        $validated = $request->validate(['confirm' => 'required|boolean']);
        if (!$validated['confirm']) {
            return response()->json(['success' => false, 'message' => 'Confirmation required'], 400);
        }
        DatabaseSeedJob::dispatch();
        return response()->json(['success' => true, 'message' => 'Database seed queued for processing']);
    }
}
