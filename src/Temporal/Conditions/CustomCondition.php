<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Temporal\Conditions;

use DateTimeInterface;

/**
 * Condition that wraps a custom evaluator function.
 *
 * This allows domain-specific conditions to be registered and used
 * in temporal expressions.
 */
final class CustomCondition extends AbstractCondition
{
    /** @var callable(DateTimeInterface): bool */
    private $evaluator;

    /**
     * @param  string  $keyword  The keyword for this condition
     * @param  callable(DateTimeInterface): bool  $evaluator  The evaluator function
     */
    public function __construct(
        private readonly string $keyword,
        callable $evaluator
    ) {
        $this->evaluator = $evaluator;
    }

    public function evaluate(?DateTimeInterface $at = null): bool
    {
        $carbon = $this->getCarbon($at);

        return (bool) ($this->evaluator)($carbon);
    }

    public function getKeywords(): array
    {
        return [$this->keyword];
    }

    public function getName(): string
    {
        return 'custom:'.$this->keyword;
    }

    public function getKeyword(): string
    {
        return $this->keyword;
    }
}
