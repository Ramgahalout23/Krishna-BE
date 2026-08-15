<?php

namespace App\OpenApi\Sections;

class SystemSection
{
    private static function single(string $ref): array
    {
        return ['content' => ['application/json' => ['schema' => ['$ref' => $ref]]]];
    }

    private static function msg(): array
    {
        return ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/MessageResponse']]]];
    }

    private static function swm(): array
    {
        return ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/SuccessWithMessage']]]];
    }

    public static function paths(): array
    {
        return [
            // ── Promotions ──
            '/promotions' => [
                'get' => [
                    'summary' => 'List active promotions',
                    'tags' => ['Promotions'],
                    'responses' => ['200' => self::single('#/components/schemas/PromotionListResponse')],
                ],
            ],

            // ── Settings ──
            '/settings' => ['get' => ['summary' => 'Get public site settings', 'tags' => ['Settings'], 'responses' => ['200' => self::single('#/components/schemas/SettingListResponse')]]],
            '/settings/maintenance' => ['get' => ['summary' => 'Get maintenance mode status', 'tags' => ['Settings'], 'responses' => ['200' => self::single('#/components/schemas/GenericDataResponse')]]],
            '/settings/404' => ['get' => ['summary' => 'Get custom 404 page settings', 'tags' => ['Settings'], 'responses' => ['200' => self::single('#/components/schemas/GenericDataResponse')]]],
            '/settings/{key}' => ['get' => ['summary' => 'Get specific setting by key', 'tags' => ['Settings'], 'parameters' => [['name' => 'key', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]], 'responses' => ['200' => self::single('#/components/schemas/SettingResponse')]]],

            // ── Pages ──
            '/pages' => ['get' => ['summary' => 'List public CMS pages', 'tags' => ['Pages'], 'responses' => ['200' => self::single('#/components/schemas/PageListResponse')]]],
            '/pages/{slug}' => ['get' => ['summary' => 'Get page by slug', 'tags' => ['Pages'], 'parameters' => [['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]], 'responses' => ['200' => self::single('#/components/schemas/PageResponse')]]],

            // ── SEO ──
            '/seo/global' => ['get' => ['summary' => 'Get global SEO settings', 'tags' => ['SEO'], 'responses' => ['200' => self::single('#/components/schemas/SeoDataResponse')]]],
            '/seo/sitemap' => ['get' => ['summary' => 'Get sitemap data', 'tags' => ['SEO'], 'responses' => ['200' => self::single('#/components/schemas/GenericDataResponse')]]],
            '/seo/robots' => ['get' => ['summary' => 'Get robots.txt content', 'tags' => ['SEO'], 'responses' => ['200' => self::single('#/components/schemas/GenericDataResponse')]]],
            '/seo/robots/raw' => ['get' => ['summary' => 'Get raw robots.txt', 'tags' => ['SEO'], 'responses' => ['200' => self::single('#/components/schemas/GenericDataResponse')]]],
            '/seo/sitemap/raw' => ['get' => ['summary' => 'Get raw sitemap XML', 'tags' => ['SEO'], 'responses' => ['200' => self::single('#/components/schemas/GenericDataResponse')]]],
            '/seo/pages/{slug}' => ['get' => ['summary' => 'Get SEO for a page', 'tags' => ['SEO'], 'parameters' => [['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]], 'responses' => ['200' => self::single('#/components/schemas/SeoDataResponse')]]],
            '/seo/{entityType}/{entityId}' => ['get' => ['summary' => 'Get SEO for entity', 'tags' => ['SEO'], 'parameters' => [['name' => 'entityType', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']], ['name' => 'entityId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]], 'responses' => ['200' => self::single('#/components/schemas/SeoDataResponse')]]],

            // ── Reels & Curated Looks ──
            '/reels' => ['get' => ['summary' => 'List reels', 'tags' => ['Reels & Looks'], 'responses' => ['200' => self::single('#/components/schemas/ReelListResponse')]]],
            '/curated-looks' => ['get' => ['summary' => 'List curated looks', 'tags' => ['Reels & Looks'], 'responses' => ['200' => self::single('#/components/schemas/CuratedLookListResponse')]]],

            // ── Inventory ──
            '/inventory/{productId}/check' => ['get' => ['summary' => 'Check product inventory', 'tags' => ['Inventory'], 'parameters' => [['name' => 'productId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]], 'responses' => ['200' => self::single('#/components/schemas/GenericDataResponse')]]],

            // ── Currencies ──
            '/currencies' => ['get' => ['summary' => 'List available currencies', 'tags' => ['Currencies'], 'responses' => ['200' => self::single('#/components/schemas/CurrencyListResponse')]]],
            '/currencies/default' => ['get' => ['summary' => 'Get default currency', 'tags' => ['Currencies'], 'responses' => ['200' => self::single('#/components/schemas/GenericDataResponse')]]],
            '/currencies/convert' => ['post' => ['summary' => 'Convert between currencies', 'tags' => ['Currencies'], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/CurrencyConversionRequest']]]], 'responses' => ['200' => self::single('#/components/schemas/CurrencyConversionResponse')]]],

            // ── Translations ──
            '/translations' => ['get' => ['summary' => 'List translations', 'tags' => ['Translations'], 'responses' => ['200' => self::single('#/components/schemas/TranslationListResponse')]]],
            '/translations/languages' => ['get' => ['summary' => 'List available languages', 'tags' => ['Translations'], 'responses' => ['200' => self::single('#/components/schemas/LanguageListResponse')]]],

            // ── Tax ──
            '/tax/calculate' => ['post' => ['summary' => 'Calculate tax for amount/address', 'tags' => ['Tax'], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/TaxCalculationRequest']]]], 'responses' => ['200' => self::single('#/components/schemas/TaxCalculationResponse')]]],

            // ── Marketing ──
            '/marketing/subscribe' => ['post' => ['summary' => 'Subscribe to newsletter', 'tags' => ['Marketing'], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/MarketingSubscribeRequest']]]], 'responses' => ['200' => self::swm()]]],
            '/marketing/unsubscribe' => ['post' => ['summary' => 'Unsubscribe from newsletter', 'tags' => ['Marketing'], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/MarketingSubscribeRequest']]]], 'responses' => ['200' => self::swm()]]],

            // ── Tracking ──
            '/tracking/pageview' => ['post' => ['summary' => 'Record a page view event', 'tags' => ['Tracking'], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/TrackingPageViewRequest']]]], 'responses' => ['200' => self::msg()]]],
            '/tracking/session' => ['post' => ['summary' => 'Create tracking session', 'tags' => ['Tracking'], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/TrackingSessionRequest']]]], 'responses' => ['200' => self::msg()]]],
            '/tracking/session/{sessionId}/end' => ['patch' => ['summary' => 'End tracking session', 'tags' => ['Tracking'], 'parameters' => [['name' => 'sessionId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]], 'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => []]]]], 'responses' => ['200' => self::msg()]]],
            '/tracking/event' => ['post' => ['summary' => 'Record a tracking event', 'tags' => ['Tracking'], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/TrackingEventRequest']]]], 'responses' => ['200' => self::msg()]]],
        ];
    }
}
