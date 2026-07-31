# mustache v3.0.0 — Phase 1: Output Sanitizer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Route every resolved value through a single sanitisation point so that security policy cannot be bypassed by a new data path, and close the `getResolvedValues()` leak.

**Architecture:** Two barriers. Barrier 1 (access) already exists in the accessors and is untouched here. Barrier 2 (output) is a new `OutputSanitizer` that sits between `pipeline->resolve()` and the point where the value forks into rendered text and `resolvedValues`. `MustacheResolver` loses all policy logic to it.

**Tech Stack:** PHP 8.2+, Laravel 12/13 (illuminate/contracts + illuminate/support only), Pest 4, PHPStan level 8, Pint.

**Spec:** `docs/superpowers/specs/2026-07-30-mustache-v3-security-enforce-design.md`, sections 5, 11.3, 11.5, 11.6.

**Ticket:** AID-733.

## Global Constraints

- `declare(strict_types=1)` in every PHP file. PSR-12 via Pint.
- All code, comments, docblocks and commit messages in **English**.
- Branch: **`3.x`**, created from `main`. `main` stays on 2.x for the whole of v3 development. Never commit v3 work to `main`.
- Tests use **real models** from `workbench/`, never mocks. Namespace `Workbench\App\Models\`.
- Gate before every commit: `composer quality` (= `pint` + `phpstan` + `pest --coverage --min=90`). Coverage floor is **90%** and the suite currently sits at 90.8% — a task that adds uncovered code will fail the gate, not warn.
- **This phase does not change any default.** `security.mode` stays `report` in `config/mustache-resolver.php` until Phase 2. Every test in this phase sets the mode it needs explicitly.
- No new runtime dependency. `illuminate/support` is available (`Str`, `Arr`); `illuminate/database` is **not** a hard dependency — guard Eloquent usage with `class_exists()` where it runs outside Laravel.

---

## File Structure

**Created:**

- `src/Core/Security/OutputSanitizer.php` — barrier 2. Decides block/filter/pass for a resolved value, and produces both representations.
- `src/Core/Security/SanitizedValue.php` — value object carrying the sanitised value (for `resolvedValues`) and its rendered text (for the template).
- `src/Contracts/SafeForTemplateSerialization.php` — marker interface; a class implementing it may be serialised whole.
- `tests/Unit/Core/Security/OutputSanitizerTest.php`
- `tests/Unit/Core/Token/TokenTypeSecurityPathTest.php`
- `tests/Feature/Security/OutputSanitizationTest.php`

**Modified:**

- `src/Core/Token/TokenType.php` — add `hasSecurityPath()`.
- `src/Core/MustacheResolver.php` — sanitise before the fork; delete `reportContainerViolations()`, `findBlacklistedKeys()`, `valueToString()`, `modelToString()`, `stripBlacklistedAttributes()`.
- `tests/Feature/Laravel/SecurityWiringTest.php` — the v2 contract test `it('leaves containers unfiltered in enforce mode (deferred to v3)')` is inverted in Task 9.

---

## Why `SanitizedValue` exists

Today `modelToString()` renders a filtered model as escaped JSON, preserving Eloquent's `escapeWhenCastingToString()`. If the sanitizer simply returned a filtered **array**, `valueToString()` would `implode(', ', ...)` it and silently change the rendered output of every consumer using whole-model serialisation.

So sanitisation produces two things at once: the value that goes into `resolvedValues` (filtered array) and the text that goes into the template (escaped JSON). One object, computed once, no second guessing downstream.

---

### Task 1: `TokenType::hasSecurityPath()`

Attribute rules must not leak onto tokens that are not attribute access. A `{{now()}}` or `{{$myVar}}` has no dot-path that means "navigate data", so running the blacklist over it manufactures false positives on names the consumer controls entirely.

`requiresAccessor()` already exists and today covers the same cases, but it answers a different question (how to resolve) and coupling security to it would mean a future resolution change silently moves the security boundary.

**Files:**
- Modify: `src/Core/Token/TokenType.php`
- Test: `tests/Unit/Core/Token/TokenTypeSecurityPathTest.php`

**Interfaces:**
- Produces: `TokenType::hasSecurityPath(): bool`

- [ ] **Step 1: Create the branch**

```bash
git checkout main && git pull origin main
git checkout -b 3.x
```

- [ ] **Step 2: Write the failing test**

Create `tests/Unit/Core/Token/TokenTypeSecurityPathTest.php`:

```php
<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Token\TokenType;

