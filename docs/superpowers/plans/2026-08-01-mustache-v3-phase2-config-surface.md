# Mustache v3.0.0 — Phase 2: Config and Public Surface Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Flip the package to secure-by-default: `enforce` mode, blacklist patterns, FQCN-only root-model whitelist, default standalone policy, parser limits, v2-published-config reconciliation, and closing the two deferred barrier gaps (`ObjectAccessor`, compound path).

**Architecture:** Phase 1 (already on `3.x`) built the two-barrier architecture: barrier 1 (accessors, per-path) and barrier 2 (`OutputSanitizer`, downstream of every resolver). Phase 2 changes no architecture — it changes *defaults and configuration surface* so both barriers are on by default for fresh installs AND for consumers whose published v2 config would otherwise mask the new keys (`mergeConfigFrom` is a shallow `array_merge`; the consumer's `security` block replaces the package's entirely).

**Tech Stack:** PHP 8.2+ (verify with Herd `php84`), illuminate/support (`Str::is` for globs), Pest, PHPStan level 8, Pint.

**Spec:** `docs/superpowers/specs/2026-07-30-mustache-v3-security-enforce-design.md` (sections 4, 6, 11.1, 11.2, 11.4, 11.9). Ticket: AID-733.

## Global Constraints

- **Branch:** all work happens directly on `3.x`. `main` (2.x) is never touched.
- **PHP binary:** run composer/pest through Herd's `php84` explicitly when the session `php` drifts: `"$HOME/Library/Application Support/Herd/bin/php84" vendor/bin/pest`. The quality gate is `composer quality` (pint + phpstan level 8 + `pest --coverage --min=90`).
- **Commits:** conventional commits, English, detailed body explaining the why, trailer line `Refs AID-733`. **NEVER** add "Generated with Claude Code" or any `Co-Authored-By` footer (package rule in `CLAUDE.md`).
- **Code:** `declare(strict_types=1)` everywhere; `readonly` where immutable; PHPStan level 8 clean; Pint clean; all code/comments in English. No `eval()`. No new dependencies.
- **Sensitivity check — MANDATORY for every test that guards a security mechanism:** temporarily disable the mechanism in `src/` (comment out the check), confirm the test goes RED, restore, confirm `git diff src/` is empty, confirm GREEN. State in your report which mechanism you disabled and what went red. A guard test that passes against the unprotected code proves nothing — phase 1 caught three plan defects this way.
- **Stop on ambiguous failure.** If a pre-existing test goes red and you cannot tell whether it fixes an old (v2) contract or reveals a regression you introduced: STOP and report, do not decide. All three phase-1 stops were correct.
- **Verify the fixture, not just the assertion.** A test asserting `password` is blocked must first prove the fixture actually carries a password value (e.g. read it with `mode: off`). Mass assignment silently drops non-fillable attributes — phase 1 had a test passing with zero filtering logic because of this.
- **Mode invariants (fixed in phase 1, still binding):** `off` → original value, same identity and type, no warnings. `report` → original value, same identity and type, rendered text identical to v2.1, but logs what enforce would change. `enforce` → acts and logs. **Report mode NEVER alters rendered text, value types, or throws where v2.1 did not** — a new throw counts as altering, which is why parser limits (Task 5) are enforce-only.
- **Absence vs deliberate emptiness:** wherever the distinction matters, a config key that is *absent* takes the v3 default and a key present as `[]` is a deliberate disable that must be honoured. Distinguish with `array_key_exists()`, **never** `??` alone when null/absent must be told apart from an explicit empty value.
- **Published-config testing (the phase-2 specific risk):** tests for config behaviour must exercise (a) the **published file itself** via `require config/mustache-resolver.php` and (b) **simulated consumer-published config** via Testbench's `defineEnvironment()` setting `mustache-resolver` before providers register. A test reading only `config()` after merge measures the package default, not what a consumer would have — the exact blind spot that produced AID-632.
- **CHANGELOG / README / UPGRADE-3.md are Phase 3.** Do not write them in this phase (matches phase 1 practice).

## File Structure (phase overview)

- `src/Core/Security/SecurityValidator.php` — gains `blacklistedPatterns` + defaults constants + `defaultPolicy()`; loses basename matching; `allowedModels` → `allowedRootModels`.
- `config/mustache-resolver.php` — gains `blacklisted_patterns`, `limits`; renames `allowed_models` → `allowed_root_models`; loses `allowed_tables`; `mode` default → `enforce`.
- `src/Laravel/MustacheServiceProvider.php` — passes new keys; parser limits wiring; calls the reconciler; boot warning.
- `src/Laravel/SecurityConfigReconciler.php` (new) — pure reconciliation of a consumer security block against v3 defaults.
- `src/Core/MustacheResolver.php` — null validator → default policy; root-whitelist-inapplicable warning.
- `src/Core/Context/ResolutionContext.php` — `fromModel` fail-closed + key rename fail-loud.
- `src/Core/Parser/MustacheParser.php` — template-length and token-count limits.
- `src/Exceptions/SecurityException.php`, `src/Exceptions/ConfigurationException.php` — new static constructors.
- `src/Core/Temporal/ConditionRegistry.php` — singleton removed.
- `src/Accessors/ObjectAccessor.php` — becomes security-aware.
- `src/Core/Compound/UseVariableResolver.php`, `src/Core/Compound/CompoundResolver.php` — compound path through the sanitizer.
- `src/Core/Security/OutputSanitizer.php` — class docblock update only (Task 8).

---

### Task 1: Blacklist patterns (`blacklisted_patterns`, glob via `Str::is`, case-insensitive)

**Files:**
- Modify: `src/Core/Security/SecurityValidator.php`
- Modify: `config/mustache-resolver.php` (security block)
- Modify: `src/Laravel/MustacheServiceProvider.php` (`registerSecurity()`)
- Test: `tests/Unit/Core/Security/SecurityValidatorTest.php`
- Test: `tests/Feature/Laravel/SecurityWiringTest.php`

**Interfaces:**
- Consumes: existing `SecurityValidator::isAttributeBlacklisted(string): bool`.
- Produces: `SecurityValidator::DEFAULT_BLACKLISTED_PATTERNS` (public const, `list<string>`), constructor param `array $blacklistedPatterns = []` in third position (after `blacklistedAttributes`, before `maxDepth`). Tasks 4 and 6 reference the constant.

- [ ] **Step 1: Write the failing unit tests** in `tests/Unit/Core/Security/SecurityValidatorTest.php` (new `describe('blacklisted patterns')` block):

```php
describe('blacklisted patterns', function () {
    it('blocks attributes matching a glob pattern', function (string $attribute) {
        $validator = new SecurityValidator(
            blacklistedPatterns: ['*_token', '*password*', 'otp'],
        );

        expect($validator->isAttributeBlacklisted($attribute))->toBeTrue();
    })->with(['auth_token', 'password_plain', 'user_password_old', 'otp']);

    it('matches patterns case-insensitively', function () {
        $validator = new SecurityValidator(blacklistedPatterns: ['*_token']);

        expect($validator->isAttributeBlacklisted('Auth_Token'))->toBeTrue();
        expect($validator->isAttributeBlacklisted('AUTH_TOKEN'))->toBeTrue();
    });

    it('documents the accepted false positive: public_key matches *_key', function () {
        $validator = new SecurityValidator(
            blacklistedPatterns: SecurityValidator::DEFAULT_BLACKLISTED_PATTERNS,
        );

        // Accepted cost per spec §4.1: visible in the log, removable by config.
        expect($validator->isAttributeBlacklisted('public_key'))->toBeTrue();
        expect($validator->isAttributeBlacklisted('sort_key'))->toBeTrue();
    });

    it('does not block non-matching attributes', function (string $attribute) {
        $validator = new SecurityValidator(
            blacklistedPatterns: SecurityValidator::DEFAULT_BLACKLISTED_PATTERNS,
        );

        expect($validator->isAttributeBlacklisted($attribute))->toBeFalse();
    })->with(['name', 'email', 'keyboard', 'options', 'tokenizer']);

    it('applies patterns through allowsPath on every segment', function () {
        $validator = new SecurityValidator(
            blacklistedPatterns: ['*_token'],
            mode: SecurityValidator::MODE_ENFORCE,
        );

        expect($validator->allowsPath('User.auth_token'))->toBeFalse();
        expect($validator->allowsPath('User.profile.refresh_token'))->toBeFalse();
        expect($validator->allowsPath('User.name'))->toBeTrue();
    });

    it('an empty pattern list disables pattern matching', function () {
        $validator = new SecurityValidator(blacklistedPatterns: []);

        expect($validator->isAttributeBlacklisted('auth_token'))->toBeFalse();
    });
});
```

