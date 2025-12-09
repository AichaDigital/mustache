<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Formatter\Formatters;

use AichaDigital\MustacheResolver\Core\Formatter\AbstractFormatter;

/**
 * Rounds a number to specified precision.
 *
 * Usage: {{expression|round}} or {{expression|round:precision}}
 * Example: 12.567|round:2 → 12.57
 */
final class RoundFormatter extends AbstractFormatter
{
    protected array $supportedTypes = ['int', 'float', 'string'];

    public function getName(): string
    {
        return 'round';
    }

    public function format(mixed $value, array $arguments = []): float
    {
        $precision = (int) $this->getArgument($arguments, 0, 0);
        $numeric = $this->toNumeric($value);

        return round((float) $numeric, $precision);
    }
}
