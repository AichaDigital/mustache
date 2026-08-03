# Changelog

All notable changes to `aichadigital/laravel-mustache-resolver` will be documented in this file.

## [Unreleased] — 3.0.0

Security major. The protections v2.1 introduced in `report` mode become the enforced default, and every fail-open path found while hardening them is closed. **Read [UPGRADE-3.md](UPGRADE-3.md) before updating** — it ships in the dist and covers each break with before/after, the observation procedure on 2.x, verification and rollback.

### Changed — breaking

- **`security.mode` defaults to `enforce`.** Fresh installs and unpublished configs block instead of reporting. **A config block published under v2 keeps the mode it declares** — it is reconciled at runtime (absent v3 keys are filled with the v3 defaults, also under a cached config) and a boot warning lists the applied defaults, the **effective** mode and the exact edit required. Until such a consumer sets `mode: 'enforce'` themselves, the v3 protections only report.
- **`null` stopped meaning "no policy" — it means THE DEFAULT POLICY (§11.2).** `MustacheResolver`, `OutputSanitizer`, the compound resolver and `ResolutionContext::fromModel()` built without a validator now apply enforce with the default blacklists. `OutputSanitizer::getValidator()` is non-nullable. Opting out requires an explicit `mode: 'off'` validator.
- **`new SecurityValidator()` carries the real default policy:** the constructor defaults for `blacklistedAttributes`/`blacklistedPatterns` are the `DEFAULT_*` constants, not `[]` — a bare instance no longer reports `enforce` while blocking nothing. An explicit `[]` remains the deliberate opt-out.
- **Whole-container serialization is blocked by default in enforce.** A token resolving to a `Model`, `Collection`, array, `Arrayable`, `Traversable` or `JsonSerializable` renders as empty and records `null`. Escapes: `security.allow_container_serialization` (plain arrays/Collections only, never Models) and the new `SafeForTemplateSerialization` interface (the only way a Model opts in). Authorised containers are still filtered recursively, depth-pruned (`max_depth` counts token depth + content depth) and cycle-cut.
- **`getResolvedValues()` changes type and content in enforce:** blocked values are recorded as `null` (key present); an authorised Model/Collection arrives as its **filtered array**, not the live object; atomic objects (Carbon) and wildcard-projection object elements are recorded as **strings**. In `report` mode identity and types are preserved — report never alters.
- **`Stringable` is no longer trusted by shape.** Atomicity is granted by interface — `DateTimeInterface`, enums, `SafeForTemplateSerialization` — and every other object takes the container gate, at the root and nested. An opaque `__toString()` could previously serialise an object past the entire container policy.
- **A scalar list is a container unless it is a wildcard projection.** Only a COLLECTION token with a `*` segment may claim the projection escape (`{{User.posts.*.title}}`); `{{user.items}}` over `['a','b']` is a container dump.
- **`allowed_models` is renamed to `allowed_root_models`** — FQCN-only (a bare basename never matches) and validates the **root** model only. A legacy key is carried over at runtime with a rename warning.
- **`allowed_tables` is removed.** `TableResolver` performs no database access; the key restricted nothing.
- **`ConditionRegistry::getInstance()`/`resetInstance()` are removed.** Instantiate or resolve from the container (which cycles correctly under Octane). No internal caller existed.
- **Parse-time ceilings (enforce only):** `security.limits.max_template_length` (in **bytes**) and `security.limits.max_tokens` throw `SecurityException` at parse time — for the main path and compound `USE` expressions alike (both read the same configured parser). `.env` values are normalized: numeric strings cast, `null`/empty/`false` = unlimited, uninterpretable values fall back to the default with a warning; an explicit `0` is literal zero, not a disable.
- **Consumer-supplied accessors and contexts are decorated (barrier 1):** a `DataAccessorInterface` or `ContextInterface` handed straight to `translate()` or the compound resolver is wrapped by the resolver's policy unless mode is `off` — blocked `get()` → `null`, blocked `has()` → `false`, `getRaw()` keeps type and identity, and an accessor's own policy is combined, never weakened. `ObjectAccessor` is security-aware.
- **`ResolutionPipeline::resolve()` is declared `@internal` (raw):** it returns unsanitized resolver output by construction; the supported entry points are `translate()` and the compound resolver.

### Added

