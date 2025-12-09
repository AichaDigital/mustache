<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Context\ResolutionContext;
use AichaDigital\MustacheResolver\Core\Token\Token;
use AichaDigital\MustacheResolver\Core\Token\TokenType;
use AichaDigital\MustacheResolver\Resolvers\CollectionResolver;

beforeEach(function () {
    $this->resolver = new CollectionResolver;
    $this->data = [
        'posts' => [
            ['title' => 'First Post', 'views' => 100],
            ['title' => 'Second Post', 'views' => 200],
            ['title' => 'Third Post', 'views' => 300],
        ],
        'addresses' => [
            ['city' => 'Madrid', 'country' => 'Spain'],
            ['city' => 'Paris', 'country' => 'France'],
        ],
    ];
    $this->context = ResolutionContext::fromArray($this->data);
});

it('has correct name', function () {
    expect($this->resolver->name())->toBe('collection');
});

it('has correct priority', function () {
    expect($this->resolver->priority())->toBe(40);
});

it('supports COLLECTION token type', function () {
    $token = Token::create('User.posts.0.title', TokenType::COLLECTION, ['User', 'posts', '0', 'title']);
    expect($this->resolver->supports($token, $this->context))->toBeTrue();
});

it('resolves numeric index access', function () {
    $token = Token::create('User.posts.0.title', TokenType::COLLECTION, ['User', 'posts', '0', 'title']);
    $result = $this->resolver->resolve($token, $this->context);

    expect($result)->toBe('First Post');
});

it('resolves second index', function () {
    $token = Token::create('User.posts.1.title', TokenType::COLLECTION, ['User', 'posts', '1', 'title']);
    $result = $this->resolver->resolve($token, $this->context);

    expect($result)->toBe('Second Post');
});

it('resolves first keyword', function () {
    $token = Token::create('User.posts.first.title', TokenType::COLLECTION, ['User', 'posts', 'first', 'title']);
    $result = $this->resolver->resolve($token, $this->context);

    expect($result)->toBe('First Post');
});

it('resolves last keyword', function () {
    $token = Token::create('User.posts.last.title', TokenType::COLLECTION, ['User', 'posts', 'last', 'title']);
    $result = $this->resolver->resolve($token, $this->context);

    expect($result)->toBe('Third Post');
});

it('resolves wildcard to array of values', function () {
    $token = Token::create('User.posts.*.title', TokenType::COLLECTION, ['User', 'posts', '*', 'title']);
    $result = $this->resolver->resolve($token, $this->context);

    expect($result)->toBe(['First Post', 'Second Post', 'Third Post']);
});

it('resolves wildcard for different field', function () {
    $token = Token::create('User.addresses.*.city', TokenType::COLLECTION, ['User', 'addresses', '*', 'city']);
    $result = $this->resolver->resolve($token, $this->context);

    expect($result)->toBe(['Madrid', 'Paris']);
});

it('returns null for out of bounds index', function () {
    $token = Token::create('User.posts.99.title', TokenType::COLLECTION, ['User', 'posts', '99', 'title']);
    $result = $this->resolver->resolve($token, $this->context);

    expect($result)->toBeNull();
});

it('returns empty array for wildcard on empty collection', function () {
    $context = ResolutionContext::fromArray(['posts' => []]);
    $token = Token::create('User.posts.*.title', TokenType::COLLECTION, ['User', 'posts', '*', 'title']);
    $result = $this->resolver->resolve($token, $context);

    expect($result)->toBe([]);
});
