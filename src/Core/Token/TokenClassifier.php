<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Token;

/**
 * Classifies raw mustache strings into typed tokens.
 *
 * This class analyzes the structure of a mustache expression
 * and determines its type based on syntax patterns.
 */
final class TokenClassifier
{
    private const COLLECTION_KEYWORDS = ['first', 'last'];

    /**
     * Classify a raw mustache string into a Token.
     */
    public function classify(string $raw): Token
    {
        $raw = trim($raw);

        // Check for null coalesce first (has highest specificity)
        if ($this->isNullCoalesce($raw)) {
            return $this->createNullCoalesceToken($raw);
        }

        // Check for temporal expression (TEMPORAL:, NOW:, TODAY:)
        if ($this->isTemporal($raw)) {
            return $this->createTemporalToken($raw);
        }

        // Check for function call
        if ($this->isFunction($raw)) {
            return $this->createFunctionToken($raw);
        }

        // Check for variable reference ($varName at start)
        if ($this->isVariable($raw)) {
            return $this->createVariableToken($raw);
        }

        // Check for math expression
        if ($this->isMath($raw)) {
            return $this->createMathToken($raw);
        }

        // Parse as path-based token
        return $this->classifyPathToken($raw);
    }

    private function isNullCoalesce(string $raw): bool
    {
        return str_contains($raw, '??');
    }

