# Changelog

All notable changes to `aichadigital/laravel-mustache-resolver` will be documented in this file.

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
