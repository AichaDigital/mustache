<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Formatter\Formatters;

use AichaDigital\MustacheResolver\Core\Formatter\AbstractFormatter;
use DateTimeInterface;

/**
 * Converts a DateTime or timestamp to full datetime string (Y-m-d H:i:s).
 *
 * Usage: {{expression|toDateTime}}
 */
final class ToDateTimeFormatter extends AbstractFormatter
{
    protected array $supportedTypes = ['int', 'string', DateTimeInterface::class];

    public function getName(): string
    {
        return 'toDateTime';
    }

    public function format(mixed $value, array $arguments = []): string
    {
        $dateTime = $this->toDateTime($value);

        return $dateTime->format('Y-m-d H:i:s');
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
