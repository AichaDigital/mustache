<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Temporal\Conditions;

use AichaDigital\MustacheResolver\Core\Temporal\TimeRange;
use DateTimeInterface;

/**
 * Condition that evaluates to true within a time range.
 *
 * Supports standard ranges (08:00-18:00) and overnight ranges (22:00-06:00).
 */
final class TimeRangeCondition extends AbstractCondition
{
    private readonly TimeRange $timeRange;

    public function __construct(string $range)
    {
        $this->timeRange = TimeRange::fromString($range);
    }

    public function evaluate(?DateTimeInterface $at = null): bool
    {
        return $this->timeRange->contains($at);
    }

    public function getKeywords(): array
    {
        return [sprintf('%s-%s', $this->timeRange->getStart(), $this->timeRange->getEnd())];
    }

    public function getName(): string
    {
        return 'time_range';
    }

    public function getTimeRange(): TimeRange
    {
        return $this->timeRange;
    }
}
