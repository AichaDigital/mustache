<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Context\ResolutionContext;
use AichaDigital\MustacheResolver\Core\Token\Token;
use AichaDigital\MustacheResolver\Core\Token\TokenType;
use AichaDigital\MustacheResolver\Resolvers\ModelResolver;

beforeEach(function () {
    $this->resolver = new ModelResolver;
    $this->data = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 30,
    ];
    $this->context = ResolutionContext::fromArray($this->data);
});

it('has correct name', function () {
    expect($this->resolver->name())->toBe('model');
});

it('has correct priority', function () {
    expect($this->resolver->priority())->toBe(20);
});

it('supports MODEL token type', function () {
    $token = Token::create('User.name', TokenType::MODEL, ['User', 'name']);
    expect($this->resolver->supports($token, $this->context))->toBeTrue();
});

it('does not support other token types', function () {
    $token = Token::create('User.posts.0.title', TokenType::COLLECTION, ['User', 'posts', '0', 'title']);
    expect($this->resolver->supports($token, $this->context))->toBeFalse();
});

it('resolves simple field access', function () {
    $token = Token::create('User.name', TokenType::MODEL, ['User', 'name']);
    $result = $this->resolver->resolve($token, $this->context);

    expect($result)->toBe('John Doe');
});

it('resolves numeric field', function () {
    $token = Token::create('User.age', TokenType::MODEL, ['User', 'age']);
    $result = $this->resolver->resolve($token, $this->context);

    expect($result)->toBe(30);
});

it('returns null for non-existent field', function () {
    $token = Token::create('User.unknown', TokenType::MODEL, ['User', 'unknown']);
    $result = $this->resolver->resolve($token, $this->context);

    expect($result)->toBeNull();
});

it('returns null for empty field path', function () {
    $token = Token::create('User', TokenType::MODEL, ['User']);
    $result = $this->resolver->resolve($token, $this->context);

    expect($result)->toBeNull();
});

it('respects expected prefix when set', function () {
    $contextWithPrefix = $this->context->withPrefix('User');

    $token = Token::create('User.name', TokenType::MODEL, ['User', 'name']);
    $result = $this->resolver->resolve($token, $contextWithPrefix);
    expect($result)->toBe('John Doe');

    $wrongPrefixToken = Token::create('Customer.name', TokenType::MODEL, ['Customer', 'name']);
    $result = $this->resolver->resolve($wrongPrefixToken, $contextWithPrefix);
    expect($result)->toBeNull();
});
