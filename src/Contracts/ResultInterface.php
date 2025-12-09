<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Contracts;

interface ResultInterface
{
    /**
     * Check if resolution was successful.
     */
    public function isSuccess(): bool;

    /**
     * Check if resolution failed.
     */
    public function isFailed(): bool;

    /**
     * Get the translated string (null if failed).
     */
    public function getTranslated(): ?string;

    /**
     * Get the original template string.
     */
    public function getOriginal(): string;

    /**
     * Get all tokens found in the template.
     */
    public function getTokens(): array;

    /**
     * Get map of token => resolved value.
     */
    public function getResolvedValues(): array;

    /**
     * Get any warnings generated during resolution.
     */
    public function getWarnings(): array;

    /**
     * Get any errors generated during resolution.
     */
    public function getErrors(): array;

    /**
     * Convert result to array representation.
     */
    public function toArray(): array;
}
