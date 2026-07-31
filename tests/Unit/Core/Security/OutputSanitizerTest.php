<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Contracts\SafeForTemplateSerialization;
use AichaDigital\MustacheResolver\Core\Security\OutputSanitizer;
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;
use AichaDigital\MustacheResolver\Core\Token\Token;
use AichaDigital\MustacheResolver\Core\Token\TokenType;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Workbench\App\Models\User;

/**
 * Token has a private constructor; Token::create() is the explicit factory.
 * Name it distinctively — Pest file-level helpers share one global namespace
 * across the whole suite, so a generic name collides at load time.
 */
function sanitizerTestToken(string $raw, TokenType $type = TokenType::MODEL): Token
{
    return Token::create($raw, $type, explode('.', $raw));
}

describe('OutputSanitizer → scalars', function () {
    it('passes scalars through unchanged and renders them', function () {
        $sanitizer = new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE));

        $result = $sanitizer->sanitize('John Doe', sanitizerTestToken('User.name'));

        expect($result->value)->toBe('John Doe');
        expect($result->text)->toBe('John Doe');
        expect($result->blocked)->toBeFalse();
    });

    it('renders null as an empty string', function () {
        $sanitizer = new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE));

        $result = $sanitizer->sanitize(null, sanitizerTestToken('User.missing'));

        expect($result->value)->toBeNull();
        expect($result->text)->toBe('');
    });

    it('renders booleans as true/false', function () {
        $sanitizer = new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE));

        expect($sanitizer->sanitize(true, sanitizerTestToken('User.active'))->text)->toBe('true');
        expect($sanitizer->sanitize(false, sanitizerTestToken('User.active'))->text)->toBe('false');
    });

    it('passes everything through untouched with no validator', function () {
        $sanitizer = new OutputSanitizer;

        $result = $sanitizer->sanitize(['a' => 1], sanitizerTestToken('User.meta'));

        expect($result->value)->toBe(['a' => 1]);
        expect($result->blocked)->toBeFalse();
    });
});

describe('OutputSanitizer → containers', function () {
    it('blocks an array in enforce mode', function () {
        $sanitizer = new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE));

        $result = $sanitizer->sanitize(['name' => 'Engineering'], sanitizerTestToken('User.department'));

        expect($result->blocked)->toBeTrue();
        expect($result->value)->toBeNull();
        expect($result->text)->toBe('');
    });

    it('leaves a container untouched in report mode but reports it', function () {
        $reported = [];
        $validator = new SecurityValidator(
            mode: SecurityValidator::MODE_REPORT,
            reporter: function (string $m, array $c) use (&$reported): void {
                $reported[] = $m;
            },
        );

        $result = (new OutputSanitizer($validator))->sanitize(['name' => 'Engineering'], sanitizerTestToken('User.department'));

        expect($result->blocked)->toBeFalse();
        expect($result->value)->toBe(['name' => 'Engineering']);
        expect($reported)->toHaveCount(1);
    });

    it('allows containers when the global flag is on', function () {
        $sanitizer = new OutputSanitizer(
            new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE),
            allowContainerSerialization: true,
        );

        $result = $sanitizer->sanitize(['name' => 'Engineering'], sanitizerTestToken('User.department'));

        expect($result->blocked)->toBeFalse();
        expect($result->value)->toBe(['name' => 'Engineering']);
    });

    it('allows an object that declares itself safe, with the flag off', function () {
        // Arrayable on purpose: a Stringable-only class is classified atomic and
        // would never reach the container path, so it would not exercise the escape.
        $safe = new class implements Arrayable, SafeForTemplateSerialization
        {
            public function toArray(): array
            {
                return ['label' => 'ok'];
            }
        };

        $result = (new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE)))
            ->sanitize($safe, sanitizerTestToken('User.badge'));

        expect($result->blocked)->toBeFalse();
        expect($result->value)->toBe(['label' => 'ok']);
    });
});