- `OutputSanitizer` (barrier 2): the single point every resolved value passes before forking into rendered text and `resolvedValues` — closes the class of bypass where a resolver (present or future) skips policy, and re-validates the token's own path so a resolver returning a bare scalar is still checked.
- `SafeForTemplateSerialization` contract: per-class opt-in to whole serialization.
- `security.blacklisted_patterns`: glob-style, case-insensitive patterns applied to every path segment (defaults: `*_token`, `*_secret`, `*_key`, `*password*`, `*_hash`, `otp`, `pin`, `cvv`). Expect occasional false positives (`public_key`, `sort_key`) — visible in the log, removable by config; `[]` disables deliberately.
- `SecurityValidator::defaultPolicy()` — the canonical v3 default policy, reporter injectable.
- The Laravel provider binds `CompoundResolver` with the same validator, container flag and configured parser as the main path — one effective policy across both public entry points.
- Boot warning for incomplete published configs: absent keys, the values applied, the effective mode, the report-only caveat and the edit to make.

### Changed

- **Report mode is louder by design:** the container warning fires for every container resolution regardless of blacklist content (it announces the upcoming block itself), and atomic-object normalisation is reported as the only advance notice of a type change. Reports stay deduplicated per request/job cycle and capped.
- Security messages and docblocks say **bytes** where `strlen` is what is measured.
- The boot warning reports the mode **in force** (from the bound validator) — a typo'd mode logs `enforce`, not the typo.

## [2.1.0] - 2026-07-23

Non-breaking security release. It wires the previously inert security configuration
into the real resolution path in `report` mode by default, so consumers can see in
their logs exactly what `enforce` mode will block before v3.0.0 flips the default.

### Added

- `security.mode` config option: `off` | `report` (default) | `enforce`
- The `MustacheServiceProvider` now builds a `SecurityValidator` from
  `config('mustache-resolver.security')` and injects it into `MustacheResolver`
- Security checks now apply to the array data path (`ArrayAccessor`), not only to Eloquent models
- `max_depth` is now enforced on dot-notation paths (previously a dead control)
- `SecurityValidator::allowsPath()`: blacklist checked on **every** path segment,
  not just the first one — `blacklisted_attributes` is no longer evadable through
  relations (`{{User.relationship.password}}`)
- The blacklist match is now case-insensitive (`{{User.Password}}` no longer
  bypasses a `password` entry on models with attribute mutators)
- `SecurityAwareAccessorInterface`: resolvers that navigate data without the
  accessor's `get()` consult it, closing the collection-token bypass
  (`{{User.posts.*.author.password}}` was unchecked even in `enforce` mode)
- `SecurityValidator` reporter hook: violations are sent to `Log::warning()`
  with the path and offending segments — in `report` mode as a preview of what
  `enforce` would block, in `enforce` mode as an audit trail of blocked attempts
- Report dedupe in the service provider: repeated violations of the same path
  log once per process, so a crafted template cannot flood the logs
- Whole-model serialization (`{{User.department}}`) no longer leaks blacklisted
  attributes: `enforce` strips them from the serialized output (recursively),
  `report` logs a warning and keeps current behavior. The warning/strip only
  fires when the model actually contains blacklisted attributes, and Eloquent's
  `escapeWhenCastingToString()` behavior is preserved
- Container observability in `report` mode: resolved arrays and collections that
  contain blacklisted attributes log a warning that a future major version will
  filter them. The output is intentionally unchanged in 2.1
- Report dedupe state is scoped to the request/job cycle (`terminating` +
  queue `looping`), so Octane and queue workers no longer accumulate paths or
  suppress reports across requests
- An invalid `security.mode` fails closed (`enforce`) with a warning
- Tests for the Laravel security wiring, the per-segment blacklist, depth limits
  and the report/enforce modes

### Fixed

- Passing an Eloquent model directly to `Mustache::translate()` now resolves
  `{{Model.field}}` correctly (previously the model was wrapped in an
  `ArrayAccessor` under a `model` key and nothing resolved). The documented
  basic usage in the README now works as written.

### Deprecated / notes

- `allowed_tables` remains unimplemented (reserved); it will take effect or be
  removed in v3.0.0
- Passing a raw Eloquent model to `translate()` now expects the documented
  `{{Model.field}}` syntax; the previous implicit `model` key wrap no longer
  applies. Consumers passing arrays — including `['model' => $model]` — are
  unaffected
- Filtering whole arrays/collections (not just their paths) is deferred to
  v3.0.0; 2.1 only reports them in `report` mode
- v3.0.0 will change the default `security.mode` to `enforce`. Run 2.1.x in
  `report` mode and review the logged warnings before upgrading.

## [2.0.0] - 2026-04-08

### Breaking

- Dropped Laravel 10.x and 11.x support
- Removed multi-version dev dependency constraints (larastan 2.x, collision 7.x, testbench 8.x/9.x, pest 2.x)

