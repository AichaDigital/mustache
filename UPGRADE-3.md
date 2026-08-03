# Upgrading to 3.0 from 2.x

v3.0.0 is a **security release**. Its single theme: the protections that v2.1 introduced in `report` mode become the **enforced default**, and every fail-open path found while hardening them is closed. Nothing here is a new feature you can ignore — if your templates resolve data, v3 changes what reaches the output.

This guide ships inside the package (repo root) because the boot warning and the published config point at it. Read it top to bottom once; the sections are ordered by decision, not by API.

## Choose your path

| You want | Constraint | Notes |
|---|---|---|
| Stay on 2.x for now | `"aichadigital/laravel-mustache-resolver": "^2.1"` | Supported for **new** vulnerabilities and Laravel compatibility until the 2.x EOL date (see "2.x support policy" below). The four v2 security limitations documented in the v3 spec remain open on 2.x permanently — closing them is what v3 is. |
| Move to 3.x | `"aichadigital/laravel-mustache-resolver": "^3.0"` | Follow this guide. Budget one working session: the mechanical part is minutes, the part that needs your judgment is reviewing what `report` mode logged. |

If you are on `dev-main`: pin to a caret constraint **before** updating anything. `dev-main` follows the branch and ignores tags entirely — it is how consumers get majors they never asked for.

## Recommended sequence (observe first, flip second)

1. **On 2.1, before touching the constraint:** set `MUSTACHE_SECURITY_MODE=report` and run your real traffic for a few days. Every line `report` logs (`mustache-resolver: … would be blocked in enforce mode`) is something v3 will block or filter. Fix the templates or whitelist deliberately — an empty log means an uneventful upgrade.
2. Update the constraint to `^3.0`, `composer update aichadigital/laravel-mustache-resolver`.
3. Re-publish the config (`php artisan vendor:publish --tag="mustache-resolver-config" --force` after backing up your customizations, or hand-add the new keys listed below) and clear any config cache (`php artisan config:clear`).
4. Decide your mode **explicitly**: `enforce` (recommended), `report` (still measuring), or `off` (you own the consequences). Fresh installs and unpublished configs run `enforce`.
5. Run the post-upgrade verification at the end of this guide.

## How v3 treats your published v2 config

`mergeConfigFrom()` merges shallow: a published `security` block **replaces** the package's block entirely, so a v2-published config would neither gain the new keys nor change your mode. v3 reconciles this at runtime, on every boot (it also works under a cached config, where `mergeConfigFrom()` never runs):

- **Keys absent from your published config are filled with the v3 defaults** for that runtime, and a `Log::warning` lists exactly which keys, which values were applied, the **effective mode**, and the edit to make. The warning repeats every boot until you add the keys — it is a nag by design.
- **Your `mode` is never overwritten.** A `mode: 'report'` published under v2 stays `report` — there is no way to distinguish an inherited value from a chosen one, and flipping it silently during `composer update` would be the worst possible breaking change. Consequence: **until you set `mode: 'enforce'` yourself, the v3 protections only report, they do not block.**
- An explicit empty array (`'blacklisted_patterns' => []`) is a deliberate opt-out and is never refilled. Absence and emptiness are different statements.
- A legacy `allowed_models` key is carried over to `allowed_root_models` for that runtime, with a warning asking you to rename it.

## The breaking changes, one by one

### 1. `security.mode` defaults to `enforce`

- **v2.1:** default `report` — violations logged, nothing blocked.
- **v3:** default `enforce` — blacklisted paths resolve to empty/null, containers are blocked, over-limit templates throw. Your published mode wins (see above).

### 2. Building without a validator now means the default policy (standalone users, read this)

- **v2.1:** `new MustacheResolver($parser, $pipeline, $cache)` — no validator, no checks. The README's own standalone example was unprotected.
- **v3:** a missing/null `SecurityValidator` or `OutputSanitizer` means **the default policy**: `enforce`, default blacklists and patterns, containers blocked, parse ceilings on. This applies to `MustacheResolver`, `OutputSanitizer`, the compound resolver, and `ResolutionContext::fromModel()`.
- Opting out is explicit and still supported:

