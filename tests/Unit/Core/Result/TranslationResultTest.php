<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Contracts\ResultInterface;
use AichaDigital\MustacheResolver\Core\Result\TranslationResult;
use AichaDigital\MustacheResolver\Core\Token\Token;
use AichaDigital\MustacheResolver\Core\Token\TokenType;

describe('TranslationResult → Success Creation', function () {
    it('creates success result with minimal parameters', function () {
        $result = TranslationResult::success(
            original: 'Hello {{name}}',
            translated: 'Hello John',
        );

        expect($result)->toBeInstanceOf(ResultInterface::class);
        expect($result->isSuccess())->toBeTrue();
        expect($result->isFailed())->toBeFalse();
    });

    it('creates success result with all parameters', function () {
        $token = Token::create(
            raw: 'name',
            type: TokenType::VARIABLE,
            path: ['name'],
            functionName: null,
            functionArgs: [],
            metadata: []
        );

        $result = TranslationResult::success(
            original: 'Hello {{name}}',
            translated: 'Hello John',
            tokens: [$token],
            resolvedValues: ['name' => 'John'],
            warnings: ['Some warning'],
        );

        expect($result->getOriginal())->toBe('Hello {{name}}');
        expect($result->getTranslated())->toBe('Hello John');
        expect($result->getTokens())->toHaveCount(1);
        expect($result->getResolvedValues())->toBe(['name' => 'John']);
        expect($result->getWarnings())->toBe(['Some warning']);
    });
});

describe('TranslationResult → Failed Creation', function () {
    it('creates failed result with missing fields', function () {
        $result = TranslationResult::failed(
            original: 'Hello {{name}}',
            missingFields: ['name'],
        );

        expect($result->isSuccess())->toBeFalse();
        expect($result->isFailed())->toBeTrue();
        expect($result->getTranslated())->toBeNull();
        expect($result->getMissingFields())->toBe(['name']);
    });

    it('creates failed result with errors', function () {
        $result = TranslationResult::failed(
            original: 'Hello {{name}}',
            missingFields: [],
            errors: ['Resolution error'],
        );

        expect($result->getErrors())->toBe(['Resolution error']);
    });

    it('creates failed result with warnings and errors', function () {
        $result = TranslationResult::failed(
            original: 'Hello {{name}}',
            missingFields: ['name'],
            errors: ['Error 1'],
            warnings: ['Warning 1'],
        );

        expect($result->getMissingFields())->toBe(['name']);
        expect($result->getErrors())->toBe(['Error 1']);
        expect($result->getWarnings())->toBe(['Warning 1']);
    });
});

describe('TranslationResult → Failure Reason', function () {
    it('returns null for success result', function () {
        $result = TranslationResult::success('original', 'translated');

        expect($result->getFailureReason())->toBeNull();
    });

    it('returns missing fields message', function () {
        $result = TranslationResult::failed('original', ['field1', 'field2']);

        expect($result->getFailureReason())->toBe('Missing fields: field1, field2');
    });

    it('returns errors message when no missing fields', function () {
        $result = TranslationResult::failed('original', [], ['Error 1', 'Error 2']);

        expect($result->getFailureReason())->toBe('Error 1; Error 2');
    });

    it('returns unknown error when no fields or errors', function () {
        $result = TranslationResult::failed('original', []);

        expect($result->getFailureReason())->toBe('Unknown error');
    });

    it('prioritizes missing fields over errors', function () {
        $result = TranslationResult::failed('original', ['field1'], ['Error 1']);

        expect($result->getFailureReason())->toBe('Missing fields: field1');
    });
});

describe('TranslationResult → toArray', function () {
    it('converts success result to array', function () {
        $token = Token::create(
            raw: 'name',
            type: TokenType::VARIABLE,
            path: ['name'],
            functionName: null,
            functionArgs: [],
            metadata: []
        );

        $result = TranslationResult::success(
            original: 'Hello {{name}}',
            translated: 'Hello John',
            tokens: [$token],
            resolvedValues: ['name' => 'John'],
            warnings: ['Warning 1'],
        );

        $array = $result->toArray();

        expect($array)->toHaveKeys([
            'success',
            'original',
            'translated',
            'tokens',
            'resolved_values',
            'missing_fields',
            'warnings',
            'errors',
            'failure_reason',
        ]);
        expect($array['success'])->toBeTrue();
        expect($array['original'])->toBe('Hello {{name}}');
        expect($array['translated'])->toBe('Hello John');
        expect($array['tokens'])->toBe(['name']);
        expect($array['resolved_values'])->toBe(['name' => 'John']);
        expect($array['warnings'])->toBe(['Warning 1']);
        expect($array['failure_reason'])->toBeNull();
    });

    it('converts failed result to array', function () {
        $result = TranslationResult::failed(
            original: 'Hello {{name}}',
            missingFields: ['name'],
            errors: ['Error 1'],
        );

        $array = $result->toArray();

        expect($array['success'])->toBeFalse();
        expect($array['translated'])->toBeNull();
        expect($array['missing_fields'])->toBe(['name']);
        expect($array['errors'])->toBe(['Error 1']);
        expect($array['failure_reason'])->toBe('Missing fields: name');
    });
});

describe('TranslationResult → Accessors', function () {
    it('returns empty arrays when not provided', function () {
        $result = TranslationResult::success('original', 'translated');

        expect($result->getTokens())->toBe([]);
        expect($result->getResolvedValues())->toBe([]);
        expect($result->getMissingFields())->toBe([]);
        expect($result->getWarnings())->toBe([]);
        expect($result->getErrors())->toBe([]);
    });
});
