<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Exceptions;

/**
 * Thrown when a USE clause variable cannot be resolved.
 *
 * Example: USE {max_power} => {{NonExistent.field}}
 */
final class VariableNotResolvedException extends ResolutionException
{
    public function __construct(
        private readonly string $variableName,
        private readonly string $expression,
        private readonly ?string $reason = null,
    ) {
        $message = sprintf(
            'Could not resolve variable "%s" from expression "%s"',
            $variableName,
            $expression,
        );

        if ($reason !== null) {
            $message .= sprintf(': %s', $reason);
        }

        parent::__construct($message);
    }

    /**
     * Get the variable name that could not be resolved.
     */
    public function getVariableName(): string
    {
        return $this->variableName;
    }

    /**
     * Get the mustache expression that failed.
     */
    public function getExpression(): string
    {
        return $this->expression;
    }

    /**
     * Get the reason for the failure (if available).
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }
}