describe('TokenType → security path', function () {
    it('marks data-navigating types as carrying a security path', function (TokenType $type) {
        expect($type->hasSecurityPath())->toBeTrue();
    })->with([
        TokenType::MODEL,
        TokenType::TABLE,
        TokenType::RELATION,
        TokenType::DYNAMIC,
        TokenType::COLLECTION,
    ]);

    it('marks non-navigating types as carrying no security path', function (TokenType $type) {
        expect($type->hasSecurityPath())->toBeFalse();
    })->with([
        TokenType::FUNCTION,
        TokenType::VARIABLE,
        TokenType::MATH,
        TokenType::NULL_COALESCE,
        TokenType::LITERAL,
        TokenType::UNKNOWN,
        TokenType::COMPOUND,
        TokenType::USE_DECLARATION,
        TokenType::LOCAL_VARIABLE,
        TokenType::FORMATTER,
        TokenType::TEMPORAL,
    ]);

    it('covers every case of the enum, so a new type cannot default into a regime', function () {
        $covered = 5 + 11;

        expect(count(TokenType::cases()))->toBe($covered);
    });
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Core/Token/TokenTypeSecurityPathTest.php`
Expected: FAIL with `Call to undefined method ...TokenType::hasSecurityPath()`

- [ ] **Step 4: Implement**

Add to `src/Core/Token/TokenType.php`, after `requiresAccessor()`:

```php
    /**
     * Whether this token's path is data navigation subject to security policy.
     *
     * Deliberately separate from requiresAccessor(): that answers how a token
     * is resolved, this answers whether attribute rules apply to it. Running
     * the blacklist over a function or variable token would produce false
     * positives on names the consumer owns entirely.
     *
     * The match is exhaustive on purpose — a new case must choose a regime.
     */
    public function hasSecurityPath(): bool
    {
        return match ($this) {
            self::MODEL,
            self::TABLE,
            self::RELATION,
            self::DYNAMIC,
            self::COLLECTION => true,
            self::FUNCTION,
            self::VARIABLE,
            self::MATH,
            self::NULL_COALESCE,
            self::LITERAL,
            self::UNKNOWN,
            self::COMPOUND,
            self::USE_DECLARATION,
            self::LOCAL_VARIABLE,
            self::FORMATTER,
            self::TEMPORAL => false,
        };
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Core/Token/TokenTypeSecurityPathTest.php`
Expected: PASS (17 tests)

- [ ] **Step 6: Commit**

```bash
git add src/Core/Token/TokenType.php tests/Unit/Core/Token/TokenTypeSecurityPathTest.php
git commit -m "feat(security): mark which token types carry a security path

Attribute rules must not reach function, variable or temporal tokens.
Kept separate from requiresAccessor() so a resolution change cannot move
the security boundary by accident, and the match is exhaustive so a new
token type has to pick a side.

Refs AID-733"
```

---

### Task 2: `SanitizedValue` and the sanitizer skeleton

**Files:**
- Create: `src/Core/Security/SanitizedValue.php`
- Create: `src/Core/Security/OutputSanitizer.php`
- Test: `tests/Unit/Core/Security/OutputSanitizerTest.php`

**Interfaces:**
- Consumes: `SecurityValidator::getMode()`, `SecurityValidator::MODE_OFF|MODE_REPORT|MODE_ENFORCE` (existing).
- Produces:
  - `SanitizedValue::__construct(mixed $value, string $text, bool $blocked = false)`, public readonly properties `$value`, `$text`, `$blocked`.
  - `OutputSanitizer::__construct(?SecurityValidator $validator = null, bool $allowContainerSerialization = false)`
  - `OutputSanitizer::sanitize(mixed $raw, TokenInterface $token): SanitizedValue`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Core/Security/OutputSanitizerTest.php`:

```php
<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Security\OutputSanitizer;
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;
use AichaDigital\MustacheResolver\Core\Token\Token;
use AichaDigital\MustacheResolver\Core\Token\TokenType;

/**
 * Token has a private constructor; Token::create() is the explicit factory.
 * Name it distinctively — Pest file-level helpers share one global namespace
 * across the whole suite, so a generic name collides at load time.
 */
function sanitizerTestToken(string $raw, TokenType $type = TokenType::MODEL): Token
{
    return Token::create($raw, $type, explode('.', $raw));
}

describe('OutputSanitizer → scalars', function () {
    it('passes scalars through unchanged and renders them', function () {
        $sanitizer = new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE));

        $result = $sanitizer->sanitize('John Doe', sanitizerTestToken('User.name'));

        expect($result->value)->toBe('John Doe');
        expect($result->text)->toBe('John Doe');
        expect($result->blocked)->toBeFalse();
    });

    it('renders null as an empty string', function () {
        $sanitizer = new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE));

        $result = $sanitizer->sanitize(null, sanitizerTestToken('User.missing'));

        expect($result->value)->toBeNull();
        expect($result->text)->toBe('');
    });

    it('renders booleans as true/false', function () {
        $sanitizer = new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE));

        expect($sanitizer->sanitize(true, sanitizerTestToken('User.active'))->text)->toBe('true');
        expect($sanitizer->sanitize(false, sanitizerTestToken('User.active'))->text)->toBe('false');
    });

    it('passes everything through untouched with no validator', function () {
        $sanitizer = new OutputSanitizer;

        $result = $sanitizer->sanitize(['a' => 1], sanitizerTestToken('User.meta'));

        expect($result->value)->toBe(['a' => 1]);
        expect($result->blocked)->toBeFalse();
    });
});
```

Note: check the real constructor of `MustacheToken` before running — if its signature differs from `(string $raw, string $full, TokenType $type)`, adapt `sanitizerTestToken()` to the real one. Do not change `MustacheToken` to fit the test.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Core/Security/OutputSanitizerTest.php`
Expected: FAIL with `Class "AichaDigital\MustacheResolver\Core\Security\OutputSanitizer" not found`

- [ ] **Step 3: Implement `SanitizedValue`**

Create `src/Core/Security/SanitizedValue.php`:

```php
<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Security;

/**
 * A resolved value after security policy has been applied.
 *
 * Carries both representations because they diverge: a filtered model is an
 * array for the caller inspecting resolved values, but escaped JSON in the
 * rendered template. Computing both here keeps the decision in one place.
 */
final readonly class SanitizedValue
{
    public function __construct(
        public mixed $value,
        public string $text,
        public bool $blocked = false,
    ) {}

    /**
     * A value the policy refused: nothing recorded, nothing rendered.
     */
    public static function blocked(): self
    {
        return new self(null, '', true);
    }
}
```

- [ ] **Step 4: Implement the sanitizer skeleton**

Create `src/Core/Security/OutputSanitizer.php`:

```php
<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Security;

use AichaDigital\MustacheResolver\Contracts\TokenInterface;

/**
 * Barrier 2: every resolved value passes through here before it forks into
 * the rendered template and the recorded resolved values.
 *
 * A resolver cannot bypass it, because it sits downstream of all of them.
 */
final readonly class OutputSanitizer
{
    public function __construct(
        private ?SecurityValidator $validator = null,
        private bool $allowContainerSerialization = false,
    ) {}

    public function sanitize(mixed $raw, TokenInterface $token): SanitizedValue
    {
        return new SanitizedValue($raw, $this->render($raw));
    }

    /**
     * Render a sanitised value for template substitution.
     */
    private function render(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return implode(', ', array_map(fn ($v): string => $this->render($v), $value));
        }

        if (is_object($value)) {
            return method_exists($value, '__toString') ? (string) $value : '';
        }

        return (string) $value;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Core/Security/OutputSanitizerTest.php`
Expected: PASS (4 tests)

- [ ] **Step 6: Commit**

```bash
git add src/Core/Security/ tests/Unit/Core/Security/OutputSanitizerTest.php
git commit -m "feat(security): add the output sanitizer skeleton

Every resolved value will pass through this one point before it forks
into rendered text and recorded values. SanitizedValue carries both
representations because they diverge for filtered models: an array for
the caller, escaped JSON for the template.

Refs AID-733"
```

---

### Task 3: Block containers, with both escapes

**Files:**
- Create: `src/Contracts/SafeForTemplateSerialization.php`
- Modify: `src/Core/Security/OutputSanitizer.php`
- Test: `tests/Unit/Core/Security/OutputSanitizerTest.php`

**Interfaces:**
- Produces: `SafeForTemplateSerialization` (empty marker interface).

- [ ] **Step 1: Write the failing test**

Append to `tests/Unit/Core/Security/OutputSanitizerTest.php`:

```php
describe('OutputSanitizer → containers', function () {
    it('blocks an array in enforce mode', function () {
        $sanitizer = new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE));

        $result = $sanitizer->sanitize(['name' => 'Engineering'], sanitizerTestToken('User.department'));

        expect($result->blocked)->toBeTrue();
        expect($result->value)->toBeNull();
        expect($result->text)->toBe('');
    });

    it('leaves a container untouched in report mode but reports it', function () {
        $reported = [];
        $validator = new SecurityValidator(
            mode: SecurityValidator::MODE_REPORT,
            reporter: function (string $m, array $c) use (&$reported): void { $reported[] = $m; },
        );

        $result = (new OutputSanitizer($validator))->sanitize(['name' => 'Engineering'], sanitizerTestToken('User.department'));

        expect($result->blocked)->toBeFalse();
        expect($result->value)->toBe(['name' => 'Engineering']);
        expect($reported)->toHaveCount(1);
    });

    it('allows containers when the global flag is on', function () {
        $sanitizer = new OutputSanitizer(
            new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE),
            allowContainerSerialization: true,
        );

        $result = $sanitizer->sanitize(['name' => 'Engineering'], sanitizerTestToken('User.department'));

        expect($result->blocked)->toBeFalse();
        expect($result->value)->toBe(['name' => 'Engineering']);
    });

    it('allows an object that declares itself safe, with the flag off', function () {
        // Arrayable on purpose: a Stringable-only class is classified atomic and
        // would never reach the container path, so it would not exercise the escape.
        $safe = new class implements \AichaDigital\MustacheResolver\Contracts\SafeForTemplateSerialization, \Illuminate\Contracts\Support\Arrayable
        {
            public function toArray(): array
            {
                return ['label' => 'ok'];
            }
        };

        $result = (new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE)))
            ->sanitize($safe, sanitizerTestToken('User.badge'));

        expect($result->blocked)->toBeFalse();
    });
});

