<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Accessors;

use AichaDigital\MustacheResolver\Contracts\DataAccessorInterface;

/**
 * Data accessor for array data sources.
 */
final readonly class ArrayAccessor implements DataAccessorInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private array $data,
    ) {}

    public function get(string $path): mixed
    {
        return data_get($this->data, $path);
    }

    public function has(string $path): bool
    {
        return data_get($this->data, $path) !== null;
    }

    /**
     * @return string[]
     */
    public function keys(): array
    {
        return array_keys($this->data);
    }

    public function getSourceType(): string
    {
        return 'array';
    }

    /**
     * @return array<string, mixed>
     */
    public function getRaw(): array
    {
        return $this->data;
    }
}
