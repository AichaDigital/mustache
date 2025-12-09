<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Formatter\Formatters;

use AichaDigital\MustacheResolver\Core\Formatter\AbstractFormatter;

/**
 * Extracts a substring.
 *
 * Usage: {{expression|substr:start}} or {{expression|substr:start:length}}
 * Example: "Hello World"|substr:0:5 → "Hello"
 */
final class SubstrFormatter extends AbstractFormatter
{
    protected array $supportedTypes = ['string', 'int', 'float'];

    public function getName(): string
    {
        return 'substr';
    }

    public function format(mixed $value, array $arguments = []): string
    {
        $start = (int) $this->getArgument($arguments, 0, 0);
        $length = $this->getArgument($arguments, 1);
        $string = $this->toString($value);

        if ($length === null) {
            return mb_substr($string, $start);
        }

        return mb_substr($string, $start, (int) $length);
    }
}
