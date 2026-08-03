# Laravel Mustache Resolver - Project Rules

## Project Overview

Framework-agnostic, fully testable, SOLID-compliant mustache template resolver for PHP applications with first-class Laravel integration.

## Package Identity

- **Name**: aichadigital/laravel-mustache-resolver
- **Namespace**: AichaDigital\MustacheResolver
- **License**: AGPL-3.0-or-later
- **PHP**: ^8.2
- **Laravel**: ^12.0 || ^13.0

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

## Lecciones aprendidas

### 2026-08-03 — v3.0.0 publicada: estado del paquete y lo que queda vivo
**Contexto:** cierre de AID-733 — tag `v3.0.0` sobre `b19aff1` (candidato aprobado `1aca3db` + estampado documental), `main` ES la línea 3 desde entonces, rama `2.x` de mantenimiento cortada en `765bd63` (EOL 2027-02-03; parches por severidad alta o cualquier severidad si el vector es exposición de datos vía plantillas). Gobierno: contrato `products/mustache.md` (revisión 2) + `products/sitelight.md` + registro `approvals/majors/mustache-3.md` en el workspace central. Gate de conformidad de sitelight: delta vacío, superficie mustache verde bajo enforce.
**Reglas operativas vigentes:**
- El comportamiento por defecto es `enforce` con la default policy también en standalone (§11.2: null = default policy, nunca «sin política»). Cualquier test o doc nuevo que asuma «report por defecto» está describiendo v2.
- `UPGRADE-3.md` viaja en el dist y lo referencian el boot warning y el config publicado — NO añadirlo a export-ignore jamás.
- Ledger de menores pendientes para 3.0.x/3.1 (detalle en el cierre de AID-733): canonicalizar el modo en el constructor de `SecurityValidator` (radio standalone), gemelos off que faltan en `PublicSurfaceBypassTest` (colección end-to-end, semántica AND, `get()` directo del contexto), hoist del wrap en `UseVariableResolver::resolveAll()`, techo agregado de tokens del compound (P2 advisory del gate Codex).
- **Conflicto de normas SIN adjudicar:** este fichero prohíbe el trailer `Co-Authored-By: Claude` y la norma global exige atribución real por commit. En `3.x`→`main` conviven `ee0c284` (con trailer) y el resto (sin él). Hasta que el propietario adjudique, los commits de ESTE repo siguen la regla local (sin trailer).
- Ningún proyecto consumidor lanza agentes que trabajen sobre mustache (orden del propietario, 2026-08-03); el gate de conformidad se corre desde aquí con worktree desechable del consumidor.

### 2026-07-23 — Config documentada sin consumidor no es seguridad (AID-632)
**Contexto:** paquete `aichadigital/laravel-mustache-resolver`, auditoría de seguridad sobre v2.0.0
**Problema:** `SecurityValidator` correcto y bien testeado, pero nada lo invocaba: el ServiceProvider no leía `config('mustache-resolver.security')`, la blacklist solo miraba el primer segmento del path y `max_depth` era un control muerto.
**Causa raíz:** «config + contrato» se dio por «modo cableado» sin verificar el camino real de ejecución; además la rama exacta del fallo (`createContext` con un Model) estaba sin cobertura, por eso nunca saltó.
**Solución:** v2.1.0 — validator construido e inyectado por el provider, blacklist por segmento case-insensitive, depth aplicado, y dos bypasses más encontrados en pre-landing (CollectionResolver vía `getRaw()`, serialización de modelos enteros vía `toJson()`).
**Regla derivada:** toda config de seguridad debe tener un test que la ejercite END-TO-END a través del ServiceProvider, no solo unitario del validador. Y la revisión adversarial pre-merge es obligatoria en trabajo de seguridad: encontró 2 críticos que la auditoría manual no vio.

### 2026-07-23 — Cambios breaking de seguridad en paquete público: puente report → ventana → major (enmendada 2026-07-30: la ventana NO es un gate)
**Contexto:** v2.1.0 del paquete, consumidor activo (sitelight) con constraint `dev-main`
**Problema:** aplicar enforce de golpe rompía consumidores sin aviso; un `composer update` descuidado con `dev-main` traga cualquier cambio de `main` independientemente de los tags.
**Causa raíz:** `dev-main` sigue la rama, no las versiones: el bump major no protege a quien lo usa. Y diseñar el enforce sin datos reales de uso habría congelado decisiones a ciegas.
**Solución:** minor non-breaking con `security.mode: off|report|enforce` (report por defecto, vía `Log::warning` deduplicado y acotado al ciclo request/job), ventana de observación de 2-4 semanas con el consumidor reportando warnings, y v3 en rama `3.x` diseñada con esos datos. `main` sigue siendo 2.x hasta el tag v3.0.0; entonces se corta rama `2.x` de mantenimiento.
**Regla derivada:** en paquetes públicos, un cambio de seguridad breaking se entrega en dos fases (minor report + major enforce) y el guardarraíl real del consumidor es su constraint (`^X.Y`), nunca el tag. Consumidores en `dev-main` se migran primero.
**ENMIENDA 2026-07-30 (cierre de AID-632, apertura de AID-733) — la «ventana de observación» queda retirada como fase bloqueante.** La formulación de arriba la coloca entre las dos fases y hace depender de ella el diseño del major, lo que dejó AID-632 siete días en curso sin nadie trabajándolo. Es incorrecto por tres razones. **(1)** La ventana y el constraint resuelven el MISMO problema —proteger al consumidor— y el constraint ya lo resuelve solo: en cuanto sitelight pasó a `^2.0`, el tag `v3.0.0` dejó de alcanzarle en cualquier `composer update` y puede permanecer en 2.x indefinidamente. No hay nada que esperar. **(2)** Una muestra de un consumidor con un patrón de uso no es evidencia representativa para diseñar el enforce de un paquete público; el flip del default a `enforce` es además una decisión de producto (fail-closed es lo correcto en seguridad), no un hallazgo empírico. **(3)** La mayoría del scope del major no depende del consumidor en absoluto: son decisiones de diseño del propio paquete (whitelist FQCN-only, fail-open→fail-closed, aislamiento de singletons, config fantasma), atacables desde el primer día. **Regla corregida:** las dos fases (minor report + major enforce) siguen siendo el patrón; lo que las separa es el constraint del consumidor, no un plazo. Los warnings del modo report se recogen EN PARALELO al desarrollo del major, como insumo de la guía de upgrade, nunca como precondición para empezarlo.

### 2026-07-23 — Auditar un paquete exige trazar el camino real del consumidor
**Contexto:** valoración de severidad de los hallazgos de AID-632 para sitelight
**Problema:** los hallazgos 1-4 eran reales en el paquete pero NO estaban activos en el consumidor urgente: sitelight convierte modelos a array antes de llamar al resolver, así que `EloquentAccessor` (y todo el `SecurityValidator`) nunca se instanciaba en su camino.
**Causa raíz:** calificar la explotabilidad mirando solo el paquete, sin leer cómo lo invoca el consumidor real (`SitelightMustacheService::buildDataContext`).
**Solución:** antes de priorizar, leer el servicio consumidor y mapear qué rama del código del paquete ejecuta de verdad. La exposición real de sitelight era otra (su propio `modelToArray()` vuelca todo lo que no esté en `$hidden`), y se le reportó como acción suya, no del paquete.
**Regla derivada:** la severidad de un hallazgo en un paquete se califica contra el camino del consumidor, no contra el caso peor teórico. El fix debe cubrir TODOS los caminos de datos (array y modelo), no solo el documentado.

---

*Last updated: 2026-08-03 (v3.0.0 released)*
