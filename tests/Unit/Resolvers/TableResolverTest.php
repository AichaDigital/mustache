<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Context\ResolutionContext;
use AichaDigital\MustacheResolver\Core\Token\Token;
use AichaDigital\MustacheResolver\Core\Token\TokenClassifier;
use AichaDigital\MustacheResolver\Core\Token\TokenType;
use AichaDigital\MustacheResolver\Resolvers\TableResolver;

beforeEach(function () {
    $this->resolver = new TableResolver;
    $this->classifier = new TokenClassifier;
});

describe('TableResolver → Token Classification', function () {
    it('classifies table access patterns', function () {
        $token = $this->classifier->classify('users.email');

        expect($token->getType())->toBe(TokenType::TABLE);
    });

    it('classifies snake_case table patterns', function () {
        $token = $this->classifier->classify('user_profiles.avatar');

        expect($token->getType())->toBe(TokenType::TABLE);
    });
});

describe('TableResolver → Supports', function () {
    it('supports table tokens', function () {
        $token = $this->classifier->classify('users.email');
        $context = ResolutionContext::fromArray([]);

        expect($this->resolver->supports($token, $context))->toBeTrue();
    });

    it('does not support model tokens', function () {
        $token = $this->classifier->classify('User.name');
        $context = ResolutionContext::fromArray([]);

        expect($this->resolver->supports($token, $context))->toBeFalse();
    });
});

describe('TableResolver → Resolution', function () {
    it('resolves simple table field', function () {
        $token = Token::create(
            raw: 'users.email',
            type: TokenType::TABLE,
            path: ['users', 'email'],
            functionName: null,
            functionArgs: [],
            metadata: []
        );
        $context = ResolutionContext::fromArray([
            'users' => ['email' => 'test@example.com'],
        ]);

        $result = $this->resolver->resolve($token, $context);

        expect($result)->toBe('test@example.com');
    });

    it('resolves snake_case table field', function () {
        $token = Token::create(
            raw: 'user_profiles.first_name',
            type: TokenType::TABLE,
            path: ['user_profiles', 'first_name'],
            functionName: null,
            functionArgs: [],
            metadata: []
        );
        $context = ResolutionContext::fromArray([
            'user_profiles' => ['first_name' => 'John'],
        ]);

        $result = $this->resolver->resolve($token, $context);

        expect($result)->toBe('John');
    });

    it('returns null for empty field path', function () {
        $token = Token::create(
            raw: 'users',
            type: TokenType::TABLE,
            path: ['users'],
            functionName: null,
            functionArgs: [],
            metadata: []
        );
        $context = ResolutionContext::fromArray([]);

        $result = $this->resolver->resolve($token, $context);

        expect($result)->toBeNull();
    });

    it('returns null for non-existent table', function () {
        $token = Token::create(
            raw: 'non_existent.field',
            type: TokenType::TABLE,
            path: ['non_existent', 'field'],
            functionName: null,
            functionArgs: [],
            metadata: []
        );
        $context = ResolutionContext::fromArray([]);

        $result = $this->resolver->resolve($token, $context);

        expect($result)->toBeNull();
    });

    it('returns null for non-existent field', function () {
        $token = Token::create(
            raw: 'users.missing_field',
            type: TokenType::TABLE,
            path: ['users', 'missing_field'],
            functionName: null,
            functionArgs: [],
            metadata: []
        );
        $context = ResolutionContext::fromArray([
            'users' => ['email' => 'test@example.com'],
        ]);

        $result = $this->resolver->resolve($token, $context);

        expect($result)->toBeNull();
    });

    it('resolves nested table data', function () {
        $token = Token::create(
            raw: 'orders.item.name',
            type: TokenType::TABLE,
            path: ['orders', 'item', 'name'],
            functionName: null,
            functionArgs: [],
            metadata: []
        );
        $context = ResolutionContext::fromArray([
            'orders' => [
                'item' => ['name' => 'Product A'],
            ],
        ]);

        $result = $this->resolver->resolve($token, $context);

        expect($result)->toBe('Product A');
    });
});

describe('TableResolver → Priority and Name', function () {
    it('has correct priority', function () {
        expect($this->resolver->priority())->toBe(10);
    });

    it('has correct name', function () {
        expect($this->resolver->name())->toBe('table');
    });
});
