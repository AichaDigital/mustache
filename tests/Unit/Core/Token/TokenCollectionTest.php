<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Token\Token;
use AichaDigital\MustacheResolver\Core\Token\TokenCollection;
use AichaDigital\MustacheResolver\Core\Token\TokenType;

beforeEach(function () {
    $this->modelToken = Token::create(
        raw: 'User.name',
        type: TokenType::MODEL,
        path: ['User', 'name'],
        functionName: null,
        functionArgs: [],
        metadata: []
    );
    $this->tableToken = Token::create(
        raw: 'users.email',
        type: TokenType::TABLE,
        path: ['users', 'email'],
        functionName: null,
        functionArgs: [],
        metadata: []
    );
    $this->variableToken = Token::create(
        raw: '$myVar',
        type: TokenType::VARIABLE,
        path: ['myVar'],
        functionName: null,
        functionArgs: [],
        metadata: []
    );
    $this->dynamicToken = Token::create(
        raw: 'User.$field',
        type: TokenType::DYNAMIC,
        path: ['User', '$field'],
        functionName: null,
        functionArgs: [],
        metadata: []
    );
});

describe('TokenCollection → Creation', function () {
    it('creates empty collection', function () {
        $collection = new TokenCollection;

        expect($collection->isEmpty())->toBeTrue();
        expect($collection->count())->toBe(0);
    });

    it('creates from array', function () {
        $collection = TokenCollection::fromArray([$this->modelToken, $this->tableToken]);

        expect($collection->count())->toBe(2);
        expect($collection->isEmpty())->toBeFalse();
    });
});

describe('TokenCollection → Add', function () {
    it('adds token immutably', function () {
        $original = new TokenCollection;
        $newCollection = $original->add($this->modelToken);

        expect($original->count())->toBe(0);
        expect($newCollection->count())->toBe(1);
    });
});

describe('TokenCollection → Access', function () {
    it('returns all tokens', function () {
        $collection = TokenCollection::fromArray([$this->modelToken, $this->tableToken]);

        expect($collection->all())->toBe([$this->modelToken, $this->tableToken]);
    });

    it('returns first token', function () {
        $collection = TokenCollection::fromArray([$this->modelToken, $this->tableToken]);

        expect($collection->first())->toBe($this->modelToken);
    });

    it('returns null for first when empty', function () {
        $collection = new TokenCollection;

        expect($collection->first())->toBeNull();
    });

    it('returns last token', function () {
        $collection = TokenCollection::fromArray([$this->modelToken, $this->tableToken]);

        expect($collection->last())->toBe($this->tableToken);
    });

    it('returns null for last when empty', function () {
        $collection = new TokenCollection;

        expect($collection->last())->toBeNull();
    });
});

describe('TokenCollection → Filtering', function () {
    it('filters by single type', function () {
        $collection = TokenCollection::fromArray([
            $this->modelToken,
            $this->tableToken,
            $this->variableToken,
        ]);

        $filtered = $collection->ofType(TokenType::MODEL);

        expect($filtered->count())->toBe(1);
        expect($filtered->first()->getType())->toBe(TokenType::MODEL);
    });

    it('filters by multiple types', function () {
        $collection = TokenCollection::fromArray([
            $this->modelToken,
            $this->tableToken,
            $this->variableToken,
        ]);

        $filtered = $collection->ofTypes([TokenType::MODEL, TokenType::TABLE]);

        expect($filtered->count())->toBe(2);
    });

    it('filters tokens requiring accessor', function () {
        $collection = TokenCollection::fromArray([
            $this->modelToken,
            $this->variableToken,
        ]);

        $filtered = $collection->requireingAccessor();

        expect($filtered->count())->toBe(1);
        expect($filtered->first()->getType())->toBe(TokenType::MODEL);
    });
});

