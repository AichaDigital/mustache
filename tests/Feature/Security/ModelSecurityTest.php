<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Accessors\EloquentAccessor;
use AichaDigital\MustacheResolver\Core\Context\ResolutionContext;
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;
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
});

describe('Model Security', function () {
    describe('allowed_models validation', function () {
        it('allows access when model is in allowed list', function () {
            $config = ['allowed_models' => ['User']];
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
            $config = ['allowed_models' => ['User']]; // User allowed, relations are accessible
            $context = ResolutionContext::fromModel($this->user, ['security' => $config] + $config);

            // Relations are part of the allowed model's data
            expect($context->get('department.name'))->toBe('Engineering');
        });

        it('allows access when allowed list is empty', function () {
            $config = ['allowed_models' => []];
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

        it('checks only first segment of path', function () {
            $config = ['blacklisted_attributes' => ['department']];
            $context = ResolutionContext::fromModel($this->user, ['security' => $config] + $config);

            expect($context->get('department.name'))->toBeNull();
        });
    });

    describe('ResolutionContext::fromModel', function () {
        it('creates context with security validation', function () {
            $config = [
                'allowed_models' => ['User'],
                'blacklisted_attributes' => ['email'],
            ];

            $context = ResolutionContext::fromModel($this->user, $config);

            expect($context->get('name'))->toBe('John Doe');
            expect($context->get('email'))->toBeNull();
        });

        it('works without security config', function () {
            $context = ResolutionContext::fromModel($this->user);

            expect($context->get('name'))->toBe('John Doe');
            expect($context->get('email'))->toBe('john@example.com');
        });
    });
});
