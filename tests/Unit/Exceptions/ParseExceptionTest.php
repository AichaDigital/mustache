<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Exceptions\MustacheException;
use AichaDigital\MustacheResolver\Exceptions\ParseException;

describe('ParseException', function () {
    it('extends MustacheException', function () {
        $exception = new ParseException('Parse error');

        expect($exception)->toBeInstanceOf(MustacheException::class);
    });

    it('creates with message only', function () {
        $exception = new ParseException('Parse error');

        expect($exception->getMessage())->toBe('Parse error');
        expect($exception->getTemplate())->toBeNull();
        expect($exception->getPosition())->toBeNull();
    });

    it('creates with template', function () {
        $exception = new ParseException('Parse error', 'Hello {{name}}');

        expect($exception->getTemplate())->toBe('Hello {{name}}');
    });

    it('creates with position', function () {
        $exception = new ParseException('Parse error', 'Hello {{name}}', 6);

        expect($exception->getPosition())->toBe(6);
    });

    it('creates with all parameters', function () {
        $exception = new ParseException(
            'Invalid mustache at position 6',
            'Hello {{name',
            6
        );

        expect($exception->getMessage())->toBe('Invalid mustache at position 6');
        expect($exception->getTemplate())->toBe('Hello {{name');
        expect($exception->getPosition())->toBe(6);
    });
});
