<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Formatter\Formatters;

use AichaDigital\MustacheResolver\Core\Formatter\AbstractFormatter;

/**
 * Converts a decimal amount to cents (multiplies by 100).
 *
 * Usage: {{expression|toCents}}
 * Example: 12.50 → 1250
 */
final class ToCentsFormatter extends AbstractFormatter
{
    protected array $supportedTypes = ['int', 'float', 'string'];

    public function getName(): string
    {
        return 'toCents';
    }

    public function format(mixed $value, array $arguments = []): int
    {
        $numeric = $this->toNumeric($value);

        return (int) round($numeric * 100);
    }
}
