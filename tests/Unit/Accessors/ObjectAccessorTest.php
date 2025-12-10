<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Accessors\ObjectAccessor;
use AichaDigital\MustacheResolver\Contracts\DataAccessorInterface;

describe('ObjectAccessor', function () {
    it('implements DataAccessorInterface', function () {
        $obj = new stdClass;
        $accessor = new ObjectAccessor($obj);

        expect($accessor)->toBeInstanceOf(DataAccessorInterface::class);
    });

    it('gets property from object', function () {
        $obj = new stdClass;
        $obj->name = 'John';

        $accessor = new ObjectAccessor($obj);

        expect($accessor->get('name'))->toBe('John');
    });

    it('gets nested property', function () {
        $nested = new stdClass;
        $nested->city = 'Madrid';

        $obj = new stdClass;
        $obj->address = $nested;

        $accessor = new ObjectAccessor($obj);

        expect($accessor->get('address.city'))->toBe('Madrid');
    });

    it('returns null for non-existent property', function () {
        $obj = new stdClass;
        $accessor = new ObjectAccessor($obj);

        expect($accessor->get('missing'))->toBeNull();
    });

    it('returns null for nested non-existent property', function () {
        $obj = new stdClass;
        $obj->address = null;

        $accessor = new ObjectAccessor($obj);

        expect($accessor->get('address.city'))->toBeNull();
    });

    it('checks if path exists', function () {
        $obj = new stdClass;
        $obj->name = 'John';

        $accessor = new ObjectAccessor($obj);

        expect($accessor->has('name'))->toBeTrue();
        expect($accessor->has('missing'))->toBeFalse();
    });

    it('returns source type', function () {
        $obj = new stdClass;
        $accessor = new ObjectAccessor($obj);

        expect($accessor->getSourceType())->toBe('object');
    });

    it('returns raw object', function () {
        $obj = new stdClass;
        $obj->name = 'John';

        $accessor = new ObjectAccessor($obj);

        expect($accessor->getRaw())->toBe($obj);
    });

    it('gets keys via object_vars', function () {
        $obj = new class
        {
            public string $name = 'John';

            public int $age = 30;
        };

        $accessor = new ObjectAccessor($obj);
        $keys = $accessor->keys();

        expect($keys)->toContain('name');
        expect($keys)->toContain('age');
    });

    it('gets keys via toArray method', function () {
        $obj = new class
        {
            public function toArray(): array
            {
                return ['name' => 'John', 'email' => 'john@example.com'];
            }
        };

        $accessor = new ObjectAccessor($obj);
        $keys = $accessor->keys();

        expect($keys)->toBe(['name', 'email']);
    });

    it('accesses via getter method', function () {
        $obj = new class
        {
            private string $internalTitle = 'My Title';

            public function getTitle(): string
            {
                return $this->internalTitle;
            }
        };

        $accessor = new ObjectAccessor($obj);

        expect($accessor->get('title'))->toBe('My Title');
    });

    it('accesses via ArrayAccess', function () {
        $obj = new ArrayObject(['name' => 'John']);

        $accessor = new ObjectAccessor($obj);

        expect($accessor->get('name'))->toBe('John');
    });

    it('accesses via magic __get', function () {
        $obj = new class
        {
            private array $data = ['name' => 'John'];

            public function __get(string $key): mixed
            {
                return $this->data[$key] ?? null;
            }
        };

        $accessor = new ObjectAccessor($obj);

        expect($accessor->get('name'))->toBe('John');
    });

    it('returns null for non-object in path', function () {
        $obj = new stdClass;
        $obj->value = 'string';

        $accessor = new ObjectAccessor($obj);

        expect($accessor->get('value.nested'))->toBeNull();
    });

    it('returns null for non-existent property on clean object', function () {
        $obj = new class
        {
            public string $name = 'John';
        };

        $accessor = new ObjectAccessor($obj);

        expect($accessor->get('missing'))->toBeNull();
    });
});
