<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Accessors\ArrayAccessor;
use AichaDigital\MustacheResolver\Contracts\DataAccessorInterface;
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;

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

    describe('with security validator', function () {
        it('blocks blacklisted attributes in any segment (enforce)', function () {
            $validator = new SecurityValidator([], ['secret']);
            $accessor = new ArrayAccessor([
                'command_center' => ['name' => 'HQ', 'secret' => 's3cr3t'],
            ], $validator);

            expect($accessor->get('command_center.secret'))->toBeNull();
            expect($accessor->has('command_center.secret'))->toBeFalse();
            expect($accessor->get('command_center.name'))->toBe('HQ');
        });

        it('blocks paths exceeding max_depth (enforce)', function () {
            $validator = new SecurityValidator([], [], 1);
            $accessor = new ArrayAccessor(['user' => ['name' => 'John']], $validator);

            expect($accessor->get('user'))->toBe(['name' => 'John']);
            expect($accessor->get('user.name'))->toBeNull();
        });

        it('resolves but reports violations in report mode', function () {
            $reported = [];
            $validator = new SecurityValidator(
                [],
                ['secret'],
                10,
                SecurityValidator::MODE_REPORT,
                function (string $message, array $context = []) use (&$reported) {
                    $reported[] = $message;
                },
            );
            $accessor = new ArrayAccessor(['api_token' => 'tok'], $validator);

            expect($accessor->get('api_token'))->toBe('tok');
            // 'secret' is blacklisted, 'api_token' is not: no report expected for this path
            expect($reported)->toBe([]);

            $validator = new SecurityValidator(
                [],
                ['api_token'],
                10,
                SecurityValidator::MODE_REPORT,
                function (string $message, array $context = []) use (&$reported) {
                    $reported[] = $message;
                },
            );
            $accessor = new ArrayAccessor(['api_token' => 'tok'], $validator);

            expect($accessor->get('api_token'))->toBe('tok');
            expect($reported)->toHaveCount(1);
        });
    });
});
