<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Contracts;

use DateTimeInterface;

/**
 * Interface for time range evaluation.
 *
 * Time ranges represent a period of time within a day,
 * such as "08:00-18:00" or "22:00-06:00" (overnight).
 */
interface TimeRangeInterface
{
    /**
     * Check if the given moment falls within this time range.
     *
     * @param  DateTimeInterface|null  $at  The moment to check. Defaults to now.
     */
    public function contains(?DateTimeInterface $at = null): bool;

    /**
     * Get the start time as a string in HH:MM format.
     */
    public function getStart(): string;

    /**
     * Get the end time as a string in HH:MM format.
     */
    public function getEnd(): string;

    /**
     * Check if this range spans overnight (crosses midnight).
     */
    public function isOvernight(): bool;

    /**
     * Create a TimeRange from a string like "08:00-18:00".
     */
    public static function fromString(string $range): self;
}
