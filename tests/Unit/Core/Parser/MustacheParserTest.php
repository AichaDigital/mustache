<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Parser\MustacheParser;
use AichaDigital\MustacheResolver\Core\Token\TokenType;
use AichaDigital\MustacheResolver\Exceptions\InvalidSyntaxException;

describe('MustacheParser', function () {
    beforeEach(function () {
        $this->parser = new MustacheParser;
    });

    describe('hasMustaches', function () {
        it('detects mustaches in template', function () {
            expect($this->parser->hasMustaches('Hello {{User.name}}!'))->toBeTrue();
        });

        it('returns false for no mustaches', function () {
            expect($this->parser->hasMustaches('Hello World!'))->toBeFalse();
        });

        it('detects multiple mustaches', function () {
            expect($this->parser->hasMustaches('{{a}} and {{b}}'))->toBeTrue();
        });
    });

    describe('extractRaw', function () {
        it('extracts single mustache', function () {
            $result = $this->parser->extractRaw('Hello {{User.name}}!');

            expect($result)->toBe(['{{User.name}}']);
        });

        it('extracts multiple mustaches', function () {
            $result = $this->parser->extractRaw('{{a}} and {{b}}');

            expect($result)->toBe(['{{a}}', '{{b}}']);
        });

        it('returns empty for no mustaches', function () {
            $result = $this->parser->extractRaw('Hello World!');

            expect($result)->toBe([]);
        });
    });

    describe('parse', function () {
        it('parses simple model token', function () {
            $tokens = $this->parser->parse('Hello {{User.name}}!');

            expect($tokens)->toHaveCount(1);
            expect($tokens[0]->getRaw())->toBe('User.name');
            expect($tokens[0]->getType())->toBe(TokenType::MODEL);
        });

        it('parses multiple tokens', function () {
            $tokens = $this->parser->parse('{{User.name}} - {{User.email}}');

            expect($tokens)->toHaveCount(2);
            expect($tokens[0]->getRaw())->toBe('User.name');
            expect($tokens[1]->getRaw())->toBe('User.email');
        });

        it('parses relation token', function () {
            $tokens = $this->parser->parse('Manager: {{User.department.manager.name}}');

            expect($tokens)->toHaveCount(1);
            expect($tokens[0]->getType())->toBe(TokenType::RELATION);
        });

        it('parses function token', function () {
            $tokens = $this->parser->parse('Date: {{now()}}');

            expect($tokens)->toHaveCount(1);
            expect($tokens[0]->getType())->toBe(TokenType::FUNCTION);
            expect($tokens[0]->getFunctionName())->toBe('now');
        });

        it('parses variable token', function () {
            $tokens = $this->parser->parse('Period: {{$period}}');

            expect($tokens)->toHaveCount(1);
            expect($tokens[0]->getType())->toBe(TokenType::VARIABLE);
        });

        it('parses null coalesce token', function () {
            $tokens = $this->parser->parse("Name: {{User.nickname ?? 'Anonymous'}}");

            expect($tokens)->toHaveCount(1);
            expect($tokens[0]->getType())->toBe(TokenType::NULL_COALESCE);
            expect($tokens[0]->getDefaultValue())->toBe('Anonymous');
        });

        it('parses collection token', function () {
            $tokens = $this->parser->parse('First: {{User.posts.0.title}}');

            expect($tokens)->toHaveCount(1);
            expect($tokens[0]->getType())->toBe(TokenType::COLLECTION);
        });

        it('returns empty for no mustaches', function () {
            $tokens = $this->parser->parse('Plain text');

            expect($tokens)->toBe([]);
        });
    });

    describe('syntax validation', function () {
        it('throws on unclosed mustache', function () {
            expect(fn () => $this->parser->parse('Hello {{User.name!'))
                ->toThrow(InvalidSyntaxException::class);
        });

        it('throws on empty mustache', function () {
            expect(fn () => $this->parser->parse('Hello {{}}!'))
                ->toThrow(InvalidSyntaxException::class);
        });

        it('throws on nested mustaches', function () {
            expect(fn () => $this->parser->parse('Hello {{{{nested}}}}!'))
                ->toThrow(InvalidSyntaxException::class);
        });
    });
});
