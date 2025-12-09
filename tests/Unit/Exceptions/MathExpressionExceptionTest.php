<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Exceptions\MathExpressionException;
use AichaDigital\MustacheResolver\Exceptions\SecurityException;

it('extends SecurityException', function () {
    $exception = new MathExpressionException('1 / 0', 'Division by zero');

    expect($exception)->toBeInstanceOf(SecurityException::class);
});

it('formats message correctly', function () {
    $exception = new MathExpressionException('1 + 2 * 3', 'Some error');

    expect($exception->getMessage())
        ->toContain('1 + 2 * 3')
        ->toContain('Some error');
});

it('provides expression', function () {
    $exception = new MathExpressionException('{a} + {b}', 'Invalid');

    expect($exception->getExpression())->toBe('{a} + {b}');
});

it('provides reason', function () {
    $exception = new MathExpressionException('1 / 0', 'Division by zero');

    expect($exception->getReason())->toBe('Division by zero');
});

it('creates tooLong exception', function () {
    $longExpression = str_repeat('1 + ', 50).'1';
    $exception = MathExpressionException::tooLong($longExpression, 100);

    expect($exception->getMessage())
        ->toContain('exceeds maximum length')
        ->toContain('100');
    expect($exception->getExpression())->toBe($longExpression);
});

it('creates tooDeep exception', function () {
    $exception = MathExpressionException::tooDeep('((((1 + 2))))', 3);

    expect($exception->getMessage())
        ->toContain('exceeds maximum nesting depth')
        ->toContain('3');
});

it('creates invalidOperator exception', function () {
    $exception = MathExpressionException::invalidOperator('1 ** 2', '**');

    expect($exception->getMessage())
        ->toContain('**')
        ->toContain('not allowed');
});

it('creates divisionByZero exception', function () {
    $exception = MathExpressionException::divisionByZero('100 / 0');

    expect($exception->getMessage())
        ->toContain('Division by zero');
    expect($exception->getExpression())->toBe('100 / 0');
});
