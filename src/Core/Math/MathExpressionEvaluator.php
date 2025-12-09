<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Math;

use AichaDigital\MustacheResolver\Exceptions\MathExpressionException;

/**
 * Safe evaluator for simple mathematical expressions.
 *
 * Supports: +, -, *, /, parentheses
 * Does NOT use eval() - implements a simple recursive descent parser
 */
final class MathExpressionEvaluator
{
    /**
     * Maximum expression length for security.
     */
    private const MAX_LENGTH = 500;

    /**
     * Maximum nesting depth for parentheses.
     */
    private const MAX_DEPTH = 10;

    /**
     * Current position in the expression.
     */
    private int $position = 0;

    /**
     * The expression being parsed.
     */
    private string $expression = '';

    /**
     * Current nesting depth.
     */
    private int $depth = 0;

    /**
     * Evaluate a mathematical expression.
     *
     * @throws MathExpressionException
     */
    public function evaluate(string $expression): int|float
    {
        $expression = $this->sanitize($expression);

        if (strlen($expression) > self::MAX_LENGTH) {
            throw MathExpressionException::tooLong($expression, self::MAX_LENGTH);
        }

        if (empty($expression)) {
            return 0;
        }

        $this->expression = $expression;
        $this->position = 0;
        $this->depth = 0;

        $result = $this->parseExpression();

        // Ensure we consumed the entire expression
        $this->skipWhitespace();
        if ($this->position < strlen($this->expression)) {
            throw MathExpressionException::invalidOperator(
                $expression,
                $this->expression[$this->position],
            );
        }

        return $result;
    }

    /**
     * Check if a string contains a mathematical expression.
     */
    public function hasExpression(string $value): bool
    {
        // Check for mathematical operators outside of simple numbers
        return (bool) preg_match('/[\+\-\*\/\(\)]/', $value);
    }

    /**
     * Sanitize the expression by removing whitespace and validating characters.
     *
     * @throws MathExpressionException
     */
    private function sanitize(string $expression): string
    {
        // Only allow numbers, operators, parentheses, decimal points, and whitespace
        if (preg_match('/[^0-9\s\+\-\*\/\(\)\.]/', $expression)) {
            throw MathExpressionException::invalidOperator(
                $expression,
                preg_replace('/[0-9\s\+\-\*\/\(\)\.]/', '', $expression) ?? '',
            );
        }

        return trim($expression);
    }

    /**
     * Parse an expression (handles + and -).
     */
    private function parseExpression(): int|float
    {
        $left = $this->parseTerm();

        while (true) {
            $this->skipWhitespace();

            if ($this->position >= strlen($this->expression)) {
                break;
            }

            $operator = $this->expression[$this->position];

            if ($operator === '+') {
                $this->position++;
                $left += $this->parseTerm();
            } elseif ($operator === '-') {
                $this->position++;
                $left -= $this->parseTerm();
            } else {
                break;
            }
        }

        return $left;
    }

    /**
     * Parse a term (handles * and /).
     */
    private function parseTerm(): int|float
    {
        $left = $this->parseFactor();

        while (true) {
            $this->skipWhitespace();

            if ($this->position >= strlen($this->expression)) {
                break;
            }

            $operator = $this->expression[$this->position];

            if ($operator === '*') {
                $this->position++;
                $left *= $this->parseFactor();
            } elseif ($operator === '/') {
                $this->position++;
                $divisor = $this->parseFactor();

                if ($divisor == 0) {
                    throw MathExpressionException::divisionByZero($this->expression);
                }

                $left /= $divisor;
            } else {
                break;
            }
        }

        return $left;
    }

    /**
     * Parse a factor (numbers and parentheses).
     */
    private function parseFactor(): int|float
    {
        $this->skipWhitespace();

        // Handle negative numbers
        if ($this->position < strlen($this->expression) && $this->expression[$this->position] === '-') {
            $this->position++;

            return -$this->parseFactor();
        }

        // Handle positive sign
        if ($this->position < strlen($this->expression) && $this->expression[$this->position] === '+') {
            $this->position++;

            return $this->parseFactor();
        }

        // Handle parentheses
        if ($this->position < strlen($this->expression) && $this->expression[$this->position] === '(') {
            $this->depth++;

            if ($this->depth > self::MAX_DEPTH) {
                throw MathExpressionException::tooDeep($this->expression, self::MAX_DEPTH);
            }

            $this->position++;
            $result = $this->parseExpression();
            $this->skipWhitespace();

            if ($this->position >= strlen($this->expression) || $this->expression[$this->position] !== ')') {
                throw MathExpressionException::invalidOperator($this->expression, 'missing closing parenthesis');
            }

            $this->position++;
            $this->depth--;

            return $result;
        }

        return $this->parseNumber();
    }

    /**
     * Parse a number.
     */
    private function parseNumber(): int|float
    {
        $this->skipWhitespace();

        $start = $this->position;
        $hasDecimal = false;

        while ($this->position < strlen($this->expression)) {
            $char = $this->expression[$this->position];

            if (is_numeric($char)) {
                $this->position++;
            } elseif ($char === '.' && ! $hasDecimal) {
                $hasDecimal = true;
                $this->position++;
            } else {
                break;
            }
        }

        if ($start === $this->position) {
            throw MathExpressionException::invalidOperator(
                $this->expression,
                'expected number at position '.$this->position,
            );
        }

        $numberStr = substr($this->expression, $start, $this->position - $start);

        return $hasDecimal ? (float) $numberStr : (int) $numberStr;
    }

    /**
     * Skip whitespace characters.
     */
    private function skipWhitespace(): void
    {
        while ($this->position < strlen($this->expression) && ctype_space($this->expression[$this->position])) {
            $this->position++;
        }
    }
}
