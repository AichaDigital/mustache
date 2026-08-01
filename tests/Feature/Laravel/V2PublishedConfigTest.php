<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Contracts\ParserInterface;
use AichaDigital\MustacheResolver\Core\MustacheResolver;
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;
use AichaDigital\MustacheResolver\Exceptions\SecurityException;
use AichaDigital\MustacheResolver\Laravel\MustacheServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Ordering note (why this file does not use defineEnvironment())
|--------------------------------------------------------------------------
|
| Testbench's defineEnvironment()/getEnvironmentSetUp() run AFTER
| Illuminate\Foundation\Bootstrap\RegisterProviders has already called
| register() on every package provider (verified empirically: probing
| config('mustache-resolver.security.mode') at the top of
| getEnvironmentSetUp() already reads 'enforce' -- our own package
| default -- before the hook body runs). That is the OPPOSITE of a real
| Laravel application's boot order, where LoadConfiguration loads every
| config/*.php file -- including a consumer's published
| config/mustache-resolver.php -- BEFORE any ServiceProvider registers.
| defineEnvironment() therefore cannot simulate "a v2-shaped file was
| already on disk when MustacheServiceProvider::register() ran".
|
| Per the task 6 brief's pre-authorised fallback, we instead seed
| config() with the v2.1.0 fixture's raw `security` block directly in
| the test body -- reproducing exactly what mergeConfigFrom() would see
| in production, where the consumer's top-level `security` key already
| exists and is left untouched by the shallow merge (AID-632) -- and
| then construct, register() and boot() a FRESH MustacheServiceProvider
| instance against it. That fresh instance's register() call is where
| reconcileSecurityConfig() actually runs against the v2-shaped block,
| exercising the real production code path instead of a second copy of
| the reconciler's own logic (already covered in isolation by
| SecurityConfigReconcilerTest).
*/

/**
 * @return array{security: array<string, mixed>}
 */
function v2PublishedSecurityFixture(): array
{
    /** @var array{security: array<string, mixed>} $v2 */
    $v2 = require __DIR__.'/../../Fixtures/config/mustache-resolver-v2.php';

    return $v2;
}

function bootFreshProviderAgainst(Application $app, array $security): MustacheServiceProvider
{
    $app['config']->set('mustache-resolver.security', $security);

    $provider = new MustacheServiceProvider($app);
    $provider->register();
    $provider->boot();

    return $provider;
}

describe('a v2-shaped published security config', function () {
    it('keeps report mode but gains the v3 keys', function () {
        bootFreshProviderAgainst($this->app, v2PublishedSecurityFixture()['security']);

        expect(config('mustache-resolver.security.mode'))->toBe('report');
        expect(config('mustache-resolver.security.blacklisted_patterns'))
            ->toBe(SecurityValidator::DEFAULT_BLACKLISTED_PATTERNS);
        expect(config('mustache-resolver.security'))->not->toHaveKey('allowed_tables');
        expect(config('mustache-resolver.security'))->toHaveKey('limits');
    });

    it('under the preserved report mode a pattern hit resolves and does not block', function () {
        bootFreshProviderAgainst($this->app, v2PublishedSecurityFixture()['security']);

        $resolver = $this->app->make(MustacheResolver::class);

        // Lowercase 'user' key, matching the array-data-path idiom used
        // throughout SecurityWiringTest (e.g. "applies the blacklist on
        // the array data path"): a PascalCase root key classifies as a
        // MODEL token, which strips the prefix and navigates $data itself
        // rather than a nested key of the given name -- wrong for this
        // plain-array fixture and unrelated to security gating.
        $result = $resolver->translate(
            'Token: {{user.auth_token}}',
            ['user' => ['auth_token' => 'tok_123']],
        );

        expect($result->getTranslated())->toBe('Token: tok_123');
    });

    it('boots with a warning naming the absent keys', function () {
        Log::spy();

        bootFreshProviderAgainst($this->app, ['mode' => 'report']);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return str_contains($message, 'missing v3 keys')
                    && in_array('security.blacklisted_patterns', $context['absent_keys_filled_with_v3_defaults'], true)
                    && $context['effective_mode'] === 'report'
                    && isset($context['report_mode']);
            });
    });
});

it('a fresh install runs enforce with no warning', function () {
    expect(config('mustache-resolver.security.mode'))->toBe('enforce');

    // The app's OWN provider already registered + booted once during
    // Testbench's createApplication(), before this test body starts, so a
    // spy started here cannot observe THAT boot() call. Feeding a fresh
    // provider the already-reconciled shipped config -- config('mustache-
    // resolver.security') at this point IS the fresh-install default,
    // every v3 key present, nothing absent -- reproduces the same "fully
    // populated config" input a real fresh install's natural boot() sees,
    // and proves it stays silent (drift guard: if SecurityConfigReconciler
    // ::defaults() ever falls out of sync with the shipped config file's
    // key names, this goes red).
    Log::spy();

    /** @var array<string, mixed> $security */
    $security = config('mustache-resolver.security');
    bootFreshProviderAgainst($this->app, $security);

    Log::shouldNotHaveReceived('warning');
});

it('an absent mode key yields one consistent effective mode for validator and parser', function () {
    // No 'mode' key at all -- simulates a pre-2.1 published config that
    // predates the mode key's introduction entirely. Before this task,
    // registerParser()'s absent-mode fallback was MODE_ENFORCE while
    // registerSecurity()'s was MODE_REPORT: a validator that never throws
    // paired with a parser that does throw was a breach of the "report
    // never throws" invariant. reconcileSecurityConfig() now fills the
    // literal 'mode' key BEFORE either singleton resolves, so there is
    // exactly one effective mode for both to derive.
    bootFreshProviderAgainst($this->app, [
        'blacklisted_attributes' => ['password'],
        'limits' => ['max_tokens' => 2],
    ]);

    expect(config('mustache-resolver.security.mode'))->toBe(SecurityValidator::MODE_ENFORCE);

    $validator = $this->app->make(SecurityValidator::class);
    expect($validator->getMode())->toBe(SecurityValidator::MODE_ENFORCE);

    $parser = $this->app->make(ParserInterface::class);

    expect(fn () => $parser->parse('{{a}} {{b}} {{c}}'))
        ->toThrow(SecurityException::class);
});
