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

describe('CollectionResolver → Prefix Matching', function () {
    it('returns null when prefix does not match', function () {
        $resolver = new CollectionResolver;
        $token = Token::create('Other.posts.0.title', TokenType::COLLECTION, ['Other', 'posts', '0', 'title']);
        $context = ResolutionContext::fromArray(['posts' => [['title' => 'Test']]])
            ->withPrefix('User');

        $result = $resolver->resolve($token, $context);

        expect($result)->toBeNull();
    });
});

describe('CollectionResolver → Null Handling', function () {
    it('returns null when intermediate value is null', function () {
        $resolver = new CollectionResolver;
        $context = ResolutionContext::fromArray(['posts' => null]);
        $token = Token::create('User.posts.0.title', TokenType::COLLECTION, ['User', 'posts', '0', 'title']);

        $result = $resolver->resolve($token, $context);

        expect($result)->toBeNull();
    });

    it('returns null when nested value is null', function () {
        $resolver = new CollectionResolver;
        $context = ResolutionContext::fromArray([
            'posts' => [['title' => null]],
        ]);
        $token = Token::create('User.posts.0.title', TokenType::COLLECTION, ['User', 'posts', '0', 'title']);

        $result = $resolver->resolve($token, $context);

        expect($result)->toBeNull();
    });
});

describe('CollectionResolver → Wildcard Resolution', function () {
    it('returns empty array when wildcard on non-iterable', function () {
        $resolver = new CollectionResolver;
        $context = ResolutionContext::fromArray(['posts' => 'not-iterable']);
        $token = Token::create('User.posts.*.title', TokenType::COLLECTION, ['User', 'posts', '*', 'title']);

        $result = $resolver->resolve($token, $context);

        expect($result)->toBe([]);
    });

    it('skips null values in wildcard results', function () {
        $resolver = new CollectionResolver;
        $context = ResolutionContext::fromArray([
            'posts' => [
                ['title' => 'First'],
                ['title' => null],
                ['title' => 'Third'],
            ],
        ]);
        $token = Token::create('User.posts.*.title', TokenType::COLLECTION, ['User', 'posts', '*', 'title']);

        $result = $resolver->resolve($token, $context);

        expect($result)->toBe(['First', 'Third']);
    });

    it('handles null in nested wildcard path', function () {
        $resolver = new CollectionResolver;
        $context = ResolutionContext::fromArray([
            'posts' => [
                ['meta' => ['views' => 100]],
                ['meta' => null],
                ['meta' => ['views' => 300]],
            ],
        ]);
        $token = Token::create('User.posts.*.meta.views', TokenType::COLLECTION, ['User', 'posts', '*', 'meta', 'views']);

        $result = $resolver->resolve($token, $context);

        expect($result)->toBe([100, 300]);
    });
});

describe('CollectionResolver → First/Last with Laravel Collection', function () {
    it('resolves first from Laravel Collection', function () {
        $resolver = new CollectionResolver;
        $collection = collect([
            ['title' => 'First Post'],
            ['title' => 'Second Post'],
        ]);
        $context = ResolutionContext::fromArray(['posts' => $collection]);
        $token = Token::create('User.posts.first.title', TokenType::COLLECTION, ['User', 'posts', 'first', 'title']);

        $result = $resolver->resolve($token, $context);

        expect($result)->toBe('First Post');
    });

    it('resolves last from Laravel Collection', function () {
        $resolver = new CollectionResolver;
        $collection = collect([
            ['title' => 'First Post'],
            ['title' => 'Last Post'],
        ]);
        $context = ResolutionContext::fromArray(['posts' => $collection]);
        $token = Token::create('User.posts.last.title', TokenType::COLLECTION, ['User', 'posts', 'last', 'title']);

        $result = $resolver->resolve($token, $context);

        expect($result)->toBe('Last Post');
    });
});

describe('CollectionResolver → First/Last with Iterator', function () {
    it('resolves first from iterator', function () {
        $resolver = new CollectionResolver;
        $iterator = new ArrayIterator([
            ['title' => 'First'],
            ['title' => 'Second'],
        ]);
        $context = ResolutionContext::fromArray(['posts' => $iterator]);
        $token = Token::create('User.posts.first.title', TokenType::COLLECTION, ['User', 'posts', 'first', 'title']);

        $result = $resolver->resolve($token, $context);

        expect($result)->toBe('First');
    });

    it('resolves last from iterator', function () {
        $resolver = new CollectionResolver;
        $iterator = new ArrayIterator([
            ['title' => 'First'],
            ['title' => 'Last'],
        ]);
        $context = ResolutionContext::fromArray(['posts' => $iterator]);
        $token = Token::create('User.posts.last.title', TokenType::COLLECTION, ['User', 'posts', 'last', 'title']);

        $result = $resolver->resolve($token, $context);

        expect($result)->toBe('Last');
    });

    it('returns null for first on non-collection', function () {
        $resolver = new CollectionResolver;
        $context = ResolutionContext::fromArray(['posts' => 'not-a-collection']);
        $token = Token::create('User.posts.first', TokenType::COLLECTION, ['User', 'posts', 'first']);

        $result = $resolver->resolve($token, $context);

        expect($result)->toBeNull();
    });

    it('returns null for last on non-collection', function () {
        $resolver = new CollectionResolver;
        $context = ResolutionContext::fromArray(['posts' => 'not-a-collection']);
        $token = Token::create('User.posts.last', TokenType::COLLECTION, ['User', 'posts', 'last']);

        $result = $resolver->resolve($token, $context);

        expect($result)->toBeNull();
    });
});

