<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Contracts;

interface CacheInterface
{
    /**
     * Get a cached value by key.
     */
    public function get(string $key): mixed;

    /**
     * Check if a key exists in cache.
     */
    public function has(string $key): bool;

    /**
     * Store a value in cache.
     *
     * @param  string  $key  Cache key
     * @param  mixed  $value  Value to cache
     * @param  int|null  $ttl  Time to live in seconds (null = forever)
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void;

    /**
     * Remove a value from cache.
     */
    public function forget(string $key): void;

    /**
     * Clear all cached values.
     */
    public function flush(): void;
}
