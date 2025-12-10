# Laravel Mustache Resolver

[![Latest Version on Packagist](https://img.shields.io/packagist/v/aichadigital/laravel-mustache-resolver.svg?style=flat-square)](https://packagist.org/packages/aichadigital/laravel-mustache-resolver)
[![Tests](https://img.shields.io/github/actions/workflow/status/AichaDigital/mustache/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/AichaDigital/mustache/actions?query=workflow%3ATests+branch%3Amain)
[![Coverage](https://codecov.io/gh/AichaDigital/mustache/graph/badge.svg)](https://codecov.io/gh/AichaDigital/mustache)
[![Code Style](https://img.shields.io/github/actions/workflow/status/AichaDigital/mustache/code-style.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/AichaDigital/mustache/actions?query=workflow%3A"Code+Style"+branch%3Amain)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/AichaDigital/mustache/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/AichaDigital/mustache/actions?query=workflow%3APHPStan+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/aichadigital/laravel-mustache-resolver.svg?style=flat-square)](https://packagist.org/packages/aichadigital/laravel-mustache-resolver)
[![PHP Version](https://img.shields.io/packagist/php-v/aichadigital/laravel-mustache-resolver.svg?style=flat-square)](https://packagist.org/packages/aichadigital/laravel-mustache-resolver)
[![Laravel Version](https://img.shields.io/badge/laravel-10.x%20|%2011.x%20|%2012.x-red?style=flat-square)](https://packagist.org/packages/aichadigital/laravel-mustache-resolver)
[![License](https://img.shields.io/packagist/l/aichadigital/laravel-mustache-resolver.svg?style=flat-square)](https://packagist.org/packages/aichadigital/laravel-mustache-resolver)

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

## Requirements

- PHP 8.2+
- Laravel 10.x, 11.x, or 12.x (optional)

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

$resolver = new MustacheResolver(
    new MustacheParser(),
    PipelineBuilder::create()->build(),
    new NullCache()
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
        'max_depth' => 10,
        'blacklisted_attributes' => ['password', 'remember_token'],
    ],
];
```

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
