<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

/**
 * Cache key registry trait.
 *
 * Tracks all cache keys created via cacheWithTracking() in a registry stored
 * under '_cache_keys_registry'. When clearTrackedCache() is called, every
 * registered key is forgotten — including ones with dynamic parameters that
 * a hardcoded static list would miss.
 *
 * Usage:
 *   class YourService
 *   {
 *       use CacheKeyRegistry;
 *
 *       public function getFoo(string $id): array
 *       {
 *           return $this->cacheWithTracking("foo_{$id}", 300, function () use ($id) {
 *               return // ... expensive computation
 *           });
 *       }
 *
 *       public function invalidateAll(): void
 *       {
 *           $this->clearTrackedCache();
 *       }
 *   }
 */
trait CacheKeyRegistry
{
    /**
     * Cache a value and track the key in the registry so clearTrackedCache()
     * can clear it even when keys have dynamic parameters.
     */
    protected function cacheWithTracking(string $key, int $ttl, callable $callback): mixed
    {
        $this->trackCacheKey($key);
        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Register a cache key so it gets cleared on the next clearTrackedCache() call.
     */
    protected function trackCacheKey(string $key): void
    {
        $registryKey = $this->registryKey();
        $keys = Cache::get($registryKey, []);
        if (!in_array($key, $keys, true)) {
            $keys[] = $key;
            Cache::forever($registryKey, $keys);
        }
    }

    /**
     * Clear all tracked cache keys.
     * Call this from your mutation methods (create/update/delete).
     */
    protected function clearTrackedCache(): void
    {
        $registryKey = $this->registryKey();
        $keys = Cache::get($registryKey, []);
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        Cache::forget($registryKey);
    }

    /**
     * Get the registry key for this class.
     * Override in individual classes to use a unique registry per service.
     */
    protected function registryKey(): string
    {
        return '_cache_registry_' . str_replace('\\', '_', static::class);
    }
}