describe('OutputSanitizer → classification precedence', function () {
    it('blocks an Arrayable that is also Stringable', function () {
        $both = new class implements \Illuminate\Contracts\Support\Arrayable, \Stringable
        {
            public function toArray(): array
            {
                return ['secret' => 'x'];
            }

            public function __toString(): string
            {
                return 'looks harmless';
            }
        };

        $result = (new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE)))
            ->sanitize($both, sanitizerTestToken('User.thing'));

        expect($result->blocked)->toBeTrue();
    });

    it('blocks a real Collection', function () {
        $result = (new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE)))
            ->sanitize(new \Illuminate\Support\Collection(['a' => 1]), sanitizerTestToken('User.items'));

        expect($result->blocked)->toBeTrue();
    });

    it('blocks a real Eloquent model', function () {
        $model = new \Workbench\App\Models\User(['name' => 'John']);

        $result = (new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE)))
            ->sanitize($model, sanitizerTestToken('User.self'));

        expect($result->blocked)->toBeTrue();
    });

    it('does not let the global flag authorise a model', function () {
        $model = new \Workbench\App\Models\User(['name' => 'John']);

        $result = (new OutputSanitizer(
            new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE),
            allowContainerSerialization: true,
        ))->sanitize($model, sanitizerTestToken('User.self'));

        expect($result->blocked)->toBeTrue();
    });

    it('treats Carbon as atomic and records it as a string, not as an object', function () {
        $date = \Carbon\Carbon::parse('2026-07-31 09:00:00');

        $result = (new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE)))
            ->sanitize($date, sanitizerTestToken('User.created_at'));

        expect($result->blocked)->toBeFalse();
        expect($result->value)->toBeString();
        expect($result->value)->toBe($result->text);
    });

    it('normalises a Stringable JsonSerializable to a string rather than blocking it', function () {
        $money = new class implements \Stringable, \JsonSerializable
        {
            public function __toString(): string
            {
                return '10.00 EUR';
            }

            public function jsonSerialize(): array
            {
                return ['amount' => 1000, 'currency' => 'EUR'];
            }
        };

        $result = (new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE)))
            ->sanitize($money, sanitizerTestToken('User.balance'));

        expect($result->blocked)->toBeFalse();
        expect($result->value)->toBe('10.00 EUR');
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Core/Security/OutputSanitizerTest.php --filter=containers`
Expected: FAIL — containers are not blocked yet

- [ ] **Step 3: Create the marker interface**

Create `src/Contracts/SafeForTemplateSerialization.php`:

```php
<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Contracts;

/**
 * Declares a class safe to serialise whole into a template.
 *
 * Whole-container serialisation bypasses every per-path check, so it is
 * blocked by default. A class implementing this interface opts itself in
 * without opening the global allow_container_serialization flag — the
 * decision lives next to the code that knows whether it is safe.
 */
