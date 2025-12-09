<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Context;

use AichaDigital\MustacheResolver\Accessors\ArrayAccessor;
use AichaDigital\MustacheResolver\Contracts\ContextInterface;
use AichaDigital\MustacheResolver\Contracts\DataAccessorInterface;

/**
 * Immutable context for resolution operations.
 */
final readonly class ResolutionContext implements ContextInterface
{
    /**
     * @param  array<string, mixed>  $variables
     * @param  array<string, mixed>  $config
     */
    private function __construct(
        private DataAccessorInterface $accessor,
        private array $variables = [],
        private bool $strict = true,
        private ?string $expectedPrefix = null,
        private array $config = [],
    ) {}

    /**
     * Create context with a data accessor.
     */
    public static function create(DataAccessorInterface $accessor): self
    {
        return new self($accessor);
    }

    /**
     * Create context from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(new ArrayAccessor($data));
    }

    public function get(string $key): mixed
    {
        if (array_key_exists($key, $this->variables)) {
            return $this->variables[$key];
        }

        return $this->accessor->get($key);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->variables) || $this->accessor->has($key);
    }

    public function with(string $key, mixed $value): static
    {
        $variables = $this->variables;
        $variables[$key] = $value;

        return new self(
            $this->accessor,
            $variables,
            $this->strict,
            $this->expectedPrefix,
            $this->config,
        );
    }

    /**
     * Create context with a different accessor.
     */
    public function withAccessor(DataAccessorInterface $accessor): static
    {
        return new self(
            $accessor,
            $this->variables,
            $this->strict,
            $this->expectedPrefix,
            $this->config,
        );
    }

    /**
     * Create context with strict mode setting.
     */
    public function withStrict(bool $strict): static
    {
        return new self(
            $this->accessor,
            $this->variables,
            $strict,
            $this->expectedPrefix,
            $this->config,
        );
    }

    /**
     * Create context with an expected prefix.
     */
    public function withPrefix(?string $prefix): static
    {
        return new self(
            $this->accessor,
            $this->variables,
            $this->strict,
            $prefix,
            $this->config,
        );
    }

    /**
     * Create context with additional config.
     *
     * @param  array<string, mixed>  $config
     */
    public function withConfig(array $config): static
    {
        return new self(
            $this->accessor,
            $this->variables,
            $this->strict,
            $this->expectedPrefix,
            array_merge($this->config, $config),
        );
    }

    public function getAccessor(): DataAccessorInterface
    {
        return $this->accessor;
    }

    /**
     * @return array<string, mixed>
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    public function isStrict(): bool
    {
        return $this->strict;
    }

    public function getExpectedPrefix(): ?string
    {
        return $this->expectedPrefix;
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}
