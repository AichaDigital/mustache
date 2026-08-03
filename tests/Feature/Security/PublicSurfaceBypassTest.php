<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Accessors\ArrayAccessor;
use AichaDigital\MustacheResolver\Accessors\EloquentAccessor;
use AichaDigital\MustacheResolver\Cache\NullCache;
use AichaDigital\MustacheResolver\Contracts\SecurityAwareAccessorInterface;
use AichaDigital\MustacheResolver\Core\Context\ResolutionContext;
use AichaDigital\MustacheResolver\Core\MustacheResolver;
use AichaDigital\MustacheResolver\Core\Parser\MustacheParser;
use AichaDigital\MustacheResolver\Core\Pipeline\PipelineBuilder;
use AichaDigital\MustacheResolver\Core\Security\OutputSanitizer;
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;
use AichaDigital\MustacheResolver\Core\Security\ValidatingAccessor;
use AichaDigital\MustacheResolver\Core\Security\ValidatingContext;
use AichaDigital\MustacheResolver\Exceptions\ModelNotAllowedException;
use Workbench\App\Models\Department;
use Workbench\App\Models\User;

/*
|--------------------------------------------------------------------------
| Why this file exists
|--------------------------------------------------------------------------
|
| MustacheResolver::createContext() injects the resolver's validator into
| the accessor it builds for a Model, an array or a plain object. The two
| branches that accept an ALREADY-BUILT collaborator -- a ContextInterface
| or a DataAccessorInterface -- had no such injection point and returned
| them untouched, so a consumer's accessor with no policy of its own was
| left unguarded.
|
| Barrier 2 (OutputSanitizer) cannot stand in for those. It re-checks
| Token::getSecurityPath(), which is null for NULL_COALESCE
| (hasSecurityPath() false) and for DYNAMIC (its field name only exists at
| runtime). That is why every block asserted here uses one of those two
| token types: a MODEL token would be caught by barrier 2 regardless and
| would prove nothing about barrier 1.
|
| Every "blocked" assertion is preceded by its OFF-mode twin. Without that
| guard, an empty result could just as easily mean the fixture never
| resolved -- the failure mode that has bitten this package before -- and
| the test would be asserting on a null that security never touched.
*/

/**
 * @param  array<int, string>  $reports  filled with every reported message
 * @param  array<int, string>  $allowedRootModels
 */
function v3SurfaceValidator(string $mode, array &$reports, array $allowedRootModels = []): SecurityValidator
{
    return new SecurityValidator(
        allowedRootModels: $allowedRootModels,
        blacklistedAttributes: SecurityValidator::DEFAULT_BLACKLISTED_ATTRIBUTES,
        blacklistedPatterns: SecurityValidator::DEFAULT_BLACKLISTED_PATTERNS,
        maxDepth: 10,
        mode: $mode,
        reporter: function (string $message, array $context = []) use (&$reports): void {
            $reports[] = $message;
        },
    );
}

function v3SurfaceResolver(SecurityValidator $validator): MustacheResolver
{
    return new MustacheResolver(
        new MustacheParser,
        PipelineBuilder::create()->build(),
        new NullCache,
        $validator,
        new OutputSanitizer($validator),
    );
}

