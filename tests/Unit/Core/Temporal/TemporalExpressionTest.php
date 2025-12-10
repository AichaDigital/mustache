<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Temporal\TemporalExpression;
use AichaDigital\MustacheResolver\Exceptions\ResolutionException;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2025-12-10 14:30:00'); // Wednesday
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('TemporalExpression → Basic Evaluation', function () {
    it('evaluates always as true', function () {
        $expr = new TemporalExpression('always');

        expect($expr->evaluate())->toBeTrue();
    });

    it('evaluates never as false', function () {
        $expr = new TemporalExpression('never');

        expect($expr->evaluate())->toBeFalse();
    });

    it('evaluates empty expression as always', function () {
        $expr = new TemporalExpression('');

        expect($expr->evaluate())->toBeTrue();
    });

    it('evaluates weekday on Wednesday', function () {
        Carbon::setTestNow('2025-12-10 14:30:00'); // Wednesday
        $expr = new TemporalExpression('weekday');

        expect($expr->evaluate())->toBeTrue();
    });

    it('evaluates weekday on Saturday as false', function () {
        Carbon::setTestNow('2025-12-13 14:30:00'); // Saturday
        $expr = new TemporalExpression('weekday');

        expect($expr->evaluate())->toBeFalse();
    });

    it('evaluates weekend on Saturday', function () {
        Carbon::setTestNow('2025-12-13 14:30:00'); // Saturday
        $expr = new TemporalExpression('weekend');

        expect($expr->evaluate())->toBeTrue();
    });
});

describe('TemporalExpression → Time Range Evaluation', function () {
    it('evaluates time range within working hours', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $expr = new TemporalExpression('08:00-18:00');

        expect($expr->evaluate())->toBeTrue();
    });

    it('evaluates time range outside working hours', function () {
        Carbon::setTestNow('2025-12-10 20:00:00');
        $expr = new TemporalExpression('08:00-18:00');

        expect($expr->evaluate())->toBeFalse();
    });

    it('evaluates overnight time range', function () {
        Carbon::setTestNow('2025-12-10 23:30:00');
        $expr = new TemporalExpression('22:00-06:00');

        expect($expr->evaluate())->toBeTrue();
    });
});

describe('TemporalExpression → CRON Evaluation', function () {
    it('evaluates cron expression when due', function () {
        Carbon::setTestNow('2025-12-10 08:00:00'); // Wednesday 8:00
        $expr = new TemporalExpression('cron:0 8 * * 1-5');

        expect($expr->evaluate())->toBeTrue();
    });

    it('evaluates cron expression when not due', function () {
        Carbon::setTestNow('2025-12-10 09:00:00'); // Wednesday 9:00
        $expr = new TemporalExpression('cron:0 8 * * 1-5');

        expect($expr->evaluate())->toBeFalse();
    });
});

describe('TemporalExpression → Nth Weekday Evaluation', function () {
    it('evaluates first Saturday of month', function () {
        Carbon::setTestNow('2025-12-06 10:00:00'); // First Saturday
        $expr = new TemporalExpression('nth:saturday:1');

        expect($expr->evaluate())->toBeTrue();
    });

    it('evaluates first and second Saturday', function () {
        Carbon::setTestNow('2025-12-13 10:00:00'); // Second Saturday
        $expr = new TemporalExpression('nth:saturday:1,2');

        expect($expr->evaluate())->toBeTrue();
    });

    it('evaluates last Friday of month', function () {
        Carbon::setTestNow('2025-12-26 10:00:00'); // Last Friday
        $expr = new TemporalExpression('last:friday');

        expect($expr->evaluate())->toBeTrue();
    });
});

describe('TemporalExpression → Logical Operators', function () {
    it('evaluates AND expression', function () {
        Carbon::setTestNow('2025-12-10 14:30:00'); // Wednesday 14:30
        $expr = new TemporalExpression('weekday && 08:00-18:00');

        expect($expr->evaluate())->toBeTrue();
    });

    it('evaluates AND with false condition', function () {
        Carbon::setTestNow('2025-12-13 14:30:00'); // Saturday 14:30
        $expr = new TemporalExpression('weekday && 08:00-18:00');

        expect($expr->evaluate())->toBeFalse();
    });

    it('evaluates OR expression', function () {
        Carbon::setTestNow('2025-12-13 14:30:00'); // Saturday
        $expr = new TemporalExpression('weekday || weekend');

        expect($expr->evaluate())->toBeTrue();
    });

    it('evaluates NOT expression', function () {
        Carbon::setTestNow('2025-12-10 14:30:00'); // Wednesday
        $expr = new TemporalExpression('!weekend');

        expect($expr->evaluate())->toBeTrue();
    });

    it('evaluates complex expression', function () {
        Carbon::setTestNow('2025-12-10 14:30:00'); // Wednesday 14:30
        $expr = new TemporalExpression('(weekday && 08:00-18:00) || weekend');

        expect($expr->evaluate())->toBeTrue();
    });
});

