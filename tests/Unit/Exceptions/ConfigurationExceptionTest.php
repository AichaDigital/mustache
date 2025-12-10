<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Exceptions\ConfigurationException;
use AichaDigital\MustacheResolver\Exceptions\MustacheException;

describe('ConfigurationException', function () {
    it('extends MustacheException', function () {
        $exception = ConfigurationException::missingResolver('TestResolver');

        expect($exception)->toBeInstanceOf(MustacheException::class);
    });

    it('creates missingResolver exception', function () {
        $exception = ConfigurationException::missingResolver('TestResolver');

        expect($exception->getMessage())->toBe('Resolver not found: TestResolver');
    });

    it('creates invalidOption exception with string value', function () {
        $exception = ConfigurationException::invalidOption('key', 'value');

        expect($exception->getMessage())->toBe("Invalid configuration value for 'key': got string");
    });

    it('creates invalidOption exception with integer value', function () {
        $exception = ConfigurationException::invalidOption('max_depth', 123);

        expect($exception->getMessage())->toBe("Invalid configuration value for 'max_depth': got integer");
    });

    it('creates invalidOption exception with array value', function () {
        $exception = ConfigurationException::invalidOption('items', []);

        expect($exception->getMessage())->toBe("Invalid configuration value for 'items': got array");
    });

    it('creates missingRequired exception', function () {
        $exception = ConfigurationException::missingRequired('api_key');

        expect($exception->getMessage())->toBe('Required configuration key missing: api_key');
    });
});
