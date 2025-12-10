<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Temporal\Conditions;

use DateTimeInterface;

/**
 * Condition that always evaluates to false.
 */
final class NeverCondition extends AbstractCondition
{
    public function evaluate(?DateTimeInterface $at = null): bool
    {
        return false;
    }

    public function getKeywords(): array
    {
        return ['never'];
    }

    public function getName(): string
    {
        return 'never';
    }
}
