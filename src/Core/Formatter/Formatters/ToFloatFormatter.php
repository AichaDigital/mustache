<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Formatter\Formatters;

use AichaDigital\MustacheResolver\Core\Formatter\AbstractFormatter;

/**
 * Converts a value to float.
 *
 * Usage: {{expression|toFloat}}
 */
final class ToFloatFormatter extends AbstractFormatter
{
    protected array $supportedTypes = ['int', 'float', 'string', 'bool'];

    public function getName(): string
    {
        return 'toFloat';
    }

    public function format(mixed $value, array $arguments = []): float
    {
        return (float) $this->toNumeric($value);
    }
}
