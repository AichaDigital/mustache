<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Formatter\Formatters;

use AichaDigital\MustacheResolver\Core\Formatter\AbstractFormatter;
use DateTimeInterface;

/**
 * Converts a DateTime or date string to Unix timestamp.
 *
 * Usage: {{expression|toUnixTime}}
 */
final class ToUnixTimeFormatter extends AbstractFormatter
{
    protected array $supportedTypes = ['int', 'string', DateTimeInterface::class];

    public function getName(): string
    {
        return 'toUnixTime';
    }

    public function format(mixed $value, array $arguments = []): int
    {
        if (is_int($value)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }

        return (new \DateTimeImmutable($value))->getTimestamp();
    }
}
