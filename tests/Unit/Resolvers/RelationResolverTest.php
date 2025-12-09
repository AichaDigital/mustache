<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Context\ResolutionContext;
use AichaDigital\MustacheResolver\Core\Token\Token;
use AichaDigital\MustacheResolver\Core\Token\TokenType;
use AichaDigital\MustacheResolver\Resolvers\RelationResolver;

describe('RelationResolver', function () {
    beforeEach(function () {
        $this->resolver = new RelationResolver;
        $this->data = [
            'department' => [
                'name' => 'Engineering',
                'code' => 'ENG',
                'manager' => [
                    'name' => 'Jane Smith',
                    'email' => 'jane@example.com',
                ],
            ],
            'posts' => [
                ['title' => 'First Post'],
                ['title' => 'Second Post'],
            ],
        ];
        $this->context = ResolutionContext::fromArray($this->data);
    });

    it('has correct name', function () {
        expect($this->resolver->name())->toBe('relation');
    });

    it('has correct priority', function () {
        expect($this->resolver->priority())->toBe(30);
    });

    it('supports RELATION token type', function () {
        $token = Token::create(
            'User.department.manager.name',
            TokenType::RELATION,
            ['User', 'department', 'manager', 'name']
        );
        expect($this->resolver->supports($token, $this->context))->toBeTrue();
    });

    it('resolves two-level relation', function () {
        $token = Token::create(
            'User.department.name',
            TokenType::RELATION,
            ['User', 'department', 'name']
        );
        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBe('Engineering');
    });

    it('resolves deep relation chain', function () {
        $token = Token::create(
            'User.department.manager.name',
            TokenType::RELATION,
            ['User', 'department', 'manager', 'name']
        );
        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBe('Jane Smith');
    });

    it('returns null for insufficient path segments', function () {
        $token = Token::create('User.name', TokenType::RELATION, ['User', 'name']);
        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBeNull();
    });

    it('returns null for non-existent relation', function () {
        $token = Token::create(
            'User.company.name',
            TokenType::RELATION,
            ['User', 'company', 'name']
        );
        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBeNull();
    });
});
