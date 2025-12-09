<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Token\TokenType;

describe('TokenType', function () {
    it('has expected cases', function () {
        expect(TokenType::cases())->toHaveCount(11);
    });

    it('can determine if accessor is required', function () {
        expect(TokenType::MODEL->requiresAccessor())->toBeTrue();
        expect(TokenType::TABLE->requiresAccessor())->toBeTrue();
        expect(TokenType::RELATION->requiresAccessor())->toBeTrue();
        expect(TokenType::DYNAMIC->requiresAccessor())->toBeTrue();
        expect(TokenType::COLLECTION->requiresAccessor())->toBeTrue();

        expect(TokenType::FUNCTION->requiresAccessor())->toBeFalse();
        expect(TokenType::VARIABLE->requiresAccessor())->toBeFalse();
        expect(TokenType::MATH->requiresAccessor())->toBeFalse();
        expect(TokenType::LITERAL->requiresAccessor())->toBeFalse();
    });

    it('can determine if nesting is supported', function () {
        expect(TokenType::MODEL->supportsNesting())->toBeTrue();
        expect(TokenType::RELATION->supportsNesting())->toBeTrue();

        expect(TokenType::FUNCTION->supportsNesting())->toBeFalse();
        expect(TokenType::VARIABLE->supportsNesting())->toBeFalse();
    });

    it('provides descriptions for all types', function () {
        foreach (TokenType::cases() as $type) {
            expect($type->description())->toBeString()->not->toBeEmpty();
        }
    });

    it('has correct string values', function () {
        expect(TokenType::MODEL->value)->toBe('model');
        expect(TokenType::TABLE->value)->toBe('table');
        expect(TokenType::RELATION->value)->toBe('relation');
        expect(TokenType::DYNAMIC->value)->toBe('dynamic');
        expect(TokenType::COLLECTION->value)->toBe('collection');
        expect(TokenType::FUNCTION->value)->toBe('function');
        expect(TokenType::VARIABLE->value)->toBe('variable');
        expect(TokenType::MATH->value)->toBe('math');
        expect(TokenType::NULL_COALESCE->value)->toBe('null_coalesce');
        expect(TokenType::LITERAL->value)->toBe('literal');
        expect(TokenType::UNKNOWN->value)->toBe('unknown');
    });
});
