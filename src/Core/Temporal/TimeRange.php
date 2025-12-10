<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Temporal;

use AichaDigital\MustacheResolver\Contracts\TimeRangeInterface;
use AichaDigital\MustacheResolver\Exceptions\InvalidSyntaxException;
use Carbon\Carbon;
use DateTimeInterface;

/**
 * Evaluates time ranges within a day.
 *
 * Supports standard ranges (08:00-18:00) and overnight ranges (22:00-06:00).
 */
final readonly class TimeRange implements TimeRangeInterface
{
    private const string TIME_PATTERN = '/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/';

    private const string RANGE_PATTERN = '/^([01]?[0-9]|2[0-3]):([0-5][0-9])-([01]?[0-9]|2[0-3]):([0-5][0-9])$/';

    private int $startMinutes;

    private int $endMinutes;

    public function __construct(
        private string $start,
        private string $end
    ) {
        $this->validateTime($start);
        $this->validateTime($end);

        $this->startMinutes = $this->toMinutes($start);
        $this->endMinutes = $this->toMinutes($end);
    }

    public function contains(?DateTimeInterface $at = null): bool
    {
        $at = $at ? Carbon::instance($at) : Carbon::now();
        $currentMinutes = ($at->hour * 60) + $at->minute;

        if ($this->isOvernight()) {
            // Overnight range: 22:00-06:00 means from 22:00 to midnight OR from midnight to 06:00
            return $currentMinutes >= $this->startMinutes || $currentMinutes < $this->endMinutes;
        }

        // Standard range: 08:00-18:00
        return $currentMinutes >= $this->startMinutes && $currentMinutes < $this->endMinutes;
    }

    public function getStart(): string
    {
        return $this->start;
    }

    public function getEnd(): string
    {
        return $this->end;
    }

    public function isOvernight(): bool
    {
        return $this->startMinutes > $this->endMinutes;
    }

    public static function fromString(string $range): self
    {
        $range = trim($range);

        if (! preg_match(self::RANGE_PATTERN, $range, $matches)) {
            throw new InvalidSyntaxException(
                sprintf('Invalid time range format: "%s". Expected format: HH:MM-HH:MM', $range)
            );
        }

        $start = sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
        $end = sprintf('%02d:%02d', (int) $matches[3], (int) $matches[4]);

        return new self($start, $end);
    }

    /**
     * Create multiple TimeRange instances from a comma-separated string.
     *
     * @return array<int, self>
     */
    public static function fromMultiple(string $ranges): array
    {
        $result = [];
        foreach (explode(',', $ranges) as $range) {
            $range = trim($range);
            if ($range !== '') {
                $result[] = self::fromString($range);
            }
        }

        return $result;
    }

    /**
     * Check if any of the given ranges contain the specified time.
     *
     * @param  array<int, self>  $ranges
     */
    public static function anyContains(array $ranges, ?DateTimeInterface $at = null): bool
    {
        foreach ($ranges as $range) {
            if ($range->contains($at)) {
                return true;
            }
        }

        return false;
    }

    private function validateTime(string $time): void
    {
        if (! preg_match(self::TIME_PATTERN, $time)) {
            throw new InvalidSyntaxException(
                sprintf('Invalid time format: "%s". Expected format: HH:MM', $time)
            );
        }
    }

    private function toMinutes(string $time): int
    {
        [$hours, $minutes] = explode(':', $time);

        return ((int) $hours * 60) + (int) $minutes;
    }
}