describe('an accessor handed straight to translate()', function () {
    it('resolves a blacklisted NULL_COALESCE path with mode off (fixture guard)', function () {
        $reports = [];
        $resolver = v3SurfaceResolver(v3SurfaceValidator(SecurityValidator::MODE_OFF, $reports));

        $result = $resolver->translate(
            "Value: {{x.password ?? 'fb'}}",
            new ArrayAccessor(['password' => 'SECRET']),
        );

        // Proves the path is genuinely reachable through this fixture, so
        // the enforce assertion below is about the policy and not about a
        // mustache that never resolved to anything.
        expect($result->getTranslated())->toBe('Value: SECRET');
        expect($reports)->toBe([]);
    });

    it('blocks a blacklisted NULL_COALESCE path in enforce', function () {
        $reports = [];
        $resolver = v3SurfaceResolver(v3SurfaceValidator(SecurityValidator::MODE_ENFORCE, $reports));

        $result = $resolver->translate(
            "Value: {{x.password ?? 'fb'}}",
            new ArrayAccessor(['password' => 'SECRET']),
        );

        expect($result->getTranslated())->toBe('Value: fb');
        expect($result->getResolvedValues())->not->toContain('SECRET');
        expect($reports)->not->toBe([]);
    });

    it('resolves but reports a blacklisted NULL_COALESCE path in report', function () {
        $reports = [];
        $resolver = v3SurfaceResolver(v3SurfaceValidator(SecurityValidator::MODE_REPORT, $reports));

        $result = $resolver->translate(
            "Value: {{x.password ?? 'fb'}}",
            new ArrayAccessor(['password' => 'SECRET']),
        );

        expect($result->getTranslated())->toBe('Value: SECRET');
        expect($reports)->toContain('mustache-resolver: path contains blacklisted attribute(s), it would be blocked in enforce mode');
    });

    it('resolves a DYNAMIC runtime field with mode off (fixture guard)', function () {
        $reports = [];
        $resolver = v3SurfaceResolver(v3SurfaceValidator(SecurityValidator::MODE_OFF, $reports));

        // DynamicFieldResolver reads indicator.field -> 'api_token', then
        // asks the accessor for 'api_token'. Neither access has a static
        // path barrier 2 could re-check, which is the whole point.
        $result = $resolver->translate('Value: {{Foo.$indicator.field}}', new ArrayAccessor([
            'indicator' => ['field' => 'api_token'],
            'api_token' => 'DYNAMIC_SECRET',
        ]));

        expect($result->getTranslated())->toBe('Value: DYNAMIC_SECRET');
    });

    it('blocks the DYNAMIC runtime field in enforce', function () {
        $reports = [];
        $resolver = v3SurfaceResolver(v3SurfaceValidator(SecurityValidator::MODE_ENFORCE, $reports));

        $result = $resolver->translate('Value: {{Foo.$indicator.field}}', new ArrayAccessor([
            'indicator' => ['field' => 'api_token'],
            'api_token' => 'DYNAMIC_SECRET',
        ]));

        expect($result->getTranslated())->toBe('Value: ');
        expect($result->getResolvedValues())->not->toContain('DYNAMIC_SECRET');
    });

    it('resolves the DYNAMIC runtime field but reports it in report', function () {
        $reports = [];
        $resolver = v3SurfaceResolver(v3SurfaceValidator(SecurityValidator::MODE_REPORT, $reports));

        $result = $resolver->translate('Value: {{Foo.$indicator.field}}', new ArrayAccessor([
            'indicator' => ['field' => 'api_token'],
            'api_token' => 'DYNAMIC_SECRET',
        ]));

        expect($result->getTranslated())->toBe('Value: DYNAMIC_SECRET');
        expect($reports)->not->toBe([]);
    });

    it('closes the collection route, which navigates getRaw() instead of get()', function () {
        $reports = [];
        $validator = v3SurfaceValidator(SecurityValidator::MODE_ENFORCE, $reports);
        $raw = new ArrayAccessor(['items' => [['password' => 'COLLECTION_SECRET']]]);

        // CollectionResolver's ONLY barrier-1 consultation is allowsPath()
        // before it takes getRaw(). Asserting on the decorator directly
        // isolates that: the wrapped accessor (no validator of its own)
        // answers true, the decorated one must answer false.
        expect($raw->allowsPath('items.0.password'))->toBeTrue();

        $decorated = ValidatingAccessor::wrap($raw, $validator);
        expect($decorated)->toBeInstanceOf(SecurityAwareAccessorInterface::class);
        expect($decorated->allowsPath('items.0.password'))->toBeFalse();
        expect($decorated->allowsPath('items.0.title'))->toBeTrue();

        $result = v3SurfaceResolver($validator)
            ->translate('Value: {{Foo.items.0.password}}', $raw);

        expect($result->getTranslated())->toBe('Value: ');
    });

    it('keeps getRaw() identical in type and identity so the collection route still works', function () {
        $reports = [];
        $raw = new ArrayAccessor(['items' => [['title' => 'Kept']]]);
        $decorated = ValidatingAccessor::wrap($raw, v3SurfaceValidator(SecurityValidator::MODE_ENFORCE, $reports));

        // Filtering or substituting getRaw() would break the accessor
        // contract (EloquentAccessor::getRaw(): Model) and duplicate the
        // sanitizer's container rules in a second place.
        expect($decorated->getRaw())->toBe($raw->getRaw());
        expect($decorated->keys())->toBe($raw->keys());
        expect($decorated->getSourceType())->toBe($raw->getSourceType());
    });

    it('applies allowed_root_models to a model the consumer wrapped themselves', function () {
        $user = User::factory()->create(['name' => 'John Doe']);
        $reports = [];

        // EloquentAccessor validates the model in its own constructor, and
        // this one was built without a validator: nothing had ever checked
        // the class before the resolver saw it.
        $accessor = new EloquentAccessor($user);
        $resolver = v3SurfaceResolver(
            v3SurfaceValidator(SecurityValidator::MODE_ENFORCE, $reports, [Department::class])
        );

        $resolver->translate('Name: {{User.name}}', $accessor);
    })->throws(ModelNotAllowedException::class);

    it('reports instead of throwing for that same model in report mode', function () {
        $user = User::factory()->create(['name' => 'John Doe']);
        $reports = [];

        $resolver = v3SurfaceResolver(
            v3SurfaceValidator(SecurityValidator::MODE_REPORT, $reports, [Department::class])
        );

        $result = $resolver->translate('Name: {{User.name}}', new EloquentAccessor($user));

        expect($result->getTranslated())->toBe('Name: John Doe');
        expect($reports)->toContain('mustache-resolver: model access would be blocked in enforce mode');
    });

    it('leaves that same model untouched in off mode', function () {
        $user = User::factory()->create(['name' => 'John Doe']);
        $reports = [];

        $resolver = v3SurfaceResolver(
            v3SurfaceValidator(SecurityValidator::MODE_OFF, $reports, [Department::class])
        );

        $result = $resolver->translate('Name: {{User.name}}', new EloquentAccessor($user));

        expect($result->getTranslated())->toBe('Name: John Doe');
        expect($reports)->toBe([]);
    });

    it('never weakens an accessor that already carries a stricter policy', function () {
        $reports = [];
        $strict = new SecurityValidator(
            blacklistedAttributes: ['nickname'],
            mode: SecurityValidator::MODE_ENFORCE,
        );

        // The resolver's own policy does not blacklist 'nickname'; the
        // wrapped accessor's does. Both must be consulted, so the path
        // stays blocked -- wrapping can only ever add a barrier.
        $decorated = ValidatingAccessor::wrap(
            new ArrayAccessor(['nickname' => 'jd'], $strict),
            v3SurfaceValidator(SecurityValidator::MODE_ENFORCE, $reports),
        );

        expect($decorated->allowsPath('nickname'))->toBeFalse();
        expect($decorated->get('nickname'))->toBeNull();
        expect($decorated->has('nickname'))->toBeFalse();
    });

    it('returns the accessor untouched in off mode, preserving its identity', function () {
        $reports = [];
        $raw = new ArrayAccessor(['password' => 'SECRET']);

        // off promises no checks are applied; substituting the consumer's
        // own object for a wrapper would be a change, and there is nothing
        // for the wrapper to do -- every check short-circuits to allowed.
        expect(ValidatingAccessor::wrap($raw, v3SurfaceValidator(SecurityValidator::MODE_OFF, $reports)))
            ->toBe($raw);
    });

    it('does not stack a second wrapper for the same policy', function () {
        $reports = [];
        $validator = v3SurfaceValidator(SecurityValidator::MODE_ENFORCE, $reports);
        $wrapped = ValidatingAccessor::wrap(new ArrayAccessor(['name' => 'John']), $validator);

        // Re-wrapping would re-run validateRootDatum() and duplicate every
        // allowsPath() call for no added protection.
        expect(ValidatingAccessor::wrap($wrapped, $validator))->toBe($wrapped);
    });
});

