<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Laravel\Facades\Mustache;
use Illuminate\Support\Facades\Log;
use Workbench\App\Models\Department;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $this->department = Department::factory()->create(['name' => 'Engineering']);
    $this->user->department()->associate($this->department);
    $this->user->save();
});

describe('Sanitisation reaches resolved values, not just rendered text', function () {
    it('does not record a blocked value in getResolvedValues()', function () {
        config()->set('mustache-resolver.security.mode', 'enforce');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['email']);

        $result = Mustache::translate('Email: {{User.email}}', $this->user);

        expect($result->getTranslated())->toBe('Email: ');
        expect($result->getResolvedValues()['User.email'] ?? null)->toBeNull();
    });

    it('does not leak a blocked value through toArray()', function () {
        config()->set('mustache-resolver.security.mode', 'enforce');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['email']);

        $result = Mustache::translate('Email: {{User.email}}', $this->user);

        expect(json_encode($result->toArray()))->not->toContain('john@example.com');
    });

    it('still records ordinary values', function () {
        config()->set('mustache-resolver.security.mode', 'enforce');

        $result = Mustache::translate('Name: {{User.name}}', $this->user);

        expect($result->getResolvedValues()['User.name'])->toBe('John Doe');
    });

    it('dedupes a report-mode warning when both the accessor and the sanitizer check the same blacklisted path', function () {
        // Two barriers check path security for a Model token: the accessor
        // (EloquentAccessor::get(), path 'email' without prefix) and the
        // sanitizer (OutputSanitizer::sanitize(), path 'email' via
        // token->getSecurityPath() — the canonical path, prefix stripped,
        // the SAME string the accessor received). The reporter dedupes by
        // "$message|$context[path]" (MustacheServiceProvider::registerSecurity()),
        // so a mismatched path string here would defeat the dedup and log
        // twice for one blocked field.
        config()->set('mustache-resolver.security.mode', 'report');
        config()->set('mustache-resolver.security.blacklisted_attributes', ['email']);
        Log::spy();

        Mustache::translate('Email: {{User.email}}', $this->user);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context = []) => str_contains($message, 'blacklisted'));
    });

    it('never retains raw objects in getResolvedValues() for a scalar projection', function () {
        config()->set('mustache-resolver.security.mode', 'enforce');

        Post::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'First Post',
            'body' => 'Body',
        ]);

        $result = Mustache::translate('{{User.posts.*.title}}', $this->user);

        $resolved = $result->getResolvedValues()['User.posts.*.title'];

        expect($resolved)->toBe(['First Post']);
        expect($resolved[0])->toBeString();
    });
});
