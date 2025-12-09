<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Parser;

use AichaDigital\MustacheResolver\Contracts\ParserInterface;
use AichaDigital\MustacheResolver\Contracts\TokenInterface;
use AichaDigital\MustacheResolver\Core\Token\Token;
use AichaDigital\MustacheResolver\Core\Token\TokenCollection;
use AichaDigital\MustacheResolver\Exceptions\InvalidSyntaxException;

/**
 * Parses template strings and extracts mustache tokens.
 */
final class MustacheParser implements ParserInterface
{
    private const PATTERN = '/\{\{([^{}]+)\}\}/';

    /**
     * Parse a template string and extract all mustache tokens.
     *
     * @return TokenInterface[]
     */
    public function parse(string $template): array
    {
        $this->validateSyntax($template);

        $rawMustaches = $this->extractRaw($template);
        $tokens = [];

        foreach ($rawMustaches as $mustache) {
            // Extract content without braces
            $content = substr($mustache, 2, -2);
            $content = trim($content);

            if ($content !== '') {
                $tokens[] = Token::fromString($content);
            }
        }

        return $tokens;
    }

    /**
     * Check if a template contains any mustache patterns.
     */
    public function hasMustaches(string $template): bool
    {
        return (bool) preg_match(self::PATTERN, $template);
    }

    /**
     * Extract raw mustache strings from a template.
     *
     * @return string[]
     */
    public function extractRaw(string $template): array
    {
        preg_match_all(self::PATTERN, $template, $matches);

        return $matches[0] ?? [];
    }

    /**
     * Parse and return as a TokenCollection.
     */
    public function parseToCollection(string $template): TokenCollection
    {
        return TokenCollection::fromArray($this->parse($template));
    }

    /**
     * Validate mustache syntax in template.
     *
     * @throws InvalidSyntaxException
     */
    private function validateSyntax(string $template): void
    {
        // Check for unclosed mustaches
        $openCount = substr_count($template, '{{');
        $closeCount = substr_count($template, '}}');

        if ($openCount !== $closeCount) {
            $position = $openCount > $closeCount
                ? strrpos($template, '{{')
                : strrpos($template, '}}');

            throw InvalidSyntaxException::unclosedMustache($template, (int) $position);
        }

        // Check for empty mustaches
        if (preg_match('/\{\{\s*\}\}/', $template, $matches, PREG_OFFSET_CAPTURE)) {
            throw InvalidSyntaxException::emptyMustache($template, (int) $matches[0][1]);
        }

        // Check for nested mustaches
        if (preg_match('/\{\{[^}]*\{\{/', $template, $matches, PREG_OFFSET_CAPTURE)) {
            throw InvalidSyntaxException::nestedMustache($template, (int) $matches[0][1]);
        }
    }
}
