<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateAIContentJob;
use App\Services\AIGeneralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIController extends Controller
{
    protected AIGeneralService $aiGeneralService;

    public function __construct()
    {
        $this->aiGeneralService = new AIGeneralService();
    }

    /**
     * Generate product description using AI (async).
     */
    public function generateProductDescription(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_name' => 'required|string',
            'features' => 'nullable|string',
            'tone' => 'nullable|string',
            'category' => 'nullable|string',
            'price' => 'nullable|numeric',
            'keywords' => 'nullable|string',
            'target_audience' => 'nullable|string',
            'brand_name' => 'nullable|string',
        ]);

        $taskId = GenerateAIContentJob::generateTaskId();
        GenerateAIContentJob::dispatch('product_description', [
            'name' => $validated['product_name'],
            'features' => !empty($validated['features']) ? explode(',', $validated['features']) : [],
            'tone' => $validated['tone'] ?? 'professional',
            'category' => $validated['category'] ?? null,
            'price' => $validated['price'] ?? null,
            'keywords' => $validated['keywords'] ?? null,
            'target_audience' => $validated['target_audience'] ?? null,
            'brand_name' => $validated['brand_name'] ?? null,
        ], $taskId);

        return response()->json(['success' => true, 'message' => 'AI generation queued', 'data' => ['task_id' => $taskId, 'status' => 'queued']], 202);
    }

    /**
     * Generate short description using AI (async).
     */
    public function generateShortDescription(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_name' => 'required|string',
            'tone' => 'nullable|string',
            'category' => 'nullable|string',
        ]);

        $taskId = GenerateAIContentJob::generateTaskId();
        GenerateAIContentJob::dispatch('short_description', [
            'name' => $validated['product_name'],
            'tone' => $validated['tone'] ?? 'professional',
            'category' => $validated['category'] ?? null,
        ], $taskId);

        return response()->json(['success' => true, 'message' => 'AI generation queued', 'data' => ['task_id' => $taskId, 'status' => 'queued']], 202);
    }

    /**
     * Generate SEO meta using AI (async).
     */
    public function generateSeoMeta(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'type' => 'required|in:product,category,page',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'brand_name' => 'nullable|string',
        ]);

        $taskId = GenerateAIContentJob::generateTaskId();
        GenerateAIContentJob::dispatch('seo_meta', $validated, $taskId);

        return response()->json(['success' => true, 'message' => 'AI generation queued', 'data' => ['task_id' => $taskId, 'status' => 'queued']], 202);
    }

    /**
     * Generate an image using AI (async).
     */
    public function generateImage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => 'required|string',
            'product_name' => 'nullable|string',
            'style' => 'nullable|in:product-photo,lifestyle,flat-lay,studio,abstract,banner',
            'size' => 'nullable|in:1024x1024,1792x1024,1024x1792',
            'reference_image_url' => 'nullable|url',
        ]);

        $taskId = GenerateAIContentJob::generateTaskId();
        GenerateAIContentJob::dispatch('image', $validated, $taskId);

        return response()->json(['success' => true, 'message' => 'AI generation queued', 'data' => ['task_id' => $taskId, 'status' => 'queued']], 202);
    }

    /**
     * Generate category description using AI (async).
     */
    public function generateCategoryDescription(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_name' => 'required|string',
            'parent_name' => 'nullable|string',
            'product_count' => 'nullable|integer',
        ]);

        $taskId = GenerateAIContentJob::generateTaskId();
        GenerateAIContentJob::dispatch('category_description', [
            'name' => $validated['category_name'],
            'parentName' => $validated['parent_name'] ?? null,
            'productCount' => $validated['product_count'] ?? null,
        ], $taskId);

        return response()->json(['success' => true, 'message' => 'AI generation queued', 'data' => ['task_id' => $taskId, 'status' => 'queued']], 202);
    }

    /**
     * Generate variant description using AI (async).
     */
    public function generateVariantDescription(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'variant_name' => 'required|string',
            'product_name' => 'nullable|string',
            'color' => 'nullable|string',
            'size' => 'nullable|string',
            'sku' => 'nullable|string',
            'category' => 'nullable|string',
            'tone' => 'nullable|string',
        ]);

        $taskId = GenerateAIContentJob::generateTaskId();
        GenerateAIContentJob::dispatch('variant_description', [
            'productName' => $validated['product_name'] ?? $validated['variant_name'],
            'color' => $validated['color'] ?? null,
            'size' => $validated['size'] ?? null,
            'sku' => $validated['sku'] ?? null,
            'category' => $validated['category'] ?? null,
            'tone' => $validated['tone'] ?? null,
        ], $taskId);

        return response()->json(['success' => true, 'message' => 'AI generation queued', 'data' => ['task_id' => $taskId, 'status' => 'queued']], 202);
    }

    /**
     * Generate variant images using AI (async).
     */
    public function generateVariantImages(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_name' => 'required|string',
            'variant_name' => 'nullable|string',
            'color' => 'nullable|string',
            'size' => 'nullable|string',
            'style' => 'nullable|string',
            'reference_image_url' => 'nullable|url',
        ]);

        $taskId = GenerateAIContentJob::generateTaskId();
        GenerateAIContentJob::dispatch('variant_images', [
            'productName' => $validated['product_name'],
            'color' => $validated['color'] ?? null,
            'size' => $validated['size'] ?? null,
            'style' => $validated['style'] ?? null,
            'referenceImageUrl' => $validated['reference_image_url'] ?? null,
        ], $taskId);

        return response()->json(['success' => true, 'message' => 'AI generation queued', 'data' => ['task_id' => $taskId, 'status' => 'queued']], 202);
    }

    /**
     * Generate variant images with stream progress.
     */
    public function generateVariantImagesStream(Request $request): JsonResponse
    {
        return $this->generateVariantImages($request);
    }

    /**
     * Test AI provider connection.
     */
    public function testConnection(Request $request): JsonResponse
    {
        $apiKey = $request->input('api_key');
        $baseUrl = $request->input('base_url');
        $chatModel = $request->input('chat_model');

        $config = [];
        if ($apiKey) $config['api_key'] = $apiKey;
        if ($baseUrl) $config['base_url'] = $baseUrl;
        if ($chatModel) $config['chat_model'] = $chatModel;

        $result = $this->aiGeneralService->testConnection($config);
        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * Generate CMS page content using AI (async).
     */
    public function generatePageContent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page_type' => 'required|string',
            'topic' => 'nullable|string',
            'tone' => 'nullable|string',
        ]);

        $taskId = GenerateAIContentJob::generateTaskId();
        GenerateAIContentJob::dispatch('page_content', [
            'title' => $validated['page_type'],
            'description' => $validated['topic'] ?? null,
            'tone' => $validated['tone'] ?? 'professional',
        ], $taskId);

        return response()->json(['success' => true, 'message' => 'AI generation queued', 'data' => ['task_id' => $taskId, 'status' => 'queued']], 202);
    }

    /**
     * Get AI content generation task status.
     */
    public function aiStatus(string $taskId): JsonResponse
    {
        $status = GenerateAIContentJob::getStatus($taskId);
        $result = $status === 'completed' ? GenerateAIContentJob::getResult($taskId) : null;

        return response()->json([
            'success' => true,
            'data' => [
                'task_id' => $taskId,
                'status' => $status,
                'result' => $result,
            ],
        ]);
    }

    /**
     * Translate a batch of translation keys using AI.
     * Accepts source translations in English and translates them to the target language.
     */
    public function translateWithAI(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_language' => 'required|string|max:5',
            'target_language' => 'required|string|max:5',
            'target_language_name' => 'nullable|string|max:50',
            'translations' => 'required|array',
        ]);

        $sourceLang = $validated['source_language'];
        $targetLang = $validated['target_language'];
        $targetName = $validated['target_language_name'] ?? $targetLang;
        $translations = $validated['translations'];

        // Limit to prevent excessive API usage
        $translations = array_slice($translations, 0, 50);

        try {
            $aiClient = \App\Services\AIProviderClient::getInstance();
            $aiClient->init();

            if (!$aiClient->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'message' => 'AI provider is not configured. Set AI_PROVIDER_API_KEY or OPENAI_API_KEY in your .env file.',
                ], 400);
            }

            // Build the prompt with all translations at once
            $entries = [];
            foreach ($translations as $key => $value) {
                $entries[] = "{$key} = {$value}";
            }
            $inputText = implode("\n", $entries);

            $systemPrompt = "You are a professional e-commerce translator. Translate the following translation keys from {$sourceLang} to {$targetName} ({$targetLang}).

