<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Accessors\ArrayAccessor;
use AichaDigital\MustacheResolver\Contracts\ContextInterface;
use AichaDigital\MustacheResolver\Contracts\DataAccessorInterface;
use AichaDigital\MustacheResolver\Core\Context\ResolutionContext;

describe('ResolutionContext → Creation', function () {
    it('implements ContextInterface', function () {
        $context = ResolutionContext::fromArray([]);

        expect($context)->toBeInstanceOf(ContextInterface::class);
    });

    it('creates from array', function () {
        $context = ResolutionContext::fromArray(['name' => 'John']);

        expect($context->get('name'))->toBe('John');
    });

    it('creates from accessor', function () {
        $accessor = new ArrayAccessor(['name' => 'John']);
        $context = ResolutionContext::create($accessor);

        expect($context->get('name'))->toBe('John');
    });
});

describe('ResolutionContext → Get and Has', function () {
    it('gets value from accessor', function () {
        $context = ResolutionContext::fromArray(['name' => 'John']);

        expect($context->get('name'))->toBe('John');
    });

    it('gets value from variables first', function () {
        $context = ResolutionContext::fromArray(['name' => 'John'])
            ->with('name', 'Jane');

        expect($context->get('name'))->toBe('Jane');
    });

    it('returns null for non-existent key', function () {
        $context = ResolutionContext::fromArray([]);

        expect($context->get('missing'))->toBeNull();
    });

    it('checks if key exists in accessor', function () {
        $context = ResolutionContext::fromArray(['name' => 'John']);

        expect($context->has('name'))->toBeTrue();
        expect($context->has('missing'))->toBeFalse();
    });

    it('checks if key exists in variables', function () {
        $context = ResolutionContext::fromArray([])
            ->with('custom', 'value');

        expect($context->has('custom'))->toBeTrue();
    });
});

describe('ResolutionContext → With Methods', function () {
    it('adds variable immutably', function () {
        $original = ResolutionContext::fromArray([]);
        $modified = $original->with('key', 'value');

        expect($original->has('key'))->toBeFalse();
        expect($modified->get('key'))->toBe('value');
    });

    it('changes accessor immutably', function () {
        $original = ResolutionContext::fromArray(['name' => 'John']);
        $newAccessor = new ArrayAccessor(['name' => 'Jane']);
        $modified = $original->withAccessor($newAccessor);

        expect($original->get('name'))->toBe('John');
        expect($modified->get('name'))->toBe('Jane');
    });

    it('changes strict mode immutably', function () {
        $original = ResolutionContext::fromArray([]);
        $modified = $original->withStrict(false);

        expect($original->isStrict())->toBeTrue();
        expect($modified->isStrict())->toBeFalse();
    });

    it('changes prefix immutably', function () {
        $original = ResolutionContext::fromArray([]);
        $modified = $original->withPrefix('User');

        expect($original->getExpectedPrefix())->toBeNull();
        expect($modified->getExpectedPrefix())->toBe('User');
    });

    it('adds config immutably', function () {
        $original = ResolutionContext::fromArray([]);
        $modified = $original->withConfig(['max_depth' => 5]);

        expect($original->config('max_depth'))->toBeNull();
        expect($modified->config('max_depth'))->toBe(5);
    });

    it('merges config when adding', function () {
        $original = ResolutionContext::fromArray([])
            ->withConfig(['key1' => 'value1']);
        $modified = $original->withConfig(['key2' => 'value2']);

        expect($modified->config('key1'))->toBe('value1');
        expect($modified->config('key2'))->toBe('value2');
    });
});

describe('ResolutionContext → Accessors', function () {
    it('returns accessor', function () {
        $accessor = new ArrayAccessor(['name' => 'John']);
        $context = ResolutionContext::create($accessor);

        expect($context->getAccessor())->toBe($accessor);
        expect($context->getAccessor())->toBeInstanceOf(DataAccessorInterface::class);
    });

    it('returns variables', function () {
        $context = ResolutionContext::fromArray([])
            ->with('key1', 'value1')
            ->with('key2', 'value2');

        $variables = $context->getVariables();

        expect($variables)->toBe(['key1' => 'value1', 'key2' => 'value2']);
    });

    it('returns strict status', function () {
        $context = ResolutionContext::fromArray([]);

        expect($context->isStrict())->toBeTrue();
    });

    it('returns expected prefix', function () {
        $context = ResolutionContext::fromArray([])
            ->withPrefix('Model');

        expect($context->getExpectedPrefix())->toBe('Model');
    });
});

describe('ResolutionContext → Config', function () {
    it('returns default for missing config', function () {
        $context = ResolutionContext::fromArray([]);

        expect($context->config('missing', 'default'))->toBe('default');
    });

    it('returns config value', function () {
        $context = ResolutionContext::fromArray([])
            ->withConfig(['setting' => 'value']);

        expect($context->config('setting'))->toBe('value');
    });

    it('returns null for missing config without default', function () {
        $context = ResolutionContext::fromArray([]);

        expect($context->config('missing'))->toBeNull();
    });
});
