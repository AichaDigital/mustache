<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Context\ResolutionContext;
use AichaDigital\MustacheResolver\Core\Token\Token;
use AichaDigital\MustacheResolver\Core\Token\TokenType;
use AichaDigital\MustacheResolver\Resolvers\VariableResolver;

beforeEach(function () {
    $this->resolver = new VariableResolver;
});

it('has correct name', function () {
    expect($this->resolver->name())->toBe('variable');
});

it('has correct priority', function () {
    expect($this->resolver->priority())->toBe(60);
});

it('supports VARIABLE token type', function () {
    $token = Token::create('$myVar', TokenType::VARIABLE, ['myVar']);
    $context = ResolutionContext::fromArray([]);
    expect($this->resolver->supports($token, $context))->toBeTrue();
});

it('resolves variable from context', function () {
    $context = ResolutionContext::fromArray([])
        ->with('period', '2024-Q1');

    $token = Token::create('$period', TokenType::VARIABLE, ['period']);
    $result = $this->resolver->resolve($token, $context);

    expect($result)->toBe('2024-Q1');
});

it('resolves complex variable value', function () {
    $context = ResolutionContext::fromArray([])
        ->with('config', ['debug' => true, 'env' => 'test']);

    $token = Token::create('$config', TokenType::VARIABLE, ['config']);
    $result = $this->resolver->resolve($token, $context);

    expect($result)->toBe(['debug' => true, 'env' => 'test']);
});

it('returns null for non-existent variable', function () {
    $context = ResolutionContext::fromArray([]);
    $token = Token::create('$unknown', TokenType::VARIABLE, ['unknown']);
    $result = $this->resolver->resolve($token, $context);

    expect($result)->toBeNull();
});

it('returns null for empty path', function () {
    $context = ResolutionContext::fromArray([]);
    $token = Token::create('$', TokenType::VARIABLE, []);
    $result = $this->resolver->resolve($token, $context);

    expect($result)->toBeNull();
});

it('resolves boolean variable', function () {
    $context = ResolutionContext::fromArray([])
        ->with('enabled', true);

    $token = Token::create('$enabled', TokenType::VARIABLE, ['enabled']);
    $result = $this->resolver->resolve($token, $context);

    expect($result)->toBeTrue();
});

it('resolves null variable value', function () {
    $context = ResolutionContext::fromArray([])
        ->with('nullable', null);

    $token = Token::create('$nullable', TokenType::VARIABLE, ['nullable']);
    $result = $this->resolver->resolve($token, $context);

    expect($result)->toBeNull();
});