Note: `keyboard` and `tokenizer` are deliberate near-misses — `*_key` and `*_token` must not match without the underscore boundary.

- [ ] **Step 2: Run to verify they fail**

Run: `vendor/bin/pest tests/Unit/Core/Security/SecurityValidatorTest.php`
Expected: FAIL — unknown named argument `blacklistedPatterns` / undefined constant.

- [ ] **Step 3: Implement** in `SecurityValidator`:

Add import `use Illuminate\Support\Str;`. Add constant and constructor param:

```php
/**
 * Default glob patterns catching real-world renames of sensitive fields.
 * Accepted cost: occasional false positives (public_key, sort_key), visible
 * in the log and removable by config. A blacklist can always be evaded by
 * renaming — patterns raise the floor, they are not a complete defence.
 */
public const DEFAULT_BLACKLISTED_PATTERNS = [
    '*_token',
    '*_secret',
    '*_key',
    '*password*',
    '*_hash',
    'otp',
    'pin',
    'cvv',
];
```

```php
public function __construct(
    private array $allowedModels = [],
    private array $blacklistedAttributes = [],
    private array $blacklistedPatterns = [],
    private int $maxDepth = 10,
    private string $mode = self::MODE_ENFORCE,
    private ?Closure $reporter = null,
) {}
```

(update the `@param` docblock accordingly: `@param array<string> $blacklistedPatterns`). Extend `isAttributeBlacklisted()`:

```php
public function isAttributeBlacklisted(string $attribute): bool
{
    $attribute = strtolower($attribute);

    foreach ($this->blacklistedAttributes as $blacklisted) {
        if (strtolower($blacklisted) === $attribute) {
            return true;
        }
    }

    // Glob-style, case-insensitive (both sides lowercased). Str::is()
    // preg-quotes everything except '*', so consumer-supplied config
    // cannot inject a catastrophic backtracking pattern.
    foreach ($this->blacklistedPatterns as $pattern) {
        if (Str::is(strtolower($pattern), $attribute)) {
            return true;
        }
    }

    return false;
}
```

- [ ] **Step 4: Wire config + provider.** In `config/mustache-resolver.php`, inside `security`, after `blacklisted_attributes`:

```php
// Attribute name patterns blocked in addition to the exact names above.
// Glob-style (Str::is), case-insensitive, applied to every path segment.
// Catches real-world renames of sensitive fields (auth_token, stripe_key,
// password_plain, ...). Expect occasional false positives (public_key,
// sort_key): they are visible in the log and removable here.
// An explicit [] disables pattern matching deliberately.
'blacklisted_patterns' => [
    '*_token',
    '*_secret',
    '*_key',
    '*password*',
    '*_hash',
    'otp',
    'pin',
    'cvv',
],
```

In `MustacheServiceProvider::registerSecurity()`, add to the `new SecurityValidator(...)` call (and update the `@var` PHPDoc on `$config` to include `blacklisted_patterns?: array<string>`):

```php
blacklistedPatterns: array_key_exists('blacklisted_patterns', $config)
    ? $config['blacklisted_patterns']
    : SecurityValidator::DEFAULT_BLACKLISTED_PATTERNS,
```

(`array_key_exists`, not `??`: an explicit `[]` — or even a present `null` — must never be silently replaced by the defaults; only true absence takes them.)

- [ ] **Step 5: Add the end-to-end wiring test** in `tests/Feature/Laravel/SecurityWiringTest.php` — a pattern-named field travels the full `translate()` path. Follow the file's existing style for building the resolver and asserting logs:

```php
it('blocks pattern-matched attributes end to end in enforce mode', function () {
    config()->set('mustache-resolver.security.mode', 'enforce');

    $resolver = $this->app->make(\AichaDigital\MustacheResolver\Core\MustacheResolver::class);

    $result = $resolver->translate(
        'Token: {{User.auth_token}} / Name: {{User.name}}',
        ['User' => ['auth_token' => 'tok_123', 'name' => 'John']],
    );

    expect($result->getTranslated())->toBe('Token:  / Name: John');
    expect($result->getResolvedValues()['User.auth_token'])->toBeNull();
});
```

Adjust the resolved-values key/accessor API to what the suite already uses (check a neighbouring test in the same file; do not invent new result API). Note the container/app rebinding pattern used by neighbouring tests to make config changes take effect (singletons are lazy; set config before first `make()`).

- [ ] **Step 6: Run the two test files, then the full suite**

Run: `vendor/bin/pest tests/Unit/Core/Security/SecurityValidatorTest.php tests/Feature/Laravel/SecurityWiringTest.php` then `composer test`
Expected: PASS, suite green.

- [ ] **Step 7: Sensitivity check.** Comment out the pattern `foreach` loop in `isAttributeBlacklisted()` → the new tests must go RED. Restore. `git diff src/` must be empty except your intended changes. Record in report.

- [ ] **Step 8: Commit**

```bash
git add -A && git commit -m "feat(security): add glob blacklist patterns to SecurityValidator" -m "..." -m "Refs AID-733"
```

---

### Task 2: FQCN-only whitelist, renamed to `allowed_root_models`

**Files:**
- Modify: `src/Core/Security/SecurityValidator.php`
- Modify: `src/Core/Context/ResolutionContext.php` (`fromModel`)
- Modify: `src/Core/MustacheResolver.php` (`createContext` — root-whitelist warning)
- Modify: `src/Exceptions/ConfigurationException.php`
- Modify: `config/mustache-resolver.php`
- Modify: `src/Laravel/MustacheServiceProvider.php`
- Test: `tests/Unit/Core/Security/SecurityValidatorTest.php`, `tests/Feature/Security/ModelSecurityTest.php`

**Interfaces:**
- Produces: `SecurityValidator` constructor param renamed `allowedModels` → `allowedRootModels` (still first position); getter renamed `getAllowedModels()` → `getAllowedRootModels()`; `ConfigurationException::renamedKey(string $old, string $new): self`. Config key `security.allowed_root_models`. `ModelNotAllowedException` is NOT touched (its own constructor/getter keep their names).
- Consumes: nothing from Task 1 beyond compiling together.

- [ ] **Step 1: Write the failing tests.** In `SecurityValidatorTest.php`:

```php
describe('FQCN-only whitelist (v3)', function () {
    it('rejects a short class name that would have matched by basename in v2', function () {
        $validator = new SecurityValidator(
            allowedRootModels: ['User'],
            mode: SecurityValidator::MODE_ENFORCE,
        );

        // The workbench user model's basename is User; v2 accepted it.
        $validator->validateModel(\Workbench\App\Models\User::class);
    })->throws(\AichaDigital\MustacheResolver\Exceptions\ModelNotAllowedException::class);

    it('accepts the fully qualified class name', function () {
        $validator = new SecurityValidator(
            allowedRootModels: [\Workbench\App\Models\User::class],
            mode: SecurityValidator::MODE_ENFORCE,
        );

        $validator->validateModel(\Workbench\App\Models\User::class);

        expect(true)->toBeTrue();
    });
});
```

(Adjust the workbench model FQCN to the one this suite actually uses — check `tests/Feature/Security/ModelSecurityTest.php` for the model it instantiates. If the suite uses a different namespace, use that class. The point is: a whitelist containing only the basename must now reject the real class.)

In `ModelSecurityTest.php` (or a new `describe` in it):

```php
it('fromModel rejects the removed allowed_models key loudly', function () {
    ResolutionContext::fromModel($this->user, [
        'allowed_models' => ['User'],
    ]);
})->throws(
    \AichaDigital\MustacheResolver\Exceptions\ConfigurationException::class,
    "Configuration key 'allowed_models' was renamed to 'allowed_root_models'",
);

it('warns that a class whitelist cannot apply to an array root datum', function () {
    $reports = [];
    $validator = new SecurityValidator(
        allowedRootModels: [\Workbench\App\Models\User::class],
        mode: SecurityValidator::MODE_ENFORCE,
        reporter: function (string $message, array $context = []) use (&$reports): void {
            $reports[] = $message;
        },
    );

    $resolver = new \AichaDigital\MustacheResolver\Core\MustacheResolver(
        new \AichaDigital\MustacheResolver\Core\Parser\MustacheParser,
        \AichaDigital\MustacheResolver\Core\Pipeline\PipelineBuilder::create()->build(),
        new \AichaDigital\MustacheResolver\Cache\NullCache,
        $validator,
    );

    $resolver->translate('{{User.name}}', ['User' => ['name' => 'John']]);

    expect($reports)->toContain(
        'mustache-resolver: allowed_root_models cannot be applied, the root datum is not a model'
    );
});
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/pest tests/Unit/Core/Security/SecurityValidatorTest.php tests/Feature/Security/ModelSecurityTest.php`
Expected: FAIL — unknown named argument `allowedRootModels`; missing `renamedKey`; missing warning.