describe('OutputSanitizer → classification precedence', function () {
    it('blocks an Arrayable that is also Stringable', function () {
        $both = new class implements Arrayable, Stringable
        {
            public function toArray(): array
            {
                return ['secret' => 'x'];
            }

            public function __toString(): string
            {
                return 'looks harmless';
            }
        };

        $result = (new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE)))
            ->sanitize($both, sanitizerTestToken('User.thing'));

        expect($result->blocked)->toBeTrue();
    });

    it('blocks a real Collection', function () {
        $result = (new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE)))
            ->sanitize(new Collection(['a' => 1]), sanitizerTestToken('User.items'));

        expect($result->blocked)->toBeTrue();
    });

    it('blocks a real Eloquent model', function () {
        $model = new User(['name' => 'John']);

        $result = (new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE)))
            ->sanitize($model, sanitizerTestToken('User.self'));

        expect($result->blocked)->toBeTrue();
    });

    it('does not let the global flag authorise a model', function () {
        $model = new User(['name' => 'John']);

        $result = (new OutputSanitizer(
            new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE),
            allowContainerSerialization: true,
        ))->sanitize($model, sanitizerTestToken('User.self'));

        expect($result->blocked)->toBeTrue();
    });

    it('treats Carbon as atomic and records it as a string, not as an object', function () {
        $date = Carbon::parse('2026-07-31 09:00:00');

        $result = (new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE)))
            ->sanitize($date, sanitizerTestToken('User.created_at'));

        expect($result->blocked)->toBeFalse();
        expect($result->value)->toBeString();
        expect($result->value)->toBe($result->text);
    });

    it('normalises a Stringable JsonSerializable to a string rather than blocking it', function () {
        $money = new class implements JsonSerializable, Stringable
        {
            public function __toString(): string
            {
                return '10.00 EUR';
            }

            public function jsonSerialize(): array
            {
                return ['amount' => 1000, 'currency' => 'EUR'];
            }
        };

        $result = (new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE)))
            ->sanitize($money, sanitizerTestToken('User.balance'));

        expect($result->blocked)->toBeFalse();
        expect($result->value)->toBe('10.00 EUR');
    });
});

