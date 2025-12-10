<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;
use AichaDigital\MustacheResolver\Exceptions\ModelNotAllowedException;

describe('SecurityValidator', function () {
    describe('validateModel', function () {
        it('allows all models when allowed list is empty', function () {
            $validator = new SecurityValidator([]);

            expect(fn () => $validator->validateModel('App\Models\User'))
                ->not->toThrow(ModelNotAllowedException::class);
        });

        it('allows model in allowed list (full class name)', function () {
            $validator = new SecurityValidator(['App\Models\User']);

            expect(fn () => $validator->validateModel('App\Models\User'))
                ->not->toThrow(ModelNotAllowedException::class);
        });

        it('allows model in allowed list (short class name)', function () {
            $validator = new SecurityValidator(['User']);

            expect(fn () => $validator->validateModel('App\Models\User'))
                ->not->toThrow(ModelNotAllowedException::class);
        });

        it('throws when model not in allowed list', function () {
            $validator = new SecurityValidator(['User']);

            expect(fn () => $validator->validateModel('App\Models\Device'))
                ->toThrow(ModelNotAllowedException::class, 'Model "App\Models\Device" is not in the allowed models list');
        });

        it('includes allowed models in exception message', function () {
            $validator = new SecurityValidator(['User', 'Device']);

            try {
                $validator->validateModel('App\Models\Asset');
                fail('Should have thrown ModelNotAllowedException');
            } catch (ModelNotAllowedException $e) {
                expect($e->getMessage())->toContain('User, Device');
                expect($e->getModelClass())->toBe('App\Models\Asset');
                expect($e->getAllowedModels())->toBe(['User', 'Device']);
            }
        });
    });

    describe('isAttributeBlacklisted', function () {
        it('returns true for blacklisted attributes', function () {
            $validator = new SecurityValidator([], ['password', 'api_token']);

            expect($validator->isAttributeBlacklisted('password'))->toBeTrue();
            expect($validator->isAttributeBlacklisted('api_token'))->toBeTrue();
        });

        it('returns false for non-blacklisted attributes', function () {
            $validator = new SecurityValidator([], ['password']);

            expect($validator->isAttributeBlacklisted('email'))->toBeFalse();
            expect($validator->isAttributeBlacklisted('name'))->toBeFalse();
        });
    });

    describe('isDepthExceeded', function () {
        it('returns true when depth exceeds max', function () {
            $validator = new SecurityValidator([], [], 5);

            expect($validator->isDepthExceeded(6))->toBeTrue();
            expect($validator->isDepthExceeded(10))->toBeTrue();
        });

        it('returns false when depth is within limit', function () {
            $validator = new SecurityValidator([], [], 5);

            expect($validator->isDepthExceeded(1))->toBeFalse();
            expect($validator->isDepthExceeded(5))->toBeFalse();
        });
    });

    describe('getAllowedModels', function () {
        it('returns allowed models list', function () {
            $validator = new SecurityValidator(['User', 'Device']);

            expect($validator->getAllowedModels())->toBe(['User', 'Device']);
        });
    });
});
