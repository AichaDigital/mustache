<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Compound;

/**
 * Represents a variable declaration in a USE clause.
 *
 * Example: {max_power} => {{CommandCenter.max_power}} > 0
 */
final readonly class UseVariable
{
    public function __construct(
        private string $name,
        private string $expression,
        private ?string $condition = null,
    ) {}

    /**
     * Get the variable name (without braces).
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the mustache expression to resolve.
     */
    public function getExpression(): string
    {
        return $this->expression;
    }

    /**
     * Get the condition (e.g., "> 0", "BETWEEN 1 AND 100").
     */
    public function getCondition(): ?string
    {
        return $this->condition;
    }

    /**
     * Check if this variable has a condition.
     */
    public function hasCondition(): bool
    {
        return $this->condition !== null;
    }

    /**
     * Get the variable reference as it appears in statements.
     */
    public function getReference(): string
    {
        return '{'.$this->name.'}';
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'expression' => $this->expression,
            'condition' => $this->condition,
        ];
    }
}
