<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Temporal\Conditions;

use AichaDigital\MustacheResolver\Core\Temporal\CronWrapper;
use DateTimeInterface;

/**
 * Condition that evaluates based on a CRON expression.
 */
final class CronCondition extends AbstractCondition
{
    private readonly CronWrapper $cron;

    public function __construct(
        private readonly string $expression
    ) {
        $this->cron = new CronWrapper($expression);
    }

    public function evaluate(?DateTimeInterface $at = null): bool
    {
        return $this->cron->isDue($at);
    }

    public function getKeywords(): array
    {
        return ['cron:'.$this->expression];
    }

    public function getName(): string
    {
        return 'cron';
    }

    public function getCronWrapper(): CronWrapper
    {
        return $this->cron;
    }

    public function getExpression(): string
    {
        return $this->expression;
    }
}
