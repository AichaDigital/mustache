<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Compound\CompoundExpressionParser;
use AichaDigital\MustacheResolver\Exceptions\InvalidUseSyntaxException;

beforeEach(function () {
    $this->parser = new CompoundExpressionParser;
});

describe('CompoundExpressionParser → isCompound', function () {
    it('detects compound expressions', function () {
        expect($this->parser->isCompound('USE {var} => {{expr}} && SELECT *'))
            ->toBeTrue();
    });

    it('detects compound with leading whitespace', function () {
        expect($this->parser->isCompound('  USE {var} => {{expr}} && SELECT *'))
            ->toBeTrue();
    });

    it('returns false for non-compound templates', function () {
        expect($this->parser->isCompound('SELECT * FROM users'))
            ->toBeFalse();

        expect($this->parser->isCompound('{{User.name}}'))
            ->toBeFalse();

        expect($this->parser->isCompound('USING something'))
            ->toBeFalse();
    });
});

describe('CompoundExpressionParser → parse', function () {
    it('parses simple compound expression', function () {
        $template = 'USE {max_power} => {{CommandCenter.max_power}} && SELECT * WHERE power < {max_power}';

        $result = $this->parser->parse($template);

        expect($result->getVariables())->toHaveCount(1);
        expect($result->getStatement())->toBe('SELECT * WHERE power < {max_power}');
        expect($result->getOriginal())->toBe($template);
    });

    it('parses variable name correctly', function () {
        $template = 'USE {my_variable} => {{Model.field}} && statement';

        $result = $this->parser->parse($template);

        expect($result->getVariables()[0]->getName())->toBe('my_variable');
    });

    it('parses mustache expression correctly', function () {
        $template = 'USE {var} => {{CommandCenter.operationalSunset.timestamp}} && statement';

        $result = $this->parser->parse($template);

        expect($result->getVariables()[0]->getExpression())
            ->toBe('{{CommandCenter.operationalSunset.timestamp}}');
    });

    it('parses condition correctly', function () {
        $template = 'USE {max_power} => {{CommandCenter.max_power}} > 0 && SELECT *';

        $result = $this->parser->parse($template);

        $variable = $result->getVariables()[0];
        expect($variable->hasCondition())->toBeTrue();
        expect($variable->getCondition())->toBe('> 0');
    });

    it('parses multiple variables', function () {
        $template = 'USE {var1} => {{Model.field1}}, {var2} => {{Model.field2}} && SELECT *';

        $result = $this->parser->parse($template);

        expect($result->getVariables())->toHaveCount(2);
        expect($result->getVariableNames())->toBe(['var1', 'var2']);
    });

    it('parses multiple variables with conditions', function () {
        $template = 'USE {power} => {{CC.power}} > 0, {sunset} => {{CC.sunset}} >= 0 && SELECT *';

        $result = $this->parser->parse($template);

        expect($result->getVariables())->toHaveCount(2);
        expect($result->getVariables()[0]->getCondition())->toBe('> 0');
        expect($result->getVariables()[1]->getCondition())->toBe('>= 0');
    });

    it('parses BETWEEN condition', function () {
        $template = 'USE {value} => {{Model.value}} BETWEEN 1 AND 100 && SELECT *';

        $result = $this->parser->parse($template);

        expect($result->getVariables()[0]->getCondition())->toBe('BETWEEN 1 AND 100');
    });

    it('parses mixed variables with and without conditions', function () {
        $template = 'USE {id} => {{Model.id}}, {power} => {{CC.power}} > 0 && SELECT *';

        $result = $this->parser->parse($template);

        expect($result->getVariables()[0]->hasCondition())->toBeFalse();
        expect($result->getVariables()[1]->hasCondition())->toBeTrue();
    });

    it('parses complex real-world example', function () {
        $template = 'USE {max_power} => {{CommandCenter.max_power}} > 0 && SELECT log_id FROM "work-analyzers" WHERE power < ({max_power} * 0.9)';

        $result = $this->parser->parse($template);

        expect($result->getVariables())->toHaveCount(1);
        expect($result->getVariables()[0]->getName())->toBe('max_power');
        expect($result->getVariables()[0]->getCondition())->toBe('> 0');
        expect($result->getStatement())->toContain('work-analyzers');
    });
});

describe('CompoundExpressionParser → parse errors', function () {
    it('throws on non-USE template', function () {
        expect(fn () => $this->parser->parse('SELECT * FROM users'))
            ->toThrow(InvalidUseSyntaxException::class);
    });

    it('throws on missing && separator', function () {
        expect(fn () => $this->parser->parse('USE {var} => {{expr}} SELECT *'))
            ->toThrow(InvalidUseSyntaxException::class, 'Missing "&&" separator');
    });

    it('throws on empty statement', function () {
        expect(fn () => $this->parser->parse('USE {var} => {{expr}} &&'))
            ->toThrow(InvalidUseSyntaxException::class, 'cannot be empty');
    });

    it('throws on invalid variable declaration', function () {
        expect(fn () => $this->parser->parse('USE invalid && SELECT *'))
            ->toThrow(InvalidUseSyntaxException::class, 'Invalid variable declaration');
    });

    it('throws on duplicate variable names', function () {
        expect(fn () => $this->parser->parse('USE {var} => {{a}}, {var} => {{b}} && SELECT *'))
            ->toThrow(InvalidUseSyntaxException::class, 'Duplicate variable name');
    });
});

describe('CompoundExpressionParser → extractLocalVariables', function () {
    it('extracts local variables from statement', function () {
        $statement = 'SELECT * WHERE power < {max_power} AND id = {log_id}';

        $result = $this->parser->extractLocalVariables($statement);

        expect($result)->toBe(['max_power', 'log_id']);
    });

    it('returns unique variables', function () {
        $statement = '{var} + {var} = {other}';

        $result = $this->parser->extractLocalVariables($statement);

        expect($result)->toContain('var');
        expect($result)->toContain('other');
        expect(count($result))->toBe(2);
    });

    it('returns empty array for no variables', function () {
        $statement = 'SELECT * FROM users';

        $result = $this->parser->extractLocalVariables($statement);

        expect($result)->toBe([]);
    });

    it('does not match mustache expressions', function () {
        $statement = '{{Model.field}} and {local_var}';

        $result = $this->parser->extractLocalVariables($statement);

        expect($result)->toBe(['local_var']);
    });
});

describe('CompoundExpressionParser → findUndeclaredVariables', function () {
    it('finds undeclared variables', function () {
        $declared = ['var1', 'var2'];
        $used = ['var1', 'var2', 'var3'];

        $result = $this->parser->findUndeclaredVariables($declared, $used);

        expect($result)->toContain('var3');
        expect(count($result))->toBe(1);
    });

    it('returns empty when all declared', function () {
        $declared = ['var1', 'var2'];
        $used = ['var1'];

        $result = $this->parser->findUndeclaredVariables($declared, $used);

        expect($result)->toBe([]);
    });
});
