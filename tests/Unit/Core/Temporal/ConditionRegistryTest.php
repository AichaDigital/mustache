<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Temporal\ConditionRegistry;
use AichaDigital\MustacheResolver\Core\Temporal\TemporalExpression;
use AichaDigital\MustacheResolver\Temporal\Conditions\AlwaysCondition;
use AichaDigital\MustacheResolver\Temporal\Conditions\NeverCondition;
use AichaDigital\MustacheResolver\Temporal\Conditions\WeekdayCondition;
use Carbon\Carbon;

beforeEach(function () {
    ConditionRegistry::resetInstance();
    $this->registry = new ConditionRegistry;
});

afterEach(function () {
    ConditionRegistry::resetInstance();
    Carbon::setTestNow();
});

describe('ConditionRegistry → Singleton', function () {
    it('returns singleton instance', function () {
        $instance1 = ConditionRegistry::getInstance();
        $instance2 = ConditionRegistry::getInstance();

        expect($instance1)->toBe($instance2);
    });

    it('resets singleton instance', function () {
        $instance1 = ConditionRegistry::getInstance();
        ConditionRegistry::resetInstance();
        $instance2 = ConditionRegistry::getInstance();

        expect($instance1)->not->toBe($instance2);
    });
});

describe('ConditionRegistry → Built-in Conditions', function () {
    it('registers built-in conditions on creation', function () {
        expect($this->registry->has('always'))->toBeTrue();
        expect($this->registry->has('never'))->toBeTrue();
        expect($this->registry->has('weekday'))->toBeTrue();
        expect($this->registry->has('weekend'))->toBeTrue();
    });

    it('gets built-in condition by keyword', function () {
        $condition = $this->registry->get('always');

        expect($condition)->toBeInstanceOf(AlwaysCondition::class);
    });

    it('evaluates always condition', function () {
        $result = $this->registry->evaluate('always');

        expect($result)->toBeTrue();
    });

    it('evaluates never condition', function () {
        $result = $this->registry->evaluate('never');

        expect($result)->toBeFalse();
    });

    it('evaluates weekday condition on weekday', function () {
        Carbon::setTestNow('2025-12-10 10:00:00'); // Wednesday
        $result = $this->registry->evaluate('weekday', Carbon::now());

        expect($result)->toBeTrue();
    });

    it('evaluates weekend condition on weekend', function () {
        Carbon::setTestNow('2025-12-13 10:00:00'); // Saturday
        $result = $this->registry->evaluate('weekend', Carbon::now());

        expect($result)->toBeTrue();
    });
});

describe('ConditionRegistry → Custom Condition Registration', function () {
    it('registers custom condition', function () {
        $customCondition = new WeekdayCondition;
        $this->registry->register($customCondition);

        expect($this->registry->has('weekday'))->toBeTrue();
    });

    it('registers custom evaluator', function () {
        $this->registry->registerEvaluator('holiday', fn () => true);

        expect($this->registry->has('holiday'))->toBeTrue();
    });

    it('evaluates custom evaluator', function () {
        $this->registry->registerEvaluator('customTrue', fn () => true);
        $this->registry->registerEvaluator('customFalse', fn () => false);

        expect($this->registry->evaluate('customTrue'))->toBeTrue();
        expect($this->registry->evaluate('customFalse'))->toBeFalse();
    });

    it('evaluates custom evaluator with datetime parameter', function () {
        $this->registry->registerEvaluator('isDecember', function ($date) {
            return $date->format('m') === '12';
        });

        Carbon::setTestNow('2025-12-10');
        expect($this->registry->evaluate('isDecember', Carbon::now()))->toBeTrue();

        Carbon::setTestNow('2025-06-10');
        expect($this->registry->evaluate('isDecember', Carbon::now()))->toBeFalse();
    });
});

describe('ConditionRegistry → Has and Get', function () {
    it('returns false for unknown keyword', function () {
        expect($this->registry->has('unknownCondition'))->toBeFalse();
    });

    it('returns null for unknown condition', function () {
        expect($this->registry->get('unknownCondition'))->toBeNull();
    });

    it('returns false when evaluating unknown keyword', function () {
        $result = $this->registry->evaluate('unknownCondition');

        expect($result)->toBeFalse();
    });
});

describe('ConditionRegistry → Keywords', function () {
    it('gets all registered keywords', function () {
        $keywords = $this->registry->getKeywords();

        expect($keywords)->toContain('always');
        expect($keywords)->toContain('never');
        expect($keywords)->toContain('weekday');
        expect($keywords)->toContain('weekend');
    });

    it('gets custom evaluator keywords', function () {
        $this->registry->registerEvaluator('custom1', fn () => true);
        $this->registry->registerEvaluator('custom2', fn () => false);

        $customKeywords = $this->registry->getCustomKeywords();

        expect($customKeywords)->toContain('custom1');
        expect($customKeywords)->toContain('custom2');
        expect($customKeywords)->not->toContain('always');
    });
});

describe('ConditionRegistry → Remove', function () {
    it('removes a registered condition', function () {
        $this->registry->registerEvaluator('toRemove', fn () => true);
        expect($this->registry->has('toRemove'))->toBeTrue();

        $this->registry->remove('toRemove');

        expect($this->registry->has('toRemove'))->toBeFalse();
    });

    it('returns self for fluent interface', function () {
        $result = $this->registry->remove('nonexistent');

        expect($result)->toBe($this->registry);
    });
});

describe('ConditionRegistry → Clear Custom', function () {
    it('clears all custom conditions but keeps built-in', function () {
        $this->registry->registerEvaluator('custom1', fn () => true);
        $this->registry->registerEvaluator('custom2', fn () => false);

        $this->registry->clearCustom();

        expect($this->registry->has('custom1'))->toBeFalse();
        expect($this->registry->has('custom2'))->toBeFalse();
        expect($this->registry->has('always'))->toBeTrue();
        expect($this->registry->has('weekday'))->toBeTrue();
    });

    it('clears custom keywords list', function () {
        $this->registry->registerEvaluator('custom1', fn () => true);
        expect($this->registry->getCustomKeywords())->toContain('custom1');

        $this->registry->clearCustom();

        expect($this->registry->getCustomKeywords())->toBeEmpty();
    });
});

describe('ConditionRegistry → Create Expression', function () {
    it('creates temporal expression with registry evaluators', function () {
        $this->registry->registerEvaluator('holiday', fn () => false);

        $expression = $this->registry->createExpression('weekday && !holiday');

        expect($expression)->toBeInstanceOf(TemporalExpression::class);
    });

    it('expression uses registered evaluators', function () {
        Carbon::setTestNow('2025-12-10 10:00:00'); // Wednesday
        $this->registry->registerEvaluator('holiday', fn () => false);

        $expression = $this->registry->createExpression('weekday && !holiday');

        expect($expression->evaluate())->toBeTrue();
    });
});

describe('ConditionRegistry → Fluent Interface', function () {
    it('register returns self', function () {
        $result = $this->registry->register(new NeverCondition);

        expect($result)->toBe($this->registry);
    });

    it('registerEvaluator returns self', function () {
        $result = $this->registry->registerEvaluator('test', fn () => true);

        expect($result)->toBe($this->registry);
    });

    it('clearCustom returns self', function () {
        $result = $this->registry->clearCustom();

        expect($result)->toBe($this->registry);
    });
});
