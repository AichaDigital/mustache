<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Token\Token;
use AichaDigital\MustacheResolver\Core\Token\TokenType;
use AichaDigital\MustacheResolver\Exceptions\MustacheException;
use AichaDigital\MustacheResolver\Exceptions\ResolutionException;

describe('ResolutionException', function () {
    it('extends MustacheException', function () {
        $exception = new ResolutionException('Resolution error');

        expect($exception)->toBeInstanceOf(MustacheException::class);
    });

    it('creates with message only', function () {
        $exception = new ResolutionException('Resolution error');

        expect($exception->getMessage())->toBe('Resolution error');
        expect($exception->getToken())->toBeNull();
    });

    it('creates with token', function () {
        $token = Token::create(
            raw: 'User.name',
            type: TokenType::MODEL,
            path: ['User', 'name'],
            functionName: null,
            functionArgs: [],
            metadata: []
        );

        $exception = new ResolutionException('Resolution error', $token);

        expect($exception->getToken())->toBe($token);
    });

    it('creates forToken with formatted message', function () {
        $token = Token::create(
            raw: 'User.name',
            type: TokenType::MODEL,
            path: ['User', 'name'],
            functionName: null,
            functionArgs: [],
            metadata: []
        );

        $exception = ResolutionException::forToken($token, 'Field not found');

        expect($exception->getMessage())->toBe("Failed to resolve token 'User.name': Field not found");
        expect($exception->getToken())->toBe($token);
    });

    it('forToken includes token raw string', function () {
        $token = Token::create(
            raw: 'Device.$config.field',
            type: TokenType::DYNAMIC,
            path: ['Device', '$config', 'field'],
            functionName: null,
            functionArgs: [],
            metadata: []
        );

        $exception = ResolutionException::forToken($token, 'Dynamic resolution failed');

        expect($exception->getMessage())->toContain('Device.$config.field');
        expect($exception->getMessage())->toContain('Dynamic resolution failed');
    });
});
