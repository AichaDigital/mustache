<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Formatter\Formatters;

use AichaDigital\MustacheResolver\Core\Formatter\AbstractFormatter;

/**
 * Formats a number with thousands separator and decimal places.
 *
 * Usage: {{expression|number}} or {{expression|number:decimals:decPoint:thousandsSep}}
 * Example: 1234567.89|number:2:,: → 1 234 567,89
 */
final class NumberFormatter extends AbstractFormatter
{
    protected array $supportedTypes = ['int', 'float', 'string'];

    public function getName(): string
    {
        return 'number';
    }

    public function format(mixed $value, array $arguments = []): string
    {
        $decimals = (int) $this->getArgument($arguments, 0, 2);
        $decPoint = (string) $this->getArgument($arguments, 1, '.');
        $thousandsSep = (string) $this->getArgument($arguments, 2, ',');

        $numeric = $this->toNumeric($value);

        return number_format((float) $numeric, $decimals, $decPoint, $thousandsSep);
    }
}
