<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Formatter\Formatters;

use AichaDigital\MustacheResolver\Core\Formatter\AbstractFormatter;
use DateTimeInterface;

/**
 * Converts a DateTime or timestamp to ISO 8601 format.
 *
 * Usage: {{expression|toIso8601}}
 */
final class ToIso8601Formatter extends AbstractFormatter
{
    protected array $supportedTypes = ['int', 'string', DateTimeInterface::class];

    public function getName(): string
    {
        return 'toIso8601';
    }

    public function format(mixed $value, array $arguments = []): string
    {
        $dateTime = $this->toDateTime($value);

        return $dateTime->format(\DateTimeInterface::ATOM);
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
