<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Temporal\CronWrapper;
use AichaDigital\MustacheResolver\Exceptions\InvalidSyntaxException;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2025-12-10 08:00:00'); // Wednesday
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('CronWrapper → Creation', function () {
    it('creates from valid cron expression', function () {
        $cron = new CronWrapper('0 8 * * 1-5');

        expect($cron)->toBeInstanceOf(CronWrapper::class);
    });

    it('throws on invalid cron expression', function () {
        expect(fn () => new CronWrapper('invalid cron'))
            ->toThrow(InvalidSyntaxException::class);
    });
});

describe('CronWrapper → isDue Evaluation', function () {
    it('returns true when cron is due', function () {
        Carbon::setTestNow('2025-12-10 08:00:00'); // Wednesday 8:00
        $cron = new CronWrapper('0 8 * * 1-5'); // Every weekday at 8:00

        expect($cron->isDue())->toBeTrue();
    });

    it('returns false when cron is not due', function () {
        Carbon::setTestNow('2025-12-10 09:00:00'); // Wednesday 9:00
        $cron = new CronWrapper('0 8 * * 1-5'); // Every weekday at 8:00

        expect($cron->isDue())->toBeFalse();
    });

    it('evaluates every minute expression', function () {
        $cron = new CronWrapper('* * * * *');

        expect($cron->isDue())->toBeTrue();
    });

    it('evaluates weekend-only expression', function () {
        Carbon::setTestNow('2025-12-13 10:00:00'); // Saturday
        $cron = new CronWrapper('0 10 * * 6,0'); // Saturday and Sunday at 10:00

        expect($cron->isDue())->toBeTrue();
    });
});

describe('CronWrapper → Next/Previous Run', function () {
    it('calculates next run date', function () {
        Carbon::setTestNow('2025-12-10 09:00:00');
        $cron = new CronWrapper('0 8 * * *'); // Daily at 8:00

        $next = $cron->getNextRunDate();

        expect($next->format('Y-m-d H:i'))->toBe('2025-12-11 08:00');
    });

    it('calculates previous run date', function () {
        Carbon::setTestNow('2025-12-10 09:00:00');
        $cron = new CronWrapper('0 8 * * *'); // Daily at 8:00

        $prev = $cron->getPreviousRunDate();

        expect($prev->format('Y-m-d H:i'))->toBe('2025-12-10 08:00');
    });
});

describe('CronWrapper → Nth Weekday', function () {
    it('identifies first Saturday of December 2025', function () {
        Carbon::setTestNow('2025-12-06 10:00:00'); // First Saturday

        expect(CronWrapper::isNthWeekday('saturday', 1))->toBeTrue();
    });

    it('returns false for wrong occurrence', function () {
        Carbon::setTestNow('2025-12-06 10:00:00'); // First Saturday

        expect(CronWrapper::isNthWeekday('saturday', 2))->toBeFalse();
    });

    it('returns false for wrong day', function () {
        Carbon::setTestNow('2025-12-06 10:00:00'); // Saturday

        expect(CronWrapper::isNthWeekday('friday', 1))->toBeFalse();
    });

    it('identifies second Wednesday', function () {
        Carbon::setTestNow('2025-12-10 10:00:00'); // Second Wednesday

        expect(CronWrapper::isNthWeekday('wednesday', 2))->toBeTrue();
    });

    it('identifies last Friday of month', function () {
        Carbon::setTestNow('2025-12-26 10:00:00'); // Last Friday of December

        expect(CronWrapper::isNthWeekday('friday', -1))->toBeTrue();
    });

    it('supports day abbreviations', function () {
        Carbon::setTestNow('2025-12-06 10:00:00'); // Saturday

        expect(CronWrapper::isNthWeekday('sat', 1))->toBeTrue();
    });

    it('throws on invalid day name', function () {
        expect(fn () => CronWrapper::isNthWeekday('funday', 1))
            ->toThrow(InvalidSyntaxException::class);
    });

    it('throws on invalid occurrence', function () {
        expect(fn () => CronWrapper::isNthWeekday('saturday', 6))
            ->toThrow(InvalidSyntaxException::class);
    });
});

describe('CronWrapper → Multiple Nth Weekdays', function () {
    it('matches first or second Saturday', function () {
        Carbon::setTestNow('2025-12-06 10:00:00'); // First Saturday

        expect(CronWrapper::isAnyNthWeekday('saturday', [1, 2]))->toBeTrue();

        Carbon::setTestNow('2025-12-13 10:00:00'); // Second Saturday
        expect(CronWrapper::isAnyNthWeekday('saturday', [1, 2]))->toBeTrue();
    });

    it('does not match third Saturday when checking first and second', function () {
        Carbon::setTestNow('2025-12-20 10:00:00'); // Third Saturday

        expect(CronWrapper::isAnyNthWeekday('saturday', [1, 2]))->toBeFalse();
    });
});

describe('CronWrapper → Last Weekday', function () {
    it('identifies last Saturday of December', function () {
        Carbon::setTestNow('2025-12-27 10:00:00'); // Last Saturday

        expect(CronWrapper::isLastWeekday('saturday'))->toBeTrue();
    });

    it('returns false for non-last Saturday', function () {
        Carbon::setTestNow('2025-12-06 10:00:00'); // First Saturday

        expect(CronWrapper::isLastWeekday('saturday'))->toBeFalse();
    });
});

describe('CronWrapper → Create Nth Weekday Cron', function () {
    it('creates cron for first Saturday at 8:00', function () {
        $cron = CronWrapper::createNthWeekdayCron('saturday', 1, '08:00');

        // First Saturday of January 2026
        Carbon::setTestNow('2026-01-03 08:00:00');
        expect($cron->isDue())->toBeTrue();
    });
});
