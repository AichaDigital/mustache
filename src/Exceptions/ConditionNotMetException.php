<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Exceptions;

/**
 * Thrown when a USE clause condition is not satisfied.
 *
 * Example: USE {max_power} => {{CommandCenter.max_power}} > 0
 * If max_power resolves to 0 or negative, this exception is thrown.
 */
final class ConditionNotMetException extends ResolutionException
{
    public function __construct(
        private readonly string $variableName,
        private readonly mixed $actualValue,
        private readonly string $condition,
        private readonly string $expression,
    ) {
        parent::__construct(sprintf(
            'Condition failed for variable "%s": value %s did not satisfy condition "%s"',
            $variableName,
            json_encode($actualValue),
            $condition,
        ));
    }

    /**
     * Get the variable name that failed the condition.
     */
    public function getVariableName(): string
    {
        return $this->variableName;
    }

    /**
     * Get the actual value that was resolved.
     */
    public function getActualValue(): mixed
    {
        return $this->actualValue;
    }

    /**
     * Get the condition that was not met.
     */
    public function getCondition(): string
    {
        return $this->condition;
    }

    /**
     * Get the full expression that contained the condition.
     */
    public function getExpression(): string
    {
        return $this->expression;
    }

    /**
     * Get detailed context for logging.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return [
            'variable' => $this->variableName,
            'value' => $this->actualValue,
            'condition' => $this->condition,
            'expression' => $this->expression,
        ];
    }
}
