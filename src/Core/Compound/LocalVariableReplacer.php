<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Compound;

/**
 * Replaces local variable references in statements.
 *
 * Takes resolved USE variable values and substitutes
 * {varname} references in the statement.
 */
final class LocalVariableReplacer
{
    /**
     * Replace all local variable references in a statement.
     *
     * @param  array<string, mixed>  $variables  Map of variable name to value
     */
    public function replace(string $statement, array $variables): string
    {
        $result = $statement;

        foreach ($variables as $name => $value) {
            $reference = '{'.$name.'}';
            $stringValue = $this->toString($value);

            $result = str_replace($reference, $stringValue, $result);
        }

        return $result;
    }

    /**
     * Check if statement contains any local variable references.
     */
    public function hasVariables(string $statement): bool
    {
        return (bool) preg_match('/\{[a-zA-Z_]\w*\}/', $statement);
    }

    /**
     * Extract all variable names from statement.
     *
     * @return string[]
     */
    public function extractVariableNames(string $statement): array
    {
        preg_match_all('/\{(\w+)\}/', $statement, $matches);

        return array_unique($matches[1]);
    }

    /**
     * Convert value to string for substitution.
     */
    private function toString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value)) {
            return json_encode($value) ?: '[]';
        }

        return (string) $value;
    }
}
