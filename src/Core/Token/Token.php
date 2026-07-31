<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Token;

use AichaDigital\MustacheResolver\Contracts\TokenInterface;

/**
 * Immutable value object representing a parsed mustache token.
 *
 * A token is a lexical unit extracted from a mustache template,
 * containing metadata about its type, path, and any special attributes.
 */
final readonly class Token implements TokenInterface
{
    /**
     * @param  string  $raw  The raw mustache content without braces
     * @param  TokenType  $type  The classified token type
     * @param  array<int, string>  $path  The path segments
     * @param  string|null  $functionName  Function name if this is a function call
     * @param  array<int, mixed>  $functionArgs  Function arguments if applicable
     * @param  string|null  $defaultValue  Default value for null coalesce
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    private function __construct(
        private string $raw,
        private TokenType $type,
        private array $path,
        private ?string $functionName = null,
        private array $functionArgs = [],
        private ?string $defaultValue = null,
        private array $metadata = [],
    ) {}

    /**
     * Create a token from a raw mustache string.
     */
    public static function fromString(string $raw): self
    {
        $classifier = new TokenClassifier;

        return $classifier->classify($raw);
    }

    /**
     * Create a token with explicit parameters.
     *
     * @param  array<int, string>  $path
     * @param  array<int, mixed>  $functionArgs
     * @param  array<string, mixed>  $metadata
     */
    public static function create(
        string $raw,
        TokenType $type,
        array $path,
        ?string $functionName = null,
        array $functionArgs = [],
        ?string $defaultValue = null,
        array $metadata = [],
    ): self {
        return new self(
            $raw,
            $type,
            $path,
            $functionName,
            $functionArgs,
            $defaultValue,
            $metadata,
        );
    }

    public function getRaw(): string
    {
        return $this->raw;
    }

    public function getFull(): string
    {
        return '{{'.$this->raw.'}}';
    }

    public function getType(): TokenType
    {
        return $this->type;
    }

    /**
     * @return array<int, string>
     */
    public function getPath(): array
    {
        return $this->path;
    }

    public function getPrefix(): string
    {
        return $this->path[0] ?? '';
    }

    /**
     * @return array<int, string>
     */
    public function getFieldPath(): array
    {
        return array_slice($this->path, 1);
    }

    public function isDynamic(): bool
    {
        foreach ($this->path as $segment) {
            if (str_starts_with($segment, '$')) {
                return true;
            }
        }

        return false;
    }

    public function getFunctionName(): ?string
    {
        return $this->functionName;
    }

    /**
     * @return array<int, mixed>
     */
    public function getFunctionArgs(): array
    {
        return $this->functionArgs;
    }

    public function getDefaultValue(): ?string
    {
        return $this->defaultValue;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * The path string the accessor actually receives for this token.
     *
     * Verified against each resolver's own navigation call:
     * - MODEL (ModelResolver), RELATION (RelationResolver) and COLLECTION
     *   (CollectionResolver) all navigate on getFieldPath() — the prefix
     *   is stripped before it reaches the accessor.
     * - TABLE (TableResolver:41-43) navigates the full path, prefix
     *   included, because tables are accessed by their full name.
     * - DYNAMIC (DynamicFieldResolver:52,66) has no static path: it reads
     *   an indicator field, then accesses a field name resolved at
     *   runtime. Both accesses already go through the accessor, which
     *   validates each of them independently — evaluating a static path
     *   here would check a different path and manufacture false
     *   positives.
     * - Every other type does not navigate data through the accessor.
     */
    public function getSecurityPath(): ?string
    {
        return match ($this->type) {
            TokenType::MODEL,
            TokenType::RELATION,
            TokenType::COLLECTION => implode('.', $this->getFieldPath()),
            TokenType::TABLE => implode('.', $this->getPath()),
            default => null,
        };
    }

    /**
     * Create a new token with a different type.
     */
    public function withType(TokenType $type): self
    {
        return new self(
            $this->raw,
            $type,
            $this->path,
            $this->functionName,
            $this->functionArgs,
            $this->defaultValue,
            $this->metadata,
        );
    }

    /**
     * Create a new token with additional metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            $this->raw,
            $this->type,
            $this->path,
            $this->functionName,
            $this->functionArgs,
            $this->defaultValue,
            array_merge($this->metadata, $metadata),
        );
    }
}
