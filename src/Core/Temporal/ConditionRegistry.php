<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Temporal;

use AichaDigital\MustacheResolver\Contracts\ConditionInterface;
use AichaDigital\MustacheResolver\Temporal\Conditions\AlwaysCondition;
use AichaDigital\MustacheResolver\Temporal\Conditions\CustomCondition;
use AichaDigital\MustacheResolver\Temporal\Conditions\NeverCondition;
use AichaDigital\MustacheResolver\Temporal\Conditions\WeekdayCondition;
use AichaDigital\MustacheResolver\Temporal\Conditions\WeekendCondition;
use DateTimeInterface;

/**
 * Registry for temporal conditions.
 *
 * Provides centralized management of built-in and custom conditions
 * that can be shared across multiple TemporalExpression instances.
 */
final class ConditionRegistry
{
    /** @var array<string, ConditionInterface> */
    private array $conditions = [];

    /** @var array<string, callable(DateTimeInterface): bool> */
    private array $customEvaluators = [];

    private static ?self $instance = null;

    public function __construct()
    {
        $this->registerBuiltIn();
    }

    /**
     * Get the global singleton instance.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Reset the global singleton instance.
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Register a condition instance.
     */
    public function register(ConditionInterface $condition): self
    {
        foreach ($condition->getKeywords() as $keyword) {
            $this->conditions[$keyword] = $condition;
        }

        return $this;
    }

    /**
     * Register a custom evaluator function.
     *
     * @param  string  $keyword  The keyword for this condition
     * @param  callable(DateTimeInterface): bool  $evaluator  The evaluator function
     */
    public function registerEvaluator(string $keyword, callable $evaluator): self
    {
        $this->customEvaluators[$keyword] = $evaluator;
        $this->conditions[$keyword] = new CustomCondition($keyword, $evaluator);

        return $this;
    }

    /**
     * Check if a condition is registered for the given keyword.
     */
    public function has(string $keyword): bool
    {
        return isset($this->conditions[$keyword]) || isset($this->customEvaluators[$keyword]);
    }

    /**
     * Get a condition by keyword.
     */
    public function get(string $keyword): ?ConditionInterface
    {
        return $this->conditions[$keyword] ?? null;
    }

    /**
     * Evaluate a keyword at the given time.
     */
    public function evaluate(string $keyword, ?DateTimeInterface $at = null): bool
    {
        $condition = $this->get($keyword);

        if ($condition !== null) {
            return $condition->evaluate($at);
        }

        if (isset($this->customEvaluators[$keyword])) {
            return (bool) ($this->customEvaluators[$keyword])($at ?? new \DateTimeImmutable);
        }

        return false;
    }

    /**
     * Get all registered keywords.
     *
     * @return array<int, string>
     */
    public function getKeywords(): array
    {
        return array_keys($this->conditions);
    }

    /**
     * Get all registered custom evaluator keywords.
     *
     * @return array<int, string>
     */
    public function getCustomKeywords(): array
    {
        return array_keys($this->customEvaluators);
    }

    /**
     * Remove a registered condition.
     */
    public function remove(string $keyword): self
    {
        unset($this->conditions[$keyword], $this->customEvaluators[$keyword]);

        return $this;
    }

    /**
     * Clear all custom conditions (keeps built-in).
     */
    public function clearCustom(): self
    {
        foreach (array_keys($this->customEvaluators) as $keyword) {
            unset($this->conditions[$keyword]);
        }
        $this->customEvaluators = [];

        return $this;
    }

    /**
     * Create a new TemporalExpression with this registry's evaluators.
     */
    public function createExpression(string $expression): TemporalExpression
    {
        $temporal = new TemporalExpression($expression);

        foreach ($this->customEvaluators as $keyword => $evaluator) {
            $temporal->registerEvaluator($keyword, $evaluator);
        }

        return $temporal;
    }

    /**
     * Register built-in conditions.
     */
    private function registerBuiltIn(): void
    {
        $this->register(new AlwaysCondition);
        $this->register(new NeverCondition);
        $this->register(new WeekdayCondition);
        $this->register(new WeekendCondition);
    }
}
