<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Parser;

use AichaDigital\MustacheResolver\Contracts\ParserInterface;
use AichaDigital\MustacheResolver\Contracts\TokenInterface;
use AichaDigital\MustacheResolver\Core\Token\Token;
use AichaDigital\MustacheResolver\Core\Token\TokenCollection;
use AichaDigital\MustacheResolver\Exceptions\InvalidSyntaxException;
use AichaDigital\MustacheResolver\Exceptions\SecurityException;

/**
 * Parses template strings and extracts mustache tokens.
 */
final class MustacheParser implements ParserInterface
{
    private const PATTERN = '/\{\{([^{}]+)\}\}/';

    public const DEFAULT_MAX_TEMPLATE_LENGTH = 100_000;

    public const DEFAULT_MAX_TOKENS = 1_000;

    public function __construct(
        private readonly ?int $maxTemplateLength = self::DEFAULT_MAX_TEMPLATE_LENGTH,
        private readonly ?int $maxTokens = self::DEFAULT_MAX_TOKENS,
    ) {}

    /**
     * Parse a template string and extract all mustache tokens.
     *
     * @return TokenInterface[]
     */
    public function parse(string $template): array
    {
        if ($this->maxTemplateLength !== null && strlen($template) > $this->maxTemplateLength) {
            throw SecurityException::templateTooLong(strlen($template), $this->maxTemplateLength);
        }

        $this->validateSyntax($template);

        $rawMustaches = $this->extractRaw($template);

        if ($this->maxTokens !== null && count($rawMustaches) > $this->maxTokens) {
            throw SecurityException::tooManyTokens(count($rawMustaches), $this->maxTokens);
        }

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
     *
     * Stays unlimited on purpose: it changes no state and only gates
     * MustacheResolver::translate()'s early return, not resolution.
     * The length/token limits guard parse(), which does the actual work.
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
