<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;
use AichaDigital\MustacheResolver\Exceptions\ModelNotAllowedException;
use AichaDigital\MustacheResolver\Laravel\Facades\Mustache;
use Illuminate\Support\Facades\Log;
use Workbench\App\Models\Department;
use Workbench\App\Models\Post;
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

describe('Security wiring through the ServiceProvider', function () {
    it('resolves the documented basic usage with an Eloquent model', function () {
        $result = Mustache::translate('Hello, {{User.name}}! Your email is {{User.email}}.', $this->user);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getTranslated())->toBe('Hello, John Doe! Your email is john@example.com.');
    });

    it('resolves relation navigation with an Eloquent model', function () {
        $result = Mustache::translate('Department: {{User.department.name}}', $this->user);

        expect($result->getTranslated())->toBe('Department: Engineering');
    });

    it('registers the SecurityValidator from config with report mode by default', function () {
        $validator = app(SecurityValidator::class);

        expect($validator->getMode())->toBe(SecurityValidator::MODE_REPORT);
        expect($validator->isAttributeBlacklisted('password'))->toBeTrue();
    });

    it('resolves but logs blacklisted attributes in report mode', function () {
        config()->set('mustache-resolver.security.mode', 'report');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['email']);
        Log::spy();

        $result = Mustache::translate('Email: {{User.email}}', $this->user);

        expect($result->getTranslated())->toBe('Email: john@example.com');
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context = []) => str_contains($message, 'blacklisted')
                && $context['path'] === 'email');
    });

    it('resolves blacklisted attributes without logging in off mode', function () {
        config()->set('mustache-resolver.security.mode', 'off');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['email']);
        config()->set('mustache-resolver.security.max_depth', 1);
        Log::spy();

        $result = Mustache::translate('Email: {{User.email}} / Dept: {{User.department.name}}', $this->user);

        expect($result->getTranslated())->toBe('Email: john@example.com / Dept: Engineering');
        Log::shouldNotHaveReceived('warning');
    });

    it('blocks blacklisted attributes in enforce mode', function () {
        config()->set('mustache-resolver.security.mode', 'enforce');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['email']);

        $result = Mustache::translate('Email: {{User.email}} / Name: {{User.name}}', $this->user);

        expect($result->getTranslated())->toBe('Email:  / Name: John Doe');
    });

    it('blocks blacklisted attributes behind relations in enforce mode', function () {
        config()->set('mustache-resolver.security.mode', 'enforce');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['name']);

        $result = Mustache::translate('Dept: {{User.department.name}}', $this->user);

        expect($result->getTranslated())->toBe('Dept: ');
    });

    it('applies max_depth in enforce mode', function () {
        config()->set('mustache-resolver.security.mode', 'enforce');
        config()->set('mustache-resolver.security.max_depth', 1);

        $result = Mustache::translate('Name: {{User.name}} / Dept: {{User.department.name}}', $this->user);

        expect($result->getTranslated())->toBe('Name: John Doe / Dept: ');
    });

    it('applies the blacklist on the array data path', function () {
        config()->set('mustache-resolver.security.mode', 'enforce');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['api_token']);

        $data = ['user' => ['name' => 'John', 'api_token' => 'tok-123']];
        $result = Mustache::translate('Name: {{user.name}} / Token: {{user.api_token}}', $data);

        expect($result->getTranslated())->toBe('Name: John / Token: ');
    });

    it('resolves but logs violations on the array data path in report mode', function () {
        config()->set('mustache-resolver.security.mode', 'report');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['api_token']);
        Log::spy();

        $data = ['user' => ['name' => 'John', 'api_token' => 'tok-123']];
        $result = Mustache::translate('Token: {{user.api_token}}', $data);

        expect($result->getTranslated())->toBe('Token: tok-123');
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context = []) => $context['path'] === 'user.api_token');
    });

    it('logs instead of throwing for disallowed models in report mode', function () {
        config()->set('mustache-resolver.security.mode', 'report');
        config()->set('mustache-resolver.security.allowed_models', ['Department']);
        Log::spy();

        $result = Mustache::translate('Name: {{User.name}}', $this->user);

        expect($result->getTranslated())->toBe('Name: John Doe');
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context = []) => str_contains($message, 'model access'));
    });

    it('falls back to enforce mode when security.mode is invalid (fail closed)', function () {
        config()->set('mustache-resolver.security.mode', 'reprot'); // typo
        config()->set('mustache-resolver.security.blacklisted_attributes', ['email']);
        Log::spy();

        $validator = app(SecurityValidator::class);

        expect($validator->getMode())->toBe(SecurityValidator::MODE_ENFORCE);
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context = []) => str_contains($message, 'invalid security.mode'));

        // Fail closed: the blacklist is enforced despite the typo
        $result = Mustache::translate('Email: {{User.email}}', $this->user);
        expect($result->getTranslated())->toBe('Email: ');
    });

    it('blocks collection tokens that bypass the accessor (evasion regression)', function () {
        Post::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'First Post',
            'body' => 'Body',
        ]);

        config()->set('mustache-resolver.security.mode', 'enforce');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['email']);

        // CollectionResolver navigates getRaw() without the accessor: the
        // blacklist must still apply to wildcard, first/last and index tokens
        $result = Mustache::translate('Emails: {{User.posts.*.author.email}}', $this->user);
        expect($result->getTranslated())->toBe('Emails: ');

        $result = Mustache::translate('Email: {{User.posts.first.author.email}}', $this->user);
        expect($result->getTranslated())->toBe('Email: ');

        $result = Mustache::translate('Email: {{User.posts.0.author.email}}', $this->user);
        expect($result->getTranslated())->toBe('Email: ');
    });

    it('resolves non-blacklisted collection tokens', function () {
        Post::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'First Post',
            'body' => 'Body',
        ]);

        config()->set('mustache-resolver.security.mode', 'enforce');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['email']);

        $result = Mustache::translate('Posts: {{User.posts.*.title}}', $this->user);
        expect($result->getTranslated())->toBe('Posts: First Post');
    });

    it('dedupes repeated security reports', function () {
        config()->set('mustache-resolver.security.mode', 'report');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['email']);
        Log::spy();

        Mustache::translate('{{User.email}}', $this->user);
        Mustache::translate('{{User.email}}', $this->user);

        Log::shouldHaveReceived('warning')->once();
    });

    it('strips blacklisted attributes when serializing a whole relation in enforce mode', function () {
        config()->set('mustache-resolver.security.mode', 'enforce');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['name']);
        Log::spy();

        // Asking for the relation itself serializes the whole model: the
        // blacklist must filter the serialized output, not just path segments
        $result = Mustache::translate('Dept: {{User.department}}', $this->user);

        expect($result->getTranslated())->not->toContain('"name"');
        expect($result->getTranslated())->not->toContain('Engineering');
        expect($result->getTranslated())->toContain('"code"');
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context = []) => str_contains($message, 'stripped from serialized model'));
    });

    it('serializes whole relations unchanged but logs in report mode', function () {
        config()->set('mustache-resolver.security.mode', 'report');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['name']);
        Log::spy();

        $result = Mustache::translate('Dept: {{User.department}}', $this->user);

        expect($result->getTranslated())->toContain('Engineering');
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context = []) => str_contains($message, 'whole model serialization'));
    });

    it('does not warn about model serialization when nothing would be filtered', function () {
        config()->set('mustache-resolver.security.mode', 'report');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['nonexistent']);
        Log::spy();

        $result = Mustache::translate('Dept: {{User.department}}', $this->user);

        expect($result->getTranslated())->toContain('Engineering');
        Log::shouldNotHaveReceived('warning');
    });

    it('keeps native serialization in enforce mode when nothing is stripped', function () {
        config()->set('mustache-resolver.security.mode', 'enforce');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['nonexistent']);
        Log::spy();

        $result = Mustache::translate('Dept: {{User.department}}', $this->user);

        expect($result->getTranslated())->toContain('"name":"Engineering"');
        Log::shouldNotHaveReceived('warning');
    });

    it('preserves escapeWhenCastingToString when filtering in enforce mode', function () {
        config()->set('mustache-resolver.security.mode', 'enforce');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['code']);

        $this->department->escapeWhenCastingToString();
        $this->department->setAttribute('name', '<b>Ops</b>');
        $this->user->setRelation('department', $this->department);

        $result = Mustache::translate('Dept: {{User.department}}', $this->user);

        expect($result->getTranslated())->toContain('&lt;b&gt;');
        expect($result->getTranslated())->not->toContain('<b>');
        expect($result->getTranslated())->not->toContain('"code"');
    });

    it('reports containers containing blacklisted attributes in report mode', function () {
        Post::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'First Post',
            'body' => 'Body',
        ]);

        config()->set('mustache-resolver.security.mode', 'report');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['title']);
        Log::spy();

        // A whole collection dumps every attribute, bypassing path checks:
        // 2.1 keeps the output intact but must report what a future major will filter
        $result = Mustache::translate('Posts: {{User.posts}}', $this->user);

        expect($result->getTranslated())->toContain('First Post');
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context = []) => str_contains($message, 'container contains blacklisted')
                && $context['path'] === 'User.posts');
    });

    it('does not report containers without blacklisted attributes', function () {
        Post::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'First Post',
            'body' => 'Body',
        ]);

        config()->set('mustache-resolver.security.mode', 'report');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['nonexistent']);
        Log::spy();

        Mustache::translate('Posts: {{User.posts}}', $this->user);

        Log::shouldNotHaveReceived('warning');
    });

    it('leaves containers unfiltered in enforce mode (deferred to v3)', function () {
        Post::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'First Post',
            'body' => 'Body',
        ]);

        config()->set('mustache-resolver.security.mode', 'enforce');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['title']);

        // 2.1 contract: filtering full containers is v3 scope, output stays intact
        $result = Mustache::translate('Posts: {{User.posts}}', $this->user);

        expect($result->getTranslated())->toContain('First Post');
    });

    it('resets report dedupe at the end of the request/job cycle', function () {
        config()->set('mustache-resolver.security.mode', 'report');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['email']);
        Log::spy();

        Mustache::translate('{{User.email}}', $this->user);

        // End of the request/job cycle: the next cycle must report again
        $this->app->terminate();

        Mustache::translate('{{User.email}}', $this->user);

        Log::shouldHaveReceived('warning')->twice();
    });

    it('throws for disallowed models in enforce mode', function () {
        config()->set('mustache-resolver.security.mode', 'enforce');
        config()->set('mustache-resolver.security.allowed_models', ['Department']);

        Mustache::translate('Name: {{User.name}}', $this->user);
    })->throws(ModelNotAllowedException::class);
});