describe('TokenCollection → Dynamic Detection', function () {
    it('detects dynamic tokens', function () {
        $collection = TokenCollection::fromArray([
            $this->modelToken,
            $this->dynamicToken,
        ]);

        expect($collection->hasDynamic())->toBeTrue();
    });

    it('returns false when no dynamic tokens', function () {
        $collection = TokenCollection::fromArray([
            $this->modelToken,
            $this->tableToken,
        ]);

        expect($collection->hasDynamic())->toBeFalse();
    });

    it('returns false for empty collection', function () {
        $collection = new TokenCollection;

        expect($collection->hasDynamic())->toBeFalse();
    });
});

describe('TokenCollection → Prefixes', function () {
    it('gets unique prefixes', function () {
        $token1 = Token::create(
            raw: 'User.name',
            type: TokenType::MODEL,
            path: ['User', 'name'],
            functionName: null,
            functionArgs: [],
            metadata: ['prefix' => 'User']
        );
        $token2 = Token::create(
            raw: 'User.email',
            type: TokenType::MODEL,
            path: ['User', 'email'],
            functionName: null,
            functionArgs: [],
            metadata: ['prefix' => 'User']
        );
        $token3 = Token::create(
            raw: 'Device.serial',
            type: TokenType::MODEL,
            path: ['Device', 'serial'],
            functionName: null,
            functionArgs: [],
            metadata: ['prefix' => 'Device']
        );

        $collection = TokenCollection::fromArray([$token1, $token2, $token3]);
        $prefixes = $collection->uniquePrefixes();

        expect($prefixes)->toHaveCount(2);
        expect($prefixes)->toContain('User');
        expect($prefixes)->toContain('Device');
    });

    it('excludes empty prefixes', function () {
        $tokenWithEmptyPath = Token::create(
            raw: '',
            type: TokenType::UNKNOWN,
            path: [],
            functionName: null,
            functionArgs: [],
            metadata: []
        );
        $collection = TokenCollection::fromArray([$tokenWithEmptyPath]);
        $prefixes = $collection->uniquePrefixes();

        expect($prefixes)->toBeEmpty();
    });
});

describe('TokenCollection → String Extraction', function () {
    it('gets raw strings', function () {
        $collection = TokenCollection::fromArray([
            $this->modelToken,
            $this->tableToken,
        ]);

        $rawStrings = $collection->getRawStrings();

        expect($rawStrings)->toBe(['User.name', 'users.email']);
    });

    it('gets full strings with braces', function () {
        $collection = TokenCollection::fromArray([
            $this->modelToken,
            $this->tableToken,
        ]);

        $fullStrings = $collection->getFullStrings();

        expect($fullStrings)->toBe(['{{User.name}}', '{{users.email}}']);
    });
});

describe('TokenCollection → Empty Checks', function () {
    it('checks isEmpty', function () {
        expect((new TokenCollection)->isEmpty())->toBeTrue();
        expect(TokenCollection::fromArray([$this->modelToken])->isEmpty())->toBeFalse();
    });

    it('checks isNotEmpty', function () {
        expect((new TokenCollection)->isNotEmpty())->toBeFalse();
        expect(TokenCollection::fromArray([$this->modelToken])->isNotEmpty())->toBeTrue();
    });
});

describe('TokenCollection → Countable', function () {
    it('implements count', function () {
        $collection = TokenCollection::fromArray([
            $this->modelToken,
            $this->tableToken,
            $this->variableToken,
        ]);

        expect(count($collection))->toBe(3);
    });
});

describe('TokenCollection → IteratorAggregate', function () {
    it('implements getIterator', function () {
        $tokens = [$this->modelToken, $this->tableToken];
        $collection = TokenCollection::fromArray($tokens);

        $iterated = [];
        foreach ($collection as $token) {
            $iterated[] = $token;
        }

        expect($iterated)->toBe($tokens);
    });
});

describe('TokenCollection → Map', function () {
    it('maps over tokens', function () {
        $collection = TokenCollection::fromArray([
            $this->modelToken,
            $this->tableToken,
        ]);

        $types = $collection->map(fn ($token) => $token->getType()->name);

        expect($types)->toBe(['MODEL', 'TABLE']);
    });
});
