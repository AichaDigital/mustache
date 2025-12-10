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
});
