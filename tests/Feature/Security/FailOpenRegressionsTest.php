<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Cache\NullCache;
use AichaDigital\MustacheResolver\Contracts\SafeForTemplateSerialization;
use AichaDigital\MustacheResolver\Core\Compound\UseVariable;
use AichaDigital\MustacheResolver\Core\Compound\UseVariableResolver;
use AichaDigital\MustacheResolver\Core\Context\ResolutionContext;
use AichaDigital\MustacheResolver\Core\MustacheResolver;
use AichaDigital\MustacheResolver\Core\Parser\MustacheParser;
use AichaDigital\MustacheResolver\Core\Pipeline\PipelineBuilder;
use AichaDigital\MustacheResolver\Core\Security\OutputSanitizer;
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;
use AichaDigital\MustacheResolver\Core\Token\Token;
use AichaDigital\MustacheResolver\Core\Token\TokenType;
use AichaDigital\MustacheResolver\Exceptions\VariableNotResolvedException;
use Carbon\Carbon;

/*
|--------------------------------------------------------------------------
| Why this file exists
|--------------------------------------------------------------------------
|
| The pre-tag adversarial gate over main...3.x reproduced five fail-open
| paths that make "enforce by default" a false promise. Each repro here is
| the gate's own seed, written red-first against the unfixed code:
|
|   #1  A scalar LIST under any token cleared the container gate: the
|       projection escape keyed on shape, not provenance. It must require
|       a wildcard COLLECTION token — the only producer of that shape
|       inside the package.
|   #2  Any Stringable classified as atomic, so an object whose
|       __toString() serialises itself walked the whole policy. Only
|       DateTimeInterface (Carbon et al.), enums and classes marked
|       SafeForTemplateSerialization stay atomic; the rest are containers.
|   #3  new OutputSanitizer() (null validator) read as mode OFF —
|       against §11.2, where null means THE DEFAULT POLICY. The compound
|       exit amplified it: barrier 1 and the parser ceilings derive from
|       that same null.
|   #4  new SecurityValidator() reported enforce while carrying empty
|       blacklists. Constructor defaults are now the DEFAULT_* constants;
|       an explicit [] remains the deliberate opt-out.
|
| (#5, the raw pipeline exit, is a documentation decision — @internal —
| and carries no behaviour to pin here.)
|
| Every "blocked" assertion has its OFF-mode (or explicit empty-policy)
| twin proving the value is reachable, per the house fixture-guard rule:
| a block asserted without reachability could be asserting on a fixture
| that never resolved.
*/

function failOpenToken(string $raw, TokenType $type = TokenType::MODEL): Token
{
    return Token::create($raw, $type, explode('.', $raw));
}

function failOpenResolver(SecurityValidator $validator): MustacheResolver
{
    return new MustacheResolver(
        new MustacheParser,
        PipelineBuilder::create()->build(),
        new NullCache,
        $validator,
        new OutputSanitizer($validator),
    );
}

describe('#4 a bare SecurityValidator carries the real default policy', function () {
    it('resolves the secret with an explicit empty policy (fixture guard + deliberate opt-out)', function () {
        $validator = new SecurityValidator(
            blacklistedAttributes: [],
            blacklistedPatterns: [],
            mode: SecurityValidator::MODE_ENFORCE,
        );

        $result = failOpenResolver($validator)->translate(
            'Value: {{User.password}}',
            ['password' => 'PLAIN_SECRET'],
        );

        expect($result->getTranslated())->toBe('Value: PLAIN_SECRET');
    });

    it('blocks the secret through new SecurityValidator() with no arguments', function () {
        $validator = new SecurityValidator;

        $result = failOpenResolver($validator)->translate(
            'Value: {{User.password}}',
            ['password' => 'PLAIN_SECRET'],
            strict: false,
        );

        expect($result->getTranslated())->not->toContain('PLAIN_SECRET');
    });

    it('carries the DEFAULT_* lists: exact names and patterns both match', function () {
        $validator = new SecurityValidator;

        expect($validator->getMode())->toBe(SecurityValidator::MODE_ENFORCE)
            ->and($validator->isAttributeBlacklisted('password'))->toBeTrue()
            ->and($validator->isAttributeBlacklisted('api_key'))->toBeTrue()
            ->and($validator->isAttributeBlacklisted('title'))->toBeFalse();
    });
});