describe('CollectionResolver → Index with ArrayAccess', function () {
    it('resolves index from ArrayAccess object', function () {
        $resolver = new CollectionResolver;
        $obj = new ArrayObject([
            ['title' => 'First'],
            ['title' => 'Second'],
        ]);
        $context = ResolutionContext::fromArray(['posts' => $obj]);
        $token = Token::create('User.posts.1.title', TokenType::COLLECTION, ['User', 'posts', '1', 'title']);

        $result = $resolver->resolve($token, $context);

        expect($result)->toBe('Second');
    });

    it('resolves index from Laravel Collection', function () {
        $resolver = new CollectionResolver;
        $collection = collect([
            ['title' => 'First'],
            ['title' => 'Second'],
        ]);
        $context = ResolutionContext::fromArray(['posts' => $collection]);
        $token = Token::create('User.posts.0.title', TokenType::COLLECTION, ['User', 'posts', '0', 'title']);

        $result = $resolver->resolve($token, $context);

        expect($result)->toBe('First');
    });

    it('returns null for index on non-collection', function () {
        $resolver = new CollectionResolver;
        $context = ResolutionContext::fromArray(['posts' => 'not-indexable']);
        $token = Token::create('User.posts.0', TokenType::COLLECTION, ['User', 'posts', '0']);

        $result = $resolver->resolve($token, $context);

        expect($result)->toBeNull();
    });
});

describe('CollectionResolver → Object Property Access', function () {
    it('resolves property from object', function () {
        $resolver = new CollectionResolver;
        $obj = new class
        {
            public string $title = 'Object Title';
        };
        $context = ResolutionContext::fromArray(['post' => $obj]);
        $token = Token::create('User.post.title', TokenType::COLLECTION, ['User', 'post', 'title']);

        $result = $resolver->resolve($token, $context);

        expect($result)->toBe('Object Title');
    });

    it('resolves via getter method', function () {
        $resolver = new CollectionResolver;
        $obj = new class
        {
            public function getTitle(): string
            {
                return 'From Getter';
            }
        };
        $context = ResolutionContext::fromArray(['post' => $obj]);
        $token = Token::create('User.post.title', TokenType::COLLECTION, ['User', 'post', 'title']);

        $result = $resolver->resolve($token, $context);

        expect($result)->toBe('From Getter');
    });

    it('resolves from ArrayAccess object', function () {
        $resolver = new CollectionResolver;
        $obj = new ArrayObject(['title' => 'ArrayAccess Title']);
        $context = ResolutionContext::fromArray(['post' => $obj]);
        $token = Token::create('User.post.title', TokenType::COLLECTION, ['User', 'post', 'title']);

        $result = $resolver->resolve($token, $context);

        expect($result)->toBe('ArrayAccess Title');
    });

    it('returns null for non-existent property', function () {
        $resolver = new CollectionResolver;
        $obj = new class
        {
            public string $title = 'Test';
        };
        $context = ResolutionContext::fromArray(['post' => $obj]);
        $token = Token::create('User.post.missing', TokenType::COLLECTION, ['User', 'post', 'missing']);

        $result = $resolver->resolve($token, $context);

        expect($result)->toBeNull();
    });

    it('returns null for property access on non-object', function () {
        $resolver = new CollectionResolver;
        $context = ResolutionContext::fromArray(['post' => 123]);
        $token = Token::create('User.post.title', TokenType::COLLECTION, ['User', 'post', 'title']);

        $result = $resolver->resolve($token, $context);

        expect($result)->toBeNull();
    });
});

describe('CollectionResolver → Last with Countable and ArrayAccess', function () {
    it('resolves last from Countable ArrayAccess', function () {
        $resolver = new CollectionResolver;
        $obj = new ArrayObject([
            ['title' => 'First'],
            ['title' => 'Last'],
        ]);
        $context = ResolutionContext::fromArray(['posts' => $obj]);
        $token = Token::create('User.posts.last.title', TokenType::COLLECTION, ['User', 'posts', 'last', 'title']);

        $result = $resolver->resolve($token, $context);

        expect($result)->toBe('Last');
    });

    it('returns null for last on empty Countable ArrayAccess', function () {
        $resolver = new CollectionResolver;
        $obj = new ArrayObject([]);
        $context = ResolutionContext::fromArray(['posts' => $obj]);
        $token = Token::create('User.posts.last', TokenType::COLLECTION, ['User', 'posts', 'last']);

        $result = $resolver->resolve($token, $context);

        expect($result)->toBeNull();
    });
});