### Added

- Laravel 13.x support

### Changed

- Updated CI matrix to PHP 8.2/8.3/8.4 with Laravel 12/13

## [1.2.0] - 2026-04-08

### Changed

- Applied Pint formatting across codebase (strict types, spacing, imports)
- Reorganized project documentation structure

### Note

- **This is the last release supporting Laravel 10.x and 11.x.**
- Future v2.x releases will require Laravel 12+.
- The v1.x branch will only receive critical security fixes.

## [1.1.1] - 2025-12-12

### Fixed

- Fixed `TokenClassifier` parsing of multiple arguments in TEMPORAL tokens
  - `TEMPORAL:isNthWeekday('saturday', 1)` now correctly parses to `['saturday', 1]`
  - `TEMPORAL:isLastWeekday('friday')` now correctly parses to `['friday']`
  - Previously, all arguments were incorrectly captured as a single string

### Changed

- Updated tests to use real `TokenClassifier` instead of manually created tokens for `isNthWeekday` and `isLastWeekday`
- Added comprehensive integration tests for temporal argument parsing

## [1.1.0] - 2025-12-10

### Added

- Temporal Expressions Module for complex time-based conditions
- New dependency: `dragonmantank/cron-expression` for CRON support
- Core Temporal Classes:

  - `TimeRange`: Evaluate time ranges (08:00-18:00), supports overnight ranges (22:00-06:00)
  - `CronWrapper`: Wrapper for CRON expressions with Nth weekday support
  - `ExpressionParser`: Parse temporal expressions with logical operators
  - `TemporalExpression`: Main evaluator for complex expressions
  - `ConditionRegistry`: Central registry for conditions

- Temporal Conditions:

  - `AlwaysCondition`: Always true
  - `NeverCondition`: Always false
  - `WeekdayCondition`: Monday to Friday
  - `WeekendCondition`: Saturday and Sunday
  - `TimeRangeCondition`: Time ranges within a day
  - `CronCondition`: CRON expression evaluation
  - `NthWeekdayCondition`: Nth occurrence of weekday (first Saturday, etc.)
  - `LastWeekdayCondition`: Last occurrence of weekday in month
  - `CustomCondition`: User-defined conditions

- TemporalResolver for mustache integration:

  - `{{TEMPORAL:isDue('weekday && 08:00-18:00')}}` - Boolean evaluation
  - `{{TEMPORAL:nextRun('cron:0 8 * * *')}}` - Next CRON run date
  - `{{NOW}}`, `{{NOW:format('Y-m-d')}}`, `{{NOW:timestamp}}` - Current datetime
  - `{{TODAY}}`, `{{TODAY:startOfDay}}`, `{{TODAY:endOfDay}}` - Today's date

- Temporal expression syntax:

  - Keywords: `always`, `never`, `weekday`, `weekend`
  - Time ranges: `HH:MM-HH:MM`
  - CRON: `cron:0 8 * * 1-5`
  - Nth weekday: `nth:saturday:1`, `nth:saturday:1,2`
  - Last weekday: `last:friday`
  - Operators: `&&` (AND), `||` (OR), `!` (NOT), `()` (grouping)

- Custom evaluator registration for domain-specific conditions (holiday, day/night, etc.)
- New TokenType: `TEMPORAL` for temporal expressions
- 526 tests with 755 assertions (+164 tests, +213 assertions)

## [1.0.0] - 2024-12-09

### Added

- Initial release
- Token system (TokenType enum, Token, TokenClassifier, TokenCollection)
- Parser (MustacheParser) for extracting mustache expressions
- Pipeline (ResolutionPipeline, PipelineBuilder) for resolution chain
- Context (ResolutionContext) for resolution state
- Result (TranslationResult) for resolution output
- 7 Built-in Resolvers:
  - NullCoalesceResolver: `{{User.name ?? 'default'}}`
  - VariableResolver: `{{$myVariable}}`
  - DynamicFieldResolver: `{{Device.$config.field}}`
  - CollectionResolver: `{{User.posts.*.title}}`, first, last
  - RelationResolver: `{{User.department.name}}`
  - ModelResolver: `{{User.name}}`
  - TableResolver: `{{users.email}}`
- Compound Expressions with USE clause syntax
- 26 Built-in Formatters (DateTime, Numeric, String)
- Safe MathExpressionEvaluator (no eval())
- Laravel integration (ServiceProvider, Facade)
- 362 tests with 542 assertions
- PHPStan level max compliance
