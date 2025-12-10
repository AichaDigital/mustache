<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Temporal\CronWrapper;
use AichaDigital\MustacheResolver\Core\Temporal\TimeRange;
use AichaDigital\MustacheResolver\Temporal\Conditions\CronCondition;
use AichaDigital\MustacheResolver\Temporal\Conditions\LastWeekdayCondition;
use AichaDigital\MustacheResolver\Temporal\Conditions\NeverCondition;
use AichaDigital\MustacheResolver\Temporal\Conditions\NthWeekdayCondition;
use AichaDigital\MustacheResolver\Temporal\Conditions\TimeRangeCondition;
use AichaDigital\MustacheResolver\Temporal\Conditions\WeekdayCondition;
use AichaDigital\MustacheResolver\Temporal\Conditions\WeekendCondition;
use Carbon\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

describe('CronCondition', function () {
    it('creates from cron expression', function () {
        $condition = new CronCondition('0 8 * * *');

        expect($condition->getExpression())->toBe('0 8 * * *');
        expect($condition->getName())->toBe('cron');
    });

    it('returns cron wrapper', function () {
        $condition = new CronCondition('0 8 * * *');

        expect($condition->getCronWrapper())->toBeInstanceOf(CronWrapper::class);
    });

    it('returns correct keywords', function () {
        $condition = new CronCondition('0 8 * * *');

        expect($condition->getKeywords())->toBe(['cron:0 8 * * *']);
    });

    it('evaluates to true when cron matches', function () {
        Carbon::setTestNow('2025-12-10 08:00:00');
        $condition = new CronCondition('0 8 * * *');

        expect($condition->evaluate(Carbon::now()))->toBeTrue();
    });

    it('evaluates to false when cron does not match', function () {
        Carbon::setTestNow('2025-12-10 09:00:00');
        $condition = new CronCondition('0 8 * * *');

        expect($condition->evaluate(Carbon::now()))->toBeFalse();
    });
});

describe('NthWeekdayCondition', function () {
    it('creates with day and occurrences', function () {
        $condition = new NthWeekdayCondition('saturday', [1]);

        expect($condition->getDayOfWeek())->toBe('saturday');
        expect($condition->getOccurrences())->toBe([1]);
        expect($condition->getName())->toBe('nth_weekday');
    });

    it('returns correct keywords for single occurrence', function () {
        $condition = new NthWeekdayCondition('saturday', [1]);

        expect($condition->getKeywords())->toBe(['nth:saturday:1']);
    });

    it('returns correct keywords for multiple occurrences', function () {
        $condition = new NthWeekdayCondition('saturday', [1, 2]);

        expect($condition->getKeywords())->toBe(['nth:saturday:1,2']);
    });

    it('evaluates to true on first saturday', function () {
        Carbon::setTestNow('2025-12-06 10:00:00'); // First Saturday of December
        $condition = new NthWeekdayCondition('saturday', [1]);

        expect($condition->evaluate(Carbon::now()))->toBeTrue();
    });

    it('evaluates to false on wrong saturday', function () {
        Carbon::setTestNow('2025-12-13 10:00:00'); // Second Saturday
        $condition = new NthWeekdayCondition('saturday', [1]);

        expect($condition->evaluate(Carbon::now()))->toBeFalse();
    });

    it('evaluates to true for last occurrence', function () {
        Carbon::setTestNow('2025-12-27 10:00:00'); // Last Saturday of December
        $condition = new NthWeekdayCondition('saturday', [-1]);

        expect($condition->evaluate(Carbon::now()))->toBeTrue();
    });
});

describe('TimeRangeCondition', function () {
    it('creates from time range string', function () {
        $condition = new TimeRangeCondition('08:00-18:00');

        expect($condition->getTimeRange())->toBeInstanceOf(TimeRange::class);
        expect($condition->getName())->toBe('time_range');
    });

    it('returns correct keywords', function () {
        $condition = new TimeRangeCondition('08:00-18:00');

        expect($condition->getKeywords())->toBe(['08:00-18:00']);
    });

    it('evaluates to true within range', function () {
        Carbon::setTestNow('2025-12-10 12:00:00');
        $condition = new TimeRangeCondition('08:00-18:00');

        expect($condition->evaluate(Carbon::now()))->toBeTrue();
    });

    it('evaluates to false outside range', function () {
        Carbon::setTestNow('2025-12-10 20:00:00');
        $condition = new TimeRangeCondition('08:00-18:00');

        expect($condition->evaluate(Carbon::now()))->toBeFalse();
    });
});

