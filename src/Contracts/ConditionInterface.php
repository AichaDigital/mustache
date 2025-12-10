<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Contracts;

use DateTimeInterface;

/**
 * Interface for temporal conditions.
 *
 * Conditions are the building blocks of temporal expressions.
 * Each condition evaluates a specific temporal rule (weekday, time range, etc.)
 */
interface ConditionInterface
{
    /**
     * Evaluate if the condition is true at the given moment.
     *
     * @param  DateTimeInterface|null  $at  The moment to evaluate. Defaults to now.
     */
    public function evaluate(?DateTimeInterface $at = null): bool;

    /**
     * Check if this condition supports the given keyword.
     *
     * This method should be fast and lightweight, as it's called
     * for every condition until one returns true.
     */
    public function supports(string $keyword): bool;

    /**
     * Get the keyword(s) this condition responds to.
     *
     * @return array<int, string>
     */
    public function getKeywords(): array;

    /**
     * Get a unique identifier for this condition.
     */
    public function getName(): string;
}