describe('OutputSanitizer → filtering authorised containers', function () {
    it('strips blacklisted keys recursively when the container is allowed', function () {
        $sanitizer = new OutputSanitizer(
            new SecurityValidator(blacklistedAttributes: ['password'], mode: SecurityValidator::MODE_ENFORCE),
            allowContainerSerialization: true,
        );

        $result = $sanitizer->sanitize([
            'name' => 'John',
            'password' => 'hunter2',
            'profile' => ['bio' => 'x', 'password' => 'nested'],
        ], sanitizerTestToken('User.data'));

        expect($result->value)->toBe([
            'name' => 'John',
            'profile' => ['bio' => 'x'],
        ]);
    });

    it('matches blacklisted keys case-insensitively', function () {
        $sanitizer = new OutputSanitizer(
            new SecurityValidator(blacklistedAttributes: ['password'], mode: SecurityValidator::MODE_ENFORCE),
            allowContainerSerialization: true,
        );

        $result = $sanitizer->sanitize(['Password' => 'x', 'ok' => 1], sanitizerTestToken('User.data'));

        expect($result->value)->toBe(['ok' => 1]);
    });

    it('does not filter in report mode, but reports', function () {
        $reported = [];
        $validator = new SecurityValidator(
            blacklistedAttributes: ['password'],
            mode: SecurityValidator::MODE_REPORT,
            reporter: function (string $m, array $c) use (&$reported): void {
                $reported[] = $c;
            },
        );

        $result = (new OutputSanitizer($validator, allowContainerSerialization: true))
            ->sanitize(['password' => 'x'], sanitizerTestToken('User.data'));

        expect($result->value)->toBe(['password' => 'x']);
        expect($reported)->not->toBeEmpty();
    });

    it('filters a model nested inside an authorised container', function () {
        $sanitizer = new OutputSanitizer(
            new SecurityValidator(blacklistedAttributes: ['password'], mode: SecurityValidator::MODE_ENFORCE),
            allowContainerSerialization: true,
        );

        // password is not in User::$fillable, so the constructor would
        // silently drop it — forceFill bypasses the guard to actually put
        // it on the model, which is the point of this fixture.
        $nested = (new User(['name' => 'John']))->forceFill(['password' => 'hunter2']);

        $result = $sanitizer->sanitize(['owner' => $nested], sanitizerTestToken('User.data'));

        expect($result->value)->toBe(['owner' => ['name' => 'John']]);
        expect(json_encode($result->value))->not->toContain('hunter2');
    });

    it('renders an authorised container as JSON, not as an imploded list', function () {
        $sanitizer = new OutputSanitizer(
            new SecurityValidator(blacklistedAttributes: ['password'], mode: SecurityValidator::MODE_ENFORCE),
            allowContainerSerialization: true,
        );

        $result = $sanitizer->sanitize(['name' => 'Engineering', 'password' => 'x'], sanitizerTestToken('User.department'));

        expect($result->text)->toBe('{"name":"Engineering"}');
    });

    it('honours escapeWhenCastingToString on the serialized container, like Eloquent does', function () {
        // Duck-typed on purpose: renderContainer() reads the flag through a
        // closure by property name, not through a Model type check, so this
        // real (non-mock) fixture exercises the same code path a model would
        // without pulling in illuminate/database migrations for a unit test.
        $safe = new class implements Arrayable, SafeForTemplateSerialization
        {
            public bool $escapeWhenCastingToString = true;

            public function toArray(): array
            {
                return ['name' => '<b>Ops</b>'];
            }
        };

        $sanitizer = new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE));

        $result = $sanitizer->sanitize($safe, sanitizerTestToken('User.department'));

        expect($result->text)->toContain('&lt;b&gt;');
        expect($result->text)->not->toContain('<b>');
    });
});

describe('OutputSanitizer → depth', function () {
    it('prunes the branch that exceeds max_depth, keeping the rest', function () {
        $sanitizer = new OutputSanitizer(
            new SecurityValidator(maxDepth: 3, mode: SecurityValidator::MODE_ENFORCE),
            allowContainerSerialization: true,
        );

        // token 'User.data' is depth 2; 'shallow' lands at 3, 'a.b' at 4
        $result = $sanitizer->sanitize([
            'shallow' => 'kept',
            'a' => ['b' => 'too deep'],
        ], sanitizerTestToken('User.data'));

        expect($result->value)->toBe(['shallow' => 'kept', 'a' => []]);
    });

    it('reports the depth violation', function () {
        $reported = [];
        $validator = new SecurityValidator(
            maxDepth: 3,
            mode: SecurityValidator::MODE_ENFORCE,
            reporter: function (string $m, array $c) use (&$reported): void {
                $reported[] = $m;
            },
        );

        (new OutputSanitizer($validator, allowContainerSerialization: true))
            ->sanitize(['a' => ['b' => 'deep']], sanitizerTestToken('User.data'));

        expect($reported)->toContain('mustache-resolver: serialized content pruned at max_depth');
    });

    it('does not prune in report mode, but reports what would be pruned', function () {
        $reported = [];
        $validator = new SecurityValidator(
            maxDepth: 3,
            mode: SecurityValidator::MODE_REPORT,
            reporter: function (string $m, array $c) use (&$reported): void {
                $reported[] = $m;
            },
        );

        $result = (new OutputSanitizer($validator, allowContainerSerialization: true))
            ->sanitize(['a' => ['b' => 'too deep']], sanitizerTestToken('User.data'));

        // Report mode reports, it does not modify: value stays raw and unpruned.
        expect($result->value)->toBe(['a' => ['b' => 'too deep']]);
        expect($reported)->toContain('mustache-resolver: serialized content would be pruned at max_depth in enforce mode');
    });
});

