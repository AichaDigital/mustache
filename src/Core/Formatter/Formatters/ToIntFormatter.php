<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Formatter\Formatters;

use AichaDigital\MustacheResolver\Core\Formatter\AbstractFormatter;

/**
 * Converts a value to integer.
 *
 * Usage: {{expression|toInt}}
 */
final class ToIntFormatter extends AbstractFormatter
{
    protected array $supportedTypes = ['int', 'float', 'string', 'bool'];

    public function getName(): string
    {
        return 'toInt';
    }

    public function format(mixed $value, array $arguments = []): int
    {
        return (int) $this->toNumeric($value);
    }
}
