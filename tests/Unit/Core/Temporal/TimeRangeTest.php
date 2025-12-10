<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Temporal\TimeRange;
use AichaDigital\MustacheResolver\Exceptions\InvalidSyntaxException;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2025-12-10 14:30:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('TimeRange → Creation', function () {
    it('creates from valid time range string', function () {
        $range = TimeRange::fromString('08:00-18:00');

        expect($range->getStart())->toBe('08:00');
        expect($range->getEnd())->toBe('18:00');
        expect($range->isOvernight())->toBeFalse();
    });

    it('creates overnight range', function () {
        $range = TimeRange::fromString('22:00-06:00');

        expect($range->getStart())->toBe('22:00');
        expect($range->getEnd())->toBe('06:00');
        expect($range->isOvernight())->toBeTrue();
    });

    it('normalizes single-digit hours', function () {
        $range = TimeRange::fromString('8:00-9:30');

        expect($range->getStart())->toBe('08:00');
        expect($range->getEnd())->toBe('09:30');
    });

    it('throws on invalid format', function () {
        expect(fn () => TimeRange::fromString('invalid'))
            ->toThrow(InvalidSyntaxException::class);
    });

    it('throws on invalid time values', function () {
        expect(fn () => TimeRange::fromString('25:00-18:00'))
            ->toThrow(InvalidSyntaxException::class);
    });
});

describe('TimeRange → Standard Range Evaluation', function () {
    it('returns true when current time is within range', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $range = TimeRange::fromString('08:00-18:00');

        expect($range->contains())->toBeTrue();
    });

    it('returns false when current time is before range', function () {
        Carbon::setTestNow('2025-12-10 07:30:00');
        $range = TimeRange::fromString('08:00-18:00');

        expect($range->contains())->toBeFalse();
    });

    it('returns false when current time is after range', function () {
        Carbon::setTestNow('2025-12-10 19:00:00');
        $range = TimeRange::fromString('08:00-18:00');

        expect($range->contains())->toBeFalse();
    });

    it('returns true at exact start time', function () {
        Carbon::setTestNow('2025-12-10 08:00:00');
        $range = TimeRange::fromString('08:00-18:00');

        expect($range->contains())->toBeTrue();
    });

    it('returns false at exact end time (exclusive)', function () {
        Carbon::setTestNow('2025-12-10 18:00:00');
        $range = TimeRange::fromString('08:00-18:00');

        expect($range->contains())->toBeFalse();
    });
});

describe('TimeRange → Overnight Range Evaluation', function () {
    it('returns true late at night', function () {
        Carbon::setTestNow('2025-12-10 23:30:00');
        $range = TimeRange::fromString('22:00-06:00');

        expect($range->contains())->toBeTrue();
    });

    it('returns true early in the morning', function () {
        Carbon::setTestNow('2025-12-10 02:00:00');
        $range = TimeRange::fromString('22:00-06:00');

        expect($range->contains())->toBeTrue();
    });

    it('returns false during the day', function () {
        Carbon::setTestNow('2025-12-10 14:00:00');
        $range = TimeRange::fromString('22:00-06:00');

        expect($range->contains())->toBeFalse();
    });

    it('returns true at start of overnight range', function () {
        Carbon::setTestNow('2025-12-10 22:00:00');
        $range = TimeRange::fromString('22:00-06:00');

        expect($range->contains())->toBeTrue();
    });

    it('returns false at end of overnight range (exclusive)', function () {
        Carbon::setTestNow('2025-12-10 06:00:00');
        $range = TimeRange::fromString('22:00-06:00');

        expect($range->contains())->toBeFalse();
    });
});

describe('TimeRange → Custom DateTime', function () {
    it('evaluates at specific datetime', function () {
        $range = TimeRange::fromString('08:00-18:00');
        $at = Carbon::create(2025, 12, 10, 10, 30, 0);

        expect($range->contains($at))->toBeTrue();
    });

    it('evaluates overnight range at specific datetime', function () {
        $range = TimeRange::fromString('22:00-06:00');
        $at = Carbon::create(2025, 12, 10, 3, 0, 0);

        expect($range->contains($at))->toBeTrue();
    });
});

describe('TimeRange → Multiple Ranges', function () {
    it('creates multiple ranges from comma-separated string', function () {
        $ranges = TimeRange::fromMultiple('08:00-12:00, 14:00-18:00');

        expect($ranges)->toHaveCount(2);
        expect($ranges[0]->getStart())->toBe('08:00');
        expect($ranges[1]->getStart())->toBe('14:00');
    });

    it('checks if any range contains time', function () {
        $ranges = TimeRange::fromMultiple('08:00-12:00, 14:00-18:00');

        Carbon::setTestNow('2025-12-10 10:00:00');
        expect(TimeRange::anyContains($ranges))->toBeTrue();

        Carbon::setTestNow('2025-12-10 13:00:00');
        expect(TimeRange::anyContains($ranges))->toBeFalse();

        Carbon::setTestNow('2025-12-10 16:00:00');
        expect(TimeRange::anyContains($ranges))->toBeTrue();
    });
});
