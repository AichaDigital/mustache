<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Accessors\ArrayAccessor;
use AichaDigital\MustacheResolver\Contracts\DataAccessorInterface;

describe('ArrayAccessor', function () {
    it('implements DataAccessorInterface', function () {
        $accessor = new ArrayAccessor([]);

        expect($accessor)->toBeInstanceOf(DataAccessorInterface::class);
    });

    it('gets value by key', function () {
        $accessor = new ArrayAccessor(['name' => 'John']);

        expect($accessor->get('name'))->toBe('John');
    });

    it('gets nested value', function () {
        $accessor = new ArrayAccessor([
            'user' => [
                'profile' => ['email' => 'john@example.com'],
            ],
        ]);

        expect($accessor->get('user.profile.email'))->toBe('john@example.com');
    });

    it('returns null for non-existent key', function () {
        $accessor = new ArrayAccessor([]);

        expect($accessor->get('missing'))->toBeNull();
    });

    it('checks if key exists', function () {
        $accessor = new ArrayAccessor(['name' => 'John']);

        expect($accessor->has('name'))->toBeTrue();
        expect($accessor->has('missing'))->toBeFalse();
    });

    it('returns keys', function () {
        $accessor = new ArrayAccessor(['name' => 'John', 'age' => 30]);

        expect($accessor->keys())->toBe(['name', 'age']);
    });

    it('returns source type', function () {
        $accessor = new ArrayAccessor([]);

        expect($accessor->getSourceType())->toBe('array');
    });

    it('returns raw array', function () {
        $data = ['name' => 'John'];
        $accessor = new ArrayAccessor($data);

        expect($accessor->getRaw())->toBe($data);
    });
});
