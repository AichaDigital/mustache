<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Formatter\Formatters;

use AichaDigital\MustacheResolver\Core\Formatter\AbstractFormatter;

/**
 * Rounds a number up (ceiling).
 *
 * Usage: {{expression|ceil}}
 * Example: 12.1 → 13
 */
final class CeilFormatter extends AbstractFormatter
{
    protected array $supportedTypes = ['int', 'float', 'string'];

    public function getName(): string
    {
        return 'ceil';
    }

    public function format(mixed $value, array $arguments = []): int
    {
        $numeric = $this->toNumeric($value);

        return (int) ceil((float) $numeric);
    }
}
