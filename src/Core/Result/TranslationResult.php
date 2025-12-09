<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Result;

use AichaDigital\MustacheResolver\Contracts\ResultInterface;
use AichaDigital\MustacheResolver\Contracts\TokenInterface;

/**
 * Immutable result of a template translation operation.
 */
final readonly class TranslationResult implements ResultInterface
{
    /**
     * @param  TokenInterface[]  $tokens
     * @param  array<string, mixed>  $resolvedValues
     * @param  string[]  $missingFields
     * @param  string[]  $warnings
     * @param  string[]  $errors
     * @param  array<string, mixed>  $metadata
     */
    private function __construct(
        private bool $success,
        private string $original,
        private ?string $translated,
        private array $tokens = [],
        private array $resolvedValues = [],
        private array $missingFields = [],
        private array $warnings = [],
        private array $errors = [],
        private array $metadata = [],
    ) {}

    /**
     * Create a successful result.
     *
     * @param  TokenInterface[]  $tokens
     * @param  array<string, mixed>  $resolvedValues
     * @param  string[]  $warnings
     */
    public static function success(
        string $original,
        string $translated,
        array $tokens = [],
        array $resolvedValues = [],
        array $warnings = [],
    ): self {
        return new self(
            success: true,
            original: $original,
            translated: $translated,
            tokens: $tokens,
            resolvedValues: $resolvedValues,
            warnings: $warnings,
        );
    }

    /**
     * Create a failed result.
     *
     * @param  string[]  $missingFields
     * @param  string[]  $errors
     * @param  string[]  $warnings
     */
    public static function failed(
        string $original,
        array $missingFields,
        array $errors = [],
        array $warnings = [],
    ): self {
        return new self(
            success: false,
            original: $original,
            translated: null,
            missingFields: $missingFields,
            warnings: $warnings,
            errors: $errors,
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isFailed(): bool
    {
        return ! $this->success;
    }

    public function getTranslated(): ?string
    {
        return $this->translated;
    }

    public function getOriginal(): string
    {
        return $this->original;
    }

    /**
     * @return TokenInterface[]
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }

    /**
     * @return array<string, mixed>
     */
    public function getResolvedValues(): array
    {
        return $this->resolvedValues;
    }

    /**
     * @return string[]
     */
    public function getMissingFields(): array
    {
        return $this->missingFields;
    }

    /**
     * @return string[]
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get a human-readable failure reason.
     */
    public function getFailureReason(): ?string
    {
        if ($this->success) {
            return null;
        }

        if (! empty($this->missingFields)) {
            return 'Missing fields: '.implode(', ', $this->missingFields);
        }

        if (! empty($this->errors)) {
            return implode('; ', $this->errors);
        }

        return 'Unknown error';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'original' => $this->original,
            'translated' => $this->translated,
            'tokens' => array_map(fn (TokenInterface $t) => $t->getRaw(), $this->tokens),
            'resolved_values' => $this->resolvedValues,
            'missing_fields' => $this->missingFields,
            'warnings' => $this->warnings,
            'errors' => $this->errors,
            'failure_reason' => $this->getFailureReason(),
        ];
    }
}
