<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Formatter\Formatters;

use AichaDigital\MustacheResolver\Core\Formatter\AbstractFormatter;

/**
 * Replaces occurrences in a string.
 *
 * Usage: {{expression|replace:search:replacement}}
 * Example: "Hello World"|replace:World:Universe → "Hello Universe"
 */
final class ReplaceFormatter extends AbstractFormatter
{
    protected array $supportedTypes = ['string', 'int', 'float'];

    public function getName(): string
    {
        return 'replace';
    }

    public function format(mixed $value, array $arguments = []): string
    {
        $search = (string) $this->getArgument($arguments, 0, '');
        $replacement = (string) $this->getArgument($arguments, 1, '');
        $string = $this->toString($value);

        if ($search === '') {
            return $string;
        }

        return str_replace($search, $replacement, $string);
    }
}