describe('WeekdayCondition', function () {
    it('has correct name', function () {
        $condition = new WeekdayCondition;

        expect($condition->getName())->toBe('weekday');
    });

    it('has weekday and weekdays keywords', function () {
        $condition = new WeekdayCondition;

        expect($condition->getKeywords())->toBe(['weekday', 'weekdays']);
    });

    it('evaluates to true on weekday', function () {
        Carbon::setTestNow('2025-12-10 10:00:00'); // Wednesday
        $condition = new WeekdayCondition;

        expect($condition->evaluate(Carbon::now()))->toBeTrue();
    });

    it('evaluates to false on weekend', function () {
        Carbon::setTestNow('2025-12-13 10:00:00'); // Saturday
        $condition = new WeekdayCondition;

        expect($condition->evaluate(Carbon::now()))->toBeFalse();
    });

    it('evaluates with null uses current time', function () {
        Carbon::setTestNow('2025-12-10 10:00:00'); // Wednesday
        $condition = new WeekdayCondition;

        expect($condition->evaluate())->toBeTrue();
    });
});

describe('WeekendCondition', function () {
    it('has correct name', function () {
        $condition = new WeekendCondition;

        expect($condition->getName())->toBe('weekend');
    });

    it('has weekend and weekends keywords', function () {
        $condition = new WeekendCondition;

        expect($condition->getKeywords())->toBe(['weekend', 'weekends']);
    });

    it('evaluates to true on weekend', function () {
        Carbon::setTestNow('2025-12-13 10:00:00'); // Saturday
        $condition = new WeekendCondition;

        expect($condition->evaluate(Carbon::now()))->toBeTrue();
    });

    it('evaluates to false on weekday', function () {
        Carbon::setTestNow('2025-12-10 10:00:00'); // Wednesday
        $condition = new WeekendCondition;

        expect($condition->evaluate(Carbon::now()))->toBeFalse();
    });

    it('evaluates with null uses current time', function () {
        Carbon::setTestNow('2025-12-13 10:00:00'); // Saturday
        $condition = new WeekendCondition;

        expect($condition->evaluate())->toBeTrue();
    });
});

describe('NeverCondition', function () {
    it('has correct name', function () {
        $condition = new NeverCondition;

        expect($condition->getName())->toBe('never');
    });

    it('has never keyword', function () {
        $condition = new NeverCondition;

        expect($condition->getKeywords())->toBe(['never']);
    });

    it('always evaluates to false', function () {
        $condition = new NeverCondition;

        expect($condition->evaluate())->toBeFalse();
        expect($condition->evaluate(Carbon::now()))->toBeFalse();
        expect($condition->evaluate(Carbon::parse('2025-12-25')))->toBeFalse();
    });
});

describe('LastWeekdayCondition', function () {
    it('creates with day of week', function () {
        $condition = new LastWeekdayCondition('saturday');

        expect($condition->getDayOfWeek())->toBe('saturday');
        expect($condition->getName())->toBe('last_weekday');
    });

    it('returns correct keywords', function () {
        $condition = new LastWeekdayCondition('friday');

        expect($condition->getKeywords())->toBe(['last:friday']);
    });

    it('evaluates to true on last saturday', function () {
        Carbon::setTestNow('2025-12-27 10:00:00'); // Last Saturday of December
        $condition = new LastWeekdayCondition('saturday');

        expect($condition->evaluate(Carbon::now()))->toBeTrue();
    });

    it('evaluates to false on other saturday', function () {
        Carbon::setTestNow('2025-12-20 10:00:00'); // Not the last Saturday
        $condition = new LastWeekdayCondition('saturday');

        expect($condition->evaluate(Carbon::now()))->toBeFalse();
    });

    it('evaluates to true on last friday', function () {
        Carbon::setTestNow('2025-12-26 10:00:00'); // Last Friday of December
        $condition = new LastWeekdayCondition('friday');

        expect($condition->evaluate(Carbon::now()))->toBeTrue();
    });
});
