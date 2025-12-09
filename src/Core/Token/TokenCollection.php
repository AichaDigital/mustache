<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Token;

use AichaDigital\MustacheResolver\Contracts\TokenInterface;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Collection of tokens with utility methods.
 *
 * @implements IteratorAggregate<int, TokenInterface>
 */
final class TokenCollection implements Countable, IteratorAggregate
{
    /**
     * @param  array<int, TokenInterface>  $tokens
     */
    public function __construct(
        private array $tokens = []
    ) {}

    /**
     * Create from an array of tokens.
     *
     * @param  array<int, TokenInterface>  $tokens
     */
    public static function fromArray(array $tokens): self
    {
        return new self($tokens);
    }

    /**
     * Add a token to the collection.
     */
    public function add(TokenInterface $token): self
    {
        $tokens = $this->tokens;
        $tokens[] = $token;

        return new self($tokens);
    }

    /**
     * Get all tokens.
     *
     * @return array<int, TokenInterface>
     */
    public function all(): array
    {
        return $this->tokens;
    }

    /**
     * Filter tokens by type.
     */
    public function ofType(TokenType $type): self
    {
        return new self(
            array_values(
                array_filter(
                    $this->tokens,
                    fn (TokenInterface $token) => $token->getType() === $type
                )
            )
        );
    }

    /**
     * Filter tokens by multiple types.
     *
     * @param  array<int, TokenType>  $types
     */
    public function ofTypes(array $types): self
    {
        return new self(
            array_values(
                array_filter(
                    $this->tokens,
                    fn (TokenInterface $token) => in_array($token->getType(), $types, true)
                )
            )
        );
    }

    /**
     * Get tokens that require a data accessor.
     */
    public function requireingAccessor(): self
    {
        return new self(
            array_values(
                array_filter(
                    $this->tokens,
                    fn (TokenInterface $token) => $token->getType()->requiresAccessor()
                )
            )
        );
    }

    /**
     * Get unique prefixes from all tokens.
     *
     * @return array<int, string>
     */
    public function uniquePrefixes(): array
    {
        $prefixes = [];

        foreach ($this->tokens as $token) {
            $prefix = $token->getPrefix();
            if ($prefix !== '' && ! in_array($prefix, $prefixes, true)) {
                $prefixes[] = $prefix;
            }
        }

        return $prefixes;
    }

    /**
     * Check if collection contains dynamic tokens.
     */
    public function hasDynamic(): bool
    {
        foreach ($this->tokens as $token) {
            if ($token->isDynamic()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get raw mustache strings.
     *
     * @return array<int, string>
     */
    public function getRawStrings(): array
    {
        return array_map(
            fn (TokenInterface $token) => $token->getRaw(),
            $this->tokens
        );
    }

    /**
     * Get full mustache strings (with braces).
     *
     * @return array<int, string>
     */
    public function getFullStrings(): array
    {
        return array_map(
            fn (TokenInterface $token) => $token->getFull(),
            $this->tokens
        );
    }

    /**
     * Check if collection is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->tokens);
    }

    /**
     * Check if collection is not empty.
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    public function count(): int
    {
        return count($this->tokens);
    }

    /**
     * @return Traversable<int, TokenInterface>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->tokens);
    }

    /**
     * Get first token or null.
     */
    public function first(): ?TokenInterface
    {
        return $this->tokens[0] ?? null;
    }

    /**
     * Get last token or null.
     */
    public function last(): ?TokenInterface
    {
        if (empty($this->tokens)) {
            return null;
        }

        return $this->tokens[count($this->tokens) - 1];
    }

    /**
     * Map over tokens.
     *
     * @template T
     *
     * @param  callable(TokenInterface): T  $callback
     * @return array<int, T>
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->tokens);
    }
}
