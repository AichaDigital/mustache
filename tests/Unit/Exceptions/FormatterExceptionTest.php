<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Exceptions\FormatterException;
use AichaDigital\MustacheResolver\Exceptions\ResolutionException;

it('extends ResolutionException', function () {
    $exception = new FormatterException('toTimeString', 'invalid');

    expect($exception)->toBeInstanceOf(ResolutionException::class);
});

it('formats message correctly', function () {
    $exception = new FormatterException('toTimeString', 'not-a-timestamp');

    expect($exception->getMessage())
        ->toContain('toTimeString')
        ->toContain('not-a-timestamp');
});

it('includes reason when provided', function () {
    $exception = new FormatterException('round', 'abc', 'Value must be numeric');

    expect($exception->getMessage())
        ->toContain('round')
        ->toContain('Value must be numeric');
});

it('provides formatter name', function () {
    $exception = new FormatterException('toDateTime', 1234567890);

    expect($exception->getFormatterName())->toBe('toDateTime');
});

it('provides input value', function () {
    $exception = new FormatterException('toCents', 12.50);

    expect($exception->getInputValue())->toBe(12.50);
});

it('provides reason when set', function () {
    $exception = new FormatterException('formatDate', null, 'Cannot format null value');

    expect($exception->getReason())->toBe('Cannot format null value');
});

it('returns null reason when not set', function () {
    $exception = new FormatterException('toInt', []);

    expect($exception->getReason())->toBeNull();
});

it('handles different input value types', function () {
    $exceptionWithArray = new FormatterException('concat', ['a', 'b', 'c']);
    expect($exceptionWithArray->getInputValue())->toBe(['a', 'b', 'c']);

    $exceptionWithNull = new FormatterException('uppercase', null);
    expect($exceptionWithNull->getInputValue())->toBeNull();

    $exceptionWithBool = new FormatterException('toInt', true);
    expect($exceptionWithBool->getInputValue())->toBeTrue();
});
