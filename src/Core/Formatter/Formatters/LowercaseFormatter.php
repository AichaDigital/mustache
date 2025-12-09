<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Formatter\Formatters;

use AichaDigital\MustacheResolver\Core\Formatter\AbstractFormatter;

/**
 * Converts string to lowercase.
 *
 * Usage: {{expression|lowercase}}
 */
final class LowercaseFormatter extends AbstractFormatter
{
    protected array $supportedTypes = ['string', 'int', 'float'];

    public function getName(): string
    {
        return 'lowercase';
    }

    public function format(mixed $value, array $arguments = []): string
    {
        return mb_strtolower($this->toString($value));
    }
}
