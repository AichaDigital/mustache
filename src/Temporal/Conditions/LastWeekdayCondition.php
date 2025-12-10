<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Temporal\Conditions;

use AichaDigital\MustacheResolver\Core\Temporal\CronWrapper;
use DateTimeInterface;

/**
 * Condition that evaluates to true on the last occurrence of a weekday in the month.
 */
final class LastWeekdayCondition extends AbstractCondition
{
    public function __construct(
        private readonly string $dayOfWeek
    ) {}

    public function evaluate(?DateTimeInterface $at = null): bool
    {
        return CronWrapper::isLastWeekday($this->dayOfWeek, $at);
    }

    public function getKeywords(): array
    {
        return ['last:'.$this->dayOfWeek];
    }

    public function getName(): string
    {
        return 'last_weekday';
    }

    public function getDayOfWeek(): string
    {
        return $this->dayOfWeek;
    }
}
