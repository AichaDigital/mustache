<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Contracts\FormatterInterface;
use AichaDigital\MustacheResolver\Core\Formatter\FormatterRegistry;
use AichaDigital\MustacheResolver\Core\Formatter\Formatters\ToIntFormatter;
use AichaDigital\MustacheResolver\Core\Formatter\Formatters\UppercaseFormatter;
use AichaDigital\MustacheResolver\Exceptions\FormatterException;

beforeEach(function () {
    $this->registry = new FormatterRegistry;
});

describe('FormatterRegistry → registration', function () {
    it('registers allowed formatters', function () {
        $formatter = new ToIntFormatter;

        $this->registry->register($formatter);

        expect($this->registry->has('toInt'))->toBeTrue();
    });

    it('throws on non-allowed formatter', function () {
        $formatter = new class implements FormatterInterface
        {
            public function getName(): string
            {
                return 'customFormatter';
            }

            public function format(mixed $value, array $arguments = []): mixed
            {
                return $value;
            }

            public function supports(mixed $value): bool
            {
                return true;
            }
        };

        expect(fn () => $this->registry->register($formatter))
            ->toThrow(FormatterException::class, 'not in the allowed list');
    });

    it('allows fluent registration', function () {
        $result = $this->registry->register(new ToIntFormatter);

        expect($result)->toBe($this->registry);
    });
});

describe('FormatterRegistry → retrieval', function () {
    it('retrieves registered formatter', function () {
        $formatter = new UppercaseFormatter;
        $this->registry->register($formatter);

        $retrieved = $this->registry->get('uppercase');

        expect($retrieved)->toBe($formatter);
    });

    it('throws on unregistered formatter', function () {
        expect(fn () => $this->registry->get('toInt'))
            ->toThrow(FormatterException::class, 'not registered');
    });

    it('throws on not allowed formatter', function () {
        expect(fn () => $this->registry->get('invalidName'))
            ->toThrow(FormatterException::class, 'not in the allowed list');
    });
});

describe('FormatterRegistry → isAllowed', function () {
    it('returns true for allowed formatters', function () {
        expect($this->registry->isAllowed('toInt'))->toBeTrue();
        expect($this->registry->isAllowed('uppercase'))->toBeTrue();
        expect($this->registry->isAllowed('formatDate'))->toBeTrue();
    });

    it('returns false for non-allowed formatters', function () {
        expect($this->registry->isAllowed('custom'))->toBeFalse();
        expect($this->registry->isAllowed('invalid'))->toBeFalse();
    });
});

describe('FormatterRegistry → apply', function () {
    it('applies formatter to value', function () {
        $this->registry->register(new UppercaseFormatter);

        $result = $this->registry->apply('uppercase', 'hello');

        expect($result)->toBe('HELLO');
    });

    it('applies formatter with arguments', function () {
        $this->registry->register(new ToIntFormatter);

        $result = $this->registry->apply('toInt', '42');

        expect($result)->toBe(42);
    });

    it('throws on unsupported type', function () {
        $formatter = new class implements FormatterInterface
        {
            public function getName(): string
            {
                return 'toInt';
            }

            public function format(mixed $value, array $arguments = []): mixed
            {
                return (int) $value;
            }

            public function supports(mixed $value): bool
            {
                return is_string($value);
            }
        };

        $registry = new FormatterRegistry;
        $registry->register($formatter);

        expect(fn () => $registry->apply('toInt', ['array']))
            ->toThrow(FormatterException::class, 'does not support');
    });
});

describe('FormatterRegistry → withBuiltins', function () {
    it('creates registry with all built-in formatters', function () {
        $registry = FormatterRegistry::withBuiltins();

        expect($registry->count())->toBe(26);
    });

    it('has all date/time formatters', function () {
        $registry = FormatterRegistry::withBuiltins();

        expect($registry->has('toTimeString'))->toBeTrue();
        expect($registry->has('toDateString'))->toBeTrue();
        expect($registry->has('toDateTime'))->toBeTrue();
        expect($registry->has('toUnixTime'))->toBeTrue();
        expect($registry->has('toIso8601'))->toBeTrue();
        expect($registry->has('formatDate'))->toBeTrue();
    });

    it('has all numeric formatters', function () {
        $registry = FormatterRegistry::withBuiltins();

        expect($registry->has('toInt'))->toBeTrue();
        expect($registry->has('toFloat'))->toBeTrue();
        expect($registry->has('toCents'))->toBeTrue();
        expect($registry->has('fromCents'))->toBeTrue();
        expect($registry->has('round'))->toBeTrue();
        expect($registry->has('floor'))->toBeTrue();
        expect($registry->has('ceil'))->toBeTrue();
        expect($registry->has('number'))->toBeTrue();
        expect($registry->has('percent'))->toBeTrue();
        expect($registry->has('abs'))->toBeTrue();
    });

    it('has all string formatters', function () {
        $registry = FormatterRegistry::withBuiltins();

        expect($registry->has('uppercase'))->toBeTrue();
        expect($registry->has('lowercase'))->toBeTrue();
        expect($registry->has('trim'))->toBeTrue();
        expect($registry->has('substr'))->toBeTrue();
        expect($registry->has('replace'))->toBeTrue();
        expect($registry->has('concat'))->toBeTrue();
        expect($registry->has('slug'))->toBeTrue();
        expect($registry->has('camel'))->toBeTrue();
        expect($registry->has('snake'))->toBeTrue();
        expect($registry->has('title'))->toBeTrue();
    });
});

describe('FormatterRegistry → metadata', function () {
    it('returns registered names', function () {
        $this->registry->register(new ToIntFormatter);
        $this->registry->register(new UppercaseFormatter);

        $names = $this->registry->getRegisteredNames();

        expect($names)->toContain('toInt');
        expect($names)->toContain('uppercase');
    });

    it('returns all allowed names', function () {
        $allowed = $this->registry->getAllowedNames();

        expect($allowed)->toContain('toInt');
        expect($allowed)->toContain('uppercase');
        expect($allowed)->toContain('formatDate');
        expect(count($allowed))->toBe(26);
    });

    it('counts registered formatters', function () {
        expect($this->registry->count())->toBe(0);

        $this->registry->register(new ToIntFormatter);
        expect($this->registry->count())->toBe(1);

        $this->registry->register(new UppercaseFormatter);
        expect($this->registry->count())->toBe(2);
    });
});
