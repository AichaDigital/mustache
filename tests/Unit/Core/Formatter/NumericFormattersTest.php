<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Formatter\Formatters\AbsFormatter;
use AichaDigital\MustacheResolver\Core\Formatter\Formatters\CeilFormatter;
use AichaDigital\MustacheResolver\Core\Formatter\Formatters\FloorFormatter;
use AichaDigital\MustacheResolver\Core\Formatter\Formatters\FromCentsFormatter;
use AichaDigital\MustacheResolver\Core\Formatter\Formatters\NumberFormatter;
use AichaDigital\MustacheResolver\Core\Formatter\Formatters\PercentFormatter;
use AichaDigital\MustacheResolver\Core\Formatter\Formatters\RoundFormatter;
use AichaDigital\MustacheResolver\Core\Formatter\Formatters\ToCentsFormatter;
use AichaDigital\MustacheResolver\Core\Formatter\Formatters\ToFloatFormatter;
use AichaDigital\MustacheResolver\Core\Formatter\Formatters\ToIntFormatter;

describe('ToIntFormatter', function () {
    beforeEach(function () {
        $this->formatter = new ToIntFormatter;
    });

    it('has correct name', function () {
        expect($this->formatter->getName())->toBe('toInt');
    });

    it('converts string to int', function () {
        expect($this->formatter->format('42'))->toBe(42);
    });

    it('converts float to int', function () {
        expect($this->formatter->format(42.9))->toBe(42);
    });

    it('returns int unchanged', function () {
        expect($this->formatter->format(42))->toBe(42);
    });
});

describe('ToFloatFormatter', function () {
    beforeEach(function () {
        $this->formatter = new ToFloatFormatter;
    });

    it('has correct name', function () {
        expect($this->formatter->getName())->toBe('toFloat');
    });

    it('converts string to float', function () {
        expect($this->formatter->format('42.5'))->toBe(42.5);
    });

    it('converts int to float', function () {
        expect($this->formatter->format(42))->toBe(42.0);
    });
});

describe('ToCentsFormatter', function () {
    beforeEach(function () {
        $this->formatter = new ToCentsFormatter;
    });

    it('has correct name', function () {
        expect($this->formatter->getName())->toBe('toCents');
    });

    it('converts decimal to cents', function () {
        expect($this->formatter->format(12.50))->toBe(1250);
    });

    it('converts whole number to cents', function () {
        expect($this->formatter->format(12))->toBe(1200);
    });

    it('handles string input', function () {
        expect($this->formatter->format('12.50'))->toBe(1250);
    });

    it('rounds correctly', function () {
        expect($this->formatter->format(12.999))->toBe(1300);
    });
});

describe('FromCentsFormatter', function () {
    beforeEach(function () {
        $this->formatter = new FromCentsFormatter;
    });

    it('has correct name', function () {
        expect($this->formatter->getName())->toBe('fromCents');
    });

    it('converts cents to decimal', function () {
        expect($this->formatter->format(1250))->toBe(12.50);
    });

    it('handles odd cents', function () {
        expect($this->formatter->format(1299))->toBe(12.99);
    });
});

describe('RoundFormatter', function () {
    beforeEach(function () {
        $this->formatter = new RoundFormatter;
    });

    it('has correct name', function () {
        expect($this->formatter->getName())->toBe('round');
    });

    it('rounds to nearest integer by default', function () {
        expect($this->formatter->format(12.567))->toBe(13.0);
    });

    it('rounds to specified precision', function () {
        expect($this->formatter->format(12.567, [2]))->toBe(12.57);
    });

    it('rounds down when appropriate', function () {
        expect($this->formatter->format(12.3))->toBe(12.0);
    });
});

describe('FloorFormatter', function () {
    beforeEach(function () {
        $this->formatter = new FloorFormatter;
    });

    it('has correct name', function () {
        expect($this->formatter->getName())->toBe('floor');
    });

    it('rounds down', function () {
        expect($this->formatter->format(12.9))->toBe(12);
    });

    it('handles negative numbers', function () {
        expect($this->formatter->format(-12.1))->toBe(-13);
    });
});

describe('CeilFormatter', function () {
    beforeEach(function () {
        $this->formatter = new CeilFormatter;
    });

    it('has correct name', function () {
        expect($this->formatter->getName())->toBe('ceil');
    });

    it('rounds up', function () {
        expect($this->formatter->format(12.1))->toBe(13);
    });

    it('handles negative numbers', function () {
        expect($this->formatter->format(-12.9))->toBe(-12);
    });
});

describe('NumberFormatter', function () {
    beforeEach(function () {
        $this->formatter = new NumberFormatter;
    });

    it('has correct name', function () {
        expect($this->formatter->getName())->toBe('number');
    });

    it('formats with default options', function () {
        expect($this->formatter->format(1234567.89))->toBe('1,234,567.89');
    });

    it('formats with custom decimals', function () {
        expect($this->formatter->format(1234567.89, [0]))->toBe('1,234,568');
    });

    it('formats with custom separators', function () {
        expect($this->formatter->format(1234567.89, [2, ',', '.']))->toBe('1.234.567,89');
    });
});

describe('PercentFormatter', function () {
    beforeEach(function () {
        $this->formatter = new PercentFormatter;
    });

    it('has correct name', function () {
        expect($this->formatter->getName())->toBe('percent');
    });

    it('formats decimal as percentage', function () {
        expect($this->formatter->format(0.856))->toBe('86%');
    });

    it('formats with decimals', function () {
        expect($this->formatter->format(0.856, [1]))->toBe('85.6%');
    });

    it('handles whole numbers', function () {
        expect($this->formatter->format(1))->toBe('100%');
    });
});

describe('AbsFormatter', function () {
    beforeEach(function () {
        $this->formatter = new AbsFormatter;
    });

    it('has correct name', function () {
        expect($this->formatter->getName())->toBe('abs');
    });

    it('returns absolute value of negative', function () {
        expect($this->formatter->format(-42.5))->toBe(42.5);
    });

    it('returns positive unchanged', function () {
        expect($this->formatter->format(42.5))->toBe(42.5);
    });

    it('handles zero', function () {
        expect($this->formatter->format(0))->toBe(0);
    });
});
