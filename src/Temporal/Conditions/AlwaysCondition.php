<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Temporal\Conditions;

use DateTimeInterface;

/**
 * Condition that always evaluates to true.
 */
final class AlwaysCondition extends AbstractCondition
{
    public function evaluate(?DateTimeInterface $at = null): bool
    {
        return true;
    }

    public function getKeywords(): array
    {
        return ['always'];
    }

    public function getName(): string
    {
        return 'always';
    }
}
