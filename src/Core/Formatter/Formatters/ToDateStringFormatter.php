<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Formatter\Formatters;

use AichaDigital\MustacheResolver\Core\Formatter\AbstractFormatter;
use DateTimeInterface;

/**
 * Converts a DateTime or timestamp to date string (Y-m-d).
 *
 * Usage: {{expression|toDateString}}
 */
final class ToDateStringFormatter extends AbstractFormatter
{
    protected array $supportedTypes = ['int', 'string', DateTimeInterface::class];

    public function getName(): string
    {
        return 'toDateString';
    }

    public function format(mixed $value, array $arguments = []): string
    {
        $dateTime = $this->toDateTime($value);

        return $dateTime->format('Y-m-d');
    }

    private function toDateTime(mixed $value): \DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        if (is_int($value)) {
            return (new \DateTimeImmutable)->setTimestamp($value);
        }

        return new \DateTimeImmutable($value);
    }
}
