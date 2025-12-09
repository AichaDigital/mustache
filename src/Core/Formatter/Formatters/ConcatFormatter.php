<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Formatter\Formatters;

use AichaDigital\MustacheResolver\Core\Formatter\AbstractFormatter;

/**
 * Concatenates strings.
 *
 * Usage: {{expression|concat:suffix}} or {{expression|concat:prefix:suffix}}
 * Example: "Hello"|concat: World → "Hello World"
 */
final class ConcatFormatter extends AbstractFormatter
{
    protected array $supportedTypes = ['string', 'int', 'float'];

    public function getName(): string
    {
        return 'concat';
    }

    public function format(mixed $value, array $arguments = []): string
    {
        $string = $this->toString($value);

        if (count($arguments) === 0) {
            return $string;
        }

        if (count($arguments) === 1) {
            // Single argument: append as suffix
            return $string.$this->getArgument($arguments, 0, '');
        }

        // Two arguments: prefix and suffix
        $prefix = (string) $this->getArgument($arguments, 0, '');
        $suffix = (string) $this->getArgument($arguments, 1, '');

        return $prefix.$string.$suffix;
    }
}
