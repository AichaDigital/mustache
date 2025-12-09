<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Cache;

use AichaDigital\MustacheResolver\Contracts\CacheInterface;

/**
 * A simple in-memory cache implementation.
 * Useful for testing and single-request caching.
 */
final class ArrayCache implements CacheInterface
{
    /**
     * @var array<string, array{value: mixed, expires: int|null}>
     */
    private array $store = [];

    public function get(string $key): mixed
    {
        if (! $this->has($key)) {
            return null;
        }

        return $this->store[$key]['value'];
    }

    public function has(string $key): bool
    {
        if (! isset($this->store[$key])) {
            return false;
        }

        $entry = $this->store[$key];

        // Check expiration
        if ($entry['expires'] !== null && $entry['expires'] < time()) {
            unset($this->store[$key]);

            return false;
        }

        return true;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $this->store[$key] = [
            'value' => $value,
            'expires' => $ttl !== null ? time() + $ttl : null,
        ];
    }

    public function forget(string $key): void
    {
        unset($this->store[$key]);
    }

    public function flush(): void
    {
        $this->store = [];
    }
}
