<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIProviderClient
{
    private static ?AIProviderClient $instance = null;
    private ?string $apiKey = null;
    private string $baseUrl = 'https://api.openai.com/v1';
    private string $chatModel = 'gpt-4o-mini';
    private string $imageModel = 'dall-e-3';

    /**
     * Initialize the AI provider with configuration.
     * Reads from env vars / config by default.
     */
    public function init(?array $config = null): void
    {
        $this->apiKey = $config['api_key'] ?? config('services.ai.api_key') ?? env('AI_PROVIDER_API_KEY') ?? env('OPENAI_API_KEY');
        $this->baseUrl = $config['base_url'] ?? config('services.ai.base_url') ?? env('AI_PROVIDER_URL') ?? 'https://api.openai.com/v1';
        $this->chatModel = $config['chat_model'] ?? config('services.ai.chat_model') ?? env('AI_CHAT_MODEL') ?? 'gpt-4o-mini';
        $this->imageModel = $config['image_model'] ?? config('services.ai.image_model') ?? env('AI_IMAGE_MODEL') ?? 'dall-e-3';
    }

    /**
     * Check if the AI provider is configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Get current configuration.
     */
    public function getConfig(): array
    {
        return [
            'api_key' => substr($this->apiKey ?? '', 0, 8) . '...',
            'base_url' => $this->baseUrl,
            'chat_model' => $this->chatModel,
            'image_model' => $this->imageModel,
        ];
    }

    /**
     * Create a chat completion.
     */
    public function createChatCompletion(array $params): string
    {
        $this->ensureConfigured();

        $messages = $params['messages'] ?? [];
        $temperature = $params['temperature'] ?? 0.7;
        $maxTokens = $params['max_tokens'] ?? 600;
        $responseFormat = $params['response_format'] ?? null;

        $body = [
            'model' => $this->chatModel,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];

        if ($responseFormat === 'json_object') {
            $body['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(60)->post("{$this->baseUrl}/chat/completions", $body);

        if (!$response->successful()) {
            $error = $response->json('error.message') ?? $response->body();
            Log::error("[AIProvider] Chat completion failed: {$error}");
            throw new \Exception("AI chat completion failed: {$error}");
        }

        return $response->json('choices.0.message.content') ?? '';
    }

    /**
     * Generate an image.
     */
    public function generateImage(array $params): array
    {
        $this->ensureConfigured();

        $body = [
            'model' => $this->imageModel,
            'prompt' => $params['prompt'],
            'n' => $params['n'] ?? 1,
            'size' => $params['size'] ?? '1024x1024',
            'quality' => $params['quality'] ?? 'standard',
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(120)->post("{$this->baseUrl}/images/generations", $body);

        if (!$response->successful()) {
            $error = $response->json('error.message') ?? $response->body();
            Log::error("[AIProvider] Image generation failed: {$error}");
            throw new \Exception("AI image generation failed: {$error}");
        }

        $data = $response->json('data.0') ?? [];
        return [
            'url' => $data['url'] ?? null,
            'revised_prompt' => $data['revised_prompt'] ?? null,
        ];
    }

    /**
     * Analyze a reference image to extract style information.
     */
    public function analyzeReferenceImage(string $imageUrl): array
    {
        $prompt = "Analyze this e-commerce product image in detail. Describe:
1. The style (product-photo, lifestyle, flat-lay, studio)
2. Lighting (natural, studio soft, dramatic, backlit)
3. Composition and pose
4. Background type
5. Color palette (list dominant colors)
6. Overall aesthetic

Image URL: {$imageUrl}

Respond with ONLY valid JSON:
{
  \"style\": \"...\",
  \"lighting\": \"...\",
  \"pose\": \"...\",
  \"composition\": \"...\",
  \"background\": \"...\",
  \"colorPalette\": \"...\",
  \"fullDescription\": \"A single paragraph describing everything about this image that would help replicate it\"
}";

        try {
            $content = $this->createChatCompletion([
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert e-commerce photography analyst. Always respond in valid JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.3,
                'max_tokens' => 600,
                'response_format' => 'json_object',
            ]);

            $parsed = json_decode($content, true);
            return [
                'style' => $parsed['style'] ?? 'product-photo',
                'lighting' => $parsed['lighting'] ?? 'studio soft',
                'pose' => $parsed['pose'] ?? 'front view',
                'composition' => $parsed['composition'] ?? 'centered',
                'background' => $parsed['background'] ?? 'clean studio',
                'colorPalette' => $parsed['colorPalette'] ?? 'neutral',
                'fullDescription' => $parsed['fullDescription'] ?? '',
            ];
        } catch (\Exception $e) {
            Log::warning("[AIProvider] Reference analysis failed: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Generate multiple images in parallel.
     */
    public function generateImagesParallel(array $views): array
    {
        $images = [];
        foreach ($views as $view) {
            try {
                $result = $this->generateImage([
                    'prompt' => $view['prompt'],
                    'quality' => 'hd',
                ]);
                if (!empty($result['url'])) {
                    $images[] = ['view' => $view['view'], 'url' => $result['url']];
                }
            } catch (\Exception $e) {
                Log::warning("[AIProvider] Image generation for '{$view['view']}' failed: {$e->getMessage()}");
            }
        }
        return ['images' => $images];
    }

    /**
     * Test connection to the AI provider.
     */
    public function testConnection(?array $config = null): array
    {
        try {
            if ($config) {
                $this->init($config);
            }
            $this->ensureConfigured();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->timeout(10)->get("{$this->baseUrl}/models");

            if ($response->successful()) {
                $models = $response->json('data') ?? [];
                $modelNames = array_column($models, 'id');
                $hasChatModel = in_array($this->chatModel, $modelNames);

                return [
                    'success' => true,
                    'message' => "Connected to {$this->baseUrl}",
                    'model' => $this->chatModel,
                    'model_available' => $hasChatModel,
                ];
            }

            return [
                'success' => false,
                'message' => "Connection failed: {$response->status()} - {$response->body()}",
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Connection failed: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Ensure the provider is configured before making API calls.
     */
    private function ensureConfigured(): void
    {
        if (empty($this->apiKey)) {
            throw new \Exception('AI provider not configured. Set AI_PROVIDER_API_KEY or OPENAI_API_KEY env var.');
        }
    }

    // ── Singleton Pattern ──

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Prevent cloning and unserialization
    private function __clone() {}
    public function __wakeup() {
        throw new \Exception('Cannot unserialize singleton');
    }
}
