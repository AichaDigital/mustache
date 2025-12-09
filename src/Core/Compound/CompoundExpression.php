<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Compound;

/**
 * Represents a parsed compound expression (USE clause + statement).
 *
 * Example:
 * USE {max_power} => {{CommandCenter.max_power}} > 0 && SELECT * WHERE power < {max_power}
 */
final readonly class CompoundExpression
{
    /**
     * @param  UseVariable[]  $variables
     */
    public function __construct(
        private array $variables,
        private string $statement,
        private string $original,
    ) {}

    /**
     * Get all USE clause variables.
     *
     * @return UseVariable[]
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    /**
     * Get the statement part (after &&).
     */
    public function getStatement(): string
    {
        return $this->statement;
    }

    /**
     * Get the original template.
     */
    public function getOriginal(): string
    {
        return $this->original;
    }

    /**
     * Check if any variable has a condition.
     */
    public function hasConditions(): bool
    {
        foreach ($this->variables as $variable) {
            if ($variable->hasCondition()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get variable by name.
     */
    public function getVariable(string $name): ?UseVariable
    {
        foreach ($this->variables as $variable) {
            if ($variable->getName() === $name) {
                return $variable;
            }
        }

        return null;
    }

    /**
     * Get all variable names.
     *
     * @return string[]
     */
    public function getVariableNames(): array
    {
        return array_map(
            fn (UseVariable $v) => $v->getName(),
            $this->variables,
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'variables' => array_map(
                fn (UseVariable $v) => $v->toArray(),
                $this->variables,
            ),
            'statement' => $this->statement,
            'original' => $this->original,
        ];
    }
}