- [ ] **Step 3: Implement the rename and FQCN-only matching** in `SecurityValidator`: rename the constructor property `allowedModels` → `allowedRootModels` (docblock too), rename `getAllowedModels()` → `getAllowedRootModels()`, and simplify `validateModel()` — the `class_basename` branch is deleted:

```php
public function validateModel(string $modelClass): void
{
    if ($this->allowedRootModels === []) {
        return; // All models allowed when list is empty (opt-in hardening)
    }

    // FQCN only. Accepting class_basename meant ['User'] authorised any
    // class in the world whose basename is User — not a whitelist.
    if (in_array($modelClass, $this->allowedRootModels, true)) {
        return;
    }

    if ($this->mode === self::MODE_OFF) {
        return;
    }

    if ($this->mode === self::MODE_REPORT) {
        $this->report('mustache-resolver: model access would be blocked in enforce mode', [
            'model' => $modelClass,
            'allowed_root_models' => $this->allowedRootModels,
        ]);

        return;
    }

    throw new ModelNotAllowedException($modelClass, $this->allowedRootModels);
}
```

Update every internal caller of the renamed pieces: `MustacheServiceProvider::registerSecurity()` (named arg + config key `allowed_root_models` + PHPDoc), `ResolutionContext::fromModel()` (next step), and the existing tests that use `allowedModels:` / `getAllowedModels()` on the **validator** (the ones on `ModelNotAllowedException` stay).

- [ ] **Step 4: Fail-loud on the removed key in `fromModel`.** Add to `ConfigurationException`:

```php
public static function renamedKey(string $old, string $new): self
{
    return new self(
        "Configuration key '{$old}' was renamed to '{$new}' in v3.0.0 — update your configuration"
    );
}
```

In `ResolutionContext::fromModel()`, before building the validator:

```php
if (array_key_exists('allowed_models', $securityConfig)) {
    throw ConfigurationException::renamedKey('allowed_models', 'allowed_root_models');
}
```

and change the key it reads to `allowed_root_models` (named arg `allowedRootModels:`). Import `ConfigurationException`.