describe('#3 a bare OutputSanitizer applies the default policy, not OFF', function () {
    it('passes a container through with an explicit off-mode validator (fixture guard)', function () {
        $sanitizer = new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_OFF));

        $raw = ['password' => 'CONTAINER_SECRET', 'name' => 'John'];
        $result = $sanitizer->sanitize($raw, failOpenToken('User.profile'));

        expect($result->blocked)->toBeFalse()
            ->and($result->value)->toBe($raw);
    });

    it('blocks a container through new OutputSanitizer() with no arguments', function () {
        $sanitizer = new OutputSanitizer;

        $result = $sanitizer->sanitize(
            ['password' => 'CONTAINER_SECRET', 'name' => 'John'],
            failOpenToken('User.profile'),
        );

        expect($result->blocked)->toBeTrue()
            ->and($result->value)->toBeNull()
            ->and($result->text)->toBe('');
    });

    it('exposes the enforce default policy through getValidator()', function () {
        $validator = (new OutputSanitizer)->getValidator();

        expect($validator)->toBeInstanceOf(SecurityValidator::class)
            ->and($validator->getMode())->toBe(SecurityValidator::MODE_ENFORCE)
            ->and($validator->isAttributeBlacklisted('password'))->toBeTrue();
    });

    it('resolves the secret through the compound exit with an off-mode sanitizer (fixture guard)', function () {
        $resolver = new UseVariableResolver(
            PipelineBuilder::create()->build(),
            new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_OFF)),
        );

        $context = ResolutionContext::fromArray(
            ['password' => 'COMPOUND_SECRET'],
            new SecurityValidator(mode: SecurityValidator::MODE_OFF),
        );

        expect($resolver->resolve(new UseVariable('x', '{{User.password}}'), $context))
            ->toBe('COMPOUND_SECRET');
    });

    it('blocks the compound exit when the sanitizer was constructed empty', function () {
        // The gate's repro: an explicitly-supplied `new OutputSanitizer()`
        // used to read as OFF, which ALSO skipped barrier 1 (the context
        // wrap derives from getValidator()) and unbounded the parser.
        $resolver = new UseVariableResolver(
            PipelineBuilder::create()->build(),
            new OutputSanitizer,
        );

        $context = ResolutionContext::fromArray(
            ['password' => 'COMPOUND_SECRET'],
            new SecurityValidator(mode: SecurityValidator::MODE_OFF),
        );

        $resolver->resolve(new UseVariable('x', '{{User.password}}'), $context);
    })->throws(VariableNotResolvedException::class);
});

describe('#1 the scalar projection escape requires wildcard-collection provenance', function () {
    it('resolves a scalar list under a MODEL token with mode off (fixture guard)', function () {
        $sanitizer = new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_OFF));

        $raw = ['LIST_SECRET_A', 'LIST_SECRET_B'];
        $result = $sanitizer->sanitize($raw, failOpenToken('User.items'));

        expect($result->blocked)->toBeFalse()
            ->and($result->value)->toBe($raw);
    });

    it('blocks a scalar list under a MODEL token in enforce: shape is not provenance', function () {
        $reports = [];
        $validator = new SecurityValidator(
            mode: SecurityValidator::MODE_ENFORCE,
            reporter: function (string $message) use (&$reports): void {
                $reports[] = $message;
            },
        );

        $result = (new OutputSanitizer($validator))->sanitize(
            ['LIST_SECRET_A', 'LIST_SECRET_B'],
            failOpenToken('User.items'),
        );

        expect($result->blocked)->toBeTrue()
            ->and($result->value)->toBeNull()
            ->and(implode(' ', $reports))->toContain('container blocked');
    });

    it('blocks a scalar list under a COLLECTION token WITHOUT a wildcard segment', function () {
        $validator = new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE);

        $result = (new OutputSanitizer($validator))->sanitize(
            ['LIST_SECRET_A', 'LIST_SECRET_B'],
            failOpenToken('User.posts.0.tags', TokenType::COLLECTION),
        );

        expect($result->blocked)->toBeTrue();
    });

    it('keeps the escape for a wildcard COLLECTION projection', function () {
        $validator = new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE);

        $result = (new OutputSanitizer($validator))->sanitize(
            ['First Post', 'Second Post'],
            failOpenToken('User.posts.*.title', TokenType::COLLECTION),
        );

        expect($result->blocked)->toBeFalse()
            ->and($result->value)->toBe(['First Post', 'Second Post'])
            ->and($result->text)->toBe('First Post, Second Post');
    });

    it('reports but does not block the list in report mode', function () {
        $reports = [];
        $validator = new SecurityValidator(
            mode: SecurityValidator::MODE_REPORT,
            reporter: function (string $message) use (&$reports): void {
                $reports[] = $message;
            },
        );

        $raw = ['LIST_SECRET_A', 'LIST_SECRET_B'];
        $result = (new OutputSanitizer($validator))->sanitize($raw, failOpenToken('User.items'));

        expect($result->blocked)->toBeFalse()
            ->and($result->value)->toBe($raw)
            ->and(implode(' ', $reports))->toContain('would be blocked in enforce mode');
    });

    it('blocks the list end to end through translate() with the default policy', function () {
        $result = failOpenResolver(SecurityValidator::defaultPolicy())->translate(
            'Value: {{User.items}}',
            ['items' => ['LIST_SECRET_A', 'LIST_SECRET_B']],
            strict: false,
        );

        expect($result->getTranslated())->toBe('Value: ');
    });
});

