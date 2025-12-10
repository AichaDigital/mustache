<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Temporal\Conditions;

use DateTimeInterface;

/**
 * Condition that evaluates to true on weekends (Saturday and Sunday).
 */
final class WeekendCondition extends AbstractCondition
{
    public function evaluate(?DateTimeInterface $at = null): bool
    {
        return $this->getCarbon($at)->isWeekend();
    }

    public function getKeywords(): array
    {
        return ['weekend', 'weekends'];
    }

    public function getName(): string
    {
        return 'weekend';
    }
}
