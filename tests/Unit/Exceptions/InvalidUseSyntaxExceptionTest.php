<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Exceptions\InvalidUseSyntaxException;
use AichaDigital\MustacheResolver\Exceptions\ParseException;

it('extends ParseException', function () {
    $exception = new InvalidUseSyntaxException('USE invalid');

    expect($exception)->toBeInstanceOf(ParseException::class);
});

it('formats message correctly', function () {
    $exception = new InvalidUseSyntaxException('USE {var} => missing separator');

    expect($exception->getMessage())
        ->toContain('Invalid USE clause syntax')
        ->toContain('USE {var} => missing separator');
});

it('includes hint when provided', function () {
    $exception = new InvalidUseSyntaxException(
        'USE {var} => {{expr}}',
        'Missing && separator before statement'
    );

    expect($exception->getMessage())
        ->toContain('Missing && separator');
});

it('provides template', function () {
    $template = 'USE {max_power} => {{CommandCenter.max_power}}';
    $exception = new InvalidUseSyntaxException($template);

    expect($exception->getTemplate())->toBe($template);
});

it('provides hint when set', function () {
    $exception = new InvalidUseSyntaxException('USE invalid', 'Expected => after variable name');

    expect($exception->getHint())->toBe('Expected => after variable name');
});

it('returns null hint when not set', function () {
    $exception = new InvalidUseSyntaxException('USE invalid');

    expect($exception->getHint())->toBeNull();
});

it('truncates long templates in message', function () {
    $longTemplate = str_repeat('x', 200);
    $exception = new InvalidUseSyntaxException($longTemplate);

    expect(strlen($exception->getMessage()))->toBeLessThan(250);
    expect($exception->getMessage())->toContain('...');
});
