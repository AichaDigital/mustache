<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Formatter\Formatters\FormatDateFormatter;
use AichaDigital\MustacheResolver\Core\Formatter\Formatters\ToDateStringFormatter;
use AichaDigital\MustacheResolver\Core\Formatter\Formatters\ToDateTimeFormatter;
use AichaDigital\MustacheResolver\Core\Formatter\Formatters\ToIso8601Formatter;
use AichaDigital\MustacheResolver\Core\Formatter\Formatters\ToTimeStringFormatter;
use AichaDigital\MustacheResolver\Core\Formatter\Formatters\ToUnixTimeFormatter;

describe('ToTimeStringFormatter', function () {
    beforeEach(function () {
        $this->formatter = new ToTimeStringFormatter;
    });

    it('has correct name', function () {
        expect($this->formatter->getName())->toBe('toTimeString');
    });

    it('formats timestamp to time string', function () {
        $timestamp = strtotime('2024-06-15 14:30:45');

        $result = $this->formatter->format($timestamp);

        expect($result)->toBe('14:30:45');
    });

    it('formats DateTime object', function () {
        $dateTime = new DateTimeImmutable('2024-06-15 14:30:45');

        $result = $this->formatter->format($dateTime);

        expect($result)->toBe('14:30:45');
    });

    it('formats date string', function () {
        $result = $this->formatter->format('2024-06-15 14:30:45');

        expect($result)->toBe('14:30:45');
    });
});

describe('ToDateStringFormatter', function () {
    beforeEach(function () {
        $this->formatter = new ToDateStringFormatter;
    });

    it('has correct name', function () {
        expect($this->formatter->getName())->toBe('toDateString');
    });

    it('formats timestamp to date string', function () {
        $timestamp = strtotime('2024-06-15 14:30:45');

        $result = $this->formatter->format($timestamp);

        expect($result)->toBe('2024-06-15');
    });

    it('formats DateTime object', function () {
        $dateTime = new DateTimeImmutable('2024-06-15 14:30:45');

        $result = $this->formatter->format($dateTime);

        expect($result)->toBe('2024-06-15');
    });
});

describe('ToDateTimeFormatter', function () {
    beforeEach(function () {
        $this->formatter = new ToDateTimeFormatter;
    });

    it('has correct name', function () {
        expect($this->formatter->getName())->toBe('toDateTime');
    });

    it('formats timestamp to full datetime', function () {
        $timestamp = strtotime('2024-06-15 14:30:45');

        $result = $this->formatter->format($timestamp);

        expect($result)->toBe('2024-06-15 14:30:45');
    });
});

describe('ToUnixTimeFormatter', function () {
    beforeEach(function () {
        $this->formatter = new ToUnixTimeFormatter;
    });

    it('has correct name', function () {
        expect($this->formatter->getName())->toBe('toUnixTime');
    });

    it('returns timestamp from timestamp', function () {
        $timestamp = 1718456400;

        $result = $this->formatter->format($timestamp);

        expect($result)->toBe($timestamp);
    });

    it('converts DateTime to timestamp', function () {
        $dateTime = new DateTimeImmutable('2024-06-15 14:30:45');

        $result = $this->formatter->format($dateTime);

        expect($result)->toBe($dateTime->getTimestamp());
    });

    it('converts date string to timestamp', function () {
        $result = $this->formatter->format('2024-06-15 14:30:45');

        expect($result)->toBe(strtotime('2024-06-15 14:30:45'));
    });
});

describe('ToIso8601Formatter', function () {
    beforeEach(function () {
        $this->formatter = new ToIso8601Formatter;
    });

    it('has correct name', function () {
        expect($this->formatter->getName())->toBe('toIso8601');
    });

    it('formats to ISO 8601', function () {
        $dateTime = new DateTimeImmutable('2024-06-15 14:30:45', new DateTimeZone('UTC'));

        $result = $this->formatter->format($dateTime);

        expect($result)->toContain('2024-06-15');
        expect($result)->toContain('14:30:45');
    });
});

describe('FormatDateFormatter', function () {
    beforeEach(function () {
        $this->formatter = new FormatDateFormatter;
    });

    it('has correct name', function () {
        expect($this->formatter->getName())->toBe('formatDate');
    });

    it('uses default format without argument', function () {
        $dateTime = new DateTimeImmutable('2024-06-15 14:30:45');

        $result = $this->formatter->format($dateTime);

        expect($result)->toBe('2024-06-15 14:30:45');
    });

    it('uses custom format from argument', function () {
        $dateTime = new DateTimeImmutable('2024-06-15 14:30:45');

        $result = $this->formatter->format($dateTime, ['d/m/Y']);

        expect($result)->toBe('15/06/2024');
    });

    it('handles complex format', function () {
        $dateTime = new DateTimeImmutable('2024-06-15 14:30:45');

        $result = $this->formatter->format($dateTime, ['l, F j, Y']);

        expect($result)->toBe('Saturday, June 15, 2024');
    });
});
