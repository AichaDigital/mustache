<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Contracts;

interface DataAccessorInterface
{
    /**
     * Get a value by dot-notation path.
     */
    public function get(string $path): mixed;

    /**
     * Check if a path exists.
     */
    public function has(string $path): bool;

    /**
     * Get all available keys at the top level.
     */
    public function keys(): array;

    /**
     * Get the underlying data source type.
     */
    public function getSourceType(): string;

    /**
     * Get the raw underlying data (for debugging).
     */
    public function getRaw(): mixed;
}