    private function isFunction(string $raw): bool
    {
        // Matches: functionName(...) but not $var or Model.field
        return (bool) preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*\s*\(/', $raw);
    }

    private function isVariable(string $raw): bool
    {
        // Starts with $ and is not part of a path (no dots before $)
        return str_starts_with($raw, '$') && ! str_contains($raw, '.');
    }

    private function isMath(string $raw): bool
    {
        // Contains arithmetic operators outside of strings
        // Simple check: has +, -, *, / with spaces around them
        return (bool) preg_match('/[\d\s]\s*[+\-*\/]\s*[\d\s]/', $raw);
    }

    private function createNullCoalesceToken(string $raw): Token
    {
        $parts = explode('??', $raw, 2);
        $expression = trim($parts[0]);
        $defaultValue = isset($parts[1]) ? trim($parts[1], " \t\n\r\0\x0B'\"") : null;

        $path = $this->parsePath($expression);

        return Token::create(
            raw: $raw,
            type: TokenType::NULL_COALESCE,
            path: $path,
            defaultValue: $defaultValue,
        );
    }

    private function createFunctionToken(string $raw): Token
    {
        // Extract function name and arguments
        if (! preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*\((.*)\)$/s', $raw, $matches)) {
            return Token::create($raw, TokenType::UNKNOWN, []);
        }

        $functionName = $matches[1];
        $argsString = trim($matches[2]);
        $args = $this->parseFunctionArgs($argsString);

        return Token::create(
            raw: $raw,
            type: TokenType::FUNCTION,
            path: [],
            functionName: $functionName,
            functionArgs: $args,
        );
    }

    private function createVariableToken(string $raw): Token
    {
        $varName = substr($raw, 1); // Remove leading $

        return Token::create(
            raw: $raw,
            type: TokenType::VARIABLE,
            path: [$varName],
        );
    }

    private function createMathToken(string $raw): Token
    {
        return Token::create(
            raw: $raw,
            type: TokenType::MATH,
            path: [],
            metadata: ['expression' => $raw],
        );
    }

    private function isTemporal(string $raw): bool
    {
        return str_starts_with($raw, 'TEMPORAL:')
            || str_starts_with($raw, 'NOW:')
            || str_starts_with($raw, 'NOW')
            || str_starts_with($raw, 'TODAY:')
            || str_starts_with($raw, 'TODAY');
    }

    private function createTemporalToken(string $raw): Token
    {
        // Determine the temporal type and extract the expression
        $temporalType = 'unknown';
        $expression = '';
        $functionName = null;
        $functionArgs = [];

        if (str_starts_with($raw, 'TEMPORAL:')) {
            $temporalType = 'temporal';
            $rest = substr($raw, 9); // Remove 'TEMPORAL:'

            // Parse function call: isDue('weekday && 08:00-18:00')
            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*\((.*)\)$/s', $rest, $matches)) {
                $functionName = $matches[1];
                $expression = trim($matches[2], " \t\n\r\0\x0B'\"");
                $functionArgs = [$expression];
            } else {
                $expression = $rest;
            }
        } elseif (str_starts_with($raw, 'NOW:')) {
            $temporalType = 'now';
            $rest = substr($raw, 4); // Remove 'NOW:'

            // Parse format or property: format('Y-m-d') or timestamp
            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*\((.*)\)$/s', $rest, $matches)) {
                $functionName = $matches[1];
                $expression = trim($matches[2], " \t\n\r\0\x0B'\"");
                $functionArgs = [$expression];
            } else {
                $functionName = $rest;
            }
        } elseif ($raw === 'NOW') {
            $temporalType = 'now';
            $functionName = 'default';
        } elseif (str_starts_with($raw, 'TODAY:')) {
            $temporalType = 'today';
            $rest = substr($raw, 6); // Remove 'TODAY:'

            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*\((.*)\)$/s', $rest, $matches)) {
                $functionName = $matches[1];
                $expression = trim($matches[2], " \t\n\r\0\x0B'\"");
                $functionArgs = [$expression];
            } else {
                $functionName = $rest;
            }
        } elseif ($raw === 'TODAY') {
            $temporalType = 'today';
            $functionName = 'default';
        }

        return Token::create(
            raw: $raw,
            type: TokenType::TEMPORAL,
            path: [],
            functionName: $functionName,
            functionArgs: $functionArgs,
            metadata: [
                'temporal_type' => $temporalType,
                'expression' => $expression,
            ],
        );
    }

    private function classifyPathToken(string $raw): Token
    {
        $path = $this->parsePath($raw);

        if (empty($path)) {
            return Token::create($raw, TokenType::UNKNOWN, []);
        }

        $prefix = $path[0];
        $type = $this->determinePathType($path, $prefix);

        return Token::create(
            raw: $raw,
            type: $type,
            path: $path,
        );
    }

    /**
     * @return array<int, string>
     */
    private function parsePath(string $expression): array
    {
        // Split by dots, but not dots inside brackets or quotes
        $segments = preg_split('/\.(?![^\[]*\])/', $expression);

        if ($segments === false) {
            return [];
        }

        return array_map('trim', $segments);
    }

    /**
     * @param  array<int, string>  $path
     */
    private function determinePathType(array $path, string $prefix): TokenType
    {
        // Check for dynamic field ($relation.field pattern)
        foreach ($path as $segment) {
            if (str_starts_with($segment, '$')) {
                return TokenType::DYNAMIC;
            }
        }

        // Check for collection access (numeric index or keywords)
        foreach ($path as $segment) {
            if (is_numeric($segment) || $segment === '*' || in_array($segment, self::COLLECTION_KEYWORDS, true)) {
                return TokenType::COLLECTION;
            }
        }

        // Check if prefix is PascalCase (Model) or snake_case (table)
        if ($this->isPascalCase($prefix)) {
            // If more than 2 segments, it's a relation chain
            if (count($path) > 2) {
                return TokenType::RELATION;
            }

            return TokenType::MODEL;
        }

        if ($this->isSnakeCase($prefix)) {
            return TokenType::TABLE;
        }

        return TokenType::UNKNOWN;
    }

    private function isPascalCase(string $value): bool
    {
        return (bool) preg_match('/^[A-Z][a-zA-Z0-9]*$/', $value);
    }

    private function isSnakeCase(string $value): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9_]*$/', $value);
    }

    /**
     * Parse function arguments string into array.
     *
     * @return array<int, mixed>
     */
    private function parseFunctionArgs(string $argsString): array
    {
        if ($argsString === '') {
            return [];
        }

        $args = [];
        $current = '';
        $depth = 0;
        $inString = false;
        $stringChar = '';

        for ($i = 0, $len = strlen($argsString); $i < $len; $i++) {
            $char = $argsString[$i];

            // Handle string boundaries
            if (($char === '"' || $char === "'") && ($i === 0 || $argsString[$i - 1] !== '\\')) {
                if (! $inString) {
                    $inString = true;
                    $stringChar = $char;
                } elseif ($char === $stringChar) {
                    $inString = false;
                }
            }

            // Track parentheses depth
            if (! $inString) {
                if ($char === '(') {
                    $depth++;
                } elseif ($char === ')') {
                    $depth--;
                }
            }

            // Split on comma at depth 0
            if ($char === ',' && $depth === 0 && ! $inString) {
                $args[] = $this->parseArgValue(trim($current));
                $current = '';
            } else {
                $current .= $char;
            }
        }

        // Add last argument
        if (trim($current) !== '') {
            $args[] = $this->parseArgValue(trim($current));
        }

        return $args;
    }

    /**
     * Parse a single argument value.
     */
    private function parseArgValue(string $value): mixed
    {
        // Remove surrounding quotes
        if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            return substr($value, 1, -1);
        }

        // Check for numeric
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        // Check for boolean
        if (strtolower($value) === 'true') {
            return true;
        }
        if (strtolower($value) === 'false') {
            return false;
        }

        // Check for null
        if (strtolower($value) === 'null') {
            return null;
        }

        // Return as field reference string
        return $value;
    }
}
