<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Temporal;

use AichaDigital\MustacheResolver\Exceptions\InvalidSyntaxException;

/**
 * Parser for temporal expressions.
 *
 * Parses expressions like "weekday && 08:00-18:00 && !holiday" into an AST.
 *
 * Syntax:
 * - Keywords: always, weekday, weekend, never
 * - Time ranges: HH:MM-HH:MM
 * - CRON: cron:EXPRESSION
 * - Nth weekday: nth:DAY:N or nth:DAY:N,M
 * - Last weekday: last:DAY
 * - Operators: && (AND), || (OR), ! (NOT)
 * - Grouping: ( )
 */
final class ExpressionParser
{
    private const string TOKEN_AND = '&&';

    private const string TOKEN_OR = '||';

    private const string TOKEN_NOT = '!';

    private const string TOKEN_LPAREN = '(';

    private const string TOKEN_RPAREN = ')';

    private string $expression;

    /** @var array<int, array{type: string, value: string}> */
    private array $tokens = [];

    private int $position = 0;

    public function __construct(string $expression)
    {
        $this->expression = trim($expression);
    }

    /**
     * Parse the expression into an AST node.
     *
     * @return array<string, mixed> The AST node
     */
    public function parse(): array
    {
        if ($this->expression === '') {
            return ['type' => 'literal', 'value' => 'always'];
        }

        $this->tokenize();
        $this->position = 0;

        $ast = $this->parseOr();

        if ($this->position < count($this->tokens)) {
            throw new InvalidSyntaxException(
                sprintf('Unexpected token at position %d: "%s"', $this->position, $this->tokens[$this->position]['value'])
            );
        }

        return $ast;
    }

    /**
     * Extract all condition keywords from the expression.
     *
     * @return array<int, string>
     */
    public function extractKeywords(): array
    {
        $this->tokenize();
        $keywords = [];

        foreach ($this->tokens as $token) {
            if ($token['type'] === 'condition') {
                $keywords[] = $token['value'];
            }
        }

        return array_unique($keywords);
    }

    private function tokenize(): void
    {
        $this->tokens = [];
        $expression = $this->expression;
        $length = strlen($expression);
        $i = 0;

        while ($i < $length) {
            // Skip whitespace
            if (ctype_space($expression[$i])) {
                $i++;

                continue;
            }

            // AND operator
            if (substr($expression, $i, 2) === self::TOKEN_AND) {
                $this->tokens[] = ['type' => 'operator', 'value' => self::TOKEN_AND];
                $i += 2;

                continue;
            }

            // OR operator
            if (substr($expression, $i, 2) === self::TOKEN_OR) {
                $this->tokens[] = ['type' => 'operator', 'value' => self::TOKEN_OR];
                $i += 2;

                continue;
            }

            // NOT operator
            if ($expression[$i] === self::TOKEN_NOT) {
                $this->tokens[] = ['type' => 'operator', 'value' => self::TOKEN_NOT];
                $i++;

                continue;
            }

            // Left parenthesis
            if ($expression[$i] === self::TOKEN_LPAREN) {
                $this->tokens[] = ['type' => 'lparen', 'value' => self::TOKEN_LPAREN];
                $i++;

                continue;
            }

            // Right parenthesis
            if ($expression[$i] === self::TOKEN_RPAREN) {
                $this->tokens[] = ['type' => 'rparen', 'value' => self::TOKEN_RPAREN];
                $i++;

                continue;
            }

            // CRON expression: cron:... (special handling for spaces in cron)
            if (substr($expression, $i, 5) === 'cron:') {
                $start = $i + 5;
                $end = $this->findCronEnd($expression, $start);
                $cronExpr = trim(substr($expression, $start, $end - $start));
                $this->tokens[] = ['type' => 'condition', 'value' => 'cron:'.$cronExpr];
                $i = $end;

                continue;
            }

            // Nth weekday: nth:day:N or nth:day:N,M
            if (substr($expression, $i, 4) === 'nth:') {
                $start = $i;
                $end = $this->findConditionEnd($expression, $i);
                $nthExpr = substr($expression, $start, $end - $start);
                $this->tokens[] = ['type' => 'condition', 'value' => $nthExpr];
                $i = $end;

                continue;
            }

            // Last weekday: last:day
            if (substr($expression, $i, 5) === 'last:') {
                $start = $i;
                $end = $this->findConditionEnd($expression, $i);
                $lastExpr = substr($expression, $start, $end - $start);
                $this->tokens[] = ['type' => 'condition', 'value' => $lastExpr];
                $i = $end;

                continue;
            }

            // Time range: HH:MM-HH:MM
            if (preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9])-([01]?[0-9]|2[0-3]):([0-5][0-9])/', substr($expression, $i), $matches)) {
                $this->tokens[] = ['type' => 'condition', 'value' => $matches[0]];
                $i += strlen($matches[0]);

                continue;
            }

            // Keyword or custom condition (alphanumeric + underscore)
            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*/', substr($expression, $i), $matches)) {
                $this->tokens[] = ['type' => 'condition', 'value' => $matches[0]];
                $i += strlen($matches[0]);

