<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Formatter\Formatters\CamelFormatter;
use AichaDigital\MustacheResolver\Core\Formatter\Formatters\ConcatFormatter;
use AichaDigital\MustacheResolver\Core\Formatter\Formatters\LowercaseFormatter;
use AichaDigital\MustacheResolver\Core\Formatter\Formatters\ReplaceFormatter;
use AichaDigital\MustacheResolver\Core\Formatter\Formatters\SlugFormatter;
use AichaDigital\MustacheResolver\Core\Formatter\Formatters\SnakeFormatter;
use AichaDigital\MustacheResolver\Core\Formatter\Formatters\SubstrFormatter;
use AichaDigital\MustacheResolver\Core\Formatter\Formatters\TitleFormatter;
use AichaDigital\MustacheResolver\Core\Formatter\Formatters\TrimFormatter;
use AichaDigital\MustacheResolver\Core\Formatter\Formatters\UppercaseFormatter;

describe('UppercaseFormatter', function () {
    beforeEach(function () {
        $this->formatter = new UppercaseFormatter;
    });

    it('has correct name', function () {
        expect($this->formatter->getName())->toBe('uppercase');
    });

    it('converts to uppercase', function () {
        expect($this->formatter->format('hello world'))->toBe('HELLO WORLD');
    });

    it('handles unicode', function () {
        expect($this->formatter->format('hëllo wörld'))->toBe('HËLLO WÖRLD');
    });
});

describe('LowercaseFormatter', function () {
    beforeEach(function () {
        $this->formatter = new LowercaseFormatter;
    });

    it('has correct name', function () {
        expect($this->formatter->getName())->toBe('lowercase');
    });

    it('converts to lowercase', function () {
        expect($this->formatter->format('HELLO WORLD'))->toBe('hello world');
    });

    it('handles unicode', function () {
        expect($this->formatter->format('HËLLO WÖRLD'))->toBe('hëllo wörld');
    });
});

describe('TrimFormatter', function () {
    beforeEach(function () {
        $this->formatter = new TrimFormatter;
    });

    it('has correct name', function () {
        expect($this->formatter->getName())->toBe('trim');
    });

    it('trims whitespace', function () {
        expect($this->formatter->format('  hello  '))->toBe('hello');
    });

    it('trims tabs and newlines', function () {
        expect($this->formatter->format("\t\nhello\t\n"))->toBe('hello');
    });
});

describe('SubstrFormatter', function () {
    beforeEach(function () {
        $this->formatter = new SubstrFormatter;
    });

    it('has correct name', function () {
        expect($this->formatter->getName())->toBe('substr');
    });

    it('extracts from start', function () {
        expect($this->formatter->format('Hello World', [0, 5]))->toBe('Hello');
    });

    it('extracts from middle', function () {
        expect($this->formatter->format('Hello World', [6]))->toBe('World');
    });

    it('handles unicode', function () {
        expect($this->formatter->format('Ñoño', [0, 2]))->toBe('Ño');
    });
});

describe('ReplaceFormatter', function () {
    beforeEach(function () {
        $this->formatter = new ReplaceFormatter;
    });

    it('has correct name', function () {
        expect($this->formatter->getName())->toBe('replace');
    });

    it('replaces occurrences', function () {
        expect($this->formatter->format('Hello World', ['World', 'Universe']))->toBe('Hello Universe');
    });

    it('replaces all occurrences', function () {
        expect($this->formatter->format('foo foo foo', ['foo', 'bar']))->toBe('bar bar bar');
    });

    it('returns original if search is empty', function () {
        expect($this->formatter->format('Hello', ['', 'x']))->toBe('Hello');
    });
});

describe('ConcatFormatter', function () {
    beforeEach(function () {
        $this->formatter = new ConcatFormatter;
    });

    it('has correct name', function () {
        expect($this->formatter->getName())->toBe('concat');
    });

    it('appends suffix', function () {
        expect($this->formatter->format('Hello', [' World']))->toBe('Hello World');
    });

    it('adds prefix and suffix', function () {
        expect($this->formatter->format('World', ['Hello ', '!']))->toBe('Hello World!');
    });

    it('returns original without arguments', function () {
        expect($this->formatter->format('Hello'))->toBe('Hello');
    });
});

describe('SlugFormatter', function () {
    beforeEach(function () {
        $this->formatter = new SlugFormatter;
    });

    it('has correct name', function () {
        expect($this->formatter->getName())->toBe('slug');
    });

    it('creates slug from text', function () {
        expect($this->formatter->format('Hello World!'))->toBe('hello-world');
    });

    it('handles special characters', function () {
        expect($this->formatter->format('Café & Résumé'))->toBe('cafe-resume');
    });

    it('handles spanish characters', function () {
        expect($this->formatter->format('Año Nuevo'))->toBe('ano-nuevo');
    });

    it('uses custom separator', function () {
        expect($this->formatter->format('Hello World', ['_']))->toBe('hello_world');
    });
});

describe('CamelFormatter', function () {
    beforeEach(function () {
        $this->formatter = new CamelFormatter;
    });

    it('has correct name', function () {
        expect($this->formatter->getName())->toBe('camel');
    });

    it('converts snake_case to camelCase', function () {
        expect($this->formatter->format('hello_world'))->toBe('helloWorld');
    });

    it('converts spaces to camelCase', function () {
        expect($this->formatter->format('hello world'))->toBe('helloWorld');
    });

    it('converts kebab-case to camelCase', function () {
        expect($this->formatter->format('hello-world'))->toBe('helloWorld');
    });
});

describe('SnakeFormatter', function () {
    beforeEach(function () {
        $this->formatter = new SnakeFormatter;
    });

    it('has correct name', function () {
        expect($this->formatter->getName())->toBe('snake');
    });

    it('converts camelCase to snake_case', function () {
        expect($this->formatter->format('helloWorld'))->toBe('hello_world');
    });

    it('converts spaces to snake_case', function () {
        expect($this->formatter->format('hello world'))->toBe('hello_world');
    });

    it('converts PascalCase to snake_case', function () {
        expect($this->formatter->format('HelloWorld'))->toBe('hello_world');
    });
});

describe('TitleFormatter', function () {
    beforeEach(function () {
        $this->formatter = new TitleFormatter;
    });

    it('has correct name', function () {
        expect($this->formatter->getName())->toBe('title');
    });

    it('converts to title case', function () {
        expect($this->formatter->format('hello world'))->toBe('Hello World');
    });

    it('handles mixed case', function () {
        expect($this->formatter->format('hELLO wORLD'))->toBe('Hello World');
    });
});
