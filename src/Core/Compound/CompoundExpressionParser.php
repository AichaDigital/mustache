<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Compound;

use AichaDigital\MustacheResolver\Exceptions\InvalidUseSyntaxException;

/**
 * Parser for compound expressions with USE clauses.
 *
 * Syntax: USE {var} => {{mustache}} [condition] [, {var2} => {{mustache2}} [condition]] && <statement>
 */
final class CompoundExpressionParser
{
    /**
     * Pattern to detect compound expressions.
     * Note: (.*) allows empty statement which is validated separately.
     */
    private const USE_PATTERN = '/^USE\s+(.+?)\s*&&\s*(.*)$/s';

    /**
     * Pattern to parse individual variable declarations.
     * Captures: 1=varname, 2=mustache expression, 3=optional condition
     */
    private const VAR_PATTERN = '/\{(\w+)\}\s*=>\s*(\{\{[^}]+\}\})(\s*(?:[><=!]+|BETWEEN)\s*[^\s,]+(?:\s+AND\s+[^\s,]+)?)?/i';

    /**
     * Check if a template is a compound expression.
     */
    public function isCompound(string $template): bool
    {
        return str_starts_with(trim($template), 'USE ');
    }

    /**
     * Parse a compound expression into its components.
     *
     * @throws InvalidUseSyntaxException
     */
    public function parse(string $template): CompoundExpression
    {
        $trimmed = trim($template);

        if (! $this->isCompound($trimmed)) {
            throw new InvalidUseSyntaxException(
                $template,
                'Template must start with "USE "'
            );
        }

        if (! preg_match(self::USE_PATTERN, $trimmed, $matches)) {
            throw new InvalidUseSyntaxException(
                $template,
                'Missing "&&" separator between USE clause and statement'
            );
        }

        $useBlock = trim($matches[1]);
        $statement = trim($matches[2]);

        if (empty($statement)) {
            throw new InvalidUseSyntaxException(
                $template,
                'Statement after "&&" cannot be empty'
            );
        }

        $variables = $this->parseVariables($useBlock, $template);

        if (empty($variables)) {
            throw new InvalidUseSyntaxException(
                $template,
                'USE clause must declare at least one variable'
            );
        }

        return new CompoundExpression($variables, $statement, $template);
    }

    /**
     * Parse variable declarations from the USE block.
     *
     * @return UseVariable[]
     *
     * @throws InvalidUseSyntaxException
     */
    private function parseVariables(string $useBlock, string $originalTemplate): array
    {
        preg_match_all(self::VAR_PATTERN, $useBlock, $matches, PREG_SET_ORDER);

        if (empty($matches)) {
            throw new InvalidUseSyntaxException(
                $originalTemplate,
                'Invalid variable declaration format. Expected: {varname} => {{expression}}'
            );
        }

        $variables = [];
        $seenNames = [];

        foreach ($matches as $match) {
            $name = $match[1];
            $expression = $match[2];
            $condition = isset($match[3]) ? trim($match[3]) : null;

            if (empty($condition)) {
                $condition = null;
            }

            // Check for duplicate variable names
            if (isset($seenNames[$name])) {
                throw new InvalidUseSyntaxException(
                    $originalTemplate,
                    sprintf('Duplicate variable name: {%s}', $name)
                );
            }
            $seenNames[$name] = true;

            $variables[] = new UseVariable($name, $expression, $condition);
        }

        return $variables;
    }

    /**
     * Extract local variable references from a statement.
     * Returns variable names found as {varname}.
     *
     * @return string[]
     */
    public function extractLocalVariables(string $statement): array
    {
        preg_match_all('/\{(\w+)\}/', $statement, $matches);

        return array_unique($matches[1]);
    }

    /**
     * Validate that all local variables in statement are declared in USE clause.
     *
     * @param  string[]  $declaredVariables
     * @param  string[]  $usedVariables
     * @return string[] List of undeclared variables
     */
    public function findUndeclaredVariables(array $declaredVariables, array $usedVariables): array
    {
        return array_diff($usedVariables, $declaredVariables);
    }
}