```php
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;

$off = new SecurityValidator(mode: SecurityValidator::MODE_OFF);
$resolver = new MustacheResolver($parser, $pipeline, $cache, $off);
```

- The default standalone policy carries **no reporter**, so it blocks silently. Pass a reporter closure to `SecurityValidator` if you want to see what it does (the Laravel provider wires `Log::warning` for you, deduplicated per request/job cycle).

### 3. `new SecurityValidator()` carries the real default lists

- **v2.1/early 3.x:** a bare `new SecurityValidator` said `enforce` but held **empty** blacklists — it enforced nothing.
- **v3:** the constructor defaults ARE the default policy (`DEFAULT_BLACKLISTED_ATTRIBUTES`, `DEFAULT_BLACKLISTED_PATTERNS`). Passing an explicit `[]` remains the deliberate empty-policy opt-out.

### 4. Containers are blocked by default

A token resolving to a whole structure — an Eloquent `Model`, a `Collection`, an array, any `Arrayable`/`Traversable`/`JsonSerializable` — is **blocked in enforce mode**: empty string in the rendered text, `null` under the token's key in `getResolvedValues()`.

Two escapes, deliberately different:

- **`security.allow_container_serialization` (bool, default false):** authorises plain **arrays and Collections** to serialize whole. It never authorises an Eloquent `Model`.
- **`SafeForTemplateSerialization` (interface):** a class you mark opts itself in — this is the only way a `Model` serializes whole.

An authorised container is still **filtered**: blacklisted keys stripped recursively, `max_depth` prunes deep branches (counted from the context root: token depth + content depth), cycles cut. The rendered text keeps the v2 string form (model JSON with `escapeWhenCastingToString` honoured, arrays imploded).

### 5. `getResolvedValues()` changes type and content

| Case | v2.1 | v3 (enforce) |
|---|---|---|
| Blocked path/container | raw value | key present, value `null`, text `''` |
| Authorised Model/Collection | the live object | the **filtered array** |
| Carbon / other atomic object | the object | its **string** form |
| Wildcard projection with object elements | raw objects | rendered strings |

In `report` mode values keep their v2 identity and type — report observes, it never alters.

### 6. `Stringable` is no longer trusted by shape

- **v2.1/early 3.x:** anything with `__toString()` counted as an atomic scalar — an object whose `__toString()` returns `json_encode(get_object_vars($this))` walked past the entire container policy.
- **v3:** atomicity is granted **by interface**: `DateTimeInterface` (Carbon and friends), enums, and classes marked `SafeForTemplateSerialization` stay atomic. Every other object — `Stringable` or not — takes the container gate, at the root and nested inside authorised containers.
- **Action:** if you render value objects (Money, Uuid wrappers, fluent strings) through templates, either mark their class with `SafeForTemplateSerialization` or cast them to string before they enter the data context.

### 7. Scalar lists are containers unless they are wildcard projections

- **v2.1/early 3.x:** any list of scalars passed through (the projection escape keyed on shape).
- **v3:** only a wildcard COLLECTION token (`{{User.posts.*.title}}`) may claim the projection escape. `{{user.items}}` over `['a', 'b']` is a container dump and is blocked in enforce (escape hatches above apply).

### 8. Blacklist patterns

New key `security.blacklisted_patterns`, glob-style (`Str::is`), case-insensitive, applied to **every** path segment. Defaults: `*_token`, `*_secret`, `*_key`, `*password*`, `*_hash`, `otp`, `pin`, `cvv`. Expect occasional false positives (`public_key`, `sort_key`): they show in the log and you remove them by overriding the list. `[]` disables patterns deliberately.

### 9. `allowed_models` → `allowed_root_models`

FQCN-only (`App\Models\User::class` — a bare `'User'` never matches), validates the **root** model only. A non-blacklisted attribute of a *nested* model resolves even when its class is not listed — nested exposure is the container policy's job, not this list's. Empty list = all models allowed (opt-in hardening). With a non-empty list and a non-model root (array data), a warning tells you the class whitelist cannot apply there.

