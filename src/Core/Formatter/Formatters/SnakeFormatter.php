<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Formatter\Formatters;

use AichaDigital\MustacheResolver\Core\Formatter\AbstractFormatter;

/**
 * Converts string to snake_case.
 *
 * Usage: {{expression|snake}}
 * Example: "helloWorld" → "hello_world"
 */
final class SnakeFormatter extends AbstractFormatter
{
    protected array $supportedTypes = ['string', 'int', 'float'];

    public function getName(): string
    {
        return 'snake';
    }

    public function format(mixed $value, array $arguments = []): string
    {
        $string = $this->toString($value);

        // Insert underscore before uppercase letters
        $string = preg_replace('/([a-z])([A-Z])/', '$1_$2', $string) ?? $string;

        // Replace non-alphanumeric with underscore
        $string = preg_replace('/[^a-zA-Z0-9]+/', '_', $string) ?? $string;

        // Convert to lowercase and trim underscores
        return trim(mb_strtolower($string), '_');
    }
}