describe('a context handed straight to translate()', function () {
    it('resolves a blacklisted NULL_COALESCE path with mode off (fixture guard)', function () {
        $reports = [];
        $resolver = v3SurfaceResolver(v3SurfaceValidator(SecurityValidator::MODE_OFF, $reports));

        $result = $resolver->translate(
            "Value: {{x.password ?? 'fb'}}",
            ResolutionContext::create(new ArrayAccessor(['password' => 'SECRET'])),
        );

        expect($result->getTranslated())->toBe('Value: SECRET');
    });

    it('blocks that path in enforce', function () {
        $reports = [];
        $resolver = v3SurfaceResolver(v3SurfaceValidator(SecurityValidator::MODE_ENFORCE, $reports));

        $result = $resolver->translate(
            "Value: {{x.password ?? 'fb'}}",
            ResolutionContext::create(new ArrayAccessor(['password' => 'SECRET'])),
        );

        expect($result->getTranslated())->toBe('Value: fb');
    });

    it('resolves but reports that path in report', function () {
        $reports = [];
        $resolver = v3SurfaceResolver(v3SurfaceValidator(SecurityValidator::MODE_REPORT, $reports));

        $result = $resolver->translate(
            "Value: {{x.password ?? 'fb'}}",
            ResolutionContext::create(new ArrayAccessor(['password' => 'SECRET'])),
        );

        expect($result->getTranslated())->toBe('Value: SECRET');
        expect($reports)->not->toBe([]);
    });

    it('applies allowed_root_models through a context built around a model', function () {
        $user = User::factory()->create(['name' => 'John Doe']);
        $reports = [];

        $resolver = v3SurfaceResolver(
            v3SurfaceValidator(SecurityValidator::MODE_ENFORCE, $reports, [Department::class])
        );

        $resolver->translate(
            'Name: {{User.name}}',
            ResolutionContext::create(new EloquentAccessor($user)),
        );
    })->throws(ModelNotAllowedException::class);
});

