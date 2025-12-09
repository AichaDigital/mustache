<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Cache;

use AichaDigital\MustacheResolver\Contracts\CacheInterface;

/**
 * A cache implementation that stores nothing.
 * Used when caching is disabled.
 */
final class NullCache implements CacheInterface
{
    public function get(string $key): mixed
    {
        return null;
    }

    public function has(string $key): bool
    {
        return false;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        // Do nothing
    }

    public function forget(string $key): void
    {
        // Do nothing
    }

    public function flush(): void
    {
        // Do nothing
    }
}