describe('OutputSanitizer → special types', function () {
    it('treats a backed enum as a scalar', function () {
        $result = (new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE)))
            ->sanitize(TokenType::MODEL, sanitizerTestToken('User.kind'));

        expect($result->blocked)->toBeFalse();
        expect($result->text)->toBe('model');
        // Unlike every other atomic object (Stringable), an enum is not
        // stringified into ->value — it survives raw, so a consumer of
        // getResolvedValues() gets the enum instance itself, not a lossy
        // string copy of it. Subtle, and the whole point of this assertion.
        expect($result->value)->toBe(TokenType::MODEL);
    });

    it('treats a Traversable as a container', function () {
        $it = new ArrayIterator(['a' => 1]);

        $result = (new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE)))
            ->sanitize($it, sanitizerTestToken('User.items'));

        expect($result->blocked)->toBeTrue();
    });

    it('normalises an atomic object nested inside an authorised container, instead of leaving it raw', function () {
        // Before this task, stripBlacklisted() only acted on values that
        // isContainer() classified as containers — a nested Stringable-only
        // value like Carbon fell through untouched, leaving the live object
        // sitting in the filtered array. json_encode() on a plain Stringable
        // (no JsonSerializable) would then emit an opaque "{}", silently
        // dropping the date instead of rendering it.
        $sanitizer = new OutputSanitizer(
            new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE),
            allowContainerSerialization: true,
        );

        $date = Carbon::parse('2026-07-31 09:00:00');

        $result = $sanitizer->sanitize(['created_at' => $date], sanitizerTestToken('User.data'));

        expect($result->value)->toBe(['created_at' => '2026-07-31 09:00:00']);
        expect($result->text)->toBe('{"created_at":"2026-07-31 09:00:00"}');
    });

    it('blocks a container whose toArray() throws, instead of returning empty', function () {
        $bad = new class implements Arrayable
        {
            public function toArray(): array
            {
                throw new RuntimeException('boom');
            }
        };

        $result = (new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE), allowContainerSerialization: true))
            ->sanitize($bad, sanitizerTestToken('User.broken'));

        // Never a partially converted array: whatever local state toArray()'s
        // own implementation built before throwing is discarded, not leaked.
        expect($result->value)->toBeNull();
        expect($result->text)->toBe('');
        expect($result->blocked)->toBeTrue();
    });

    it('does not alter a container it could not convert, in report mode', function () {
        $reported = [];
        $bad = new class implements Arrayable, Stringable
        {
            public function toArray(): array
            {
                throw new RuntimeException('boom');
            }

            public function __toString(): string
            {
                return 'legacy-rendering';
            }
        };

        $validator = new SecurityValidator(
            mode: SecurityValidator::MODE_REPORT,
            reporter: function (string $m, array $c) use (&$reported): void {
                $reported[] = $m;
            },
        );

        $result = (new OutputSanitizer($validator, allowContainerSerialization: true))
            ->sanitize($bad, sanitizerTestToken('User.broken'));

        // Report observes, it does not modify: the SAME object survives with
        // its own identity (not a clone, not a partial array), the rendering
        // it would have had with security off, and blocked stays false — the
        // invariant that makes report mode usable as a pre-upgrade measure.
        expect($result->value)->toBe($bad);
        expect($result->blocked)->toBeFalse();
        expect($result->text)->toBe('legacy-rendering');
        expect($reported)->toContain('mustache-resolver: container could not be converted safely, would be blocked in enforce mode');
    });

    it('never attempts conversion in off mode', function () {
        $spy = new class implements Arrayable
        {
            public bool $called = false;

            public function toArray(): array
            {
                $this->called = true;

                return ['x' => 1];
            }
        };

        $result = (new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_OFF)))
            ->sanitize($spy, sanitizerTestToken('User.thing'));

        expect($spy->called)->toBeFalse();
        expect($result->value)->toBe($spy);
    });

    it('converts a JsonSerializable-only container through jsonSerialize()', function () {
        // Not Arrayable, not Traversable, not Stringable: isContainer() only
        // classifies this as a container via its "any remaining object"
        // fallback. toArray() must still know how to read it — falling back
        // to get_object_vars() would miss data behind private state.
        $data = new class implements JsonSerializable
        {
            public function jsonSerialize(): array
            {
                return ['x' => 1];
            }
        };

        $result = (new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE), allowContainerSerialization: true))
            ->sanitize($data, sanitizerTestToken('User.meta'));

        expect($result->blocked)->toBeFalse();
        expect($result->value)->toBe(['x' => 1]);
    });

    it('converts an authorised Traversable through iterator_to_array()', function () {
        $it = new ArrayIterator(['a' => 1]);

        $result = (new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE), allowContainerSerialization: true))
            ->sanitize($it, sanitizerTestToken('User.items'));

        expect($result->blocked)->toBeFalse();
        expect($result->value)->toBe(['a' => 1]);
    });

    it('cuts a cyclic structure instead of recursing forever', function () {
        // throwsNoExceptions() is deliberately not chained here: it calls
        // PHPUnit's expectNotToPerformAssertions(), which conflicts with the
        // expect() below under this project's failOnRisky="true" — it would
        // mark the test risky and fail the suite on that alone. The
        // assertion below already proves "no exception was thrown": an
        // uncaught exception (or an exhausted stack/memory limit from
        // unbounded recursion) would fail this test before reaching it.
        $a = new stdClass;
        $a->name = 'a';
        $a->self = $a;

        $result = (new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE), allowContainerSerialization: true))
            ->sanitize($a, sanitizerTestToken('User.node'));

        expect($result->blocked)->toBeFalse();
    });

    it('cuts a mutual cycle between two objects', function () {
        $a = new stdClass;
        $b = new stdClass;
        $a->other = $b;
        $b->other = $a;

        $result = (new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE), allowContainerSerialization: true))
            ->sanitize($a, sanitizerTestToken('User.node'));

        expect($result->blocked)->toBeFalse();
    });

    it('reports a cut cycle with its own message, not the depth one', function () {
        $reported = [];
        $a = new stdClass;
        $a->name = 'a';
        $a->self = $a;

        $validator = new SecurityValidator(
            mode: SecurityValidator::MODE_ENFORCE,
            reporter: function (string $m, array $c) use (&$reported): void {
                $reported[] = $m;
            },
        );

        (new OutputSanitizer($validator, allowContainerSerialization: true))
            ->sanitize($a, sanitizerTestToken('User.node'));

        expect($reported)->toContain('mustache-resolver: cyclic reference cut from serialized content');
        expect($reported)->not->toContain('mustache-resolver: serialized content pruned at max_depth');
    });

    it('reports a depth prune as depth, never mislabels it as a cycle', function () {
        $reported = [];
        $validator = new SecurityValidator(
            maxDepth: 3,
            mode: SecurityValidator::MODE_ENFORCE,
            reporter: function (string $m, array $c) use (&$reported): void {
                $reported[] = $m;
            },
        );

        // No repeated object anywhere here — a plain, non-cyclic structure
        // that simply exceeds max_depth. Must never surface the cycle
        // wording; the shared $pruned flag this fix replaces would have
        // made either message possible for either cause.
        (new OutputSanitizer($validator, allowContainerSerialization: true))
            ->sanitize(['a' => ['b' => 'too deep']], sanitizerTestToken('User.data'));

        expect($reported)->toContain('mustache-resolver: serialized content pruned at max_depth');
        expect($reported)->not->toContain('mustache-resolver: cyclic reference cut from serialized content');
    });

    it('keeps the same object under two sibling keys — not a cycle', function () {
        $company = new stdClass;
        $company->name = 'Acme';

        $sanitizer = new OutputSanitizer(
            new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE),
            allowContainerSerialization: true,
        );

        $result = $sanitizer->sanitize(['a' => $company, 'b' => $company], sanitizerTestToken('User.data'));

        expect($result->value)->toBe([
            'a' => ['name' => 'Acme'],
            'b' => ['name' => 'Acme'],
        ]);
    });

    it('keeps the same object repeated in different branches — not a cycle', function () {
        $company = new stdClass;
        $company->name = 'Acme';

        $sanitizer = new OutputSanitizer(
            new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE),
            allowContainerSerialization: true,
        );

        $result = $sanitizer->sanitize([
            'group' => ['lead' => $company],
            'other' => ['owner' => $company],
        ], sanitizerTestToken('User.data'));

        expect($result->value)->toBe([
            'group' => ['lead' => ['name' => 'Acme']],
            'other' => ['owner' => ['name' => 'Acme']],
        ]);
    });

    it('detaches the ancestor after a failed nested conversion, so a repeated sibling is not mistaken for a cycle', function () {
        $reported = [];
        $bad = new class implements Arrayable
        {
            public function toArray(): array
            {
                throw new RuntimeException('boom');
            }
        };

        $validator = new SecurityValidator(
            mode: SecurityValidator::MODE_ENFORCE,
            reporter: function (string $m, array $c) use (&$reported): void {
                $reported[] = $m;
            },
        );

        $result = (new OutputSanitizer($validator, allowContainerSerialization: true))
            ->sanitize(['a' => $bad, 'b' => $bad], sanitizerTestToken('User.data'));

        // If the ancestor stack were not cleaned up after 'a' failed, 'b'
        // would see the same object still marked as an active ancestor and
        // wrongly report a cut cycle (null) instead of attempting — and
        // independently failing — conversion again.
        expect($result->value)->toBe(['a' => [], 'b' => []]);
        expect($reported)->toContain('mustache-resolver: nested value could not be converted safely, dropped');
        expect($reported)->not->toContain('mustache-resolver: cyclic reference cut from serialized content');
    });
});

