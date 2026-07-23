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

    describe('getMode', function () {
        it('defaults to enforce mode', function () {
            expect((new SecurityValidator)->getMode())->toBe(SecurityValidator::MODE_ENFORCE);
        });

        it('returns the configured mode', function () {
            expect((new SecurityValidator(mode: SecurityValidator::MODE_REPORT))->getMode())
                ->toBe(SecurityValidator::MODE_REPORT);
        });
    });

    describe('allowsPath', function () {
        it('allows paths without violations', function () {
            $validator = new SecurityValidator([], ['password'], 10);

            expect($validator->allowsPath('name'))->toBeTrue();
            expect($validator->allowsPath('department.name'))->toBeTrue();
        });

        it('blocks blacklisted attributes in any segment of the path (enforce)', function () {
            $validator = new SecurityValidator([], ['name']);

            expect($validator->allowsPath('name'))->toBeFalse();
            // Evasion regression: the blacklist is not evadable through a relation
            expect($validator->allowsPath('department.name'))->toBeFalse();
            expect($validator->allowsPath('department.manager.name'))->toBeFalse();
        });

        it('blocks paths exceeding max_depth (enforce)', function () {
            $validator = new SecurityValidator([], [], 1);

            expect($validator->allowsPath('name'))->toBeTrue();
            expect($validator->allowsPath('department.name'))->toBeFalse();
        });

        it('allows everything in off mode', function () {
            $validator = new SecurityValidator([], ['password'], 1, SecurityValidator::MODE_OFF);

            expect($validator->allowsPath('password'))->toBeTrue();
            expect($validator->allowsPath('a.b.c.d.e'))->toBeTrue();
        });

        it('allows but reports blacklisted segments in report mode', function () {
            $reported = [];
            $validator = new SecurityValidator(
                [],
                ['password'],
                10,
                SecurityValidator::MODE_REPORT,
                function (string $message, array $context = []) use (&$reported) {
                    $reported[] = ['message' => $message, 'context' => $context];
                },
            );

            expect($validator->allowsPath('department.password'))->toBeTrue();
            expect($reported)->toHaveCount(1);
            expect($reported[0]['message'])->toContain('blacklisted');
            expect($reported[0]['context']['path'])->toBe('department.password');
            expect($reported[0]['context']['blacklisted_segments'])->toBe(['password']);
        });

        it('allows but reports excessive depth in report mode', function () {
            $reported = [];
            $validator = new SecurityValidator(
                [],
                [],
                1,
                SecurityValidator::MODE_REPORT,
                function (string $message, array $context = []) use (&$reported) {
                    $reported[] = ['message' => $message, 'context' => $context];
                },
            );

            expect($validator->allowsPath('department.name'))->toBeTrue();
            expect($reported)->toHaveCount(1);
            expect($reported[0]['message'])->toContain('max_depth');
            expect($reported[0]['context']['depth'])->toBe(2);
        });

        it('does not fail in report mode without a reporter', function () {
            $validator = new SecurityValidator([], ['password'], 10, SecurityValidator::MODE_REPORT);

            expect($validator->allowsPath('password'))->toBeTrue();
        });

        it('reports both blacklist and depth violations for the same path in report mode', function () {
            $reported = [];
            $validator = new SecurityValidator(
                [],
                ['password'],
                1,
                SecurityValidator::MODE_REPORT,
                function (string $message, array $context = []) use (&$reported) {
                    $reported[] = $message;
                },
            );

            expect($validator->allowsPath('department.password'))->toBeTrue();
            expect($reported)->toHaveCount(2);
            expect($reported[0])->toContain('blacklisted');
            expect($reported[1])->toContain('max_depth');
        });
    });

    describe('validateModel modes', function () {
        it('reports instead of throwing in report mode', function () {
            $reported = [];
            $validator = new SecurityValidator(
                ['User'],
                [],
                10,
                SecurityValidator::MODE_REPORT,
                function (string $message, array $context = []) use (&$reported) {
                    $reported[] = ['message' => $message, 'context' => $context];
                },
            );

            expect(fn () => $validator->validateModel('App\Models\Device'))
                ->not->toThrow(ModelNotAllowedException::class);
            expect($reported)->toHaveCount(1);
            expect($reported[0]['context']['model'])->toBe('App\Models\Device');
        });

        it('does nothing in off mode', function () {
            $validator = new SecurityValidator(['User'], [], 10, SecurityValidator::MODE_OFF);

            expect(fn () => $validator->validateModel('App\Models\Device'))
                ->not->toThrow(ModelNotAllowedException::class);
        });
    });
    describe('case-insensitive blacklist', function () {
        it('matches blacklisted attributes regardless of case', function () {
            $validator = new SecurityValidator([], ['password', 'api_token']);

            expect($validator->isAttributeBlacklisted('Password'))->toBeTrue();
            expect($validator->isAttributeBlacklisted('API_TOKEN'))->toBeTrue();
            expect($validator->allowsPath('department.Password'))->toBeFalse();
        });

        it('matches blacklist entries written in any case', function () {
            $validator = new SecurityValidator([], ['Password']);

            expect($validator->isAttributeBlacklisted('password'))->toBeTrue();
        });
    });

    describe('enforce mode audit trail', function () {
        it('reports blocked paths in enforce mode', function () {
            $reported = [];
            $validator = new SecurityValidator(
                [],
                ['password'],
                10,
                SecurityValidator::MODE_ENFORCE,
                function (string $message, array $context = []) use (&$reported) {
                    $reported[] = ['message' => $message, 'context' => $context];
                },
            );

            expect($validator->allowsPath('user.password'))->toBeFalse();
            expect($reported)->toHaveCount(1);
            expect($reported[0]['message'])->toContain('blocked by security policy');
            expect($reported[0]['context']['path'])->toBe('user.password');
        });

        it('reports depth blocks in enforce mode', function () {
            $reported = [];
            $validator = new SecurityValidator(
                [],
                [],
                1,
                SecurityValidator::MODE_ENFORCE,
                function (string $message, array $context = []) use (&$reported) {
                    $reported[] = ['message' => $message, 'context' => $context];
                },
            );

            expect($validator->allowsPath('a.b'))->toBeFalse();
            expect($reported)->toHaveCount(1);
            expect($reported[0]['message'])->toContain('max_depth');
        });

        it('does not report clean paths in enforce mode', function () {
            $reported = [];
            $validator = new SecurityValidator(
                [],
                ['password'],
                10,
                SecurityValidator::MODE_ENFORCE,
                function (string $message, array $context = []) use (&$reported) {
                    $reported[] = $message;
                },
            );

            expect($validator->allowsPath('user.name'))->toBeTrue();
            expect($reported)->toBe([]);
        });
    });
});
