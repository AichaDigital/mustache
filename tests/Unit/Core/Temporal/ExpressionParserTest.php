<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Temporal\ExpressionParser;
use AichaDigital\MustacheResolver\Exceptions\InvalidSyntaxException;

describe('ExpressionParser → Simple Conditions', function () {
    it('parses keyword condition', function () {
        $parser = new ExpressionParser('weekday');
        $ast = $parser->parse();

        expect($ast)->toBe([
            'type' => 'keyword',
            'keyword' => 'weekday',
        ]);
    });

    it('parses always keyword', function () {
        $parser = new ExpressionParser('always');
        $ast = $parser->parse();

        expect($ast['type'])->toBe('keyword');
        expect($ast['keyword'])->toBe('always');
    });

    it('parses empty expression as always', function () {
        $parser = new ExpressionParser('');
        $ast = $parser->parse();

        expect($ast['type'])->toBe('literal');
        expect($ast['value'])->toBe('always');
    });

    it('parses time range', function () {
        $parser = new ExpressionParser('08:00-18:00');
        $ast = $parser->parse();

        expect($ast)->toBe([
            'type' => 'time_range',
            'range' => '08:00-18:00',
        ]);
    });

    it('parses cron expression', function () {
        $parser = new ExpressionParser('cron:0 8 * * 1-5');
        $ast = $parser->parse();

        expect($ast)->toBe([
            'type' => 'cron',
            'expression' => '0 8 * * 1-5',
        ]);
    });

    it('parses nth weekday', function () {
        $parser = new ExpressionParser('nth:saturday:1');
        $ast = $parser->parse();

        expect($ast)->toBe([
            'type' => 'nth_weekday',
            'day' => 'saturday',
            'occurrences' => [1],
        ]);
    });

    it('parses nth weekday with multiple occurrences', function () {
        $parser = new ExpressionParser('nth:saturday:1,2');
        $ast = $parser->parse();

        expect($ast)->toBe([
            'type' => 'nth_weekday',
            'day' => 'saturday',
            'occurrences' => [1, 2],
        ]);
    });

    it('parses last weekday', function () {
        $parser = new ExpressionParser('last:friday');
        $ast = $parser->parse();

        expect($ast)->toBe([
            'type' => 'last_weekday',
            'day' => 'friday',
        ]);
    });

    it('parses custom condition', function () {
        $parser = new ExpressionParser('holiday');
        $ast = $parser->parse();

        expect($ast)->toBe([
            'type' => 'custom',
            'keyword' => 'holiday',
        ]);
    });
});

describe('ExpressionParser → AND Expressions', function () {
    it('parses AND expression', function () {
        $parser = new ExpressionParser('weekday && 08:00-18:00');
        $ast = $parser->parse();

        expect($ast['type'])->toBe('and');
        expect($ast['left']['type'])->toBe('keyword');
        expect($ast['left']['keyword'])->toBe('weekday');
        expect($ast['right']['type'])->toBe('time_range');
    });

    it('parses chained AND expressions', function () {
        $parser = new ExpressionParser('weekday && 08:00-18:00 && always');
        $ast = $parser->parse();

        expect($ast['type'])->toBe('and');
        expect($ast['left']['type'])->toBe('and');
    });
});

describe('ExpressionParser → OR Expressions', function () {
    it('parses OR expression', function () {
        $parser = new ExpressionParser('weekday || weekend');
        $ast = $parser->parse();

        expect($ast['type'])->toBe('or');
        expect($ast['left']['keyword'])->toBe('weekday');
        expect($ast['right']['keyword'])->toBe('weekend');
    });
});

describe('ExpressionParser → NOT Expressions', function () {
    it('parses NOT expression', function () {
        $parser = new ExpressionParser('!weekend');
        $ast = $parser->parse();

        expect($ast['type'])->toBe('not');
        expect($ast['operand']['keyword'])->toBe('weekend');
    });

    it('parses double NOT', function () {
        $parser = new ExpressionParser('!!weekday');
        $ast = $parser->parse();

        expect($ast['type'])->toBe('not');
        expect($ast['operand']['type'])->toBe('not');
    });
});

describe('ExpressionParser → Complex Expressions', function () {
    it('respects operator precedence (AND before OR)', function () {
        $parser = new ExpressionParser('weekday && 08:00-18:00 || weekend');
        $ast = $parser->parse();

        // OR should be the root because it has lower precedence
        expect($ast['type'])->toBe('or');
        expect($ast['left']['type'])->toBe('and');
        expect($ast['right']['keyword'])->toBe('weekend');
    });

    it('parses grouped expressions', function () {
        $parser = new ExpressionParser('(weekday || weekend) && 08:00-18:00');
        $ast = $parser->parse();

        expect($ast['type'])->toBe('and');
        expect($ast['left']['type'])->toBe('or');
        expect($ast['right']['type'])->toBe('time_range');
    });

    it('parses NOT with grouping', function () {
        $parser = new ExpressionParser('!(weekday && 08:00-18:00)');
        $ast = $parser->parse();

        expect($ast['type'])->toBe('not');
        expect($ast['operand']['type'])->toBe('and');
    });

    it('parses complex real-world expression', function () {
        $parser = new ExpressionParser('weekday && 08:00-18:00 && !holiday');
        $ast = $parser->parse();

        expect($ast['type'])->toBe('and');
    });
});

describe('ExpressionParser → Extract Keywords', function () {
    it('extracts all keywords from expression', function () {
        $parser = new ExpressionParser('weekday && 08:00-18:00 && !holiday');
        $keywords = $parser->extractKeywords();

        expect($keywords)->toContain('weekday');
        expect($keywords)->toContain('08:00-18:00');
        expect($keywords)->toContain('holiday');
    });
});

describe('ExpressionParser → Error Handling', function () {
    it('throws on unexpected character', function () {
        $parser = new ExpressionParser('weekday @ weekend');

        expect(fn () => $parser->parse())
            ->toThrow(InvalidSyntaxException::class);
    });

    it('throws on unclosed parenthesis', function () {
        $parser = new ExpressionParser('(weekday && weekend');

        expect(fn () => $parser->parse())
            ->toThrow(InvalidSyntaxException::class);
    });

    it('throws on trailing operator', function () {
        $parser = new ExpressionParser('weekday &&');

        expect(fn () => $parser->parse())
            ->toThrow(InvalidSyntaxException::class);
    });
});