describe('OutputSanitizer → token path validation', function () {
    it('blocks a scalar returned under a blacklisted path', function () {
        $sanitizer = new OutputSanitizer(
            new SecurityValidator(blacklistedAttributes: ['password'], mode: SecurityValidator::MODE_ENFORCE)
        );

        $result = $sanitizer->sanitize('hunter2', sanitizerTestToken('User.password'));

        expect($result->blocked)->toBeTrue();
        expect($result->text)->toBe('');
    });

    it('blocks a blacklisted segment behind a relation', function () {
        $sanitizer = new OutputSanitizer(
            new SecurityValidator(blacklistedAttributes: ['password'], mode: SecurityValidator::MODE_ENFORCE)
        );

        expect($sanitizer->sanitize('x', sanitizerTestToken('User.owner.password'))->blocked)->toBeTrue();
    });

    it('does not apply attribute rules to tokens without a security path', function () {
        $sanitizer = new OutputSanitizer(
            new SecurityValidator(blacklistedAttributes: ['password'], mode: SecurityValidator::MODE_ENFORCE)
        );

        $result = $sanitizer->sanitize('ok', sanitizerTestToken('password()', TokenType::FUNCTION));

        expect($result->blocked)->toBeFalse();
    });

    it('does not block in report mode', function () {
        $sanitizer = new OutputSanitizer(
            new SecurityValidator(blacklistedAttributes: ['password'], mode: SecurityValidator::MODE_REPORT)
        );

        expect($sanitizer->sanitize('hunter2', sanitizerTestToken('User.password'))->blocked)->toBeFalse();
    });
});
