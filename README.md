# Laravel Mustache Resolver
<!-- AI-BADGES:START profile=essential -->
[![Latest Version](https://img.shields.io/packagist/v/aichadigital/laravel-mustache-resolver.svg?style=flat-square)](https://packagist.org/packages/aichadigital/laravel-mustache-resolver)
[![Total Downloads](https://img.shields.io/packagist/dt/aichadigital/laravel-mustache-resolver.svg?style=flat-square)](https://packagist.org/packages/aichadigital/laravel-mustache-resolver)
[![Pipeline](https://gitlab.castris.com/aichadigital/mustache/badges/main/pipeline.svg?style=flat-square)](https://gitlab.castris.com/aichadigital/mustache/-/pipelines)
[![Coverage](https://gitlab.castris.com/aichadigital/mustache/badges/main/coverage.svg?style=flat-square)](https://gitlab.castris.com/aichadigital/mustache/-/pipelines)
[![PHPStan level 8](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg?style=flat-square&logo=php)](https://phpstan.org/)
[![PHP Version](https://img.shields.io/packagist/php-v/aichadigital/laravel-mustache-resolver.svg?style=flat-square&logo=php)](https://packagist.org/packages/aichadigital/laravel-mustache-resolver)
[![Laravel Version](https://img.shields.io/badge/Laravel-12.x%20%7C%2013.x-red.svg?style=flat-square&logo=laravel)](https://laravel.com)
[![License](https://img.shields.io/packagist/l/aichadigital/laravel-mustache-resolver.svg?style=flat-square)](https://packagist.org/packages/aichadigital/laravel-mustache-resolver)
<!-- AI-BADGES:END -->

> Development happens on
> [gitlab.castris.com](https://gitlab.castris.com/aichadigital/mustache).
> The GitHub repository is a read-only distribution mirror: issues and pull
> requests opened there are not seen.

A framework-agnostic, fully testable, SOLID-compliant mustache template resolver for PHP applications with first-class Laravel integration.

## Features

- **Simple field resolution**: `{{User.name}}`
- **Relation navigation**: `{{User.department.manager.name}}`
- **Dynamic fields**: `{{Device.$manufacturer.field_parameter}}`
- **Collection access**: `{{User.posts.0.title}}`, `{{User.addresses.*.city}}`
- **Built-in functions**: `{{now()}}`, `{{format(User.date, 'Y-m-d')}}`
- **Null coalescing**: `{{User.nickname ?? 'Anonymous'}}`
- **Framework-agnostic core** with optional Laravel integration
- **100% testable** without database

## Compatibility

| Package version | PHP            | Laravel        | Security default | Status              |
|-----------------|----------------|----------------|------------------|---------------------|
| 3.x             | 8.2, 8.3, 8.4 | 12.x, 13.x    | `enforce`        | Active development  |
| 2.x             | 8.2, 8.3, 8.4 | 12.x, 13.x    | `report`         | New vulnerabilities (high, or any severity on template data exposure) + Laravel compatibility until **2027-02-03** (see [UPGRADE-3.md](UPGRADE-3.md)) |
| 1.x             | 8.2, 8.3, 8.4 | 10.x, 11.x, 12.x | none          | End of life         |

**Upgrading from 2.x?** Read [UPGRADE-3.md](UPGRADE-3.md) first — v3 enforces by default, blocks whole-container serialization, and changes the types in `getResolvedValues()`. A config published under v2 keeps its own `mode` (you must flip it to `enforce` yourself); absent v3 keys are filled with safe defaults at runtime, with a boot warning naming them.

## Requirements

- PHP 8.2+
- Laravel 12.x or 13.x (optional)

## Installation

```bash
composer require aichadigital/laravel-mustache-resolver
```

### Laravel

The package auto-discovers the service provider. Optionally publish the config:

```bash
php artisan vendor:publish --tag="mustache-resolver-config"
```

### Standalone (without Laravel)

```php
use AichaDigital\MustacheResolver\Core\MustacheResolver;
use AichaDigital\MustacheResolver\Core\Parser\MustacheParser;
use AichaDigital\MustacheResolver\Core\Pipeline\PipelineBuilder;
use AichaDigital\MustacheResolver\Cache\NullCache;

// Secure by default (v3): building without a validator applies the DEFAULT
// POLICY — enforce mode, the default blacklists and patterns, containers
// blocked, parse ceilings on. It carries no reporter, so it blocks silently.
$resolver = new MustacheResolver(
    new MustacheParser(),
    PipelineBuilder::create()->build(),
    new NullCache()
);

// To see what the policy does, pass a validator with a reporter:
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;

$reported = SecurityValidator::defaultPolicy(
    reporter: fn (string $message, array $context) => error_log($message)
);
$resolver = new MustacheResolver(
    new MustacheParser(),
    PipelineBuilder::create()->build(),
    new NullCache(),
    $reported,
);

// Opting out is explicit — null no longer means "no policy":
$off = new SecurityValidator(mode: SecurityValidator::MODE_OFF);
$unprotected = new MustacheResolver(
    new MustacheParser(),
    PipelineBuilder::create()->build(),
    new NullCache(),
    $off,
);
```

## Usage

### Basic Usage with Laravel Facade

```php
use AichaDigital\MustacheResolver\Laravel\Facades\Mustache;

$template = "Hello, {{User.name}}! Your email is {{User.email}}.";
$user = User::find(1);

$result = Mustache::translate($template, $user);

if ($result->isSuccess()) {
    echo $result->getTranslated();
    // "Hello, John! Your email is john@example.com."
}
```

### Relation Navigation

```php
$template = "Manager: {{User.department.manager.name}}";
$result = Mustache::translate($template, $user);
```

### Collection Access

```php
// Access by index
$template = "First post: {{User.posts.0.title}}";

// Access first/last
$template = "Latest: {{User.posts.last.title}}";

// Wildcard (returns array)
$template = "All cities: {{User.addresses.*.city}}";
```

### With Variables

```php
$template = "Report for {{$period}}: {{User.name}}";
$result = Mustache::translate($template, $user, ['period' => '2024-Q1']);
```

### Batch Processing

```php
$templates = [
    "Name: {{User.name}}",
    "Email: {{User.email}}",
    "Department: {{User.department.name}}",
];

$results = Mustache::translateBatch($templates, $user);
```

### Non-strict Mode

```php
// Missing fields return empty string instead of failing
$result = Mustache::translate($template, $user, [], strict: false);
```

## Configuration

```php
// config/mustache-resolver.php
return [
    'strict' => true,           // Throw on unresolvable mustaches
    'keep_unresolved' => false, // Keep mustaches if not resolved (non-strict)

    'cache' => [
        'enabled' => false,
        'ttl' => 3600,
    ],

    'security' => [
        'mode' => 'enforce',    // 'off' | 'report' | 'enforce' (v3 default: enforce)
        'allowed_root_models' => [],          // FQCN-only, root model only; [] = all
        'max_depth' => 10,
        'allow_container_serialization' => false, // arrays/Collections only, never Models
        'blacklisted_attributes' => ['password', 'remember_token', 'api_token', 'secret'],
        'blacklisted_patterns' => ['*_token', '*_secret', '*_key', '*password*', '*_hash', 'otp', 'pin', 'cvv'],
        'limits' => [
            'max_template_length' => 100000,  // bytes; null/empty = unlimited
            'max_tokens' => 1000,
        ],
    ],
];
```

## Security

**v3 enforces by default.** Every resolved value passes two barriers: the accessors
validate each path before data is touched (barrier 1 — it also prevents the lazy
relation query), and an `OutputSanitizer` downstream of every resolver decides what
reaches both the rendered text and `getResolvedValues()` (barrier 2 — a resolver
cannot bypass it). Building any entry point without a validator applies the
**default policy**; opting out requires an explicit `mode: 'off'`.

The `security.mode` setting controls what happens on a violation:

- `enforce` (default): blacklisted paths resolve to empty/null, whole containers
  are blocked unless escaped, over-limit templates throw `SecurityException`,
  disallowed root models throw `ModelNotAllowedException`. Blocked attempts are
  logged as an audit trail.
- `report`: every violation is logged via `Log::warning()` and resolution proceeds
  **unchanged** — output, identity and types stay exactly as with security off.
  Use it as the measuring tool before flipping to `enforce`.
- `off`: no checks are applied, and objects you hand in are returned untouched.

What the policy covers:

- **Paths:** every segment of a dot-notation path is checked against
  `blacklisted_attributes` (exact, case-insensitive) and `blacklisted_patterns`
  (glob, case-insensitive) — a blacklisted attribute is also blocked behind a
  relation (`{{User.relationship.password}}`) and inside collection tokens
  (`{{User.posts.*.author.password}}`). Paths deeper than `max_depth` are rejected.
- **Containers:** a token resolving to a whole `Model`, `Collection`, array or
  `Arrayable`/`Traversable`/`JsonSerializable` is blocked by default. Escapes:
  `allow_container_serialization` (plain arrays/Collections only) or the
  `SafeForTemplateSerialization` interface (the only way a `Model` opts in).
  Authorised containers are still filtered recursively and depth-pruned.
- **Objects:** only `DateTimeInterface` (Carbon), enums and classes marked
  `SafeForTemplateSerialization` count as atomic scalars. Any other object —
  `Stringable` included — takes the container gate: an opaque `__toString()`
  is not trust.
- **Ceilings:** `security.limits` bounds template length (bytes) and token count
  at parse time, in `enforce` only, for the main path and compound `USE`
  expressions alike.
- **Your own accessors/contexts:** a `DataAccessorInterface` or
  `ContextInterface` handed straight to `translate()` is decorated with the
  resolver's policy (never weakening the accessor's own), because two token
  types (`??` defaults and `$dynamic` fields) carry no static path for
  barrier 2 to re-check.

Consumer-registered resolvers are **trusted code**, outside the threat model —
the policy defends against data exposure through templates, not against code you
installed. Repeated violations of the same path are logged once per request/job
cycle. An invalid `security.mode` value fails closed (`enforce`) with a warning,
and the boot warning always states the **effective** mode.

Upgrading from 2.x: the full break-by-break list, the observation procedure and
the rollback path live in [UPGRADE-3.md](UPGRADE-3.md).


## Custom Resolvers

```php
use AichaDigital\MustacheResolver\Contracts\ResolverInterface;

class CustomResolver implements ResolverInterface
{
    public function supports(TokenInterface $token, ContextInterface $context): bool
    {
        return $token->getPrefix() === 'Custom';
    }

    public function resolve(TokenInterface $token, ContextInterface $context): mixed
    {
        // Your resolution logic
    }

    public function priority(): int
    {
        return 150; // Higher than built-in resolvers
    }

    public function name(): string
    {
        return 'custom';
    }
}
```

Register in config:

```php
'resolvers' => [
    \App\Resolvers\CustomResolver::class,
],
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [AichaDigital](https://github.com/AichaDigital)
- [All Contributors](../../contributors)

## License

The AGPL-3.0-or-later License. Please see [License File](LICENSE) for more information.
