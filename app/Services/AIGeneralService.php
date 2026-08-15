<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AIGeneralService
{
    private AIProviderClient $ai;

    public function __construct()
    {
        $this->ai = AIProviderClient::getInstance();
    }

    /**
     * Check if AI provider is configured.
     */
    public function isConfigured(): bool
    {
        return $this->ai->isConfigured();
    }

    /**
     * Generate product description.
     */
    public function generateProductDescription(array $product): array
    {
        $prompt = "You are an expert e-commerce copywriter. Write a compelling product description.\n\n"
            . "Product: {$product['name']}\n"
            . ($product['category'] ? "Category: {$product['category']}\n" : '')
            . ($product['price'] ? "Price: \${$product['price']}\n" : '')
            . ($product['brand_name'] ? "Brand: {$product['brand_name']}\n" : '')
            . "Tone: {$product['tone']}\n"
            . ($product['target_audience'] ? "Target Audience: {$product['target_audience']}\n" : '')
            . (!empty($product['features']) ? "Key Features: " . implode(', ', $product['features']) . "\n" : '')
            . ($product['keywords'] ? "Keywords to include: {$product['keywords']}\n" : '')
            . "\nWrite a persuasive, SEO-friendly product description (2-3 paragraphs, 150-250 words).\n\n"
            . "Respond with ONLY valid JSON:\n{\"description\":\"...\",\"keyFeatures\":[\"Feature 1\",\"Feature 2\"]}";

        try {
            $content = $this->ai->createChatCompletion([
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert e-commerce copywriter. Always respond in valid JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
                'max_tokens' => 600,
                'response_format' => 'json_object',
            ]);

            $parsed = json_decode($content, true) ?? [];
            return [
                'description' => $parsed['description'] ?? "Introducing {$product['name']} — the perfect addition to your collection.",
                'key_features' => $parsed['keyFeatures'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error("[AIGeneral] Product description failed: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Generate short description.
     */
    public function generateShortDescription(array $product): string
    {
        $prompt = "Generate a punchy, benefit-driven short description (1-2 sentences, max 150 chars) for:\n"
            . "Product: {$product['name']}\n"
            . ($product['category'] ? "Category: {$product['category']}\n" : '')
            . "Tone: {$product['tone']}\n";

        try {
            return $this->ai->createChatCompletion([
                'messages' => [
                    ['role' => 'system', 'content' => 'You write ultra-concise e-commerce product summaries.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
                'max_tokens' => 120,
            ]);
        } catch (\Exception $e) {
            Log::error("[AIGeneral] Short description failed: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Generate SEO meta.
     */
    public function generateSeoMeta(array $entity): array
    {
        $prompt = "Generate SEO metadata for this {$entity['type']}:\n\n"
            . "Name: {$entity['name']}\n"
            . ($entity['description'] ? "Description: {$entity['description']}\n" : '')
            . ($entity['category'] ? "Category: {$entity['category']}\n" : '')
            . ($entity['brand_name'] ? "Brand: {$entity['brand_name']}\n" : '')
            . "\nRespond with ONLY valid JSON:\n{\"metaTitle\":\"SEO title (50-60 chars)\",\"metaDescription\":\"Meta description (150-160 chars)\",\"metaKeywords\":\"keyword1, keyword2, keyword3\"}";

        try {
            $content = $this->ai->createChatCompletion([
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an e-commerce SEO expert. Always respond in valid JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.5,
                'max_tokens' => 300,
                'response_format' => 'json_object',
            ]);

            $parsed = json_decode($content, true) ?? [];
            return [
                'meta_title' => $parsed['metaTitle'] ?? $entity['name'],
                'meta_description' => $parsed['metaDescription'] ?? "Shop {$entity['name']} — premium quality at great prices.",
                'meta_keywords' => $parsed['metaKeywords'] ?? str_replace(' ', ', ', strtolower($entity['name'])),
            ];
        } catch (\Exception $e) {
            Log::error("[AIGeneral] SEO meta failed: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Generate image.
     */
    public function generateImage(array $options): array
    {
        $styleGuide = [
            'product-photo' => 'Professional product photography, clean white background, studio lighting',
            'lifestyle' => 'Lifestyle shot, natural lighting, real people using the product',
            'flat-lay' => 'Flat lay photography, overhead angle, styled composition',
            'studio' => 'Studio photography, controlled dramatic lighting, premium look',
            'abstract' => 'Abstract artistic interpretation, bold colors',
            'banner' => 'Professional e-commerce banner, wide landscape, high-end retail look',
        ];

        $styleDesc = $styleGuide[$options['style'] ?? 'product-photo'] ?? $styleGuide['product-photo'];
        $name = !empty($options['product_name']) ? " for {$options['product_name']}" : '';
        $fullPrompt = "{$styleDesc}. {$options['prompt']}{$name}. High quality, 4K, e-commerce ready.";

        try {
            $result = $this->ai->generateImage([
                'prompt' => $fullPrompt,
                'size' => $options['size'] ?? '1024x1024',
                'quality' => 'standard',
            ]);

            return [
                'url' => $result['url'] ?? null,
                'revised_prompt' => $result['revised_prompt'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error("[AIGeneral] Image generation failed: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Generate category description.
     */
    public function generateCategoryDescription(array $category): array
    {
        $prompt = "Write a compelling category description for an e-commerce store.\n\n"
            . "Category: {$category['name']}\n"
            . ($category['parentName'] ? "Parent Category: {$category['parentName']}\n" : '')
            . ($category['productCount'] ? "Products in this category: {$category['productCount']}\n" : '')
            . "\nWrite 2 short paragraphs (max 120 words).\n\n"
            . "Respond with ONLY valid JSON:\n{\"description\":\"...\",\"seoDescription\":\"...\"}";

        try {
            $content = $this->ai->createChatCompletion([
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a category content strategist. Always respond in valid JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
                'max_tokens' => 400,
                'response_format' => 'json_object',
            ]);

            $parsed = json_decode($content, true) ?? [];
            return [
                'description' => $parsed['description'] ?? "Explore our {$category['name']} collection.",
                'seo_description' => $parsed['seoDescription'] ?? "Shop the best {$category['name']} online.",
            ];
        } catch (\Exception $e) {
            Log::error("[AIGeneral] Category description failed: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Generate variant description.
     */
    public function generateVariantDescription(array $variant): array
    {
        $attrs = array_filter([$variant['color'] ?? null, $variant['size'] ?? null]);
        $attrsStr = !empty($attrs) ? 'Variant Attributes: ' . implode(', ', $attrs) : '';

        $prompt = "Write a compelling variant description.\n\n"
            . "Product: {$variant['productName']}\n"
            . ($attrsStr ? "{$attrsStr}\n" : '')
            . ($variant['category'] ? "Category: {$variant['category']}\n" : '')
            . ($variant['sku'] ? "SKU: {$variant['sku']}\n" : '')
            . "\nWrite 1-2 sentences (max 120 words).\n\n"
            . "Respond with ONLY valid JSON:\n{\"description\":\"...\",\"highlights\":[\"Highlight 1\",\"Highlight 2\"]}";

        try {
            $content = $this->ai->createChatCompletion([
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a product variant copywriter. Always respond in valid JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
                'max_tokens' => 400,
                'response_format' => 'json_object',
            ]);

            $parsed = json_decode($content, true) ?? [];
            return [
                'description' => $parsed['description'] ?? "{$variant['productName']} — the perfect choice.",
                'highlights' => $parsed['highlights'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error("[AIGeneral] Variant description failed: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Generate multiple variant images.
     */
    public function generateVariantImages(array $options): array
    {
        try {
            $colorStr = $options['color'] ? " in {$options['color']}" : '';
            $sizeStr = $options['size'] ? ", size {$options['size']}" : '';
            $attrs = "{$colorStr}{$sizeStr}";

            $views = [
                [
                    'view' => 'front',
                    'prompt' => "Professional e-commerce model photography, front view. A model wearing {$options['productName']}{$attrs}, full body front pose, clean studio background, soft diffused lighting, high-end fashion look, 4K, photorealistic.",
                ],
                [
                    'view' => 'back',
                    'prompt' => "Professional e-commerce photography, back view. A model wearing {$options['productName']}{$attrs}, turned around showing the back, clean studio background, 4K, photorealistic.",
                ],
                [
                    'view' => 'side',
                    'prompt' => "Professional e-commerce photography, three-quarter view. A model wearing {$options['productName']}{$attrs}, side angle silhouette, clean studio background, 4K, photorealistic.",
                ],
                [
                    'view' => 'detail',
                    'prompt' => "Professional e-commerce macro detail shot of {$options['productName']}{$attrs}, showing fabric texture, stitching, craftsmanship, sharp focus, 4K, product photography.",
                ],
            ];

            return $this->ai->generateImagesParallel($views);
        } catch (\Exception $e) {
            Log::error("[AIGeneral] Variant images failed: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Test AI provider connection.
     */
    public function testConnection(array $config): array
    {
        return $this->ai->testConnection($config);
    }

    /**
     * Generate page content.
     */
    public function generatePageContent(array $page): array
    {
        $prompt = "Write content for a store page titled \"{$page['title']}\".\n"
            . ($page['description'] ? "Description: {$page['description']}\n" : '')
            . "Tone: {$page['tone']}\n\n"
            . "Write well-formatted HTML content (use <h2>, <p>, <ul>, <li> tags), 300-500 words.\n\n"
            . "Respond with ONLY valid JSON:\n{\"content\":\"HTML content here...\",\"seoTitle\":\"SEO title\",\"seoDescription\":\"SEO description\"}";

        try {
            $content = $this->ai->createChatCompletion([
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a CMS content writer. Always respond in valid JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
                'max_tokens' => 1200,
                'response_format' => 'json_object',
            ]);

            $parsed = json_decode($content, true) ?? [];
            return [
                'content' => $parsed['content'] ?? "<h2>{$page['title']}</h2><p>Welcome to our {$page['title']} page.</p>",
                'seo_title' => $parsed['seoTitle'] ?? $page['title'],
                'seo_description' => $parsed['seoDescription'] ?? "Learn more about {$page['title']} at our store.",
            ];
        } catch (\Exception $e) {
            Log::error("[AIGeneral] Page content failed: {$e->getMessage()}");
            throw $e;
        }
    }
}
