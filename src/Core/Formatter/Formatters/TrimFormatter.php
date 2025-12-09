<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Formatter\Formatters;

use AichaDigital\MustacheResolver\Core\Formatter\AbstractFormatter;

/**
 * Trims whitespace from string.
 *
 * Usage: {{expression|trim}}
 */
final class TrimFormatter extends AbstractFormatter
{
    protected array $supportedTypes = ['string', 'int', 'float'];

    public function getName(): string
    {
        return 'trim';
    }

    public function format(mixed $value, array $arguments = []): string
    {
        return trim($this->toString($value));
    }
}
