<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Context\ResolutionContext;
use AichaDigital\MustacheResolver\Core\Token\Token;
use AichaDigital\MustacheResolver\Core\Token\TokenClassifier;
use AichaDigital\MustacheResolver\Core\Token\TokenType;
use AichaDigital\MustacheResolver\Exceptions\ResolutionException;
use AichaDigital\MustacheResolver\Resolvers\DynamicFieldResolver;

beforeEach(function () {
    $this->resolver = new DynamicFieldResolver;
    $this->classifier = new TokenClassifier;
});

describe('DynamicFieldResolver → Token Classification', function () {
    it('classifies dynamic field patterns', function () {
        $token = $this->classifier->classify('Device.$config.field_name');

        expect($token->getType())->toBe(TokenType::DYNAMIC);
    });
});

describe('DynamicFieldResolver → Supports', function () {
    it('supports dynamic tokens', function () {
        $token = $this->classifier->classify('Device.$config.field');
        $context = ResolutionContext::fromArray([]);

        expect($this->resolver->supports($token, $context))->toBeTrue();
    });

    it('does not support model tokens', function () {
        $token = $this->classifier->classify('User.name');
        $context = ResolutionContext::fromArray([]);

        expect($this->resolver->supports($token, $context))->toBeFalse();
    });
});

describe('DynamicFieldResolver → Resolution', function () {
    it('resolves dynamic field from context', function () {
        $token = Token::create(
            raw: 'Device.$config.field_name',
            type: TokenType::DYNAMIC,
            path: ['Device', '$config', 'field_name'],
            functionName: null,
            functionArgs: [],
            metadata: []
        );
        $context = ResolutionContext::fromArray([
            'config' => ['field_name' => 'serial'],
            'serial' => 'ABC123',
        ]);

        $result = $this->resolver->resolve($token, $context);

        expect($result)->toBe('ABC123');
    });

    it('returns null when field indicator path not found', function () {
        $token = Token::create(
            raw: 'Device.$config.missing_field',
            type: TokenType::DYNAMIC,
            path: ['Device', '$config', 'missing_field'],
            functionName: null,
            functionArgs: [],
            metadata: []
        );
        $context = ResolutionContext::fromArray([
            'config' => [],
        ]);

        $result = $this->resolver->resolve($token, $context);

        expect($result)->toBeNull();
    });

    it('returns null when no dynamic segment found', function () {
        $token = Token::create(
            raw: 'Device.regular.path',
            type: TokenType::DYNAMIC,
            path: ['Device', 'regular', 'path'],
            functionName: null,
            functionArgs: [],
            metadata: []
        );
        $context = ResolutionContext::fromArray([]);

        $result = $this->resolver->resolve($token, $context);

        expect($result)->toBeNull();
    });

    it('throws when field indicator resolves to non-string', function () {
        $token = Token::create(
            raw: 'Device.$config.field_name',
            type: TokenType::DYNAMIC,
            path: ['Device', '$config', 'field_name'],
            functionName: null,
            functionArgs: [],
            metadata: []
        );
        $context = ResolutionContext::fromArray([
            'config' => ['field_name' => 123],
        ]);

        expect(fn () => $this->resolver->resolve($token, $context))
            ->toThrow(ResolutionException::class, 'Dynamic field indicator must resolve to string');
    });

    it('throws when field indicator resolves to array', function () {
        $token = Token::create(
            raw: 'Device.$config.field_name',
            type: TokenType::DYNAMIC,
            path: ['Device', '$config', 'field_name'],
            functionName: null,
            functionArgs: [],
            metadata: []
        );
        $context = ResolutionContext::fromArray([
            'config' => ['field_name' => ['nested' => 'array']],
        ]);

        expect(fn () => $this->resolver->resolve($token, $context))
            ->toThrow(ResolutionException::class, 'Dynamic field indicator must resolve to string');
    });

    it('resolves nested dynamic field path', function () {
        $token = Token::create(
            raw: 'Device.$manufacturer.parameter.value',
            type: TokenType::DYNAMIC,
            path: ['Device', '$manufacturer', 'parameter', 'value'],
            functionName: null,
            functionArgs: [],
            metadata: []
        );
        $context = ResolutionContext::fromArray([
            'manufacturer' => [
                'parameter' => ['value' => 'imei'],
            ],
            'imei' => 'IMEI12345',
        ]);

        $result = $this->resolver->resolve($token, $context);

        expect($result)->toBe('IMEI12345');
    });
});

describe('DynamicFieldResolver → Priority and Name', function () {
    it('has correct priority', function () {
        expect($this->resolver->priority())->toBe(50);
    });

    it('has correct name', function () {
        expect($this->resolver->name())->toBe('dynamic');
    });
});