describe('TemporalExpression → Custom Evaluators', function () {
    it('registers and uses custom evaluator', function () {
        $expr = new TemporalExpression('weekday && !holiday');
        $expr->registerEvaluator('holiday', fn () => false);

        expect($expr->evaluate())->toBeTrue();
    });

    it('custom evaluator can return true', function () {
        $expr = new TemporalExpression('holiday');
        $expr->registerEvaluator('holiday', fn () => true);

        expect($expr->evaluate())->toBeTrue();
    });

    it('custom evaluator receives datetime', function () {
        $receivedDate = null;
        $expr = new TemporalExpression('custom');
        $expr->registerEvaluator('custom', function ($at) use (&$receivedDate) {
            $receivedDate = $at;

            return true;
        });

        $expr->evaluate();

        expect($receivedDate)->not->toBeNull();
        expect($receivedDate->format('Y-m-d'))->toBe('2025-12-10');
    });

    it('throws on unregistered custom evaluator', function () {
        $expr = new TemporalExpression('undefined_condition');

        expect(fn () => $expr->evaluate())
            ->toThrow(ResolutionException::class);
    });

    it('reports has evaluator correctly', function () {
        $expr = new TemporalExpression('holiday');
        expect($expr->hasEvaluator('holiday'))->toBeFalse();

        $expr->registerEvaluator('holiday', fn () => false);
        expect($expr->hasEvaluator('holiday'))->toBeTrue();
    });

    it('returns registered keywords', function () {
        $expr = new TemporalExpression('');
        $expr->registerEvaluator('holiday', fn () => false);
        $expr->registerEvaluator('special_day', fn () => false);

        expect($expr->getRegisteredKeywords())->toContain('holiday');
        expect($expr->getRegisteredKeywords())->toContain('special_day');
    });
});

describe('TemporalExpression → Missing Evaluators Detection', function () {
    it('detects missing evaluators', function () {
        $expr = new TemporalExpression('weekday && holiday && special');

        $missing = $expr->getMissingEvaluators();

        expect($missing)->toContain('holiday');
        expect($missing)->toContain('special');
        expect($missing)->not->toContain('weekday');
    });

    it('returns empty array when all evaluators are present', function () {
        $expr = new TemporalExpression('weekday && holiday');
        $expr->registerEvaluator('holiday', fn () => false);

        expect($expr->getMissingEvaluators())->toBeEmpty();
    });
});

describe('TemporalExpression → Expression Retrieval', function () {
    it('returns original expression', function () {
        $expr = new TemporalExpression('weekday && 08:00-18:00');

        expect($expr->getExpression())->toBe('weekday && 08:00-18:00');
    });
});

describe('TemporalExpression → Real World Scenarios', function () {
    it('evaluates Sitelight weekday working hours', function () {
        Carbon::setTestNow('2025-12-10 10:00:00'); // Wednesday 10:00
        $expr = new TemporalExpression('weekday && 08:00-18:00');

        expect($expr->evaluate())->toBeTrue();
    });

    it('evaluates Sitelight day or weekend', function () {
        $expr = new TemporalExpression('day || weekend');
        $expr->registerEvaluator('day', fn () => true);

        expect($expr->evaluate())->toBeTrue();
    });

    it('evaluates first Saturday of month at specific time', function () {
        Carbon::setTestNow('2025-12-06 10:30:00'); // First Saturday 10:30
        $expr = new TemporalExpression('nth:saturday:1 && 10:00-14:00');

        expect($expr->evaluate())->toBeTrue();
    });

    it('evaluates complex trigger condition', function () {
        Carbon::setTestNow('2025-12-10 14:00:00'); // Wednesday 14:00

        $expr = new TemporalExpression('(weekday && 08:00-18:00 && !holiday) || special_event');
        $expr->registerEvaluator('holiday', fn () => false);
        $expr->registerEvaluator('special_event', fn () => false);

        expect($expr->evaluate())->toBeTrue();
    });
});

describe('TemporalExpression → Custom DateTime', function () {
    it('evaluates at specific datetime', function () {
        $expr = new TemporalExpression('weekday && 08:00-18:00');
        $at = Carbon::create(2025, 12, 10, 14, 0, 0); // Wednesday 14:00

        expect($expr->evaluate($at))->toBeTrue();
    });

    it('evaluates at weekend datetime', function () {
        $expr = new TemporalExpression('weekend');
        $at = Carbon::create(2025, 12, 13, 10, 0, 0); // Saturday

        expect($expr->evaluate($at))->toBeTrue();
    });
});
