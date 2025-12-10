<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Temporal\Conditions;

use DateTimeInterface;

/**
 * Condition that evaluates to true on weekdays (Monday to Friday).
 */
final class WeekdayCondition extends AbstractCondition
{
    public function evaluate(?DateTimeInterface $at = null): bool
    {
        return $this->getCarbon($at)->isWeekday();
    }

    public function getKeywords(): array
    {
        return ['weekday', 'weekdays'];
    }

    public function getName(): string
    {
        return 'weekday';
    }
}
