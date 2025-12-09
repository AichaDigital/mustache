<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Formatter\Formatters;

use AichaDigital\MustacheResolver\Core\Formatter\AbstractFormatter;

/**
 * Converts cents to decimal amount (divides by 100).
 *
 * Usage: {{expression|fromCents}}
 * Example: 1250 → 12.50
 */
final class FromCentsFormatter extends AbstractFormatter
{
    protected array $supportedTypes = ['int', 'float', 'string'];

    public function getName(): string
    {
        return 'fromCents';
    }

    public function format(mixed $value, array $arguments = []): float
    {
        $numeric = $this->toNumeric($value);

        return $numeric / 100;
    }
}
