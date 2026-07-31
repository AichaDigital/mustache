<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;
use AichaDigital\MustacheResolver\Exceptions\ModelNotAllowedException;
use AichaDigital\MustacheResolver\Laravel\Facades\Mustache;
use Carbon\Carbon;
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

    it('resolves non-blacklisted collection tokens (v3: scalar projection, not a container dump)', function () {
        Post::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'First Post',
            'body' => 'Body',
        ]);

        config()->set('mustache-resolver.security.mode', 'enforce');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['email']);

        // The wildcard produces a list of already-extracted scalar titles,
        // not a raw container — OutputSanitizer::isScalarProjection()
        // recognises the shape and skips the container-block gate.
        $result = Mustache::translate('Posts: {{User.posts.*.title}}', $this->user);
        expect($result->getTranslated())->toBe('Posts: First Post');
    });

    it('keeps a wildcard projection of a blacklisted attribute blocked', function () {
        Post::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'First Post',
            'body' => 'Body',
        ]);

        config()->set('mustache-resolver.security.mode', 'enforce');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['title']);

        // The projection escape only applies after the path check: the
        // path 'posts.*.title' is rejected by the blacklist before the
        // sanitizer's projection classification is ever consulted.
        $result = Mustache::translate('Posts: {{User.posts.*.title}}', $this->user);
        expect($result->getTranslated())->toBe('Posts: ');
    });

    it('dedupes repeated security reports', function () {
        config()->set('mustache-resolver.security.mode', 'report');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['email']);
        Log::spy();

        Mustache::translate('{{User.email}}', $this->user);
        Mustache::translate('{{User.email}}', $this->user);

        Log::shouldHaveReceived('warning')->once();
    });

    it('blocks a whole relation container in enforce mode (v3: containers are blocked by default)', function () {
        config()->set('mustache-resolver.security.mode', 'enforce');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['name']);
        Log::spy();

        // v3 contract (OutputSanitizer::maySerialiseWhole()): a bare
        // Eloquent model is a container, and Barrier 2 blocks whole-
        // container serialization by default in enforce mode — it no
        // longer strips the blacklisted key and keeps the rest, replacing
        // the 2.1 "strips blacklisted attributes ... " contract.
        $result = Mustache::translate('Dept: {{User.department}}', $this->user);

        expect($result->getTranslated())->toBe('Dept: ');
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context = []) => str_contains($message, 'container blocked by security policy'));
    });

    it('serializes whole relations unchanged but logs in report mode', function () {
        config()->set('mustache-resolver.security.mode', 'report');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['name']);
        Log::spy();

        $result = Mustache::translate('Dept: {{User.department}}', $this->user);

        expect($result->getTranslated())->toContain('Engineering');
        // v3 contract: the message comes from the sanitizer's generic
        // container-block gate (OutputSanitizer::sanitize()), not from a
        // model-specific "whole model serialization" message — the gate
        // is the same one for any non-whitelisted container.
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context = []) => str_contains($message, 'container would be blocked in enforce mode'));
    });

    it('warns about model serialization in report mode even when the blacklist matches nothing (v3: unconditional per-container report)', function () {
        config()->set('mustache-resolver.security.mode', 'report');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['nonexistent']);
        Log::spy();

        // v3 contract: the report is about the container itself being
        // unauthorised for whole serialization — it fires before any
        // blacklist is even consulted (OutputSanitizer::sanitize()'s
        // container gate), replacing the 2.1 "only warn if the blacklist
        // would have mattered" contract.
        $result = Mustache::translate('Dept: {{User.department}}', $this->user);

        expect($result->getTranslated())->toContain('Engineering');
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context = []) => str_contains($message, 'container would be blocked in enforce mode'));
    });

    it('blocks a whole relation container in enforce mode even when the blacklist matches nothing (v3: unconditional per-container block)', function () {
        config()->set('mustache-resolver.security.mode', 'enforce');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['nonexistent']);
        Log::spy();

        // v3 contract: replaces 2.1's "keeps native serialization when
        // nothing is stripped" — the block does not depend on whether the
        // blacklist matches anything inside the container.
        $result = Mustache::translate('Dept: {{User.department}}', $this->user);

        expect($result->getTranslated())->toBe('Dept: ');
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context = []) => str_contains($message, 'container blocked by security policy'));
    });

    it('blocks a whole relation container in enforce mode even with an XSS payload and escapeWhenCastingToString set (v3: nothing leaks when blocked)', function () {
        config()->set('mustache-resolver.security.mode', 'enforce');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['code']);

        $this->department->escapeWhenCastingToString();
        $this->department->setAttribute('name', '<b>Ops</b>');
        $this->user->setRelation('department', $this->department);

        // v3 contract: a non-whitelisted model is blocked before escaping
        // is even reached — escapeWhenCastingToString() only matters for
        // containers AUTHORISED for whole serialization (see
        // OutputSanitizerTest > filtering authorised containers > it
        // honours escapeWhenCastingToString on the serialized container).
        $result = Mustache::translate('Dept: {{User.department}}', $this->user);

        expect($result->getTranslated())->toBe('Dept: ');
        expect($result->getTranslated())->not->toContain('<b>');
        expect($result->getTranslated())->not->toContain('&lt;b&gt;');
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

    it('warns about container serialization in report mode even when the blacklist matches nothing (v3: unconditional per-container report)', function () {
        Post::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'First Post',
            'body' => 'Body',
        ]);

        config()->set('mustache-resolver.security.mode', 'report');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['nonexistent']);
        Log::spy();

        // v3 contract: replaces 2.1's "does not report containers without
        // blacklisted attributes" — the report fires for the container
        // itself, independent of whether the blacklist matches.
        Mustache::translate('Posts: {{User.posts}}', $this->user);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context = []) => str_contains($message, 'container would be blocked in enforce mode'));
    });

    it('blocks a whole collection container in enforce mode (v3: containers are blocked by default, no longer deferred)', function () {
        Post::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'First Post',
            'body' => 'Body',
        ]);

        config()->set('mustache-resolver.security.mode', 'enforce');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['title']);

        // v3 contract: this inverts the 2.1 "leaves containers unfiltered
        // in enforce mode (deferred to v3)" test it replaces — that
        // deferral is what Phase 1's OutputSanitizer delivers.
        $result = Mustache::translate('Posts: {{User.posts}}', $this->user);

        expect($result->getTranslated())->toBe('Posts: ');
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

    it('closes the barrier-1 gap for a plain object routed through NULL_COALESCE', function () {
        config()->set('mustache-resolver.security.mode', 'enforce');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['password']);

        $plainObject = (object) ['password' => 'SECRET'];

        // createContext()'s plain-object fallback wraps $data as
        // ['model' => $data]. NULL_COALESCE tokens strip their first path
        // segment the same way MODEL tokens do (Token::getFieldPath()), so
        // the leading 'x' here is discarded and the path that actually
        // reaches the accessor is 'model.password' — through the wrapper,
        // into the plain object's own property. hasSecurityPath() is false
        // for NULL_COALESCE, so barrier 2 never checks this path either:
        // before this fix, an accessor built without the security
        // validator left this path completely unguarded.
        $result = Mustache::translate("Value: {{x.model.password ?? 'fb'}}", $plainObject);

        expect($result->getTranslated())->toBe('Value: fb');
        expect($result->getResolvedValues())->not->toContain('SECRET');
    });

    it('resolves the equivalent array path correctly, for comparison with the plain-object case above', function () {
        config()->set('mustache-resolver.security.mode', 'enforce');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['password']);

        $data = ['model' => ['password' => 'SECRET']];

        $result = Mustache::translate("Value: {{x.model.password ?? 'fb'}}", $data);

        expect($result->getTranslated())->toBe('Value: fb');
    });

    it('does not retain the raw Carbon object through TranslationResult::toArray() in enforce mode', function () {
        config()->set('mustache-resolver.security.mode', 'enforce');

        $result = Mustache::translate('Created: {{User.created_at}}', $this->user);

        expect($result->getResolvedValues()['User.created_at'])->toBeString();

        $array = $result->toArray();
        expect($array['resolved_values']['User.created_at'])->toBeString();
    });

    it('changes nothing for a consumer on the shipped default mode, report (Phase 1 promise)', function () {
        // No config()->set() here on purpose: this exercises the
        // ServiceProvider's actual shipped default (security.mode =
        // 'report'). A consumer who installs this branch without touching
        // config must see IDENTICAL behaviour to before this phase.
        $result = Mustache::translate('Created: {{User.created_at}}', $this->user);

        expect($result->getResolvedValues()['User.created_at'])->toBeInstanceOf(Carbon::class);
    });
});
