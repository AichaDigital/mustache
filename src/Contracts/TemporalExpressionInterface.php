<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Contracts;

use DateTimeInterface;

/**
 * Interface for temporal expression evaluation.
 *
 * Temporal expressions combine multiple conditions with logical operators
 * to create complex temporal rules like "weekday && 08:00-18:00 && !holiday".
 */
interface TemporalExpressionInterface
{
    /**
     * Evaluate if the expression is true at the given moment.
     *
     * @param  DateTimeInterface|null  $at  The moment to evaluate. Defaults to now.
     */
    public function evaluate(?DateTimeInterface $at = null): bool;

    /**
     * Register a custom evaluator for domain-specific conditions.
     *
     * This allows projects to add their own conditions (like "holiday", "day", "night")
     * without modifying the package.
     *
     * @param  string  $keyword  The keyword to register (e.g., "holiday")
     * @param  callable(DateTimeInterface): bool  $evaluator  The evaluator function
     * @return $this
     */
    public function registerEvaluator(string $keyword, callable $evaluator): self;

    /**
     * Check if a custom evaluator is registered for the given keyword.
     */
    public function hasEvaluator(string $keyword): bool;

    /**
     * Get the raw expression string.
     */
    public function getExpression(): string;

    /**
     * Get all registered custom evaluator keywords.
     *
     * @return array<int, string>
     */
    public function getRegisteredKeywords(): array;
}
