<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Temporal\Conditions;

use AichaDigital\MustacheResolver\Core\Temporal\CronWrapper;
use DateTimeInterface;

/**
 * Condition that evaluates to true on the Nth occurrence of a weekday in the month.
 *
 * Examples:
 * - First Saturday: new NthWeekdayCondition('saturday', [1])
 * - First and second Saturday: new NthWeekdayCondition('saturday', [1, 2])
 * - Last Friday: new NthWeekdayCondition('friday', [-1])
 */
final class NthWeekdayCondition extends AbstractCondition
{
    /**
     * @param  string  $dayOfWeek  Day name (monday, saturday, etc.)
     * @param  array<int, int>  $occurrences  Which occurrences (1-5 for first-fifth, -1 for last)
     */
    public function __construct(
        private readonly string $dayOfWeek,
        private readonly array $occurrences
    ) {}

    public function evaluate(?DateTimeInterface $at = null): bool
    {
        return CronWrapper::isAnyNthWeekday($this->dayOfWeek, $this->occurrences, $at);
    }

    public function getKeywords(): array
    {
        $occurrenceStr = implode(',', $this->occurrences);

        return ['nth:'.$this->dayOfWeek.':'.$occurrenceStr];
    }

    public function getName(): string
    {
        return 'nth_weekday';
    }

    public function getDayOfWeek(): string
    {
        return $this->dayOfWeek;
    }

    /**
     * @return array<int, int>
     */
    public function getOccurrences(): array
    {
        return $this->occurrences;
    }
}
