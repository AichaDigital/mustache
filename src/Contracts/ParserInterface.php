<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Contracts;

interface ParserInterface
{
    /**
     * Parse a template string and extract all mustache tokens.
     *
     * @param  string  $template  The template string containing mustaches
     * @return TokenInterface[] Array of parsed tokens
     */
    public function parse(string $template): array;

    /**
     * Check if a template contains any mustache patterns.
     */
    public function hasMustaches(string $template): bool;

    /**
     * Extract raw mustache strings from a template.
     *
     * @return string[] Array of raw mustache strings (e.g., ["{{User.name}}", "{{now()}}"])
     */
    public function extractRaw(string $template): array;
}
