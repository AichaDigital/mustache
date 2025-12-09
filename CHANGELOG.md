# Changelog

All notable changes to `aichadigital/laravel-mustache-resolver` will be documented in this file.

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