describe('#2 an arbitrary Stringable is a container, not an atomic scalar', function () {
    it('renders the self-serialising Stringable with mode off (fixture guard)', function () {
        $leaky = new class
        {
            private string $password = 'STRINGABLE_SECRET';

            public function __toString(): string
            {
                return (string) json_encode(['password' => $this->password]);
            }
        };

        $sanitizer = new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_OFF));
        $result = $sanitizer->sanitize($leaky, failOpenToken('User.profile'));

        expect($result->blocked)->toBeFalse()
            ->and($result->text)->toContain('STRINGABLE_SECRET');
    });

    it('blocks the self-serialising Stringable in enforce', function () {
        $leaky = new class
        {
            private string $password = 'STRINGABLE_SECRET';

            public function __toString(): string
            {
                return (string) json_encode(['password' => $this->password]);
            }
        };

        $reports = [];
        $validator = new SecurityValidator(
            mode: SecurityValidator::MODE_ENFORCE,
            reporter: function (string $message) use (&$reports): void {
                $reports[] = $message;
            },
        );

        $result = (new OutputSanitizer($validator))->sanitize($leaky, failOpenToken('User.profile'));

        expect($result->blocked)->toBeTrue()
            ->and($result->text)->toBe('')
            ->and(implode(' ', $reports))->toContain('container blocked');
    });

    it('reports without altering the Stringable in report mode', function () {
        $leaky = new class
        {
            public function __toString(): string
            {
                return (string) json_encode(['password' => 'STRINGABLE_SECRET']);
            }
        };

        $reports = [];
        $validator = new SecurityValidator(
            mode: SecurityValidator::MODE_REPORT,
            reporter: function (string $message) use (&$reports): void {
                $reports[] = $message;
            },
        );

        $result = (new OutputSanitizer($validator))->sanitize($leaky, failOpenToken('User.profile'));

        expect($result->blocked)->toBeFalse()
            ->and($result->value)->toBe($leaky)
            ->and(implode(' ', $reports))->toContain('would be blocked in enforce mode');
    });

    it('keeps Carbon atomic: normalised to its string form in enforce, never blocked', function () {
        $validator = new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE);
        $date = Carbon::parse('2026-08-03 10:00:00');

        $result = (new OutputSanitizer($validator))->sanitize($date, failOpenToken('User.created_at'));

        expect($result->blocked)->toBeFalse()
            ->and($result->text)->toContain('2026-08-03');
    });

    it('keeps a class marked SafeForTemplateSerialization atomic', function () {
        $marked = new class implements SafeForTemplateSerialization
        {
            public function __toString(): string
            {
                return 'MARKED_VALUE';
            }
        };

        $validator = new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE);
        $result = (new OutputSanitizer($validator))->sanitize($marked, failOpenToken('User.badge'));

        expect($result->blocked)->toBeFalse()
            ->and($result->text)->toBe('MARKED_VALUE');
    });

    it('never lets a nested Stringable smuggle its dump through an authorised container', function () {
        $leaky = new class
        {
            private string $password = 'STRINGABLE_SECRET';

            public function __toString(): string
            {
                return (string) json_encode(['password' => $this->password]);
            }
        };

        $validator = new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE);
        $sanitizer = new OutputSanitizer($validator, allowContainerSerialization: true);

        $result = $sanitizer->sanitize(['note' => $leaky], failOpenToken('User.meta'));

        expect($result->text)->not->toContain('STRINGABLE_SECRET')
            ->and(json_encode($result->value))->not->toContain('STRINGABLE_SECRET');
    });

    it('still projects a wildcard list of Carbon values (trusted atomics)', function () {
        $validator = new SecurityValidator(mode: SecurityValidator::MODE_ENFORCE);

        $result = (new OutputSanitizer($validator))->sanitize(
            [Carbon::parse('2026-01-01'), Carbon::parse('2026-02-01')],
            failOpenToken('User.posts.*.published_at', TokenType::COLLECTION),
        );

        expect($result->blocked)->toBeFalse()
            ->and($result->text)->toContain('2026-01-01');
    });
});
