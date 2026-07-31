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
