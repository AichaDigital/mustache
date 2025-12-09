<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Exceptions;

/**
 * Thrown when a math expression is invalid or too complex.
 */
final class MathExpressionException extends SecurityException
{
    public function __construct(
        private readonly string $expression,
        private readonly string $reason,
    ) {
        parent::__construct(sprintf(
            'Invalid math expression "%s": %s',
            $expression,
            $reason,
        ));
    }

    /**
     * Get the expression that caused the error.
     */
    public function getExpression(): string
    {
        return $this->expression;
    }

    /**
     * Get the reason for the failure.
     */
    public function getReason(): string
    {
        return $this->reason;
    }

    /**
     * Create exception for expression that is too long.
     */
    public static function tooLong(string $expression, int $maxLength): self
    {
        return new self(
            $expression,
            sprintf('Expression exceeds maximum length of %d characters', $maxLength),
        );
    }

    /**
     * Create exception for expression that is too deeply nested.
     */
    public static function tooDeep(string $expression, int $maxDepth): self
    {
        return new self(
            $expression,
            sprintf('Expression exceeds maximum nesting depth of %d', $maxDepth),
        );
    }

    /**
     * Create exception for invalid operator.
     */
    public static function invalidOperator(string $expression, string $operator): self
    {
        return new self(
            $expression,
            sprintf('Operator "%s" is not allowed', $operator),
        );
    }

    /**
     * Create exception for division by zero.
     */
    public static function divisionByZero(string $expression): self
    {
        return new self($expression, 'Division by zero');
    }
}
