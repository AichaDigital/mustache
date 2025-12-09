<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Formatter\Formatters;

use AichaDigital\MustacheResolver\Core\Formatter\AbstractFormatter;

/**
 * Rounds a number down (floor).
 *
 * Usage: {{expression|floor}}
 * Example: 12.9 → 12
 */
final class FloorFormatter extends AbstractFormatter
{
    protected array $supportedTypes = ['int', 'float', 'string'];

    public function getName(): string
    {
        return 'floor';
    }

    public function format(mixed $value, array $arguments = []): int
    {
        $numeric = $this->toNumeric($value);

        return (int) floor((float) $numeric);
    }
}
