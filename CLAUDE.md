# Laravel Mustache Resolver - Project Rules

## Project Overview

Framework-agnostic, fully testable, SOLID-compliant mustache template resolver for PHP applications with first-class Laravel integration.

## Package Identity

- **Name**: aichadigital/laravel-mustache-resolver
- **Namespace**: AichaDigital\MustacheResolver
- **License**: AGPL-3.0-or-later
- **PHP**: ^8.2
- **Laravel**: ^10.0 || ^11.0 || ^12.0

## Code Standards

### Language

- All code, comments, and documentation in **English**
- Commit messages in **English**

### PHP Standards

- `declare(strict_types=1)` in all PHP files
- PSR-12 coding standard (enforced by Laravel Pint)
- PHPStan level max
- Use `readonly` classes where immutability is required
- Use PHP 8.2+ features (readonly classes, enums, named arguments, etc.)

### Naming Conventions

- **Classes**: PascalCase
- **Methods/Functions**: camelCase
- **Variables**: camelCase
- **Constants**: UPPER_SNAKE_CASE
- **Interfaces**: Suffix with `Interface` (e.g., `ResolverInterface`)
- **Enums**: PascalCase with cases in UPPER_SNAKE_CASE

## Architecture

### Directory Structure

```
src/
├── Contracts/           # Interfaces only
├── Core/                # Framework-agnostic core
│   ├── Parser/
│   ├── Token/
│   ├── Context/
│   ├── Pipeline/
│   └── Result/
├── Resolvers/           # Built-in resolvers
├── Accessors/           # Data access adapters
├── Functions/           # Built-in functions
├── Exceptions/          # Custom exceptions
├── Cache/               # Cache adapters
└── Laravel/             # Laravel-specific integration
    ├── Facades/
    └── Commands/
```

### Design Principles

- **SOLID** principles strictly followed
- **Dependency Inversion**: Core depends on abstractions, not concretions
- **Open/Closed**: Open for extension (new resolvers), closed for modification
- **Single Responsibility**: Each class has ONE job

### No eval() Policy

- **NEVER** use `eval()` or similar dynamic code execution
- All template resolution must be safe string manipulation

## Testing

### Framework

- **Pest** for all tests
- Real models and migrations in `workbench/` directory
- Factory-based testing: Preparation, Execution, Assertion

### Test Structure

```
tests/
├── Unit/                # Unit tests (no database)
│   ├── Core/
│   └── Resolvers/
├── Feature/             # Integration tests (with database)
│   └── Laravel/
└── Architecture/        # Arch tests
```

### Test Requirements

- Every change requires tests
- Tests must NOT mock - use real models with SQLite in-memory
- Workbench models/migrations are NOT published with package
- Run full test suite before commits

### Running Tests

```bash
composer test           # Run all tests
composer test-coverage  # Run with coverage
composer analyse        # Run PHPStan
composer format         # Run Pint
```

## Database Rules

### ENUMS - ABSOLUTE PROHIBITION

- **NEVER** use ENUM type in database columns
- Use PHP Enum class + `unsignedTinyInteger` column

```php
// Correct approach
enum TokenType: int {
    case MODEL = 1;
    case TABLE = 2;
}

// Migration
$table->unsignedTinyInteger('token_type');

// Model cast
protected $casts = ['token_type' => TokenType::class];
```

### Migrations

- **NEVER** run `php artisan migrate` without explicit permission
- **NEVER** edit existing published migrations
- Test migrations go in `workbench/database/migrations/`

## Git Workflow

### Commits

- Follow conventional commits format
- **NEVER** add "Generated with Claude Code" or "Co-Authored-By: Claude"
- Run lint and full test suite before commit

### Pre-commit Checklist

```bash
composer format && composer analyse && composer test
```

## Dependencies

### Core (require)

- illuminate/contracts
- illuminate/support

### Development (require-dev)

- laravel/pint
- larastan/larastan
- orchestra/testbench
- pestphp/pest
- phpstan/*

### Avoid

- spatie/laravel-package-tools (removed to simplify version matrix)

## Configuration

### Config File

- Published to `config/mustache-resolver.php`
- All options documented with examples

### Environment Variables

- Prefix with `MUSTACHE_`
- Document all in config file

## Security

### Input Validation

- Sanitize all template inputs
- Configurable restrictions on paths
- No arbitrary code execution

### Allowed Functions

- Whitelist-based function registry
- No user-defined function calls without explicit registration

---

*Last updated: 2025-12-08*