### 10. `allowed_tables` is removed

It restricted nothing: `TableResolver` performs no database access. Delete the key from your config; the reconciler drops it.

### 11. `ConditionRegistry::getInstance()` / `resetInstance()` are removed

Instantiate it (`new ConditionRegistry`) or resolve it from the container, which cycles correctly under Octane. There was no internal caller — if you never called it, nothing changes.

### 12. Parse-time ceilings (enforce only)

`security.limits.max_template_length` (in **bytes**, `strlen`) and `security.limits.max_tokens`. Exceeding either throws `SecurityException` at parse time — including for compound `USE` expressions, which use the same configured parser. `null`/empty/`false` = unlimited; numeric strings from `.env` are cast; an explicit `0` is literal zero (rejects everything — it is not a disable). In `report` and `off` no ceiling applies.

### 13. Accessors and contexts you hand in are decorated

A `DataAccessorInterface` or `ContextInterface` you pass straight to `translate()` (or to the compound resolver) is wrapped by the resolver's validator unless mode is `off`: blocked `get()` returns `null`, blocked `has()` returns `false`, `getRaw()` keeps type and identity. If your accessor carries its own policy, both apply — wrapping never weakens yours. In `off` mode your object is returned untouched.

### 14. `ResolutionPipeline::resolve()` is declared internal

It returns the raw, unsanitized resolver output by construction. The supported entry points are `MustacheResolver::translate()` and the compound resolver. If you were resolving tokens through the pipeline directly, you are past the security policy — that code is yours to vouch for.

## Report-mode noise you should expect

- The container warning in `report` fires for every container resolution **whether or not** it contains blacklisted attributes — it is announcing the future block itself, not just the filtering.
- Atomic-object normalisation (Carbon → string) is reported in `report` too: it is the only advance notice that upgrading to `enforce` changes a value's **type**, not just its presence.
- Reports are deduplicated per request/job cycle by path, and capped, so a crafted template cannot flood the log.

## Post-upgrade verification

```bash
php artisan config:clear
php artisan tinker
```

```php
// 1. The mode you think you have is the mode in force:
app(\AichaDigital\MustacheResolver\Core\Security\SecurityValidator::class)->getMode(); // 'enforce'

// 2. The default policy blocks:
Mustache::translate('x{{User.password}}x', ['password' => 's3cret'], strict: false)->getTranslated(); // "xx"

// 3. Your legitimate templates still resolve (run a real one):
Mustache::translate('{{User.name}}', $user)->getTranslated();
```

Then watch the log for `mustache-resolver:` warnings for a day. Silence + correct output = done. If the boot warning about missing keys appears, make the edit it names — it will not stop nagging otherwise.

## Rollback

Composer-level rollback is safe: v3 touches no storage and writes nothing. Restore the previous constraint (`^2.1`), `composer update aichadigital/laravel-mustache-resolver`, restore your v2 config file if you replaced it, `php artisan config:clear`. Template output returns to v2 behaviour immediately.

## Known risks

- **The pattern list can false-positive** on legitimate fields (`public_key`, `sort_key`). Symptom: an empty value where you expected one, plus a log line naming the segment. Fix: override `blacklisted_patterns` without that pattern.
- **A consumer that publishes config but leaves `mode: 'report'` is NOT protected** — only warned. The boot warning says so explicitly; believe it.
- **Type changes in `getResolvedValues()`** can break downstream code that assumed live objects (a `Carbon`, a `Model`). The report-mode observation window exists to surface exactly this before you flip.
- **`strict: true` (default) + enforce**: a blocked path makes the whole translation fail loudly rather than render empty. That is the point — but audit your strict-mode call sites before flipping production.

## 2.x support policy

2.x receives patches for **new** vulnerabilities (ones not documented as v2 limitations in the v3 spec) and for Laravel compatibility, for six months from the v3.0.0 release.

- **Absolute 2.x EOL date:** _to be stamped at tag time_.
- **Minimum severity that triggers a 2.x patch:** _to be stamped at tag time_.

The four v2 limitations that v3 exists to close are permanent on 2.x and will not be patched there.
