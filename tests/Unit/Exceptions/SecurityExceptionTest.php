<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Exceptions\MustacheException;
use AichaDigital\MustacheResolver\Exceptions\SecurityException;

describe('SecurityException', function () {
    it('extends MustacheException', function () {
        $exception = SecurityException::unregisteredFunction('eval');

        expect($exception)->toBeInstanceOf(MustacheException::class);
    });

    it('creates unregisteredFunction exception', function () {
        $exception = SecurityException::unregisteredFunction('dangerousFunc');

        expect($exception->getMessage())->toBe('Function not registered: dangerousFunc');
    });

    it('creates restrictedPath exception', function () {
        $exception = SecurityException::restrictedPath('/etc/passwd');

        expect($exception->getMessage())->toBe("Access to path '/etc/passwd' is restricted");
    });

    it('creates dangerousExpression exception', function () {
        $exception = SecurityException::dangerousExpression('__proto__');

        expect($exception->getMessage())->toBe('Expression contains dangerous patterns: __proto__');
    });

    it('states the template-length ceiling in bytes, matching the strlen() that guards it', function () {
        $exception = SecurityException::templateTooLong(120, 100);

        expect($exception->getMessage())
            ->toBe('Template length 120 exceeds the configured maximum of 100 bytes');
    });

    it('creates tooManyTokens exception', function () {
        $exception = SecurityException::tooManyTokens(5, 2);

        expect($exception->getMessage())
            ->toBe('Template contains 5 mustache tokens, exceeding the configured maximum of 2');
    });
});
