<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Formatter\Formatters;

use AichaDigital\MustacheResolver\Core\Formatter\AbstractFormatter;

/**
 * Returns the absolute value of a number.
 *
 * Usage: {{expression|abs}}
 * Example: -42.5 → 42.5
 */
final class AbsFormatter extends AbstractFormatter
{
    protected array $supportedTypes = ['int', 'float', 'string'];

    public function getName(): string
    {
        return 'abs';
    }

    public function format(mixed $value, array $arguments = []): int|float
    {
        $numeric = $this->toNumeric($value);

        return abs($numeric);
    }
}
