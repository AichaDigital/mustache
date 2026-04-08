# Changelog

All notable changes to `aichadigital/laravel-mustache-resolver` will be documented in this file.

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
