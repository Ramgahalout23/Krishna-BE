<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TranslationService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TranslationController extends Controller
{
    public function __construct(protected TranslationService $translationService) {}

    /**
     * Public: Get translations for a language.
     */
    public function index(Request $request): JsonResponse
    {
        $lang = $request->query('lang', 'en');
        $group = $request->query('group', 'frontend');
        return response()->json([
            'success' => true,
            'data' => $this->translationService->getTranslations($lang, $group),
        ]);
    }

    /**
     * Public: Get supported languages.
     */
    public function languages(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->translationService->getLanguages()]);
    }

    /**
     * Admin: Get all languages.
     */
    public function adminLanguages(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->translationService->getAllLanguagesAdmin()]);
    }

    /**
     * Admin: Create/update language.
     */
    public function storeLanguage(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string|max:5',
                'name' => 'required|string',
                'native_name' => 'nullable|string',
                'is_default' => 'nullable|boolean',
                'is_active' => 'nullable|boolean',
            ]);

            $lang = $this->translationService->upsertLanguage($validated);
            return response()->json(['success' => true, 'message' => 'Language saved', 'data' => $lang], 201);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Admin: Delete language.
     */
    public function destroyLanguage(string $id): JsonResponse
    {
        try {
            $this->translationService->deleteLanguage($id);
            return response()->json(['success' => true, 'message' => 'Language deleted']);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Admin: Bulk update translations.
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'language_code' => 'required|string|max:5',
                'group' => 'required|string',
                'translations' => 'required|array',
            ]);

            $this->translationService->bulkSetTranslations(
                $validated['language_code'],
                $validated['group'],
                $validated['translations']
            );

            return response()->json(['success' => true, 'message' => 'Translations updated']);
        } catch (AppError $e) { return $e->render(); }
    }
}
