<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Formatter\Formatters;

use AichaDigital\MustacheResolver\Core\Formatter\AbstractFormatter;

/**
 * Converts string to uppercase.
 *
 * Usage: {{expression|uppercase}}
 */
final class UppercaseFormatter extends AbstractFormatter
{
    protected array $supportedTypes = ['string', 'int', 'float'];

    public function getName(): string
    {
        return 'uppercase';
    }

    public function format(mixed $value, array $arguments = []): string
    {
        return mb_strtoupper($this->toString($value));
    }
}
