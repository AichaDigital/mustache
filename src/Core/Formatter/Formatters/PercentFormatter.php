<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Formatter\Formatters;

use AichaDigital\MustacheResolver\Core\Formatter\AbstractFormatter;

/**
 * Formats a decimal as percentage.
 *
 * Usage: {{expression|percent}} or {{expression|percent:decimals}}
 * Example: 0.856|percent:1 → 85.6%
 */
final class PercentFormatter extends AbstractFormatter
{
    protected array $supportedTypes = ['int', 'float', 'string'];

    public function getName(): string
    {
        return 'percent';
    }

    public function format(mixed $value, array $arguments = []): string
    {
        $decimals = (int) $this->getArgument($arguments, 0, 0);
        $numeric = $this->toNumeric($value);

        return number_format((float) $numeric * 100, $decimals).'%';
    }
}