(Programmatic API fails loud on the removed key; the *published-config* path warns-and-maps instead — that is Task 6's reconciler. Both are deliberate: silently ignoring the old key would drop a hardening control without notice.)

- [ ] **Step 5: Root-whitelist-inapplicable warning** in `MustacheResolver::createContext()`. Add a private method and call it from BOTH the `is_array($data)` branch and the final plain-object fallback branch (which wraps in an array):

```php
/**
 * A class whitelist cannot be applied when the root datum is not a model
 * (spec §11.4): warn so a consumer who populated allowed_root_models and
 * feeds arrays does not read a guarantee into it that does not exist.
 */
private function warnRootWhitelistInapplicable(mixed $data): void
{
    if ($this->securityValidator === null) {
        return;
    }

    if ($this->securityValidator->getMode() === SecurityValidator::MODE_OFF) {
        return;
    }

    if ($this->securityValidator->getAllowedRootModels() === []) {
        return;
    }

    $this->securityValidator->reportViolation(
        'mustache-resolver: allowed_root_models cannot be applied, the root datum is not a model',
        ['type' => get_debug_type($data)],
    );
}
```

- [ ] **Step 6: Config file rename.** In `config/mustache-resolver.php` replace the `allowed_models` entry with:

```php
// Restrict which Eloquent model classes may be used as the ROOT data
// source. Fully-qualified class names only (::class). Empty = all
// allowed — this is opt-in hardening, not the primary barrier.
// It validates the root model only: a non-blacklisted attribute of a
// NESTED model resolves even when its class is absent from this list
// (nested serialization is covered by the container policy instead).
'allowed_root_models' => [],
```

- [ ] **Step 7: Run both test files, then the full suite.** Fix any test that referenced the old named argument or getter on the validator. If a test asserted basename acceptance as a *contract*, that is the v2 contract this task deliberately breaks: update it to assert rejection (spec section 9: "FQCN rejection of short names", seen red first).

Run: `composer test`
Expected: green.

- [ ] **Step 8: Sensitivity check.** Re-add a temporary `class_basename` acceptance branch in `validateModel()` → the FQCN-rejection test must go RED. Restore, `git diff src/` clean of the temporary change, green.

- [ ] **Step 9: Commit**

```bash
git add -A && git commit -m "feat(security)!: FQCN-only whitelist renamed to allowed_root_models" -m "..." -m "Refs AID-733"
```

---

### Task 3: Removals — `allowed_tables` config key and the `ConditionRegistry` singleton

**Files:**
- Modify: `config/mustache-resolver.php` (delete the `allowed_tables` entry and its comment)
- Modify: `src/Core/Temporal/ConditionRegistry.php` (delete `$instance`, `getInstance()`, `resetInstance()`)
- Test: `tests/Unit/Core/Temporal/ConditionRegistryTest.php`

**Interfaces:**
- Produces: `ConditionRegistry` is instance-only from here on. Nothing else in `src/` referenced the singleton (verified: only its own test does).

- [ ] **Step 1: Delete the singleton.** Remove from `ConditionRegistry`: the `private static ?self $instance = null;` property, `getInstance()`, and `resetInstance()`. Rationale (belongs in the commit body, not a code comment): `src/` never invokes them; public static API with no internal consumer does not need per-request isolation under Octane, it needs deleting — callers instantiate the registry or resolve it from the container, which cycles correctly.

- [ ] **Step 2: Rewrite the singleton tests.** In `ConditionRegistryTest.php`, delete the `beforeEach`/`afterEach` calls to `resetInstance()` and the `describe` block covering `getInstance()`/`resetInstance()` semantics. Every remaining test constructs `new ConditionRegistry` directly. Keep all behavioural coverage (register, evaluate, keywords, remove, clearCustom, createExpression).

- [ ] **Step 3: Delete `allowed_tables`** (the whole commented entry) from `config/mustache-resolver.php`. There is no runtime reader — `TableResolver` performs no database access; the key was configuration theatre (spec §4.4). The published-file guardrail test asserting its absence lands in Task 6.

- [ ] **Step 4: Full suite**

Run: `composer test`
Expected: green. If anything besides `ConditionRegistryTest` referenced the singleton, STOP and report (it would contradict the verified premise).

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "refactor(security)!: remove allowed_tables config and the ConditionRegistry singleton" -m "..." -m "Refs AID-733"
```

---

### Task 4: Standalone default policy — `null` validator means the default policy (§11.2, approved)

**Files:**
- Modify: `src/Core/Security/SecurityValidator.php` (defaults constant + `defaultPolicy()`)
- Modify: `src/Core/MustacheResolver.php` (constructor)
- Modify: `src/Core/Context/ResolutionContext.php` (`fromModel` fail-closed + per-key defaults)
- Test: `tests/Feature/Security/ModelSecurityTest.php`, `tests/Unit/Core/Security/SecurityValidatorTest.php`, plus suite-wide triage

**Interfaces:**
- Produces: `SecurityValidator::DEFAULT_BLACKLISTED_ATTRIBUTES` (public const), `SecurityValidator::defaultPolicy(?Closure $reporter = null): self`. `MustacheResolver`'s `$securityValidator` property becomes non-nullable internally (signature unchanged: `?SecurityValidator $securityValidator = null`). Tasks 6 and 8 consume both.
- Consumes: `DEFAULT_BLACKLISTED_PATTERNS` (Task 1).

- [ ] **Step 1: Write the failing tests.**

In `SecurityValidatorTest.php`:

```php
describe('defaultPolicy', function () {
    it('builds the v3 default policy: enforce, exact blacklist, patterns', function () {
        $validator = SecurityValidator::defaultPolicy();

        expect($validator->getMode())->toBe(SecurityValidator::MODE_ENFORCE);
        expect($validator->getMaxDepth())->toBe(10);
        expect($validator->getAllowedRootModels())->toBe([]);
        expect($validator->isAttributeBlacklisted('password'))->toBeTrue();
        expect($validator->isAttributeBlacklisted('auth_token'))->toBeTrue();
    });

    it('carries no reporter by default and accepts one', function () {
        $reports = [];
        $validator = SecurityValidator::defaultPolicy(
            function (string $message, array $context = []) use (&$reports): void {
                $reports[] = $message;
            }
        );

        $validator->allowsPath('User.password');

        expect($reports)->not->toBeEmpty();
    });
});
```

In `ModelSecurityTest.php`, REPLACE `it('works without security config')` — that test fixes the v2 fail-open as contract, and closing it is this task's purpose (finding 7 / §11.2):

```php
it('applies the default policy when no security config is given (v3)', function () {
    // Fixture guard: prove the model actually carries the sensitive value
    // when the policy is off — otherwise the block assertion below could
    // pass against a fixture that never had a password at all.
    $off = ResolutionContext::fromModel($this->user, ['mode' => SecurityValidator::MODE_OFF]);
    expect($off->get('password'))->not->toBeNull();

    $context = ResolutionContext::fromModel($this->user);

    expect($context->get('name'))->toBe('John Doe');
    expect($context->get('password'))->toBeNull();
});
```

If the fixture guard fails because the workbench user has no password set, fix the FIXTURE (e.g. `forceFill(['password' => 'secret-hash'])` at creation), never the assertion.

Also add the standalone-resolver test (the README example path, §11.2):

```php
it('a resolver built without a validator gets the default policy', function () {
    $resolver = new \AichaDigital\MustacheResolver\Core\MustacheResolver(
        new \AichaDigital\MustacheResolver\Core\Parser\MustacheParser,
        \AichaDigital\MustacheResolver\Core\Pipeline\PipelineBuilder::create()->build(),
        new \AichaDigital\MustacheResolver\Cache\NullCache,
    );

    $result = $resolver->translate(
        'Secret: {{User.api_token}} / Name: {{User.name}}',
        ['User' => ['api_token' => 'tok_123', 'name' => 'John']],
    );

    expect($result->getTranslated())->toBe('Secret:  / Name: John');
});

it('opting out requires mode off explicitly', function () {
    $resolver = new \AichaDigital\MustacheResolver\Core\MustacheResolver(
        new \AichaDigital\MustacheResolver\Core\Parser\MustacheParser,
        \AichaDigital\MustacheResolver\Core\Pipeline\PipelineBuilder::create()->build(),
        new \AichaDigital\MustacheResolver\Cache\NullCache,
        new SecurityValidator(mode: SecurityValidator::MODE_OFF),
    );

    $result = $resolver->translate(
        'Secret: {{User.api_token}}',
        ['User' => ['api_token' => 'tok_123']],
    );

    expect($result->getTranslated())->toBe('Secret: tok_123');
});
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/pest tests/Feature/Security/ModelSecurityTest.php tests/Unit/Core/Security/SecurityValidatorTest.php`
Expected: FAIL — `defaultPolicy` undefined; no-config context still resolves password.

- [ ] **Step 3: Implement.** In `SecurityValidator`:

```php
/**
 * The exact-name blacklist shipped as default. Kept in sync with the
 * published config file by tests/Unit/Config/PublishedConfigTest.
 */
public const DEFAULT_BLACKLISTED_ATTRIBUTES = [
    'password',
    'remember_token',
    'api_token',
    'secret',
];

/**
 * The v3 default policy: what a consumer gets when they construct the
 * resolver (or a model context) without any security configuration.
 * null stopped meaning "no policy" in v3 — it means THIS policy.
 * Opting out requires mode: off explicitly. Carries no reporter unless
 * given one, so standalone it blocks silently (README documents both paths).
 */
public static function defaultPolicy(?Closure $reporter = null): self
{
    return new self(
        blacklistedAttributes: self::DEFAULT_BLACKLISTED_ATTRIBUTES,
        blacklistedPatterns: self::DEFAULT_BLACKLISTED_PATTERNS,
        maxDepth: 10,
        mode: self::MODE_ENFORCE,
        reporter: $reporter,
    );
}
```

In `MustacheResolver` — the signature does not change; the behaviour does:

```php
private readonly SecurityValidator $securityValidator;

private readonly OutputSanitizer $sanitizer;

public function __construct(
    private readonly ParserInterface $parser,
    private readonly ResolutionPipeline $pipeline,
    private readonly CacheInterface $cache,
    ?SecurityValidator $securityValidator = null,
    ?OutputSanitizer $sanitizer = null,
) {
    // v3 (§11.2): null stops meaning "no policy" and means "the default
    // policy". Opting out requires an explicit mode: off validator.
    $this->securityValidator = $securityValidator ?? SecurityValidator::defaultPolicy();
    $this->sanitizer = $sanitizer ?? new OutputSanitizer($this->securityValidator);
}
```

(`$securityValidator` stops being a promoted property; every internal use — `createContext`, `warnRootWhitelistInapplicable` — now sees a non-null validator, so drop the now-dead null checks inside this class only.)

In `ResolutionContext::fromModel()` — fail-closed plus per-key defaults via `array_key_exists` (absence → default; explicit `[]` → deliberate):

```php
public static function fromModel(Model $model, array $securityConfig = []): self
{
    if (array_key_exists('allowed_models', $securityConfig)) {
        throw ConfigurationException::renamedKey('allowed_models', 'allowed_root_models');
    }

    $validator = $securityConfig === []
        ? SecurityValidator::defaultPolicy()
        : self::validatorFromConfig($securityConfig);

    $accessor = new EloquentAccessor($model, $validator);

    return new self($accessor, [], true, null, $securityConfig);
}

/**
 * @param  array<string, mixed>  $securityConfig
 */
private static function validatorFromConfig(array $securityConfig): SecurityValidator
{
    $reporter = $securityConfig['reporter'] ?? null;

    /** @var array<string> $allowedRootModels */
    $allowedRootModels = $securityConfig['allowed_root_models'] ?? [];

    /** @var array<string> $blacklistedAttributes */
    $blacklistedAttributes = array_key_exists('blacklisted_attributes', $securityConfig)
        ? $securityConfig['blacklisted_attributes']
        : SecurityValidator::DEFAULT_BLACKLISTED_ATTRIBUTES;

    /** @var array<string> $blacklistedPatterns */
    $blacklistedPatterns = array_key_exists('blacklisted_patterns', $securityConfig)
        ? $securityConfig['blacklisted_patterns']
        : SecurityValidator::DEFAULT_BLACKLISTED_PATTERNS;

    return new SecurityValidator(
        allowedRootModels: $allowedRootModels,
        blacklistedAttributes: $blacklistedAttributes,
        blacklistedPatterns: $blacklistedPatterns,
        maxDepth: (int) ($securityConfig['max_depth'] ?? 10),
        mode: (string) ($securityConfig['mode'] ?? SecurityValidator::MODE_ENFORCE),
        reporter: $reporter instanceof Closure ? $reporter : null,
    );
}
```

- [ ] **Step 4: Full-suite triage — the deliberate breaking wave.** Run `composer test`. Expect reds in tests that construct `MustacheResolver` or `fromModel` without a validator and touch (a) sensitive-named fields (`password`, `*_token`, `secret`, ...) or (b) whole containers (arrays/collections resolved entire — the default policy blocks them). For EACH red, classify:
  - **Old v2 fail-open contract** → update the test: either assert the new blocked behaviour, or declare intent explicitly (`mode: off` validator, or non-sensitive fixture field names) when the test is about something other than security.
  - **Anything ambiguous** → STOP, report the test name and both readings. Do not decide.

Never weaken `src/` to make an old test pass.

- [ ] **Step 5: Sensitivity check.** In `MustacheResolver`'s constructor, temporarily restore `$securityValidator ?? null` semantics (assign null-object... simplest: bypass by assigning `$securityValidator` and let sanitizer receive null) → the standalone default-policy tests must go RED. Restore, `git diff src/` clean, green.

- [ ] **Step 6: Run full gates**

Run: `composer test` then `composer analyse`
Expected: green, PHPStan level 8 clean (nullability changes are the risk — fix types, not with ignores).

- [ ] **Step 7: Commit**

```bash
git add -A && git commit -m "feat(security)!: null validator now means the default policy (enforce)" -m "..." -m "Refs AID-733"
```

---

### Task 5: Parser limits — template length and token count (enforce-only)

**Files:**
- Modify: `src/Core/Parser/MustacheParser.php`
- Modify: `src/Exceptions/SecurityException.php`
- Modify: `config/mustache-resolver.php`
- Modify: `src/Laravel/MustacheServiceProvider.php` (`registerParser()`)
- Test: `tests/Unit/Core/Parser/MustacheParserTest.php` (or the parser's existing unit test file — locate it first), `tests/Feature/Laravel/SecurityWiringTest.php`

**Interfaces:**
- Produces: `MustacheParser::DEFAULT_MAX_TEMPLATE_LENGTH = 100_000`, `MustacheParser::DEFAULT_MAX_TOKENS = 1_000` (public consts); constructor `__construct(?int $maxTemplateLength = self::DEFAULT_MAX_TEMPLATE_LENGTH, ?int $maxTokens = self::DEFAULT_MAX_TOKENS)` — `null` disables a limit; `SecurityException::templateTooLong()` / `::tooManyTokens()`. Config block `security.limits`. Task 6's reconciler consumes the constants.
- Rationale (commit body): a template with many relation paths amplifies lazy queries without a ceiling — the other half of finding 4 (§11.9). Limits are parse-time guards; they throw only under `enforce` wiring because a NEW throw in `report` would violate the report-changes-nothing invariant.

- [ ] **Step 1: Write the failing unit tests** (in the parser's existing unit test file):

```php
describe('parser limits', function () {
    it('throws when the template exceeds max length', function () {
        $parser = new MustacheParser(maxTemplateLength: 50, maxTokens: null);

        $parser->parse(str_repeat('x', 40).'{{User.name}}'.str_repeat('x', 40));
    })->throws(
        \AichaDigital\MustacheResolver\Exceptions\SecurityException::class,
        'exceeds the configured maximum',
    );

    it('accepts a template exactly at max length', function () {
        $template = '{{User.name}}';
        $parser = new MustacheParser(maxTemplateLength: strlen($template), maxTokens: null);

        expect($parser->parse($template))->toHaveCount(1);
    });

    it('throws when the template exceeds max tokens', function () {
        $parser = new MustacheParser(maxTemplateLength: null, maxTokens: 2);

        $parser->parse('{{a}} {{b}} {{c}}');
    })->throws(
        \AichaDigital\MustacheResolver\Exceptions\SecurityException::class,
        'exceeding the configured maximum',
    );

    it('accepts a template exactly at max tokens', function () {
        $parser = new MustacheParser(maxTemplateLength: null, maxTokens: 2);

        expect($parser->parse('{{a}} {{b}}'))->toHaveCount(2);
    });

    it('null disables both limits', function () {
        $parser = new MustacheParser(maxTemplateLength: null, maxTokens: null);

        expect($parser->parse(str_repeat('{{a}} ', 2000)))->toHaveCount(2000);
    });

    it('applies generous defaults out of the box', function () {
        $parser = new MustacheParser;

        expect($parser->parse('{{User.name}}'))->toHaveCount(1);
    });
});
```

- [ ] **Step 2: Run to verify failure**

Expected: FAIL — unknown named arguments.

- [ ] **Step 3: Implement.** `SecurityException` gains:

```php
public static function templateTooLong(int $length, int $max): self
{
    return new self("Template length {$length} exceeds the configured maximum of {$max} characters");
}

public static function tooManyTokens(int $count, int $max): self
{
    return new self("Template contains {$count} mustache tokens, exceeding the configured maximum of {$max}");
}
```

`MustacheParser` (stays `final`; keeps no other state):

```php
public const DEFAULT_MAX_TEMPLATE_LENGTH = 100_000;

public const DEFAULT_MAX_TOKENS = 1_000;

public function __construct(
    private readonly ?int $maxTemplateLength = self::DEFAULT_MAX_TEMPLATE_LENGTH,
    private readonly ?int $maxTokens = self::DEFAULT_MAX_TOKENS,
) {}
```

In `parse()`, first line before `validateSyntax()`:

```php
if ($this->maxTemplateLength !== null && strlen($template) > $this->maxTemplateLength) {
    throw SecurityException::templateTooLong(strlen($template), $this->maxTemplateLength);
}
```

After `$rawMustaches = $this->extractRaw($template);`:

```php
if ($this->maxTokens !== null && count($rawMustaches) > $this->maxTokens) {
    throw SecurityException::tooManyTokens(count($rawMustaches), $this->maxTokens);
}
```

`hasMustaches()` stays unlimited on purpose: it changes no state and gates `translate()`'s early return; limits guard *resolution*.

- [ ] **Step 4: Config + provider wiring.** Config, inside `security` after `max_depth`:

```php
// Parse-time ceilings guarding resolution amplification (a template
// with many relation paths multiplies lazy queries). Applied only when
// mode is 'enforce' — a new throw in report mode would break the
// "report changes nothing" invariant. null = unlimited.
'limits' => [
    'max_template_length' => env('MUSTACHE_SECURITY_MAX_TEMPLATE_LENGTH', 100000),
    'max_tokens' => env('MUSTACHE_SECURITY_MAX_TOKENS', 1000),
],
```

`registerParser()` becomes:

```php
protected function registerParser(): void
{
    $this->app->singleton(ParserInterface::class, function ($app) {
        /** @var array<string, mixed> $security */
        $security = $app['config']['mustache-resolver']['security'] ?? [];

        // Limits throw; report must not change behaviour vs v2.1, so only
        // enforce wires them. off keeps the explicit "no checks" promise.
        if (($security['mode'] ?? SecurityValidator::MODE_ENFORCE) !== SecurityValidator::MODE_ENFORCE) {
            return new MustacheParser(maxTemplateLength: null, maxTokens: null);
        }

        /** @var array<string, mixed> $limits */
        $limits = $security['limits'] ?? [];

        return new MustacheParser(
            maxTemplateLength: array_key_exists('max_template_length', $limits)
                ? $limits['max_template_length']
                : MustacheParser::DEFAULT_MAX_TEMPLATE_LENGTH,
            maxTokens: array_key_exists('max_tokens', $limits)
                ? $limits['max_tokens']
                : MustacheParser::DEFAULT_MAX_TOKENS,
        );
    });
}
```

(import `SecurityValidator` if not already imported.) Add a wiring test in `SecurityWiringTest.php`: with `mode=report`, a 3-token template against `max_tokens=2` config resolves without throwing; with `mode=enforce` it throws `SecurityException`.

- [ ] **Step 5: Full suite + sensitivity.** Run `composer test`. Sensitivity: comment out the token-count throw → the over-limit tests go RED; restore, diff clean, green. Watch for collateral: `UseVariableResolver` constructs `new MustacheParser` internally (defaults active — USE expressions are tiny, must stay green); any test parsing very large templates must be triaged per Task 4's rule.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat(security): bound template length and token count at parse time" -m "..." -m "Refs AID-733"
```

---

### Task 6: `mode: enforce` default + v2-published-config reconciliation and startup warning (§11.1)

**Files:**
- Create: `src/Laravel/SecurityConfigReconciler.php`
- Modify: `config/mustache-resolver.php` (`mode` default + comment)
- Modify: `src/Laravel/MustacheServiceProvider.php`
- Create: `tests/Unit/Laravel/SecurityConfigReconcilerTest.php`
- Create: `tests/Unit/Config/PublishedConfigTest.php`
- Create: `tests/Feature/Laravel/V2PublishedConfigTest.php`
- Create: `tests/Fixtures/config/mustache-resolver-v2.php` (verbatim copy of the v2.1.0 published config: `git show v2.1.0:config/mustache-resolver.php > tests/Fixtures/config/mustache-resolver-v2.php`)

**Interfaces:**
- Produces: `SecurityConfigReconciler::reconcile(array $security): array{security: array<string, mixed>, absent: list<string>, legacy_allowed_models: bool}` (pure static, no framework state).
- Consumes: `SecurityValidator::DEFAULT_BLACKLISTED_ATTRIBUTES`, `::DEFAULT_BLACKLISTED_PATTERNS`, `::MODE_ENFORCE` (Tasks 1/4); `MustacheParser::DEFAULT_MAX_TEMPLATE_LENGTH`, `::DEFAULT_MAX_TOKENS` (Task 5).

- [ ] **Step 1: Write the failing reconciler unit tests** (`tests/Unit/Laravel/SecurityConfigReconcilerTest.php`):

```php
use AichaDigital\MustacheResolver\Core\Parser\MustacheParser;
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;
use AichaDigital\MustacheResolver\Laravel\SecurityConfigReconciler;

describe('SecurityConfigReconciler', function () {
    it('fills every absent key with the v3 default and records it', function () {
        $result = SecurityConfigReconciler::reconcile([]);

        expect($result['security']['mode'])->toBe(SecurityValidator::MODE_ENFORCE);
        expect($result['security']['blacklisted_attributes'])->toBe(SecurityValidator::DEFAULT_BLACKLISTED_ATTRIBUTES);
        expect($result['security']['blacklisted_patterns'])->toBe(SecurityValidator::DEFAULT_BLACKLISTED_PATTERNS);
        expect($result['security']['allowed_root_models'])->toBe([]);
        expect($result['security']['allow_container_serialization'])->toBeFalse();
        expect($result['security']['max_depth'])->toBe(10);
        expect($result['security']['limits'])->toBe([
            'max_template_length' => MustacheParser::DEFAULT_MAX_TEMPLATE_LENGTH,
            'max_tokens' => MustacheParser::DEFAULT_MAX_TOKENS,
        ]);
        expect($result['absent'])->toContain('security.mode', 'security.blacklisted_patterns', 'security.limits');
        expect($result['legacy_allowed_models'])->toBeFalse();
    });

    it('never overrides a present mode, even report', function () {
        $result = SecurityConfigReconciler::reconcile(['mode' => 'report']);

        expect($result['security']['mode'])->toBe('report');
        expect($result['absent'])->not->toContain('security.mode');
    });

    it('honours a deliberate empty value over the default', function () {
        $result = SecurityConfigReconciler::reconcile(['blacklisted_patterns' => []]);

        expect($result['security']['blacklisted_patterns'])->toBe([]);
        expect($result['absent'])->not->toContain('security.blacklisted_patterns');
    });

    it('maps legacy allowed_models into allowed_root_models and flags it', function () {
        $result = SecurityConfigReconciler::reconcile([
            'allowed_models' => ['App\\Models\\User'],
        ]);

        expect($result['security']['allowed_root_models'])->toBe(['App\\Models\\User']);
        expect($result['security'])->not->toHaveKey('allowed_models');
        expect($result['legacy_allowed_models'])->toBeTrue();
    });

    it('lets an explicit allowed_root_models win over the legacy key', function () {
        $result = SecurityConfigReconciler::reconcile([
            'allowed_models' => ['App\\Models\\Old'],
            'allowed_root_models' => ['App\\Models\\NewUser'],
        ]);

        expect($result['security']['allowed_root_models'])->toBe(['App\\Models\\NewUser']);
        expect($result['legacy_allowed_models'])->toBeTrue();
    });

    it('drops the removed allowed_tables key', function () {
        $result = SecurityConfigReconciler::reconcile(['allowed_tables' => ['users']]);

        expect($result['security'])->not->toHaveKey('allowed_tables');
    });

    it('reconciling the real v2.1.0 published security block preserves report mode', function () {
        /** @var array{security: array<string, mixed>} $v2 */
        $v2 = require __DIR__.'/../../Fixtures/config/mustache-resolver-v2.php';

        $result = SecurityConfigReconciler::reconcile($v2['security']);

        expect($result['security']['mode'])->toBe('report');
        expect($result['security']['blacklisted_patterns'])->toBe(SecurityValidator::DEFAULT_BLACKLISTED_PATTERNS);
        expect($result['absent'])->toContain('security.blacklisted_patterns', 'security.limits');
    });
});
```

(Note: the v2 fixture's `mode` reads `env('MUSTACHE_SECURITY_MODE', 'report')` — in the test environment the env var is unset, so it evaluates to `'report'`. If the fixture's `env()` call is a problem outside the app, wrap the require in the test with Testbench present — these are Pest tests booted through the package TestCase, so `env()` exists.)

- [ ] **Step 2: Run to verify failure** — class does not exist.

- [ ] **Step 3: Implement `SecurityConfigReconciler`:**

```php
<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Laravel;

use AichaDigital\MustacheResolver\Core\Parser\MustacheParser;
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;

/**
 * Reconciles a consumer's security config block against the v3 defaults.
 *
 * Why this exists: mergeConfigFrom() uses a shallow array_merge and
 * `security` is a top-level key, so a config block published under v2
 * replaces the package block ENTIRELY — the v3 keys simply do not exist
 * for that consumer, and under config:cache the merge never runs at all.
 * Stating "v3 defaults to enforce" without this would repeat the exact
 * defect AID-632 exposed: trusting a default an intermediate layer masks.
 *
 * Rules (spec §11.1):
 * - A key that is ABSENT takes the v3 default (recorded, warned at boot).
 * - A key that is PRESENT — including as [] — is a deliberate choice and
 *   is never touched. array_key_exists(), never ??.
 * - `mode` follows the same absence rule but is NEVER rewritten when
 *   present: there is no way to tell an inherited `report` from a chosen one.
 * - Legacy `allowed_models` is carried into `allowed_root_models` (unless
 *   the new key is present) and flagged so the boot warning names the
 *   rename — silently ignoring it would drop a hardening control.
 * - Removed keys (`allowed_models`, `allowed_tables`) are stripped.
 */
final class SecurityConfigReconciler
{
    /**
     * @param  array<string, mixed>  $security
     * @return array{security: array<string, mixed>, absent: list<string>, legacy_allowed_models: bool}
     */
    public static function reconcile(array $security): array
    {
        $absent = [];
        $legacyAllowedModels = array_key_exists('allowed_models', $security);

        if ($legacyAllowedModels && ! array_key_exists('allowed_root_models', $security)) {
            $security['allowed_root_models'] = $security['allowed_models'];
        }

        unset($security['allowed_models'], $security['allowed_tables']);

        foreach (self::defaults() as $key => $default) {
            if (! array_key_exists($key, $security)) {
                $security[$key] = $default;
                $absent[] = 'security.'.$key;
            }
        }

        return [
            'security' => $security,
            'absent' => $absent,
            'legacy_allowed_models' => $legacyAllowedModels,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaults(): array
    {
        return [
            'mode' => SecurityValidator::MODE_ENFORCE,
            'allowed_root_models' => [],
            'max_depth' => 10,
            'allow_container_serialization' => false,
            'blacklisted_attributes' => SecurityValidator::DEFAULT_BLACKLISTED_ATTRIBUTES,
            'blacklisted_patterns' => SecurityValidator::DEFAULT_BLACKLISTED_PATTERNS,
            'limits' => [
                'max_template_length' => MustacheParser::DEFAULT_MAX_TEMPLATE_LENGTH,
                'max_tokens' => MustacheParser::DEFAULT_MAX_TOKENS,
            ],
        ];
    }
}
```

Run the reconciler unit tests: PASS.

- [ ] **Step 4: Flip the config default and wire the provider.** In `config/mustache-resolver.php`:

```php
// Enforcement mode:
// - 'off': no checks are applied
// - 'report': violations are logged (Log::warning) but resolution proceeds
// - 'enforce': violations block resolution / model access (v3 default)
// Fresh installs get 'enforce'. A config block published under v2 keeps
// the mode it declares — see UPGRADE-3.md.
'mode' => env('MUSTACHE_SECURITY_MODE', 'enforce'),
```

In `MustacheServiceProvider`: add properties, call the reconciler in `register()` right after `mergeConfigFrom`, warn in `boot()`:

```php
/** @var list<string> */
private array $absentSecurityKeys = [];

private bool $legacyAllowedModelsDetected = false;
```

```php
public function register(): void
{
    $this->mergeConfigFrom(
        __DIR__.'/../../config/mustache-resolver.php',
        'mustache-resolver'
    );

    $this->reconcileSecurityConfig();

    // ... existing register* calls unchanged
}

/**
 * Runs on every request on purpose: under a config cache built before the
 * upgrade, mergeConfigFrom() is skipped entirely and the cached block is
 * the consumer's v2 file — this is the only place absence can be detected.
 */
protected function reconcileSecurityConfig(): void
{
    /** @var array<string, mixed> $security */
    $security = $this->app['config']->get('mustache-resolver.security', []);

    $result = SecurityConfigReconciler::reconcile($security);

    $this->app['config']->set('mustache-resolver.security', $result['security']);
    $this->absentSecurityKeys = $result['absent'];
    $this->legacyAllowedModelsDetected = $result['legacy_allowed_models'];
}
```

In `boot()` (after the publishes block):

```php
if ($this->absentSecurityKeys !== [] || $this->legacyAllowedModelsDetected) {
    $this->warnAboutIncompleteSecurityConfig();
}
```

```php
/**
 * The warning must state: which keys were absent and what defaults now
 * apply, the effective mode, the report-only caveat, the exact edit
 * required, and a link to UPGRADE-3.md. It must NOT assert which file
 * the configuration came from — under cached config that cannot be
 * verified (spec §11.1).
 */
protected function warnAboutIncompleteSecurityConfig(): void
{
    /** @var string $mode */
    $mode = $this->app['config']->get('mustache-resolver.security.mode', SecurityValidator::MODE_ENFORCE);

    $context = [
        'absent_keys_filled_with_v3_defaults' => $this->absentSecurityKeys,
        'effective_mode' => $mode,
        'action' => 'Add the listed keys to your published mustache-resolver.php config (or re-publish it), then review UPGRADE-3.md.',
    ];

    if ($this->legacyAllowedModelsDetected) {
        $context['renamed_key'] = 'security.allowed_models is now security.allowed_root_models '
            .'(FQCN-only, validates the root model only). Its value was carried over; rename the key.';
    }

    if ($mode === SecurityValidator::MODE_REPORT) {
        $context['report_mode'] = 'Under report mode the v3 protections only report, they do not block.';
    }

    Log::warning(
        'mustache-resolver: security configuration is missing v3 keys; defaults were applied for this runtime',
        $context,
    );
}
```

Also harden the remaining read sites in `registerSecurity()` (defaults now live at the read point too, so the control cannot go inert if reconciliation ever regresses):

```php
$mode = $config['mode'] ?? SecurityValidator::MODE_ENFORCE;
```

```php
allowedRootModels: $config['allowed_root_models'] ?? [],
blacklistedAttributes: array_key_exists('blacklisted_attributes', $config)
    ? $config['blacklisted_attributes']
    : SecurityValidator::DEFAULT_BLACKLISTED_ATTRIBUTES,
```

(patterns read site already landed in Task 1; `max_depth` keeps `?? 10`.)

- [ ] **Step 5: Feature tests** (`tests/Feature/Laravel/V2PublishedConfigTest.php`). Simulate a consumer-published config with Testbench — set the FULL `mustache-resolver` config in `defineEnvironment` so `mergeConfigFrom`'s shallow merge sees the consumer's top-level `security` key, exactly like a real published file:

```php
<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\MustacheResolver;
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;

// Pest's defineEnvironment hook: check how the suite's TestCase exposes it.
// If the suite uses class-based TestCases, create this as a class test or
// use Pest's ->defineEnvironment support consistent with neighbouring
// feature tests. The requirement is: the config is set BEFORE the package
// provider registers.

it('a v2 published config keeps report mode but gains the v3 keys', function () {
    // defineEnvironment set config('mustache-resolver') to the v2.1.0 file.
    expect(config('mustache-resolver.security.mode'))->toBe('report');
    expect(config('mustache-resolver.security.blacklisted_patterns'))
        ->toBe(SecurityValidator::DEFAULT_BLACKLISTED_PATTERNS);
    expect(config('mustache-resolver.security'))->not->toHaveKey('allowed_tables');
    expect(config('mustache-resolver.security'))->toHaveKey('limits');
});

it('under the preserved report mode a pattern hit resolves and does not block', function () {
    $resolver = $this->app->make(MustacheResolver::class);

    $result = $resolver->translate(
        'Token: {{User.auth_token}}',
        ['User' => ['auth_token' => 'tok_123']],
    );

    expect($result->getTranslated())->toBe('Token: tok_123');
});
```

plus the fresh-install counterpart (no simulated config — default TestCase):

```php
it('a fresh install runs enforce with no warning', function () {
    expect(config('mustache-resolver.security.mode'))->toBe('enforce');
    // No absent keys: the package config carries every v3 key.
});
```

and one warning-emission test — construct the provider directly against a doctored config and spy the log:

```php
it('boots with a warning naming the absent keys', function () {
    \Illuminate\Support\Facades\Log::spy();

    $this->app['config']->set('mustache-resolver.security', ['mode' => 'report']);

    $provider = new \AichaDigital\MustacheResolver\Laravel\MustacheServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return str_contains($message, 'missing v3 keys')
                && in_array('security.blacklisted_patterns', $context['absent_keys_filled_with_v3_defaults'], true)
                && $context['effective_mode'] === 'report'
                && isset($context['report_mode']);
        });
});
```

(If double-registering the provider on the booted app causes side effects in this suite, extract the two calls under test — `reconcileSecurityConfig()` + `warnAboutIncompleteSecurityConfig()` — and invoke them directly on a fresh provider instance; the assertion set stays identical.)

- [ ] **Step 6: Published-file guardrail** (`tests/Unit/Config/PublishedConfigTest.php`) — reads the FILE, not `config()` (AID-589 lesson: a guard reading merged config cannot detect reintroduction):

```php
<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Parser\MustacheParser;
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;

it('the published config file ships the v3 security surface', function () {
    /** @var array<string, mixed> $file */
    $file = require dirname(__DIR__, 3).'/config/mustache-resolver.php';

    /** @var array<string, mixed> $security */
    $security = $file['security'];

    expect($security['mode'])->toBe('enforce');
    expect($security)->not->toHaveKey('allowed_tables');
    expect($security)->not->toHaveKey('allowed_models');
    expect($security)->toHaveKey('allowed_root_models');
    expect($security['blacklisted_attributes'])->toBe(SecurityValidator::DEFAULT_BLACKLISTED_ATTRIBUTES);
    expect($security['blacklisted_patterns'])->toBe(SecurityValidator::DEFAULT_BLACKLISTED_PATTERNS);
    expect($security['limits'])->toBe([
        'max_template_length' => MustacheParser::DEFAULT_MAX_TEMPLATE_LENGTH,
        'max_tokens' => MustacheParser::DEFAULT_MAX_TOKENS,
    ]);
});
```

(Adjust the `dirname` depth to the actual test location. `env()` in the file resolves through the booted testbench app; MUSTACHE_* env vars are unset in the suite, so defaults apply — if any is set in `phpunit.xml.dist`, unset it there rather than weakening the assertion.)

- [ ] **Step 7: Full suite + sensitivity.** `composer test`. Sensitivity: disable the reconciler call in `register()` → the v2-published-config feature tests must go RED (patterns key missing). Restore, diff clean, green.

- [ ] **Step 8: Commit**

```bash
git add -A && git commit -m "feat(security)!: enforce by default, reconcile v2-published config with a boot warning" -m "..." -m "Refs AID-733"
```

---

### Task 7: `ObjectAccessor` becomes security-aware

**Files:**
- Modify: `src/Accessors/ObjectAccessor.php`
- Test: `tests/Unit/Accessors/ObjectAccessorTest.php`

**Interfaces:**
- Produces: `ObjectAccessor::__construct(object $object, ?SecurityValidator $securityValidator = null)`; implements `SecurityAwareAccessorInterface` (`allowsPath(string): bool`). Mirrors `ArrayAccessor` exactly.
- Decision recorded (deferred from phase 1): the class is dead code in `src/` (never instantiated since the initial scaffold) but public API — a consumer can hand it through the `DataAccessorInterface` branch of `createContext()` and silently lose barrier 1. The defect is the missing interface, not the dead instantiation, so it becomes security-aware rather than deleted. A validator-less construction keeps v2 behaviour (accessors validate only when given a validator; barrier 2 still applies via `translate()`).

- [ ] **Step 1: Write the failing tests** (append to `ObjectAccessorTest.php`):

```php
describe('security awareness (v3)', function () {
    it('implements SecurityAwareAccessorInterface', function () {
        $accessor = new ObjectAccessor(new stdClass);

        expect($accessor)->toBeInstanceOf(
            \AichaDigital\MustacheResolver\Contracts\SecurityAwareAccessorInterface::class
        );
    });

    it('blocks a blacklisted property in enforce mode', function () {
        $obj = new stdClass;
        $obj->password = 'secret-value';
        $obj->name = 'John';

        $accessor = new ObjectAccessor($obj, new \AichaDigital\MustacheResolver\Core\Security\SecurityValidator(
            blacklistedAttributes: ['password'],
            mode: \AichaDigital\MustacheResolver\Core\Security\SecurityValidator::MODE_ENFORCE,
        ));

        // Fixture guard: without a validator the value IS there.
        expect((new ObjectAccessor($obj))->get('password'))->toBe('secret-value');

        expect($accessor->get('password'))->toBeNull();
        expect($accessor->get('name'))->toBe('John');
        expect($accessor->has('password'))->toBeFalse();
    });

    it('reports but resolves in report mode', function () {
        $obj = new stdClass;
        $obj->password = 'secret-value';

        $reports = [];
        $accessor = new ObjectAccessor($obj, new \AichaDigital\MustacheResolver\Core\Security\SecurityValidator(
            blacklistedAttributes: ['password'],
            mode: \AichaDigital\MustacheResolver\Core\Security\SecurityValidator::MODE_REPORT,
            reporter: function (string $message, array $context = []) use (&$reports): void {
                $reports[] = $message;
            },
        ));

        expect($accessor->get('password'))->toBe('secret-value');
        expect($reports)->not->toBeEmpty();
    });

    it('keeps v2 behaviour without a validator', function () {
        $obj = new stdClass;
        $obj->password = 'secret-value';

        expect((new ObjectAccessor($obj))->get('password'))->toBe('secret-value');
    });
});
```

- [ ] **Step 2: Run to verify failure** — interface assertion and enforce-block fail.

- [ ] **Step 3: Implement** — mirror `ArrayAccessor`:

```php
final readonly class ObjectAccessor implements DataAccessorInterface, SecurityAwareAccessorInterface
{
    public function __construct(
        private object $object,
        private ?SecurityValidator $securityValidator = null,
    ) {}

    public function get(string $path): mixed
    {
        if (! $this->allowsPath($path)) {
            return null;
        }

        // ... existing segment walk unchanged
    }

    /**
     * Check every segment against the blacklist and the path depth.
     */
    public function allowsPath(string $path): bool
    {
        return $this->securityValidator === null || $this->securityValidator->allowsPath($path);
    }

    // has() already delegates to get(), so it inherits the gate.
}
```

(add both imports; keep the rest of the class untouched.)

- [ ] **Step 4: Full suite + sensitivity.** Sensitivity: make `allowsPath()` return `true` unconditionally → enforce-block test RED. Restore, diff clean, green.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "fix(security): ObjectAccessor gains barrier 1 (SecurityAwareAccessorInterface)" -m "..." -m "Refs AID-733"
```

---

### Task 8: Compound path through the sanitizer (`UseVariableResolver`)

**Files:**
- Modify: `src/Core/Compound/UseVariableResolver.php`
- Modify: `src/Core/Compound/CompoundResolver.php`
- Modify: `src/Core/Security/OutputSanitizer.php` (class docblock ONLY)
- Test: `tests/Unit/Core/Compound/UseVariableResolverTest.php` (locate the existing compound tests first; create the file if none exists)

**Interfaces:**
- Produces: `UseVariableResolver::__construct(ResolutionPipeline $pipeline, ?OutputSanitizer $sanitizer = null)`; `CompoundResolver::__construct(ResolutionPipeline $pipeline, ?OutputSanitizer $sanitizer = null)`. `null` sanitizer → default-policy sanitizer (same §11.2 rule as Task 4).
- Consumes: `SecurityValidator::defaultPolicy()` (Task 4), `OutputSanitizer`, `SanitizedValue->blocked`.
- Context: this closes the second exit path phase 1 documented as inert-but-public — `UseVariableResolver` calls the pipeline directly and substitutes the raw value without reaching barrier 2. Unreachable from `translate()` today (no default resolver handles `COMPOUND`, `CompoundResolver` is registered nowhere), but it is public API and v3 promises the barrier sits downstream of every resolution.

- [ ] **Step 1: Write the failing tests:**

```php
use AichaDigital\MustacheResolver\Core\Compound\UseVariable;
use AichaDigital\MustacheResolver\Core\Compound\UseVariableResolver;
use AichaDigital\MustacheResolver\Core\Context\ResolutionContext;
use AichaDigital\MustacheResolver\Core\Pipeline\PipelineBuilder;
use AichaDigital\MustacheResolver\Core\Security\OutputSanitizer;
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;
use AichaDigital\MustacheResolver\Exceptions\VariableNotResolvedException;

describe('UseVariableResolver security (v3)', function () {
    it('blocks a blacklisted path through the compound exit', function () {
        $resolver = new UseVariableResolver(PipelineBuilder::create()->build());

        $context = ResolutionContext::fromArray(
            ['User' => ['api_token' => 'tok_123']],
            new SecurityValidator(mode: SecurityValidator::MODE_OFF),
        );

        // The accessor is off (barrier 1 disabled on purpose) so the raw
        // value reaches the compound exit; the DEFAULT sanitizer (§11.2)
        // must be the thing that blocks it.
        $variable = new UseVariable('token', '{{User.api_token}}');

        $resolver->resolve($variable, $context);
    })->throws(VariableNotResolvedException::class, 'blocked by security policy');

    it('passes values through untouched with an off-mode sanitizer', function () {
        $resolver = new UseVariableResolver(
            PipelineBuilder::create()->build(),
            new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_OFF)),
        );

        $context = ResolutionContext::fromArray(
            ['User' => ['api_token' => 'tok_123']],
            new SecurityValidator(mode: SecurityValidator::MODE_OFF),
        );

        $variable = new UseVariable('token', '{{User.api_token}}');

        expect($resolver->resolve($variable, $context))->toBe('tok_123');
    });
});
```

Check `UseVariable`'s real constructor signature before writing (name + expression order, condition parameter) and match it — adapt the construction to what the existing compound tests do. The two assertions are the contract; the fixture plumbing follows the suite.

- [ ] **Step 2: Run to verify failure** — the first test resolves `tok_123` instead of throwing.

- [ ] **Step 3: Implement.** `UseVariableResolver`:

```php
private readonly OutputSanitizer $sanitizer;

