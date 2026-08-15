<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheService
{
    private string $prefix = 'app:';
    private array $ttlMap = [];
    private int $defaultTtl = 300; // 5 minutes

    /**
     * Set default TTL for keys with a given prefix.
     */
    public function setDefaultTTL(string $prefix, int $ttlSeconds): void
    {
        $this->ttlMap[$prefix] = $ttlSeconds;
    }

    /**
     * Set the global key prefix.
     */
    public function setPrefix(string $prefix): void
    {
        $this->prefix = rtrim($prefix, ':') . ':';
    }

    /**
     * Get a cached value. Returns null on miss.
     */
    public function get(string $key): mixed
    {
        return Cache::get($this->prefix . $key);
    }

    /**
     * Store a value with optional TTL override (seconds).
     */
    public function set(string $key, mixed $value, ?int $ttlOverride = null): void
    {
        $ttl = $ttlOverride ?? $this->resolveTTL($key);
        Cache::put($this->prefix . $key, $value, now()->addSeconds($ttl));
    }

    /**
     * Check if a key exists.
     */
    public function has(string $key): bool
    {
        return Cache::has($this->prefix . $key);
    }

    /**
     * Delete a single key.
     */
    public function del(string $key): void
    {
        Cache::forget($this->prefix . $key);
    }

    /**
     * Delete all keys with a given prefix.
     */
    public function flushPattern(string $prefix): void
    {
        // Laravel Cache doesn't support pattern-based deletion natively with all drivers.
        // For Redis, we can use the Redis facade directly.
        if (config('cache.default') === 'redis') {
            try {
                $redis = \Illuminate\Support\Facades\Redis::connection();
                $keys = $redis->keys($this->prefix . $prefix . '*');
                if (!empty($keys)) {
                    $redis->del($keys);
                }
                return;
            } catch (\Exception $e) {
                Log::warning('[Cache] Redis pattern flush failed, falling back to tag-based flush: ' . $e->getMessage());
            }
        }

        // Fallback: we can't flush by pattern without Redis, so log a warning
        Log::warning('[Cache] flushPattern requires Redis driver. Current driver: ' . config('cache.default'));
    }

    /**
     * Flush all cached data.
     */
    public function flushAll(): void
    {
        Cache::flush();
    }

    /**
     * Remember a value (cache-first with callback fallback).
     */
    public function remember(string $key, \Closure $callback, ?int $ttlOverride = null): mixed
    {
        $ttl = $ttlOverride ?? $this->resolveTTL($key);
        return Cache::remember($this->prefix . $key, now()->addSeconds($ttl), $callback);
    }

    /**
     * Remember a value forever (no expiration).
     */
    public function rememberForever(string $key, \Closure $callback): mixed
    {
        return Cache::rememberForever($this->prefix . $key, $callback);
    }

    /**
     * Get or set a value with automatic TTL from map.
     */
    public function resolveTTL(string $key): int
    {
        foreach ($this->ttlMap as $prefix => $ttl) {
            if (str_starts_with($key, $prefix)) {
                return $ttl;
            }
        }
        return $this->defaultTtl;
    }
}
