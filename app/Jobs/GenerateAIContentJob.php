<?php

namespace App\Jobs;

use App\Services\AIGeneralService;
use App\Services\AIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateAIContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 15;
    public int $timeout = 120; // 2 minutes — AI calls can be slow

    protected string $taskId;

    /**
     * @param string $type One of: 'product_description', 'short_description', 'seo_meta', 'image', 'category_description', 'variant_description', 'page_content'
     * @param array $params Parameters for the generation (entity data, options, etc.)
     * @param string|null $taskId Optional unique ID to track this generation
     */
    public function __construct(
        protected string $type,
        protected array $params,
        ?string $taskId = null
    ) {
        $this->taskId = $taskId ?? self::generateTaskId();
    }

    public function handle(AIGeneralService $aiService): void
    {
        Log::info("[GenerateAIContentJob] Starting AI {$this->type} generation [{$this->taskId}]");

        try {
            $this->saveStatus('processing');

            $result = match ($this->type) {
                'product_description'   => $aiService->generateProductDescription($this->params),
                'short_description'     => ['description' => $aiService->generateShortDescription($this->params)],
                'seo_meta'              => $aiService->generateSeoMeta($this->params),
                'image'                 => $aiService->generateImage($this->params),
                'category_description'  => $aiService->generateCategoryDescription($this->params),
                'variant_description'   => $aiService->generateVariantDescription($this->params),
                'variant_images'        => $aiService->generateVariantImages($this->params),
                'page_content'          => $aiService->generatePageContent($this->params),
                default                 => throw new \InvalidArgumentException("Unknown AI content type: {$this->type}"),
            };

            $this->saveResult($result);
            $this->saveStatus('completed');

            Log::info("[GenerateAIContentJob] AI {$this->type} generation completed [{$this->taskId}]");
        } catch (\Exception $e) {
            $this->saveResult([
                'error' => $e->getMessage(),
                'type' => $this->type,
            ]);
            $this->saveStatus('failed');
            Log::error("[GenerateAIContentJob] AI {$this->type} generation failed [{$this->taskId}]: {$e->getMessage()}");
            throw $e;
        }
    }

    protected function saveStatus(string $status): void
    {
        Storage::disk('local')->put(
            "ai-content/{$this->taskId}/status.txt",
            $status
        );
    }

    protected function saveResult(array $result): void
    {
        Storage::disk('local')->put(
            "ai-content/{$this->taskId}/result.json",
            json_encode($result, JSON_PRETTY_PRINT)
        );
    }

    public static function getStatus(string $taskId): string
    {
        $path = "ai-content/{$taskId}/status.txt";
        if (!Storage::disk('local')->exists($path)) {
            return 'not_found';
        }
        return trim(Storage::disk('local')->get($path));
    }

    public static function getResult(string $taskId): ?array
    {
        $path = "ai-content/{$taskId}/result.json";
        if (!Storage::disk('local')->exists($path)) {
            return null;
        }
        return json_decode(Storage::disk('local')->get($path), true);
    }

    public static function generateTaskId(): string
    {
        return 'ai-' . Str::uuid();
    }

    public function failed(\Throwable $exception): void
    {
        $this->saveStatus('failed');
        Log::error("[GenerateAIContentJob] Permanently failed after {$this->tries} attempts for {$this->type} [{$this->taskId}]: {$exception->getMessage()}");
    }
}
