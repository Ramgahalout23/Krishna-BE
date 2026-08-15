<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AIAdCopyService
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
     * Generate platform-optimized ad copy.
     */
    public function generateAdCopy(array $options): array
    {
        $platform = $options['platform'] ?? 'FACEBOOK';
        $productName = $options['product_name'] ?? 'Our Product';
        $brandName = $options['brand_name'] ?? 'Our Store';
        $tone = $options['tone'] ?? 'professional';

        $systemPrompt = $this->buildSystemPrompt($platform, $tone);

        $userPrompt = "Generate a high-converting {$platform} ad for:

Brand: {$brandName}
Product: {$productName}
" . (!empty($options['product_description']) ? "Description: {$options['product_description']}\n" : '') . "
" . (!empty($options['product_category']) ? "Category: {$options['product_category']}\n" : '') . "
" . (!empty($options['product_price']) ? "Price: \${$options['product_price']}\n" : '') . "
" . (!empty($options['discount']) ? "Offer: {$options['discount']}\n" : '') . "
" . (!empty($options['objective']) ? "Objective: {$options['objective']}\n" : '') . "

Respond with ONLY a valid JSON object:
{\"headline\":\"...\",\"primaryText\":\"...\",\"description\":\"...\",\"callToAction\":\"SHOP_NOW\",\"suggestions\":[],\"hashtags\":[]}";

        try {
            $content = $this->ai->createChatCompletion([
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.8,
                'max_tokens' => 800,
                'response_format' => 'json_object',
            ]);

            $parsed = json_decode($content, true) ?? [];
            return [
                'headline' => $parsed['headline'] ?? 'Shop Now',
                'primary_text' => $parsed['primaryText'] ?? "Check out {$productName} today!",
                'description' => $parsed['description'] ?? '',
                'call_to_action' => $parsed['callToAction'] ?? 'SHOP_NOW',
                'suggestions' => $parsed['suggestions'] ?? [],
                'hashtags' => $parsed['hashtags'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error("[AIAdCopy] Generation failed: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Generate A/B test variants.
     */
    public function generateVariants(array $options): array
    {
        $count = $options['max_variants'] ?? 3;
        $platform = $options['platform'] ?? 'FACEBOOK';

        $systemPrompt = $this->buildSystemPrompt($platform, $options['tone'] ?? 'professional');
        $baseInfo = "Brand: {$options['brand_name']}\nProduct: {$options['product_name']}";

        $userPrompt = "Generate {$count} DISTINCT A/B test variants for a {$platform} ad campaign.\n\n{$baseInfo}\n\nEach variant should target a different angle: urgency, social proof, benefit/value.\n\nRespond with ONLY valid JSON:\n{\"variants\":[{\"variantName\":\"...\",\"headline\":\"...\",\"primaryText\":\"...\",\"description\":\"...\",\"callToAction\":\"SHOP_NOW\",\"reasoning\":\"...\"}]}";

        try {
            $content = $this->ai->createChatCompletion([
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.9,
                'max_tokens' => 1500,
                'response_format' => 'json_object',
            ]);

            $parsed = json_decode($content, true) ?? [];
            return array_slice($parsed['variants'] ?? [], 0, $count);
        } catch (\Exception $e) {
            Log::error("[AIAdCopy] Variant generation failed: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Suggest audience targeting.
     */
    public function suggestAudience(array $options): array
    {
        $userPrompt = "You are an expert audience strategist.

Analyze this product and suggest the perfect target audience:
Product: {$options['product_name']}
" . (!empty($options['product_category']) ? "Category: {$options['product_category']}\n" : '') . "

Respond with ONLY valid JSON:
{\"ageRange\":\"25-45\",\"gender\":\"ALL\",\"interests\":[],\"behaviors\":[],\"locations\":[\"India\"],\"platforms\":[\"Facebook\",\"Instagram\"],\"bestTimeToAdvertise\":\"...\",\"estimatedReach\":\"...\"}";

        try {
            $content = $this->ai->createChatCompletion([
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert digital advertising audience strategist.'],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.7,
                'max_tokens' => 800,
                'response_format' => 'json_object',
            ]);

            return json_decode($content, true) ?? [];
        } catch (\Exception $e) {
            Log::error("[AIAdCopy] Audience suggestion failed: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Generate full campaign strategy (combines ad copy + variants + audience).
     */
    public function generateFullStrategy(array $options): array
    {
        $adCopy = $this->generateAdCopy($options);
        $variants = $this->generateVariants($options);
        $audience = $this->suggestAudience($options);

        $productName = $options['product_name'] ?? 'New';

        return [
            'campaign_name' => "{$productName} Campaign",
            'ad_copy' => $adCopy,
            'variants' => $variants,
            'audience' => $audience,
            'budget_recommendation' => 'Start with ₹500/day and optimize based on performance',
            'platform_specific_tips' => [],
            'expected_kpis' => 'Monitor CTR and conversion rate',
        ];
    }

    /**
     * Generate banner design suggestions.
     */
    public function generateBannerDesign(array $options): array
    {
        $platform = $options['platform'] ?? 'INSTAGRAM';
        $brandName = $options['brand_name'] ?? 'THREVOLT';
        $productName = $options['product_name'] ?? 'Unknown';
        $headline = $options['headline'] ?? '';

        $headlinePart = $headline ? "Headline: {$headline}\n" : '';
        $userPrompt = "Design a complete banner/layout for a {$platform} ad.

Brand: {$brandName}
Product: {$productName}
{$headlinePart}
Respond with ONLY valid JSON:
{\"layoutType\":\"image-full\",\"imagePlacement\":\"...\",\"primaryTextOverlay\":\"...\",\"secondaryTextOverlay\":\"...\",\"ctaButton\":{\"text\":\"Shop Now\",\"style\":\"filled rounded\"},\"colorScheme\":{\"primary\":\"#1a1a2e\",\"secondary\":\"#16213e\",\"accent\":\"#e94560\",\"background\":\"#ffffff\"},\"imageRecommendations\":[],\"bannerPreviewDescription\":\"...\"}";

        try {
            $content = $this->ai->createChatCompletion([
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert ad creative designer. Always respond in valid JSON.'],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.8,
                'max_tokens' => 1000,
                'response_format' => 'json_object',
            ]);

            return json_decode($content, true) ?? [];
        } catch (\Exception $e) {
            Log::error("[AIAdCopy] Banner design failed: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Build platform-specific system prompt.
     */
    private function buildSystemPrompt(string $platform, string $tone): string
    {
        $toneGuide = "Write in a {$tone} tone.";
        $platformGuides = [
            'FACEBOOK' => 'You are an expert Facebook Ads copywriter. Headline max 40 chars, primary text 125-250 chars.',
            'INSTAGRAM' => 'You are an expert Instagram Ads copywriter. Visual-first, use emojis, include 5-8 hashtags.',
            'GOOGLE' => 'You are an expert Google Ads copywriter. Headlines max 30 chars, descriptions max 90 chars.',
            'YOUTUBE' => 'You are an expert YouTube Ads copywriter. Hook viewers in first 5 seconds.',
            'WHATSAPP' => 'You are an expert WhatsApp Business copywriter. Personal, conversational, mobile-first.',
        ];

        $guide = $platformGuides[$platform] ?? $platformGuides['FACEBOOK'];
        return "{$guide}\n\n{$toneGuide}\n\nAlways respond in valid JSON format.";
    }
}