public function __construct(
    private readonly ResolutionPipeline $pipeline,
    ?OutputSanitizer $sanitizer = null,
) {
    $this->conditionEvaluator = new ConditionEvaluator;
    $this->parser = new MustacheParser;
    // §11.2: null means the default policy, here too — this class is the
    // one public exit that bypassed barrier 2 in phase 1.
    $this->sanitizer = $sanitizer ?? new OutputSanitizer(SecurityValidator::defaultPolicy());
}
```

In `resolve()`, between the pipeline call and the null check:

```php
$sanitized = $this->sanitizer->sanitize($value, $token);

if ($sanitized->blocked) {
    throw new VariableNotResolvedException(
        $variable->getName(),
        $expression,
        'Expression blocked by security policy',
    );
}

$value = $sanitized->value;
```

(imports: `OutputSanitizer`, `SecurityValidator`.) `CompoundResolver`:

```php
public function __construct(
    private readonly ResolutionPipeline $pipeline,
    ?OutputSanitizer $sanitizer = null,
) {
    $this->parser = new CompoundExpressionParser;
    $this->variableResolver = new UseVariableResolver($pipeline, $sanitizer);
    $this->replacer = new LocalVariableReplacer;
}
```

- [ ] **Step 4: Update the `OutputSanitizer` class docblock.** The paragraph starting "The known exception is compound expressions" is now false — replace it with a statement that `UseVariableResolver` passes every resolved value through its own sanitizer instance (default policy when none injected), so the compound exit is covered.

- [ ] **Step 5: Full suite + sensitivity.** `composer test` — existing compound tests constructing without a sanitizer now run under the default policy; triage per Task 4's rule (innocuous fixtures should stay green; sensitive-named ones are the old fail-open contract). Sensitivity: comment out the `sanitize()` call → the blocking test goes RED. Restore, diff clean, green.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "fix(security): route the compound exit through the output sanitizer" -m "..." -m "Refs AID-733"
```

