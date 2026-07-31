<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Security\OutputSanitizer;
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;
use AichaDigital\MustacheResolver\Core\Token\Token;
use AichaDigital\MustacheResolver\Core\Token\TokenType;

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