                continue;
            }

            throw new InvalidSyntaxException(
                sprintf('Unexpected character at position %d: "%s"', $i, $expression[$i])
            );
        }
    }

    private function findConditionEnd(string $expression, int $start): int
    {
        $length = strlen($expression);
        $i = $start;

        while ($i < $length) {
            $char = $expression[$i];

            // End on whitespace, operators, or parentheses
            if (ctype_space($char) ||
                $char === self::TOKEN_LPAREN ||
                $char === self::TOKEN_RPAREN ||
                substr($expression, $i, 2) === self::TOKEN_AND ||
                substr($expression, $i, 2) === self::TOKEN_OR) {
                break;
            }

            $i++;
        }

        return $i;
    }

    /**
     * Find the end of a CRON expression (allows spaces within).
     *
     * CRON expressions have 5 parts separated by spaces, so we can't stop at spaces.
     * We stop at logical operators (&&, ||) or parentheses.
     */
    private function findCronEnd(string $expression, int $start): int
    {
        $length = strlen($expression);
        $i = $start;

        while ($i < $length) {
            // End on logical operators or parentheses
            if ($expression[$i] === self::TOKEN_LPAREN ||
                $expression[$i] === self::TOKEN_RPAREN ||
                substr($expression, $i, 2) === self::TOKEN_AND ||
                substr($expression, $i, 2) === self::TOKEN_OR) {
                break;
            }

            $i++;
        }

        return $i;
    }

    /**
     * Parse OR expressions (lowest precedence).
     *
     * @return array<string, mixed>
     */
    private function parseOr(): array
    {
        $left = $this->parseAnd();

        while ($this->currentTokenIs('operator', self::TOKEN_OR)) {
            $this->position++;
            $right = $this->parseAnd();
            $left = [
                'type' => 'or',
                'left' => $left,
                'right' => $right,
            ];
        }

        return $left;
    }

    /**
     * Parse AND expressions (medium precedence).
     *
     * @return array<string, mixed>
     */
    private function parseAnd(): array
    {
        $left = $this->parseNot();

        while ($this->currentTokenIs('operator', self::TOKEN_AND)) {
            $this->position++;
            $right = $this->parseNot();
            $left = [
                'type' => 'and',
                'left' => $left,
                'right' => $right,
            ];
        }

        return $left;
    }

    /**
     * Parse NOT expressions (highest precedence for unary).
     *
     * @return array<string, mixed>
     */
    private function parseNot(): array
    {
        if ($this->currentTokenIs('operator', self::TOKEN_NOT)) {
            $this->position++;

            return [
                'type' => 'not',
                'operand' => $this->parseNot(),
            ];
        }

        return $this->parsePrimary();
    }

    /**
     * Parse primary expressions (conditions and grouped expressions).
     *
     * @return array<string, mixed>
     */
    private function parsePrimary(): array
    {
        // Grouped expression
        if ($this->currentTokenIs('lparen')) {
            $this->position++;
            $expr = $this->parseOr();

            if (! $this->currentTokenIs('rparen')) {
                throw new InvalidSyntaxException('Missing closing parenthesis');
            }

            $this->position++;

            return $expr;
        }

        // Condition
        if ($this->position < count($this->tokens) && $this->tokens[$this->position]['type'] === 'condition') {
            $value = $this->tokens[$this->position]['value'];
            $this->position++;

            return $this->parseCondition($value);
        }

        throw new InvalidSyntaxException(
            sprintf(
                'Expected condition or grouped expression at position %d, got: %s',
                $this->position,
                $this->position < count($this->tokens) ? $this->tokens[$this->position]['value'] : 'end of expression'
            )
        );
    }

    /**
     * Parse a single condition value into its specific type.
     *
     * @return array<string, mixed>
     */
    private function parseCondition(string $value): array
    {
        // CRON expression
        if (str_starts_with($value, 'cron:')) {
            return [
                'type' => 'cron',
                'expression' => substr($value, 5),
            ];
        }

        // Nth weekday
        if (str_starts_with($value, 'nth:')) {
            return $this->parseNthWeekday($value);
        }

        // Last weekday
        if (str_starts_with($value, 'last:')) {
            return [
                'type' => 'last_weekday',
                'day' => substr($value, 5),
            ];
        }

        // Time range
        if (preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9])-([01]?[0-9]|2[0-3]):([0-5][0-9])$/', $value)) {
            return [
                'type' => 'time_range',
                'range' => $value,
            ];
        }

        // Built-in keywords
        $builtInKeywords = ['always', 'never', 'weekday', 'weekend'];
        if (in_array($value, $builtInKeywords, true)) {
            return [
                'type' => 'keyword',
                'keyword' => $value,
            ];
        }

        // Custom condition (registered evaluator)
        return [
            'type' => 'custom',
            'keyword' => $value,
        ];
    }

    /**
     * Parse nth:day:N or nth:day:N,M syntax.
     *
     * @return array<string, mixed>
     */
    private function parseNthWeekday(string $value): array
    {
        $parts = explode(':', $value);

        if (count($parts) !== 3) {
            throw new InvalidSyntaxException(
                sprintf('Invalid nth weekday syntax: "%s". Expected nth:DAY:N or nth:DAY:N,M', $value)
            );
        }

        $day = $parts[1];
        $occurrencesPart = $parts[2];

        // Parse occurrences (can be single or comma-separated)
        $occurrences = array_map(
            fn ($o) => (int) trim($o),
            explode(',', $occurrencesPart)
        );

        return [
            'type' => 'nth_weekday',
            'day' => $day,
            'occurrences' => $occurrences,
        ];
    }

    private function currentTokenIs(string $type, ?string $value = null): bool
    {
        if ($this->position >= count($this->tokens)) {
            return false;
        }

        $token = $this->tokens[$this->position];

        if ($token['type'] !== $type) {
            return false;
        }

        if ($value !== null && $token['value'] !== $value) {
            return false;
        }

        return true;
    }
}
