<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Temporal\Conditions\AlwaysCondition;
use AichaDigital\MustacheResolver\Temporal\Conditions\CronCondition;
use AichaDigital\MustacheResolver\Temporal\Conditions\CustomCondition;
use AichaDigital\MustacheResolver\Temporal\Conditions\LastWeekdayCondition;
use AichaDigital\MustacheResolver\Temporal\Conditions\NeverCondition;
use AichaDigital\MustacheResolver\Temporal\Conditions\NthWeekdayCondition;
use AichaDigital\MustacheResolver\Temporal\Conditions\TimeRangeCondition;
use AichaDigital\MustacheResolver\Temporal\Conditions\WeekdayCondition;
use AichaDigital\MustacheResolver\Temporal\Conditions\WeekendCondition;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2025-12-10 14:30:00'); // Wednesday
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('AlwaysCondition', function () {
    it('always evaluates to true', function () {
        $condition = new AlwaysCondition;

        expect($condition->evaluate())->toBeTrue();
    });

    it('supports always keyword', function () {
        $condition = new AlwaysCondition;

        expect($condition->supports('always'))->toBeTrue();
        expect($condition->supports('never'))->toBeFalse();
    });

    it('has correct name', function () {
        $condition = new AlwaysCondition;

        expect($condition->getName())->toBe('always');
    });
});

describe('NeverCondition', function () {
    it('always evaluates to false', function () {
        $condition = new NeverCondition;

        expect($condition->evaluate())->toBeFalse();
    });

    it('supports never keyword', function () {
        $condition = new NeverCondition;

        expect($condition->supports('never'))->toBeTrue();
    });
});

describe('WeekdayCondition', function () {
    it('evaluates true on Wednesday', function () {
        Carbon::setTestNow('2025-12-10 14:30:00'); // Wednesday
        $condition = new WeekdayCondition;

        expect($condition->evaluate())->toBeTrue();
    });

    it('evaluates false on Saturday', function () {
        Carbon::setTestNow('2025-12-13 14:30:00'); // Saturday
        $condition = new WeekdayCondition;

        expect($condition->evaluate())->toBeFalse();
    });

    it('supports weekday and weekdays keywords', function () {
        $condition = new WeekdayCondition;

        expect($condition->supports('weekday'))->toBeTrue();
        expect($condition->supports('weekdays'))->toBeTrue();
    });
});

describe('WeekendCondition', function () {
    it('evaluates true on Saturday', function () {
        Carbon::setTestNow('2025-12-13 14:30:00'); // Saturday
        $condition = new WeekendCondition;

        expect($condition->evaluate())->toBeTrue();
    });

    it('evaluates true on Sunday', function () {
        Carbon::setTestNow('2025-12-14 14:30:00'); // Sunday
        $condition = new WeekendCondition;

        expect($condition->evaluate())->toBeTrue();
    });

    it('evaluates false on Wednesday', function () {
        Carbon::setTestNow('2025-12-10 14:30:00'); // Wednesday
        $condition = new WeekendCondition;

        expect($condition->evaluate())->toBeFalse();
    });

    it('supports weekend and weekends keywords', function () {
        $condition = new WeekendCondition;

        expect($condition->supports('weekend'))->toBeTrue();
        expect($condition->supports('weekends'))->toBeTrue();
    });
});

describe('TimeRangeCondition', function () {
    it('evaluates true within range', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $condition = new TimeRangeCondition('08:00-18:00');

        expect($condition->evaluate())->toBeTrue();
    });

    it('evaluates false outside range', function () {
        Carbon::setTestNow('2025-12-10 20:00:00');
        $condition = new TimeRangeCondition('08:00-18:00');

        expect($condition->evaluate())->toBeFalse();
    });

    it('returns time range object', function () {
        $condition = new TimeRangeCondition('08:00-18:00');

        expect($condition->getTimeRange()->getStart())->toBe('08:00');
        expect($condition->getTimeRange()->getEnd())->toBe('18:00');
    });
});

describe('CronCondition', function () {
    it('evaluates true when cron is due', function () {
        Carbon::setTestNow('2025-12-10 08:00:00'); // Wednesday 8:00
        $condition = new CronCondition('0 8 * * 1-5');

        expect($condition->evaluate())->toBeTrue();
    });

    it('evaluates false when cron is not due', function () {
        Carbon::setTestNow('2025-12-10 09:00:00');
        $condition = new CronCondition('0 8 * * 1-5');

        expect($condition->evaluate())->toBeFalse();
    });

    it('returns expression', function () {
        $condition = new CronCondition('0 8 * * 1-5');

        expect($condition->getExpression())->toBe('0 8 * * 1-5');
    });
});

describe('NthWeekdayCondition', function () {
    it('evaluates true on first Saturday', function () {
        Carbon::setTestNow('2025-12-06 10:00:00'); // First Saturday
        $condition = new NthWeekdayCondition('saturday', [1]);

        expect($condition->evaluate())->toBeTrue();
    });

    it('evaluates true on first or second Saturday', function () {
        Carbon::setTestNow('2025-12-13 10:00:00'); // Second Saturday
        $condition = new NthWeekdayCondition('saturday', [1, 2]);

        expect($condition->evaluate())->toBeTrue();
    });

    it('evaluates false on wrong occurrence', function () {
        Carbon::setTestNow('2025-12-20 10:00:00'); // Third Saturday
        $condition = new NthWeekdayCondition('saturday', [1, 2]);

        expect($condition->evaluate())->toBeFalse();
    });

    it('returns day and occurrences', function () {
        $condition = new NthWeekdayCondition('saturday', [1, 2]);

        expect($condition->getDayOfWeek())->toBe('saturday');
        expect($condition->getOccurrences())->toBe([1, 2]);
    });
});

describe('LastWeekdayCondition', function () {
    it('evaluates true on last Saturday', function () {
        Carbon::setTestNow('2025-12-27 10:00:00'); // Last Saturday
        $condition = new LastWeekdayCondition('saturday');

        expect($condition->evaluate())->toBeTrue();
    });

    it('evaluates false on non-last Saturday', function () {
        Carbon::setTestNow('2025-12-06 10:00:00'); // First Saturday
        $condition = new LastWeekdayCondition('saturday');

        expect($condition->evaluate())->toBeFalse();
    });

    it('returns day', function () {
        $condition = new LastWeekdayCondition('friday');

        expect($condition->getDayOfWeek())->toBe('friday');
    });
});

describe('CustomCondition', function () {
    it('evaluates using custom callback', function () {
        $condition = new CustomCondition('holiday', fn () => true);

        expect($condition->evaluate())->toBeTrue();
    });

    it('receives datetime in callback', function () {
        $receivedDate = null;
        $condition = new CustomCondition('test', function ($at) use (&$receivedDate) {
            $receivedDate = $at;

            return true;
        });

        $condition->evaluate();

        expect($receivedDate)->not->toBeNull();
        expect($receivedDate->format('Y-m-d'))->toBe('2025-12-10');
    });

    it('supports its keyword', function () {
        $condition = new CustomCondition('holiday', fn () => true);

        expect($condition->supports('holiday'))->toBeTrue();
        expect($condition->supports('other'))->toBeFalse();
    });

    it('has correct name', function () {
        $condition = new CustomCondition('holiday', fn () => true);

        expect($condition->getName())->toBe('custom:holiday');
    });
});
