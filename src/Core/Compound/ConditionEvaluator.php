<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Compound;

use AichaDigital\MustacheResolver\Exceptions\ConditionNotMetException;

/**
 * Evaluates conditions on resolved values.
 *
 * Supports comparison operators: =, ==, ===, !=, !==, <>, <, <=, >, >=
 * Supports BETWEEN operator: BETWEEN X AND Y
 */
final class ConditionEvaluator
{
    /**
     * Pattern for BETWEEN condition.
     */
    private const BETWEEN_PATTERN = '/^BETWEEN\s+(.+?)\s+AND\s+(.+)$/i';

    /**
     * Pattern for comparison operators.
     */
    private const COMPARISON_PATTERN = '/^([><=!]+)\s*(.+)$/';

    /**
     * Evaluate a condition against a value.
     *
     * @throws ConditionNotMetException If condition fails
     */
    public function evaluate(
        string $variableName,
        mixed $value,
        string $condition,
        string $expression,
    ): bool {
        $trimmedCondition = trim($condition);

        // Check for BETWEEN
        if (preg_match(self::BETWEEN_PATTERN, $trimmedCondition, $matches)) {
            return $this->evaluateBetween($variableName, $value, $matches[1], $matches[2], $expression);
        }

        // Check for comparison operators
        if (preg_match(self::COMPARISON_PATTERN, $trimmedCondition, $matches)) {
            return $this->evaluateComparison($variableName, $value, $matches[1], $matches[2], $expression);
        }

        // Unknown condition format - pass through
        return true;
    }

    /**
     * Evaluate a BETWEEN condition.
     *
     * @throws ConditionNotMetException
     */
    private function evaluateBetween(
        string $variableName,
        mixed $value,
        string $min,
        string $max,
        string $expression,
    ): bool {
        $numericValue = $this->toNumeric($value);
        $minValue = $this->toNumeric(trim($min));
        $maxValue = $this->toNumeric(trim($max));

        $condition = sprintf('BETWEEN %s AND %s', $min, $max);

        if ($numericValue < $minValue || $numericValue > $maxValue) {
            throw new ConditionNotMetException($variableName, $value, $condition, $expression);
        }

        return true;
    }

    /**
     * Evaluate a comparison condition.
     *
     * @throws ConditionNotMetException
     */
    private function evaluateComparison(
        string $variableName,
        mixed $value,
        string $operator,
        string $operand,
        string $expression,
    ): bool {
        $compareValue = $this->parseOperand(trim($operand));
        $condition = $operator.' '.$operand;

        $result = match ($operator) {
            '=' => $value == $compareValue,
            '==' => $value == $compareValue,
            '===' => $value === $compareValue,
            '!=' => $value != $compareValue,
            '!==' => $value !== $compareValue,
            '<>' => $value != $compareValue,
            '<' => $this->toNumeric($value) < $this->toNumeric($compareValue),
            '<=' => $this->toNumeric($value) <= $this->toNumeric($compareValue),
            '>' => $this->toNumeric($value) > $this->toNumeric($compareValue),
            '>=' => $this->toNumeric($value) >= $this->toNumeric($compareValue),
            default => true, // Unknown operator, pass through
        };

        if (! $result) {
            throw new ConditionNotMetException($variableName, $value, $condition, $expression);
        }

        return true;
    }

    /**
     * Check if condition is met without throwing exception.
     */
    public function check(mixed $value, string $condition): bool
    {
        try {
            return $this->evaluate('check', $value, $condition, '');
        } catch (ConditionNotMetException) {
            return false;
        }
    }

    /**
     * Parse an operand value (handle strings, numbers, booleans).
     */
    private function parseOperand(string $operand): mixed
    {
        // Check for quoted strings
        if (
            (str_starts_with($operand, '"') && str_ends_with($operand, '"')) ||
            (str_starts_with($operand, "'") && str_ends_with($operand, "'"))
        ) {
            return substr($operand, 1, -1);
        }

        // Check for boolean
        $lower = strtolower($operand);
        if ($lower === 'true') {
            return true;
        }
        if ($lower === 'false') {
            return false;
        }
        if ($lower === 'null') {
            return null;
        }

        // Check for numeric
        if (is_numeric($operand)) {
            return str_contains($operand, '.') ? (float) $operand : (int) $operand;
        }

        // Return as string
        return $operand;
    }

    /**
     * Convert value to numeric for comparison.
     */
    private function toNumeric(mixed $value): int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return 0;
    }
}
