<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Contracts;

interface ContextInterface
{
    /**
     * Get a value from the context by key.
     */
    public function get(string $key): mixed;

    /**
     * Check if a key exists in the context.
     */
    public function has(string $key): bool;

    /**
     * Create a new context with an additional value.
     * Context is immutable; this returns a new instance.
     */
    public function with(string $key, mixed $value): static;

    /**
     * Get the primary data accessor (Model, Array, etc).
     */
    public function getAccessor(): DataAccessorInterface;

    /**
     * Get all registered variables.
     */
    public function getVariables(): array;

    /**
     * Check if strict mode is enabled.
     */
    public function isStrict(): bool;

    /**
     * Get the expected prefix for model access (e.g., 'User', 'CommandCenter').
     */
    public function getExpectedPrefix(): ?string;

    /**
     * Get configuration value.
     */
    public function config(string $key, mixed $default = null): mixed;
}
