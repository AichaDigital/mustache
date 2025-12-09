<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Compound\LocalVariableReplacer;

beforeEach(function () {
    $this->replacer = new LocalVariableReplacer;
});

describe('LocalVariableReplacer → replace', function () {
    it('replaces single variable', function () {
        $result = $this->replacer->replace(
            'SELECT * WHERE power < {max_power}',
            ['max_power' => 100],
        );

        expect($result)->toBe('SELECT * WHERE power < 100');
    });

    it('replaces multiple variables', function () {
        $result = $this->replacer->replace(
            'SELECT * WHERE power < {max_power} AND id = {log_id}',
            ['max_power' => 100, 'log_id' => 42],
        );

        expect($result)->toBe('SELECT * WHERE power < 100 AND id = 42');
    });

    it('replaces same variable multiple times', function () {
        $result = $this->replacer->replace(
            '{var} + {var} = {result}',
            ['var' => 5, 'result' => 10],
        );

        expect($result)->toBe('5 + 5 = 10');
    });

    it('handles float values', function () {
        $result = $this->replacer->replace(
            'power < {max_power}',
            ['max_power' => 99.5],
        );

        expect($result)->toBe('power < 99.5');
    });

    it('handles string values', function () {
        $result = $this->replacer->replace(
            "name = '{name}'",
            ['name' => 'John'],
        );

        expect($result)->toBe("name = 'John'");
    });

    it('handles boolean values', function () {
        $result = $this->replacer->replace(
            'active = {is_active}',
            ['is_active' => true],
        );

        expect($result)->toBe('active = true');
    });

    it('handles null values', function () {
        $result = $this->replacer->replace(
            'value = {empty}',
            ['empty' => null],
        );

        expect($result)->toBe('value = null');
    });

    it('handles datetime values', function () {
        $dt = new DateTimeImmutable('2024-06-15 14:30:00');
        $result = $this->replacer->replace(
            'created_at = {timestamp}',
            ['timestamp' => $dt],
        );

        expect($result)->toBe('created_at = 2024-06-15 14:30:00');
    });

    it('preserves unmatched variables', function () {
        $result = $this->replacer->replace(
            '{known} and {unknown}',
            ['known' => 'value'],
        );

        expect($result)->toBe('value and {unknown}');
    });
});

describe('LocalVariableReplacer → hasVariables', function () {
    it('detects variables', function () {
        expect($this->replacer->hasVariables('power < {max_power}'))->toBeTrue();
    });

    it('returns false when no variables', function () {
        expect($this->replacer->hasVariables('SELECT * FROM users'))->toBeFalse();
    });

    it('does not match mustache expressions', function () {
        expect($this->replacer->hasVariables('{{Model.field}}'))->toBeFalse();
    });

    it('detects underscore variables', function () {
        expect($this->replacer->hasVariables('{my_variable}'))->toBeTrue();
    });
});

describe('LocalVariableReplacer → extractVariableNames', function () {
    it('extracts single variable', function () {
        $result = $this->replacer->extractVariableNames('power < {max_power}');

        expect($result)->toBe(['max_power']);
    });

    it('extracts multiple variables', function () {
        $result = $this->replacer->extractVariableNames('{a} + {b} = {c}');

        expect($result)->toContain('a');
        expect($result)->toContain('b');
        expect($result)->toContain('c');
    });

    it('returns unique names only', function () {
        $result = $this->replacer->extractVariableNames('{x} + {x}');

        expect($result)->toHaveCount(1);
        expect($result)->toContain('x');
    });

    it('returns empty array when no variables', function () {
        $result = $this->replacer->extractVariableNames('SELECT * FROM users');

        expect($result)->toBe([]);
    });
});