---

## Final verification (after all tasks)

- [ ] `composer quality` green with `php84` (pint + phpstan level 8 + pest coverage ≥ 90%).
- [ ] `git log --oneline v2.1.0..HEAD` reads as a coherent phase-2 story; working tree clean.
- [ ] Whole-branch review over the phase-2 range (SDD final review), pointing the reviewer at the ledger's deferred minors.
- [ ] Confirm NOTHING in phase 3's scope leaked in (no README/UPGRADE/CHANGELOG edits).

## Self-review notes (spec coverage)

- §4.1 patterns → Task 1. §4.3 FQCN → Task 2. §4.4 removals → Task 3. §11.2 standalone + finding 7 fail-open → Task 4. §11.9 limits → Task 5. §11.1 reconciliation + mode default → Task 6. Phase-1 deferred `ObjectAccessor` → Task 7. Phase-1 deferred compound exit → Task 8.
- §11.4 array-root warning → Task 2 Step 5. §11.5/§11.6 landed in phase 1 (no phase-2 work). §11.7/§11.8/§11.10 (EOL date, communication, docs) → Phase 3.
- Out of scope, deliberate: the report-mode double `stripBlacklisted()` walk (waste only), the `TokenType` list triplication, PHPStan level max (quality debt tracked in AID-733's own section, not phase 2).
