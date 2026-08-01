<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Accessors\EloquentAccessor;
use AichaDigital\MustacheResolver\Cache\NullCache;
use AichaDigital\MustacheResolver\Core\Context\ResolutionContext;
use AichaDigital\MustacheResolver\Core\MustacheResolver;
use AichaDigital\MustacheResolver\Core\Parser\MustacheParser;
use AichaDigital\MustacheResolver\Core\Pipeline\PipelineBuilder;
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;
use AichaDigital\MustacheResolver\Exceptions\ConfigurationException;
use AichaDigital\MustacheResolver\Exceptions\ModelNotAllowedException;
use Workbench\App\Models\Department;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $this->department = Department::factory()->create([
        'name' => 'Engineering',
    ]);

    $this->user->department()->associate($this->department);
    $this->user->save();

    // password is not in User::$fillable and the workbench users table has
    // no such column — forceFill (after the save above) sets it in memory
    // only, never persisted, so the default-policy fixture below actually
    // carries a sensitive value to block, without requiring a schema change.
    $this->user->forceFill(['password' => 'secret-hash']);
});

describe('Model Security', function () {
    describe('allowed_root_models validation', function () {
        it('allows access when model is in allowed list', function () {
            $config = ['allowed_root_models' => [User::class]];
            $context = ResolutionContext::fromModel($this->user, ['security' => $config] + $config);

            expect($context->get('name'))->toBe('John Doe');
        });

        it('throws when model is not in allowed list', function () {
            $validator = new SecurityValidator(['Department']);

            expect(fn () => new EloquentAccessor($this->user, $validator))
                ->toThrow(
                    ModelNotAllowedException::class,
                    'Model "Workbench\App\Models\User" is not in the allowed models list'
                );
        });

        it('allows access to related models when main model is allowed', function () {
            $config = ['allowed_root_models' => [User::class]]; // User allowed, relations are accessible
            $context = ResolutionContext::fromModel($this->user, ['security' => $config] + $config);

            // Relations are part of the allowed model's data
            expect($context->get('department.name'))->toBe('Engineering');
        });

        it('allows access when allowed list is empty', function () {
            $config = ['allowed_root_models' => []];
            $context = ResolutionContext::fromModel($this->user, ['security' => $config] + $config);

            expect($context->get('name'))->toBe('John Doe');
            expect($context->get('department.name'))->toBe('Engineering');
        });
    });

    describe('blacklisted_attributes', function () {
        it('returns null for blacklisted attributes', function () {
            $config = ['blacklisted_attributes' => ['email']];
            $context = ResolutionContext::fromModel($this->user, ['security' => $config] + $config);

            expect($context->get('email'))->toBeNull();
        });

        it('allows non-blacklisted attributes', function () {
            $config = ['blacklisted_attributes' => ['email']];
            $context = ResolutionContext::fromModel($this->user, ['security' => $config] + $config);

            expect($context->get('name'))->toBe('John Doe');
            expect($context->get('id'))->not->toBeNull();
        });

        it('checks every segment of the path', function () {
            $config = ['blacklisted_attributes' => ['department']];
            $context = ResolutionContext::fromModel($this->user, ['security' => $config] + $config);

            expect($context->get('department.name'))->toBeNull();
        });

        it('blocks blacklisted attributes behind relations (evasion regression)', function () {
            $config = ['blacklisted_attributes' => ['name']];
            $context = ResolutionContext::fromModel($this->user, ['security' => $config] + $config);

            expect($context->get('name'))->toBeNull();
            // Previously evaded: the blacklist only checked the first segment
            expect($context->get('department.name'))->toBeNull();
        });

        it('resolves but reports blacklisted paths in report mode', function () {
            $reported = [];
            $config = [
                'blacklisted_attributes' => ['email'],
                'mode' => SecurityValidator::MODE_REPORT,
                'reporter' => function (string $message, array $context = []) use (&$reported) {
                    $reported[] = ['message' => $message, 'context' => $context];
                },
            ];
            $context = ResolutionContext::fromModel($this->user, $config);

            expect($context->get('email'))->toBe('john@example.com');
            expect($reported)->toHaveCount(1);
            expect($reported[0]['context']['path'])->toBe('email');
        });
    });

    describe('max_depth', function () {
        it('blocks paths exceeding max_depth in enforce mode', function () {
            $config = ['max_depth' => 1];
            $context = ResolutionContext::fromModel($this->user, ['security' => $config] + $config);

            expect($context->get('name'))->toBe('John Doe');
            expect($context->get('department.name'))->toBeNull();
        });

        it('resolves but reports excessive depth in report mode', function () {
            $reported = [];
            $config = [
                'max_depth' => 1,
                'mode' => SecurityValidator::MODE_REPORT,
                'reporter' => function (string $message, array $context = []) use (&$reported) {
                    $reported[] = ['message' => $message, 'context' => $context];
                },
            ];
            $context = ResolutionContext::fromModel($this->user, $config);

            expect($context->get('department.name'))->toBe('Engineering');
            expect($reported)->toHaveCount(1);
            expect($reported[0]['message'])->toContain('max_depth');
        });
    });

    describe('ResolutionContext::fromModel', function () {
        it('creates context with security validation', function () {
            $config = [
                'allowed_root_models' => [User::class],
                'blacklisted_attributes' => ['email'],
            ];

            $context = ResolutionContext::fromModel($this->user, $config);

            expect($context->get('name'))->toBe('John Doe');
            expect($context->get('email'))->toBeNull();
        });

        it('applies the default policy when no security config is given (v3)', function () {
            // Fixture guard: prove the model actually carries the sensitive
            // value when the policy is off — otherwise the block assertion
            // below could pass against a fixture that never had a password
            // at all.
            $off = ResolutionContext::fromModel($this->user, ['mode' => SecurityValidator::MODE_OFF]);
            expect($off->get('password'))->not->toBeNull();

            $context = ResolutionContext::fromModel($this->user);

            expect($context->get('name'))->toBe('John Doe');
            expect($context->get('password'))->toBeNull();
        });

        it('ignores a non-Closure reporter in report mode', function () {
            $config = [
                'blacklisted_attributes' => ['email'],
                'mode' => SecurityValidator::MODE_REPORT,
                'reporter' => 'not-a-closure',
            ];
            $context = ResolutionContext::fromModel($this->user, $config);

            // The invalid reporter is dropped: resolution proceeds without errors
            expect($context->get('email'))->toBe('john@example.com');
        });

        it('fromModel rejects the removed allowed_models key loudly', function () {
            ResolutionContext::fromModel($this->user, [
                'allowed_models' => ['User'],
            ]);
        })->throws(
            ConfigurationException::class,
            "Configuration key 'allowed_models' was renamed to 'allowed_root_models'",
        );

        it('warns that a class whitelist cannot apply to an array root datum', function () {
            $reports = [];
            $validator = new SecurityValidator(
                allowedRootModels: [User::class],
                mode: SecurityValidator::MODE_ENFORCE,
                reporter: function (string $message, array $context = []) use (&$reports): void {
                    $reports[] = $message;
                },
            );

            $resolver = new MustacheResolver(
                new MustacheParser,
                PipelineBuilder::create()->build(),
                new NullCache,
                $validator,
            );

            $resolver->translate('{{User.name}}', ['User' => ['name' => 'John']]);

            expect($reports)->toContain(
                'mustache-resolver: allowed_root_models cannot be applied, the root datum is not a model'
            );
        });
    });

    describe('MustacheResolver standalone default policy (v3, §11.2)', function () {
        it('a resolver built without a validator gets the default policy', function () {
            $resolver = new MustacheResolver(
                new MustacheParser,
                PipelineBuilder::create()->build(),
                new NullCache,
            );

            // MODEL tokens (PascalCase prefix, e.g. "User") strip the
            // prefix and navigate the field path against the ROOT data
            // (see ModelResolverTest.php) — the array is flat, not
            // wrapped under a 'User' key.
            $result = $resolver->translate(
                'Secret: {{User.api_token}} / Name: {{User.name}}',
                ['api_token' => 'tok_123', 'name' => 'John'],
            );

            expect($result->getTranslated())->toBe('Secret:  / Name: John');
        });

        it('opting out requires mode off explicitly', function () {
            $resolver = new MustacheResolver(
                new MustacheParser,
                PipelineBuilder::create()->build(),
                new NullCache,
                new SecurityValidator(mode: SecurityValidator::MODE_OFF),
            );

            $result = $resolver->translate(
                'Secret: {{User.api_token}}',
                ['api_token' => 'tok_123'],
            );

            expect($result->getTranslated())->toBe('Secret: tok_123');
        });
    });
});