Rules:
- Keep the translation key (before the = sign) exactly as-is
- Only translate the value (after the = sign)
- Preserve any {variable} placeholders exactly as-is (e.g. {count}, {amount}, {name}, {store}, {year}, {percent}, {threshold}, {query})
- Use natural, fluent {$targetName} appropriate for an e-commerce storefront
- Keep the same format: one entry per line with 'key = translated_value'
- Return ONLY the translated entries, nothing else";

            $response = $aiClient->createChatCompletion([
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $inputText],
                ],
                'temperature' => 0.3,
                'max_tokens' => 2000,
            ]);

            // Parse the response back into key-value pairs
            $result = [];
            $lines = explode("\n", trim($response));
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;

                // Split on the first ' = ' separator
                $pos = strpos($line, ' = ');
                if ($pos !== false) {
                    $key = trim(substr($line, 0, $pos));
                    $value = trim(substr($line, $pos + 3));
                    // Remove any outer quotes that the AI might have added
                    $value = trim($value, '"\'');
                    if (!empty($key) && isset($translations[$key])) {
                        $result[$key] = $value;
                    }
                }
            }

            // If AI failed to translate some keys, include them as original
            foreach ($translations as $key => $value) {
                if (!isset($result[$key])) {
                    $result[$key] = $value;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'translations' => $result,
                    'translated_count' => count($result),
                    'source_language' => $sourceLang,
                    'target_language' => $targetLang,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'AI translation failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
