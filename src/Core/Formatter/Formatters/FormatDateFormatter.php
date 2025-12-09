<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Formatter\Formatters;

use AichaDigital\MustacheResolver\Core\Formatter\AbstractFormatter;
use DateTimeInterface;

/**
 * Formats a DateTime with a custom format string.
 *
 * Usage: {{expression|formatDate:format}}
 * Example: {{created_at|formatDate:d/m/Y}}
 */
final class FormatDateFormatter extends AbstractFormatter
{
    protected array $supportedTypes = ['int', 'string', DateTimeInterface::class];

    public function getName(): string
    {
        return 'formatDate';
    }

    public function format(mixed $value, array $arguments = []): string
    {
        $format = $this->getArgument($arguments, 0, 'Y-m-d H:i:s');
        $dateTime = $this->toDateTime($value);

        return $dateTime->format((string) $format);
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
