<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Temporal\Conditions;

use AichaDigital\MustacheResolver\Contracts\ConditionInterface;
use Carbon\Carbon;
use DateTimeInterface;

/**
 * Base class for temporal conditions.
 */
abstract class AbstractCondition implements ConditionInterface
{
    public function supports(string $keyword): bool
    {
        return in_array(strtolower($keyword), $this->getKeywords(), true);
    }

    /**
     * Get the current time as Carbon instance.
     */
    protected function getCarbon(?DateTimeInterface $at = null): Carbon
    {
        return $at ? Carbon::instance($at) : Carbon::now();
    }
}