describe('ValidatingContext', function () {
    it('gates get() and has(), which are access points of their own', function () {
        $reports = [];

        // No built-in resolver reaches data through ContextInterface::get()
        // -- they all go via getAccessor() -- but the method is public API
        // and a consumer's own resolver (or their own ContextInterface
        // implementation) can, so decorating the accessor alone would leave
        // a hole. Asserted here directly, because the pipeline offers no
        // route through which to observe it.
        $context = new ValidatingContext(
            ResolutionContext::create(new ArrayAccessor(['password' => 'SECRET', 'name' => 'John'])),
            v3SurfaceValidator(SecurityValidator::MODE_ENFORCE, $reports),
        );

        expect($context->get('name'))->toBe('John');
        expect($context->has('name'))->toBeTrue();
        expect($context->get('password'))->toBeNull();
        expect($context->has('password'))->toBeFalse();
    });

    it('returns a decorated instance from with(), so one copy cannot undo the barrier', function () {
        $reports = [];
        $context = new ValidatingContext(
            ResolutionContext::create(new ArrayAccessor(['password' => 'SECRET'])),
            v3SurfaceValidator(SecurityValidator::MODE_ENFORCE, $reports),
        );

        // with() is the immutable-copy method compound resolution uses;
        // handing back the bare inner context there would drop the barrier
        // for the rest of the resolution.
        $copy = $context->with('local', 'value');

        expect($copy)->toBeInstanceOf(ValidatingContext::class);
        expect($copy->getVariables())->toBe(['local' => 'value']);
        expect($copy->get('password'))->toBeNull();
        expect($copy->getAccessor())->toBeInstanceOf(ValidatingAccessor::class);
    });

    it('exposes the decorated accessor through getAccessor() and delegates the rest', function () {
        $reports = [];
        $inner = ResolutionContext::create(new ArrayAccessor(['name' => 'John']))
            ->withStrict(false)
            ->withPrefix('User')
            ->withConfig(['k' => 'v']);

        $context = new ValidatingContext($inner, v3SurfaceValidator(SecurityValidator::MODE_ENFORCE, $reports));

        expect($context->getAccessor())->toBeInstanceOf(ValidatingAccessor::class);
        expect($context->isStrict())->toBeFalse();
        expect($context->getExpectedPrefix())->toBe('User');
        expect($context->config('k'))->toBe('v');
        expect($context->config('missing', 'default'))->toBe('default');
    });

    it('returns the context untouched in off mode and does not re-wrap in enforce', function () {
        $reports = [];
        $inner = ResolutionContext::create(new ArrayAccessor([]));

        expect(ValidatingContext::wrap($inner, v3SurfaceValidator(SecurityValidator::MODE_OFF, $reports)))
            ->toBe($inner);

        $validator = v3SurfaceValidator(SecurityValidator::MODE_ENFORCE, $reports);
        $wrapped = ValidatingContext::wrap($inner, $validator);

        expect(ValidatingContext::wrap($wrapped, $validator))->toBe($wrapped);
    });
});