interface SafeForTemplateSerialization {}
```

- [ ] **Step 4: Implement container detection**

Replace `sanitize()` in `src/Core/Security/OutputSanitizer.php`:

```php
    public function sanitize(mixed $raw, TokenInterface $token): SanitizedValue
    {
        if ($this->validator === null || $this->validator->getMode() === SecurityValidator::MODE_OFF) {
            return new SanitizedValue($raw, $this->render($raw));
        }

        if ($this->isContainer($raw) && ! $this->maySerialiseWhole($raw)) {
            $this->validator->reportViolation(
                $this->validator->getMode() === SecurityValidator::MODE_ENFORCE
                    ? 'mustache-resolver: container blocked by security policy'
                    : 'mustache-resolver: container would be blocked in enforce mode',
                ['path' => $token->getRaw(), 'type' => get_debug_type($raw)],
            );

            if ($this->validator->getMode() === SecurityValidator::MODE_ENFORCE) {
                return SanitizedValue::blocked();
            }
        }

        // An object treated as atomic must not survive raw into resolvedValues:
        // TranslationResult::toArray() would keep the object, and a later
        // serialization could expose the structure this barrier just decided
        // not to expose. Text and recorded value become the same string.
        if (is_object($raw) && ! $raw instanceof \UnitEnum && ! $this->isContainer($raw)) {
            $text = $this->render($raw);

            return new SanitizedValue($text, $text);
        }

        return new SanitizedValue($raw, $this->render($raw));
    }

    /**
     * Structural classification, in strict precedence order.
     *
     * Order matters and is the whole point. PHP 8 makes every class with
     * __toString() implicitly Stringable, so Eloquent models and collections
     * are Stringable — testing that first would classify the two types this
     * barrier exists for as harmless scalars.
     *
     *   array                     → container
     *   non-object                → scalar
     *   UnitEnum                  → scalar
     *   Arrayable or Traversable  → container
     *   Stringable                → atomic renderable
     *   any remaining object      → container
     *
     * JsonSerializable is deliberately absent: it is a conversion mechanism,
     * not evidence of being a container. Carbon implements it and is an
     * atomic value.
     */
    private function isContainer(mixed $value): bool
    {
        if (is_array($value)) {
            return true;
        }

        if (! is_object($value)) {
            return false;
        }

        if ($value instanceof \UnitEnum) {
            return false;
        }

        if ($value instanceof \Illuminate\Contracts\Support\Arrayable || $value instanceof \Traversable) {
            return true;
        }

        if ($value instanceof \Stringable) {
            return false;
        }

        return true;
    }

    /**
     * Whether whole serialisation is permitted for this value.
     *
     * The two escapes are not interchangeable: the global flag exists for
     * plain arrays and collections, which cannot implement an interface. A
     * model has a class, so it opts in through that class or not at all —
     * letting the flag authorise models would collapse the distinction.
     */
    private function maySerialiseWhole(mixed $value): bool
    {
        if ($value instanceof \AichaDigital\MustacheResolver\Contracts\SafeForTemplateSerialization) {
            return true;
        }

        if (! $this->allowContainerSerialization) {
            return false;
        }

        return ! (class_exists(\Illuminate\Database\Eloquent\Model::class)
            && $value instanceof \Illuminate\Database\Eloquent\Model);
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Core/Security/OutputSanitizerTest.php`
Expected: PASS (8 tests)

- [ ] **Step 6: Commit**

```bash
git add src/Contracts/SafeForTemplateSerialization.php src/Core/Security/OutputSanitizer.php tests/Unit/Core/Security/OutputSanitizerTest.php
git commit -m "feat(security): block whole-container serialization by default

Serialising a whole model, array or collection bypasses every per-path
check and dumps whatever is inside, and it is rarely intentional: it is
usually an incomplete path. Two escapes, because they cover different
cases — a global flag for plain arrays, which cannot implement anything,
and a marker interface for classes that know they are safe.

Refs AID-733"
```

---

### Task 4: Filter authorised containers recursively

**Files:**
- Modify: `src/Core/Security/OutputSanitizer.php`
- Test: `tests/Unit/Core/Security/OutputSanitizerTest.php`

**Interfaces:**
- Consumes: `SecurityValidator::isAttributeBlacklisted(string): bool` (existing).

- [ ] **Step 1: Write the failing test**

```php
describe('OutputSanitizer → filtering authorised containers', function () {
    it('strips blacklisted keys recursively when the container is allowed', function () {
        $sanitizer = new OutputSanitizer(
            new SecurityValidator(blacklistedAttributes: ['password'], mode: SecurityValidator::MODE_ENFORCE),
            allowContainerSerialization: true,
        );

        $result = $sanitizer->sanitize([
            'name' => 'John',
            'password' => 'hunter2',
            'profile' => ['bio' => 'x', 'password' => 'nested'],
        ], sanitizerTestToken('User.data'));

        expect($result->value)->toBe([
            'name' => 'John',
            'profile' => ['bio' => 'x'],
        ]);
    });

    it('matches blacklisted keys case-insensitively', function () {
        $sanitizer = new OutputSanitizer(
            new SecurityValidator(blacklistedAttributes: ['password'], mode: SecurityValidator::MODE_ENFORCE),
            allowContainerSerialization: true,
        );

        $result = $sanitizer->sanitize(['Password' => 'x', 'ok' => 1], sanitizerTestToken('User.data'));

        expect($result->value)->toBe(['ok' => 1]);
    });

    it('does not filter in report mode, but reports', function () {
        $reported = [];
        $validator = new SecurityValidator(
            blacklistedAttributes: ['password'],
            mode: SecurityValidator::MODE_REPORT,
            reporter: function (string $m, array $c) use (&$reported): void { $reported[] = $c; },
        );

        $result = (new OutputSanitizer($validator, allowContainerSerialization: true))
            ->sanitize(['password' => 'x'], sanitizerTestToken('User.data'));

        expect($result->value)->toBe(['password' => 'x']);
        expect($reported)->not->toBeEmpty();
    });

    it('filters a model nested inside an authorised container', function () {
        $sanitizer = new OutputSanitizer(
            new SecurityValidator(blacklistedAttributes: ['password'], mode: SecurityValidator::MODE_ENFORCE),
            allowContainerSerialization: true,
        );

        $nested = new \Workbench\App\Models\User(['name' => 'John', 'password' => 'hunter2']);

        $result = $sanitizer->sanitize(['owner' => $nested], sanitizerTestToken('User.data'));

        expect(json_encode($result->value))->not->toContain('hunter2');
    });

    it('renders an authorised container as JSON, not as an imploded list', function () {
        $sanitizer = new OutputSanitizer(
            new SecurityValidator(blacklistedAttributes: ['password'], mode: SecurityValidator::MODE_ENFORCE),
            allowContainerSerialization: true,
        );

        $result = $sanitizer->sanitize(['name' => 'Engineering', 'password' => 'x'], sanitizerTestToken('User.department'));

        expect($result->text)->toBe('{"name":"Engineering"}');
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Core/Security/OutputSanitizerTest.php --filter=filtering`
Expected: FAIL — blacklisted keys still present

- [ ] **Step 3: Implement**

In `sanitize()`, replace the final `return` with:

```php
        if ($this->isContainer($raw)) {
            return $this->sanitiseContainer($raw, $token);
        }

        return new SanitizedValue($raw, $this->render($raw));
```

And add:

```php
    /**
     * Filter an authorised container and build both representations.
     */
    private function sanitiseContainer(mixed $raw, TokenInterface $token): SanitizedValue
    {
        $array = $this->toArray($raw);
        $filtered = $this->stripBlacklisted($array, $found);

        if ($found !== []) {
            $this->validator?->reportViolation(
                $this->validator->getMode() === SecurityValidator::MODE_ENFORCE
                    ? 'mustache-resolver: blacklisted attributes stripped from serialized container'
                    : 'mustache-resolver: container contains blacklisted attribute(s), they would be filtered in enforce mode',
                ['path' => $token->getRaw(), 'blacklisted_attributes' => array_values(array_unique($found))],
            );
        }

        if ($this->validator?->getMode() === SecurityValidator::MODE_REPORT) {
            return new SanitizedValue($raw, $this->renderContainer($this->stripBlacklisted($array), $raw));
        }

        return new SanitizedValue($filtered, $this->renderContainer($filtered, $raw));
    }

    /**
     * Render a container as JSON, preserving Eloquent's casting escape.
     *
     * This is what modelToString() did in 2.1 and it is contract: a consumer
     * serialising a whole relation gets JSON, not an imploded list. The escape
     * flag is protected, so it is read through a closure bound to the model.
     *
     * @param  array<mixed>  $filtered
     */
    private function renderContainer(array $filtered, mixed $original): string
    {
        $json = json_encode($filtered) ?: '';

        if (! is_object($original) || ! property_exists($original, 'escapeWhenCastingToString')) {
            return $json;
        }

        $reader = function (): bool {
            // @phpstan-ignore-next-line — bound to the model to read its protected flag
            return (bool) $this->escapeWhenCastingToString;
        };

        return $reader->call($original) ? e($json) : $json;
    }

    /**
     * @return array<mixed>
     */
    private function toArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof \Illuminate\Contracts\Support\Arrayable) {
            return $value->toArray();
        }

        return get_object_vars($value);
    }

    /**
     * Remove blacklisted keys recursively, collecting what was removed.
     *
     * @param  array<mixed>  $data
     * @param  array<int, string>|null  $found
     * @return array<mixed>
     */
    private function stripBlacklisted(array $data, ?array &$found = null): array
    {
        $found ??= [];

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->validator?->isAttributeBlacklisted($key)) {
                $found[] = $key;
                unset($data[$key]);

                continue;
            }

            if ($value instanceof \Illuminate\Contracts\Support\Arrayable) {
                $value = $value->toArray();
            }

            if (is_array($value)) {
                $data[$key] = $this->stripBlacklisted($value, $found);
            }
        }

        return $data;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Core/Security/OutputSanitizerTest.php`
Expected: PASS (11 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Core/Security/OutputSanitizer.php tests/Unit/Core/Security/OutputSanitizerTest.php
git commit -m "feat(security): filter blacklisted keys from authorised containers

An allowed container is still not a free pass: blacklisted keys are
stripped recursively, and what was stripped is reported. Report mode
leaves the value intact so it stays usable as a pre-upgrade measuring
tool.

Refs AID-733"
```

---

### Task 5: `max_depth` over serialised content, pruning by branch

**Files:**
- Modify: `src/Core/Security/OutputSanitizer.php`
- Test: `tests/Unit/Core/Security/OutputSanitizerTest.php`

**Interfaces:**
- Consumes: `SecurityValidator::isDepthExceeded(int): bool` (existing).

Depth counts from the context root: token depth plus depth inside the serialised content. The limit measures how deep data is exposed, wherever that depth comes from. Exceeding it prunes the offending **branch**, not the whole container.

- [ ] **Step 1: Write the failing test**

```php
describe('OutputSanitizer → depth', function () {
    it('prunes the branch that exceeds max_depth, keeping the rest', function () {
        $sanitizer = new OutputSanitizer(
            new SecurityValidator(maxDepth: 3, mode: SecurityValidator::MODE_ENFORCE),
            allowContainerSerialization: true,
        );

        // token 'User.data' is depth 2; 'shallow' lands at 3, 'a.b' at 4
        $result = $sanitizer->sanitize([
            'shallow' => 'kept',
            'a' => ['b' => 'too deep'],
        ], sanitizerTestToken('User.data'));

        expect($result->value)->toBe(['shallow' => 'kept', 'a' => []]);
    });

    it('reports the depth violation', function () {
        $reported = [];
        $validator = new SecurityValidator(
            maxDepth: 3,
            mode: SecurityValidator::MODE_ENFORCE,
            reporter: function (string $m, array $c) use (&$reported): void { $reported[] = $m; },
        );

        (new OutputSanitizer($validator, allowContainerSerialization: true))
            ->sanitize(['a' => ['b' => 'deep']], sanitizerTestToken('User.data'));

        expect($reported)->toContain('mustache-resolver: serialized content pruned at max_depth');
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Core/Security/OutputSanitizerTest.php --filter=depth`
Expected: FAIL — nothing is pruned

- [ ] **Step 3: Implement**

Change `sanitiseContainer()` to seed the depth from the token, and thread it through:

```php
        $baseDepth = count($token->getPath());
        $filtered = $this->stripBlacklisted($array, $found, $baseDepth, $pruned);

        if ($pruned) {
            $this->validator?->reportViolation(
                'mustache-resolver: serialized content pruned at max_depth',
                // 'max_depth' must carry the configured threshold, matching the
                // convention SecurityValidator::allowsPath() already established.
                // The token's own depth goes under its own name.
                ['path' => $token->getRaw(), 'token_depth' => $baseDepth, 'max_depth' => $this->validator->getMaxDepth()],
            );
        }
```

And extend `stripBlacklisted()`:

```php
    private function stripBlacklisted(array $data, ?array &$found = null, int $depth = 0, bool &$pruned = false): array
    {
        $found ??= [];

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->validator?->isAttributeBlacklisted($key)) {
                $found[] = $key;
                unset($data[$key]);

                continue;
            }

            if ($value instanceof \Illuminate\Contracts\Support\Arrayable) {
                $value = $value->toArray();
            }

            if (is_array($value)) {
                if ($this->validator?->isDepthExceeded($depth + 2)) {
                    $data[$key] = [];
                    $pruned = true;

                    continue;
                }

                $data[$key] = $this->stripBlacklisted($value, $found, $depth + 1, $pruned);
            }
        }

        return $data;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Core/Security/OutputSanitizerTest.php`
Expected: PASS (13 tests)

If the depth arithmetic is off by one against the test expectations, fix the **implementation** to match the documented rule (token depth + content depth, pruning the branch that exceeds), not the test.

- [ ] **Step 5: Commit**

```bash
git add src/Core/Security/OutputSanitizer.php tests/Unit/Core/Security/OutputSanitizerTest.php
git commit -m "feat(security): apply max_depth to serialized content

Depth counts from the context root, so a shallow token serialising a
deep structure is limited the same way a deep token is. Exceeding it
prunes the offending branch rather than discarding the container, which
keeps the rest of the value useful.

Refs AID-733"
```

---

### Task 6: Special types and cycles

**Files:**
- Modify: `src/Core/Security/OutputSanitizer.php`
- Test: `tests/Unit/Core/Security/OutputSanitizerTest.php`

Per spec §11.5: `Stringable` and enums resolve as scalars; `Arrayable`, `JsonSerializable` and `Traversable` are containers; cycles are cut and reported; a `toArray()` that throws or returns a non-array is treated as a blocked container, never as an empty success.

- [ ] **Step 1: Write the failing test**

```php
describe('OutputSanitizer → special types', function () {
    it('treats a backed enum as a scalar', function () {
        $result = (new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE)))
            ->sanitize(\AichaDigital\MustacheResolver\Core\Token\TokenType::MODEL, sanitizerTestToken('User.kind'));

        expect($result->blocked)->toBeFalse();
        expect($result->text)->toBe('model');
    });

    it('treats a Traversable as a container', function () {
        $it = new \ArrayIterator(['a' => 1]);

        $result = (new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE)))
            ->sanitize($it, sanitizerTestToken('User.items'));

        expect($result->blocked)->toBeTrue();
    });

    it('blocks a container whose toArray() throws, instead of returning empty', function () {
        $bad = new class implements \Illuminate\Contracts\Support\Arrayable
        {
            public function toArray(): array
            {
                throw new \RuntimeException('boom');
            }
        };

        $result = (new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE), allowContainerSerialization: true))
            ->sanitize($bad, sanitizerTestToken('User.broken'));

        expect($result->blocked)->toBeTrue();
    });

    it('cuts a cyclic structure instead of recursing forever', function () {
        $a = new \stdClass;
        $a->name = 'a';
        $a->self = $a;

        $result = (new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE), allowContainerSerialization: true))
            ->sanitize($a, sanitizerTestToken('User.node'));

        expect($result->blocked)->toBeFalse();
    })->throwsNoExceptions();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Core/Security/OutputSanitizerTest.php --filter='special types'`
Expected: FAIL — the enum case fails first, and the cyclic case exhausts memory or recursion

- [ ] **Step 3: Implement**

In `isContainer()`, `\UnitEnum` and `\Stringable` are already excluded. Add `Traversable` handling in `toArray()`, wrap the conversion, and track visited objects:

```php
    /**
     * @return array<mixed>|null  null when the value cannot be converted safely
     */
    private function toArray(mixed $value): ?array
    {
        try {
            if (is_array($value)) {
                return $value;
            }

            if ($value instanceof \Illuminate\Contracts\Support\Arrayable) {
                $converted = $value->toArray();

                return is_array($converted) ? $converted : null;
            }

            if ($value instanceof \JsonSerializable) {
                $converted = $value->jsonSerialize();

                return is_array($converted) ? $converted : null;
            }

            if ($value instanceof \Traversable) {
                return iterator_to_array($value);
            }

            return get_object_vars($value);
        } catch (\Throwable) {
            return null;
        }
    }
```

In `sanitiseContainer()`, guard the null:

```php
        $array = $this->toArray($raw);

        if ($array === null) {
            $this->validator?->reportViolation(
                'mustache-resolver: container could not be converted safely, blocked',
                ['path' => $token->getRaw(), 'type' => get_debug_type($raw)],
            );

            return SanitizedValue::blocked();
        }
```

For cycles, thread an `SplObjectStorage` through the recursion. Replace `stripBlacklisted()` entirely with this final form — it supersedes the versions from Tasks 4 and 5:

```php
    /**
     * Remove blacklisted keys recursively, prune past max_depth, cut cycles.
     *
     * @param  array<mixed>  $data
     * @param  array<int, string>|null  $found
     * @param  \SplObjectStorage<object, mixed>|null  $seen
     * @return array<mixed>
     */
    private function stripBlacklisted(
        array $data,
        ?array &$found = null,
        int $depth = 0,
        bool &$pruned = false,
        ?\SplObjectStorage $seen = null,
    ): array {
        $found ??= [];
        $seen ??= new \SplObjectStorage;

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->validator?->isAttributeBlacklisted($key)) {
                $found[] = $key;
                unset($data[$key]);

                continue;
            }

            if (is_object($value)) {
                // Same classification helper as the root value. Using a different
                // rule here is how a model nested inside an authorised array would
                // escape filtering by virtue of being Stringable.
                if (! $this->isContainer($value)) {
                    $data[$key] = $value instanceof \UnitEnum ? $value : $this->render($value);

                    continue;
                }

                if ($seen->contains($value)) {
                    $data[$key] = null;
                    $pruned = true;

                    continue;
                }

                $seen->attach($value);
                $value = $this->toArray($value) ?? [];
            }

            if (is_array($value)) {
                if ($this->validator?->isDepthExceeded($depth + 2)) {
                    $data[$key] = [];
                    $pruned = true;

                    continue;
                }

                $data[$key] = $this->stripBlacklisted($value, $found, $depth + 1, $pruned, $seen);
            }
        }

        return $data;
    }
```

Update the two call sites in `sanitiseContainer()` to match the new signature — they pass `$found`, `$baseDepth` and `$pruned` positionally and let `$seen` default.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Core/Security/OutputSanitizerTest.php`
Expected: PASS (17 tests)

**Correction applied 2026-07-31, after this task's review.** Two defects came from the snippets above, not from the implementation:

**Cycle detection must use an ancestor stack, not an accumulating set.** A flat `SplObjectStorage` cannot tell a cycle from a legitimately repeated sibling: `['a' => $company, 'b' => $company]` had its second occurrence silently nulled. Attach on entering a branch and detach on leaving — via `try`/`finally`, so the stack unwinds even when conversion throws. `A → A` and `A → B → A` are cut; sibling repeats serialise twice, because they are real data.

**Do not share one `$pruned` boolean across causes.** Separate `cycle_cut`, `depth_pruned` and `conversion_failed`, or the logs attribute an alteration to the wrong reason.

**A failed `toArray()` is a policy event and follows the mode:** `off` does not even attempt conversion; `report` warns that enforce would block, and returns the original object with its identity, type and legacy rendering intact — never a partially converted array; `enforce` warns and returns `SanitizedValue::blocked()`.

Note on scope: `max_depth` bounds depth, not width or expansion count. An object repeated thousands of times can expand thousands of times, and that is not the cycle detector's problem to solve — if a size defence is ever needed it belongs elsewhere, as an explicit node or byte budget.

- [ ] **Step 5: Commit**

```bash
git add src/Core/Security/OutputSanitizer.php tests/Unit/Core/Security/OutputSanitizerTest.php
git commit -m "feat(security): define container behaviour for special types

Stringable and enums are scalars; Arrayable, JsonSerializable and
Traversable are containers. A toArray() that throws or returns a
non-array is blocked rather than treated as an empty success, which
would have been a silent leak of nothing where something was expected.
Cycles are cut with an object seen-set.

Refs AID-733"
```

---

### Task 7: Validate the token path centrally

**Files:**
- Modify: `src/Core/Security/OutputSanitizer.php`
- Test: `tests/Unit/Core/Security/OutputSanitizerTest.php`

This is barrier 2 catching what barrier 1 missed: a resolver that navigates on its own and returns a **scalar** clears the accessor entirely. Re-validating the token path here covers the package's own resolvers, which is the failure that actually happened twice (`CollectionResolver` via `getRaw()`, whole-model `toJson()`).

Only tokens with `hasSecurityPath()` are checked, so function and variable tokens are untouched.

- [ ] **Step 1: Write the failing test**

```php
describe('OutputSanitizer → token path validation', function () {
    it('blocks a scalar returned under a blacklisted path', function () {
        $sanitizer = new OutputSanitizer(
            new SecurityValidator(blacklistedAttributes: ['password'], mode: SecurityValidator::MODE_ENFORCE)
        );

        $result = $sanitizer->sanitize('hunter2', sanitizerTestToken('User.password'));

        expect($result->blocked)->toBeTrue();
        expect($result->text)->toBe('');
    });

    it('blocks a blacklisted segment behind a relation', function () {
        $sanitizer = new OutputSanitizer(
            new SecurityValidator(blacklistedAttributes: ['password'], mode: SecurityValidator::MODE_ENFORCE)
        );

        expect($sanitizer->sanitize('x', sanitizerTestToken('User.owner.password'))->blocked)->toBeTrue();
    });

    it('does not apply attribute rules to tokens without a security path', function () {
        $sanitizer = new OutputSanitizer(
            new SecurityValidator(blacklistedAttributes: ['password'], mode: SecurityValidator::MODE_ENFORCE)
        );

        $result = $sanitizer->sanitize('ok', sanitizerTestToken('password()', TokenType::FUNCTION));

        expect($result->blocked)->toBeFalse();
    });

    it('does not block in report mode', function () {
        $sanitizer = new OutputSanitizer(
            new SecurityValidator(blacklistedAttributes: ['password'], mode: SecurityValidator::MODE_REPORT)
        );

        expect($sanitizer->sanitize('hunter2', sanitizerTestToken('User.password'))->blocked)->toBeFalse();
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Core/Security/OutputSanitizerTest.php --filter='token path'`
Expected: FAIL — scalars pass through unchecked

- [ ] **Step 3: Implement**

At the top of `sanitize()`, after the OFF short-circuit:

```php
        if ($token->getType()->hasSecurityPath() && ! $this->validator->allowsPath($token->getRaw())) {
            return SanitizedValue::blocked();
        }
```

`allowsPath()` already reports and already returns `true` in report mode, so no duplicate reporting logic is needed here. The accessor may have called it too; deduplication is the reporter's job and is already wired in the service provider.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Core/Security/OutputSanitizerTest.php`
Expected: PASS (21 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Core/Security/OutputSanitizer.php tests/Unit/Core/Security/OutputSanitizerTest.php
git commit -m "feat(security): re-validate the token path at the output barrier

A resolver that navigates on its own and returns a scalar clears the
accessor entirely, and a string carries no mark of origin. Checking the
token path here covers the package's own resolvers, which is the failure
that already happened twice. Tokens without a security path are left
alone so function and variable names are never treated as attributes.

Refs AID-733"
```

---

### Correction before Task 8 (2026-07-31)

Wiring the sanitizer revealed three defects that only surface once both barriers run together. Tasks 8 and 9 are **merged** as a consequence: cabling, deleting the old path and updating the v2 contracts are one atomic change, and splitting them would require an intermediate commit with PHPStan and 13 tests deliberately red — not a reviewable unit.

**1. The two barriers spoke different path languages.** The accessor receives what the resolver hands it, which is not the token's raw text. Verified per resolver:

- `MODEL`, `RELATION`, `COLLECTION` → `implode('.', getFieldPath())` (`ModelResolver:41`, `RelationResolver:41`, `CollectionResolver:47`)
- `TABLE` → `implode('.', getPath())`, prefix included (`TableResolver:41-43`)
- `DYNAMIC` → no static canonical path: it reads an indicator and then accesses the name obtained at runtime (`DynamicFieldResolver:52,66`). The accessor already validates both accesses; evaluating `getFieldPath()` at the output barrier would check a different path and manufacture false positives.

Left unfixed this is not merely noisy logs: `max_depth` was evaluated at two different depths, so `{{User.name}}` with `max_depth: 1` passed barrier 1 and was wrongly blocked by barrier 2.

The mapping is encapsulated as `TokenInterface::getSecurityPath(): ?string` — not by scattering `getFieldPath()` through the sanitizer. `null` means "no static path to validate", and the sanitizer skips path validation for it. **`DYNAMIC` is removed from `hasSecurityPath()`** for this static validation.

Deduplication keys on the canonical path. The raw token text stays available for presentation, and is used in the sanitizer's own events — containers, cycles, conversion failures — where it does not compete with barrier 1. Note the practical limit: the accessor usually reports first and does not know the full token, so the sanitizer's second warning is the one deduplicated away.

**2. `report` mode altered the rendered text.** `sanitiseContainer()` returned the raw value with a text computed from the *filtered* array. The value survived intact but the template output did not — which destroys the whole point of report mode as a pre-upgrade measuring tool, and is what produced most of the legacy test failures. Report must return the original text.

**3. The cycle ancestor stack did not include the root.** The root is an ancestor too, so `A → B → A` was cut one hop later than it should be. Correct before closing Phase 1.

### Task 8+9 (merged): Wire it into `MustacheResolver`, before the fork

**Files:**
- Modify: `src/Core/MustacheResolver.php:41-79`
- Test: `tests/Feature/Security/OutputSanitizationTest.php`

This is the task that closes the third bypass: today `$resolvedValues` receives the **raw** value while only the text is sanitised.

**Interfaces:**
- Consumes: `OutputSanitizer::sanitize(mixed, TokenInterface): SanitizedValue`
- Produces: `MustacheResolver::__construct(..., ?OutputSanitizer $sanitizer = null)` — a fifth optional parameter, after `$securityValidator`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Security/OutputSanitizationTest.php`:

```php
<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Laravel\Facades\Mustache;
use Workbench\App\Models\Department;
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
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/Security/OutputSanitizationTest.php`
Expected: FAIL — `getResolvedValues()` still contains the raw email

- [ ] **Step 3: Implement**

Change the constructor in `src/Core/MustacheResolver.php`. The sanitizer is optional to pass but **always present** — a nullable property would force a fallback path duplicating the rendering logic Task 9 deletes:

```php
    private readonly OutputSanitizer $sanitizer;

    public function __construct(
        private readonly ParserInterface $parser,
        private readonly ResolutionPipeline $pipeline,
        private readonly CacheInterface $cache,
        private readonly ?SecurityValidator $securityValidator = null,
        ?OutputSanitizer $sanitizer = null,
    ) {
        $this->sanitizer = $sanitizer ?? new OutputSanitizer($securityValidator);
    }
```

This preserves current behaviour: with a null validator the sanitizer passes everything through, and with the provider's validator (today `report`) it reports without blocking. It does **not** anticipate §11.2 — that is about changing the default policy, which stays in Phase 2. Here it only guarantees the single point always exists.

Replace lines 60-64 of the token loop:

```php
                $raw = $this->pipeline->resolve($token, $context);
                $sanitized = $this->sanitizer->sanitize($raw, $token);
                $translated = str_replace($token->getFull(), $sanitized->text, $translated);
                $resolvedValues[$token->getRaw()] = $sanitized->value;
```

Then register it in `src/Laravel/MustacheServiceProvider.php`, inside `registerResolver()`:

```php
            return new MustacheResolver(
                $app->make(ParserInterface::class),
                $app->make(ResolutionPipeline::class),
                $app->make(CacheInterface::class),
                $app->make(SecurityValidator::class),
                new OutputSanitizer(
                    $app->make(SecurityValidator::class),
                    (bool) ($app['config']['mustache-resolver']['security']['allow_container_serialization'] ?? false),
                ),
            );
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/Security/OutputSanitizationTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Run the whole suite**

Run: `vendor/bin/pest`
Expected: the v2 contract test `it('leaves containers unfiltered in enforce mode (deferred to v3)')` now FAILS. That is correct and expected — Task 9 inverts it. Every other test must pass.

- [ ] **Step 6: Commit**

```bash
git add src/Core/MustacheResolver.php src/Laravel/MustacheServiceProvider.php tests/Feature/Security/OutputSanitizationTest.php
git commit -m "fix(security): sanitize before the value forks

The rendered text was sanitised but resolvedValues kept the raw value,
so a consumer logging the result, returning it over an API or handing it
to a job leaked regardless of the template output. Sanitising one step
earlier closes that and any future bypass introduced by a new
destination.

Refs AID-733"
```

---

### Task 9: Remove the old policy logic from `MustacheResolver`

**Files:**
- Modify: `src/Core/MustacheResolver.php` — delete `reportContainerViolations()`, `findBlacklistedKeys()`, `valueToString()`, `modelToString()`, `stripBlacklistedAttributes()`
- Modify: `tests/Feature/Laravel/SecurityWiringTest.php:303` — invert the v2 contract test

- [ ] **Step 1: Invert the v2 contract test**

Replace `it('leaves containers unfiltered in enforce mode (deferred to v3)')` with:

```php
    it('blocks containers in enforce mode', function () {
        config()->set('mustache-resolver.security.mode', 'enforce');

        $result = Mustache::translate('Dept: {{User.department}}', $this->user);

        expect($result->getTranslated())->toBe('Dept: ');
    });
```

- [ ] **Step 2: Delete the dead methods**

Remove the five private methods listed above from `src/Core/MustacheResolver.php`, and the now-unused imports (`Model`, `Arrayable`, `SecurityValidator` if no longer referenced). The `?SecurityValidator` constructor parameter stays — `createContext()` still passes it to the accessors, which is barrier 1.

- [ ] **Step 3: Run the whole suite**

Run: `vendor/bin/pest`
Expected: PASS. Any failure here is a real behaviour change to investigate, not a test to adjust.

- [ ] **Step 4: Run the full quality gate**

Run: `composer quality`
Expected: Pint clean, PHPStan level 8 clean, coverage ≥ 90%.

If coverage dropped below 90, add the missing unit tests to `OutputSanitizerTest` — do not lower the threshold.

- [ ] **Step 5: Commit**

```bash
git add src/Core/MustacheResolver.php tests/Feature/Laravel/SecurityWiringTest.php
git commit -m "refactor(security): move policy out of the resolver

The resolver no longer knows about the blacklist, serialization or
container reporting: it resolves and delegates. Around 130 lines that
mixed translation with policy now live in one auditable place.

The v2 contract test asserting containers stay unfiltered is inverted:
that behaviour was explicitly deferred to this major.

Refs AID-733"
```

---

### Task 10: The hostile-resolver guardrail

This is the test that justifies the whole architecture: it proves barrier 2 covers code that does not exist yet. A custom resolver registered by the consumer is trusted code and outside the threat model (spec §11.3) — but a **package** resolver navigating on its own is exactly what failed twice, and this is what would have caught it.

**Files:**
- Test: `tests/Feature/Security/HostileResolverTest.php`

- [ ] **Step 1: Write the test**

Create `tests/Feature/Security/HostileResolverTest.php`:

```php
<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Contracts\ContextInterface;
use AichaDigital\MustacheResolver\Contracts\ResolverInterface;
use AichaDigital\MustacheResolver\Contracts\TokenInterface;
use AichaDigital\MustacheResolver\Laravel\Facades\Mustache;
use Workbench\App\Models\User;

/**
 * Stands in for a package resolver that navigates on its own instead of
 * going through the accessor — the shape of the CollectionResolver and
 * whole-model serialization bypasses.
 */
final class HostileResolver implements ResolverInterface
{
    public function supports(TokenInterface $token, ContextInterface $context): bool
    {
        return str_contains($token->getRaw(), 'password');
    }

    public function resolve(TokenInterface $token, ContextInterface $context): mixed
    {
        return 'hunter2';   // straight past the accessor
    }

    public function priority(): int
    {
        return 1;           // ahead of the built-ins
    }

    public function name(): string
    {
        return 'hostile';
    }
}

it('intercepts a resolver that navigates past the accessor', function () {
    config()->set('mustache-resolver.security.mode', 'enforce');
    config()->set('mustache-resolver.security.blacklisted_attributes', ['password']);
    config()->set('mustache-resolver.resolvers', [HostileResolver::class]);

    $user = User::factory()->create(['name' => 'John Doe']);

    $result = Mustache::translate('Secret: {{User.password}}', $user);

    expect($result->getTranslated())->toBe('Secret: ');
    expect($result->getResolvedValues()['User.password'] ?? null)->toBeNull();
});
```

- [ ] **Step 2: Run it**

Run: `vendor/bin/pest tests/Feature/Security/HostileResolverTest.php`
Expected: PASS — Task 7 already put the defence in place.

- [ ] **Step 3: Prove it is not theatre**

Temporarily comment out the token-path check added in Task 7 and re-run. The test must FAIL. Restore the check and confirm `git diff src/` is empty.

This sensitivity check is mandatory: a guardrail that passes against the unprotected code proves nothing.

- [ ] **Step 4: Run the full gate**

Run: `composer quality`
Expected: all green.

- [ ] **Step 5: Commit and push the branch**

```bash
git add tests/Feature/Security/HostileResolverTest.php
git commit -m "test(security): prove the output barrier catches a bypassing resolver

Verified by reverting the token-path check and watching it go red. A
guardrail that passes against the unprotected code proves nothing.

Refs AID-733"
git push -u origin 3.x
```

---

## Stopping point

Phase 1 ends here. The branch `3.x` is green, the architecture is in place, and **no default has changed** — a consumer installing from this branch behaves exactly as 2.1.0 unless they set `enforce` themselves.

Before planning Phase 2, review:

- Did the serialisation contract survive contact with the implementation, or did any case in §11.5 turn out ambiguous or wrong?
- Is the depth arithmetic (token depth + content depth) the rule that actually makes sense once written?
- Did coverage hold at 90% without contorting the tests?

Answers to those feed the Phase 2 plan (config and public surface) and, if any of them moved, an amendment to the spec first.
