<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Compound\ConditionEvaluator;
use AichaDigital\MustacheResolver\Exceptions\ConditionNotMetException;

beforeEach(function () {
    $this->evaluator = new ConditionEvaluator;
});

describe('ConditionEvaluator → comparison operators', function () {
    it('evaluates greater than', function () {
        $result = $this->evaluator->evaluate('var', 10, '> 5', '{{expr}}');

        expect($result)->toBeTrue();
    });

    it('throws when greater than fails', function () {
        expect(fn () => $this->evaluator->evaluate('var', 3, '> 5', '{{expr}}'))
            ->toThrow(ConditionNotMetException::class);
    });

    it('evaluates greater than or equal', function () {
        expect($this->evaluator->evaluate('var', 5, '>= 5', '{{expr}}'))->toBeTrue();
        expect($this->evaluator->evaluate('var', 6, '>= 5', '{{expr}}'))->toBeTrue();
    });

    it('evaluates less than', function () {
        expect($this->evaluator->evaluate('var', 3, '< 5', '{{expr}}'))->toBeTrue();
    });

    it('throws when less than fails', function () {
        expect(fn () => $this->evaluator->evaluate('var', 10, '< 5', '{{expr}}'))
            ->toThrow(ConditionNotMetException::class);
    });

    it('evaluates less than or equal', function () {
        expect($this->evaluator->evaluate('var', 5, '<= 5', '{{expr}}'))->toBeTrue();
        expect($this->evaluator->evaluate('var', 4, '<= 5', '{{expr}}'))->toBeTrue();
    });

    it('evaluates equality', function () {
        expect($this->evaluator->evaluate('var', 5, '== 5', '{{expr}}'))->toBeTrue();
        expect($this->evaluator->evaluate('var', '5', '== 5', '{{expr}}'))->toBeTrue();
    });

    it('throws when equality fails', function () {
        expect(fn () => $this->evaluator->evaluate('var', 6, '== 5', '{{expr}}'))
            ->toThrow(ConditionNotMetException::class);
    });

    it('evaluates strict equality', function () {
        expect($this->evaluator->evaluate('var', 5, '=== 5', '{{expr}}'))->toBeTrue();
    });

    it('evaluates inequality', function () {
        expect($this->evaluator->evaluate('var', 6, '!= 5', '{{expr}}'))->toBeTrue();
        expect($this->evaluator->evaluate('var', 6, '<> 5', '{{expr}}'))->toBeTrue();
    });

    it('evaluates strict inequality', function () {
        expect($this->evaluator->evaluate('var', '5', '!== 5', '{{expr}}'))->toBeTrue();
    });
});

describe('ConditionEvaluator → BETWEEN operator', function () {
    it('evaluates BETWEEN in range', function () {
        $result = $this->evaluator->evaluate('var', 50, 'BETWEEN 1 AND 100', '{{expr}}');

        expect($result)->toBeTrue();
    });

    it('evaluates BETWEEN at lower bound', function () {
        $result = $this->evaluator->evaluate('var', 1, 'BETWEEN 1 AND 100', '{{expr}}');

        expect($result)->toBeTrue();
    });

    it('evaluates BETWEEN at upper bound', function () {
        $result = $this->evaluator->evaluate('var', 100, 'BETWEEN 1 AND 100', '{{expr}}');

        expect($result)->toBeTrue();
    });

    it('throws when below range', function () {
        expect(fn () => $this->evaluator->evaluate('var', 0, 'BETWEEN 1 AND 100', '{{expr}}'))
            ->toThrow(ConditionNotMetException::class);
    });

    it('throws when above range', function () {
        expect(fn () => $this->evaluator->evaluate('var', 101, 'BETWEEN 1 AND 100', '{{expr}}'))
            ->toThrow(ConditionNotMetException::class);
    });

    it('is case insensitive', function () {
        expect($this->evaluator->evaluate('var', 50, 'between 1 and 100', '{{expr}}'))->toBeTrue();
    });
});

describe('ConditionEvaluator → check method', function () {
    it('returns true when condition met', function () {
        expect($this->evaluator->check(10, '> 5'))->toBeTrue();
    });

    it('returns false when condition not met', function () {
        expect($this->evaluator->check(3, '> 5'))->toBeFalse();
    });

    it('returns true for BETWEEN in range', function () {
        expect($this->evaluator->check(50, 'BETWEEN 1 AND 100'))->toBeTrue();
    });

    it('returns false for BETWEEN out of range', function () {
        expect($this->evaluator->check(200, 'BETWEEN 1 AND 100'))->toBeFalse();
    });
});

describe('ConditionEvaluator → value types', function () {
    it('handles string numeric values', function () {
        expect($this->evaluator->evaluate('var', '10', '> 5', '{{expr}}'))->toBeTrue();
    });

    it('handles float values', function () {
        expect($this->evaluator->evaluate('var', 10.5, '> 5.5', '{{expr}}'))->toBeTrue();
    });

    it('handles negative values', function () {
        expect($this->evaluator->evaluate('var', -5, '< 0', '{{expr}}'))->toBeTrue();
    });
});

describe('ConditionEvaluator → exception context', function () {
    it('includes variable name in exception', function () {
        try {
            $this->evaluator->evaluate('max_power', 0, '> 0', '{{CommandCenter.max_power}}');
            $this->fail('Expected exception not thrown');
        } catch (ConditionNotMetException $e) {
            expect($e->getVariableName())->toBe('max_power');
        }
    });

    it('includes actual value in exception', function () {
        try {
            $this->evaluator->evaluate('var', 42, '> 100', '{{expr}}');
            $this->fail('Expected exception not thrown');
        } catch (ConditionNotMetException $e) {
            expect($e->getActualValue())->toBe(42);
        }
    });

    it('includes condition in exception', function () {
        try {
            $this->evaluator->evaluate('var', 42, '> 100', '{{expr}}');
            $this->fail('Expected exception not thrown');
        } catch (ConditionNotMetException $e) {
            expect($e->getCondition())->toBe('> 100');
        }
    });

    it('includes expression in exception', function () {
        try {
            $this->evaluator->evaluate('var', 42, '> 100', '{{Model.field}}');
            $this->fail('Expected exception not thrown');
        } catch (ConditionNotMetException $e) {
            expect($e->getExpression())->toBe('{{Model.field}}');
        }
    });
});
