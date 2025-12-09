<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Token\TokenType;

describe('TokenType', function () {
    it('has expected cases', function () {
        expect(TokenType::cases())->toHaveCount(15);
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
        expect(TokenType::COMPOUND->requiresAccessor())->toBeFalse();
        expect(TokenType::LOCAL_VARIABLE->requiresAccessor())->toBeFalse();
    });

    it('can determine if nesting is supported', function () {
        expect(TokenType::MODEL->supportsNesting())->toBeTrue();
        expect(TokenType::RELATION->supportsNesting())->toBeTrue();

        expect(TokenType::FUNCTION->supportsNesting())->toBeFalse();
        expect(TokenType::VARIABLE->supportsNesting())->toBeFalse();
        expect(TokenType::COMPOUND->supportsNesting())->toBeFalse();
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
        expect(TokenType::COMPOUND->value)->toBe('compound');
        expect(TokenType::USE_DECLARATION->value)->toBe('use_declaration');
        expect(TokenType::LOCAL_VARIABLE->value)->toBe('local_variable');
        expect(TokenType::FORMATTER->value)->toBe('formatter');
    });

    it('can determine if type is compound related', function () {
        expect(TokenType::COMPOUND->isCompoundRelated())->toBeTrue();
        expect(TokenType::USE_DECLARATION->isCompoundRelated())->toBeTrue();
        expect(TokenType::LOCAL_VARIABLE->isCompoundRelated())->toBeTrue();
        expect(TokenType::FORMATTER->isCompoundRelated())->toBeTrue();

        expect(TokenType::MODEL->isCompoundRelated())->toBeFalse();
        expect(TokenType::FUNCTION->isCompoundRelated())->toBeFalse();
        expect(TokenType::VARIABLE->isCompoundRelated())->toBeFalse();
    });
});
