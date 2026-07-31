<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Token\TokenType;

describe('TokenType → security path', function () {
    it('marks data-navigating types as carrying a security path', function (TokenType $type) {
        expect($type->hasSecurityPath())->toBeTrue();
    })->with([
        TokenType::MODEL,
        TokenType::TABLE,
        TokenType::RELATION,
        TokenType::COLLECTION,
    ]);

    it('marks non-navigating types as carrying no security path', function (TokenType $type) {
        expect($type->hasSecurityPath())->toBeFalse();
    })->with([
        TokenType::DYNAMIC,
        TokenType::FUNCTION,
        TokenType::VARIABLE,
        TokenType::MATH,
        TokenType::NULL_COALESCE,
        TokenType::LITERAL,
        TokenType::UNKNOWN,
        TokenType::COMPOUND,
        TokenType::USE_DECLARATION,
        TokenType::LOCAL_VARIABLE,
        TokenType::FORMATTER,
        TokenType::TEMPORAL,
    ]);

    it('covers every case of the enum, so a new type cannot default into a regime', function () {
        $covered = 4 + 12;

        expect(count(TokenType::cases()))->toBe($covered);
    });
});
