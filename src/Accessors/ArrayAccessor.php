<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Accessors;

use AichaDigital\MustacheResolver\Contracts\DataAccessorInterface;
use AichaDigital\MustacheResolver\Contracts\SecurityAwareAccessorInterface;
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;

/**
 * Data accessor for array data sources.
 */
final readonly class ArrayAccessor implements DataAccessorInterface, SecurityAwareAccessorInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private array $data,
        private ?SecurityValidator $securityValidator = null,
    ) {}

    public function get(string $path): mixed
    {
        if (! $this->allowsPath($path)) {
            return null;
        }

        return data_get($this->data, $path);
    }

    /**
     * Check every segment against the blacklist and the path depth.
     */
    public function allowsPath(string $path): bool
    {
        return $this->securityValidator === null || $this->securityValidator->allowsPath($path);
    }

    public function has(string $path): bool
    {
        return $this->get($path) !== null;
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
