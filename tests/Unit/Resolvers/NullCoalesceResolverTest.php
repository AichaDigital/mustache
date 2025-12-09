<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Context\ResolutionContext;
use AichaDigital\MustacheResolver\Core\Token\Token;
use AichaDigital\MustacheResolver\Core\Token\TokenType;
use AichaDigital\MustacheResolver\Resolvers\NullCoalesceResolver;

describe('NullCoalesceResolver', function () {
    beforeEach(function () {
        $this->resolver = new NullCoalesceResolver;
        $this->data = [
            'name' => 'John',
            'nickname' => null,
            'email' => 'john@example.com',
        ];
        $this->context = ResolutionContext::fromArray($this->data);
    });

    it('has correct name', function () {
        expect($this->resolver->name())->toBe('null_coalesce');
    });

    it('has correct priority', function () {
        expect($this->resolver->priority())->toBe(90);
    });

    it('supports NULL_COALESCE token type', function () {
        $token = Token::create(
            "User.name ?? 'default'",
            TokenType::NULL_COALESCE,
            ['User', 'name'],
            defaultValue: 'default'
        );
        expect($this->resolver->supports($token, $this->context))->toBeTrue();
    });

    it('returns value when not null', function () {
        $token = Token::create(
            "User.name ?? 'Anonymous'",
            TokenType::NULL_COALESCE,
            ['User', 'name'],
            defaultValue: 'Anonymous'
        );
        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBe('John');
    });

    it('returns default when value is null', function () {
        $token = Token::create(
            "User.nickname ?? 'No nickname'",
            TokenType::NULL_COALESCE,
            ['User', 'nickname'],
            defaultValue: 'No nickname'
        );
        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBe('No nickname');
    });

    it('returns default when path does not exist', function () {
        $token = Token::create(
            "User.unknown ?? 'fallback'",
            TokenType::NULL_COALESCE,
            ['User', 'unknown'],
            defaultValue: 'fallback'
        );
        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBe('fallback');
    });

    it('returns null default as string', function () {
        $token = Token::create(
            "User.unknown ?? ''",
            TokenType::NULL_COALESCE,
            ['User', 'unknown'],
            defaultValue: ''
        );
        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBe('');
    });
});
