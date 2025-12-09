<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Math\MathExpressionEvaluator;
use AichaDigital\MustacheResolver\Exceptions\MathExpressionException;

beforeEach(function () {
    $this->evaluator = new MathExpressionEvaluator;
});

describe('MathExpressionEvaluator → basic operations', function () {
    it('evaluates addition', function () {
        expect($this->evaluator->evaluate('2 + 3'))->toBe(5);
    });

    it('evaluates subtraction', function () {
        expect($this->evaluator->evaluate('10 - 4'))->toBe(6);
    });

    it('evaluates multiplication', function () {
        expect($this->evaluator->evaluate('3 * 4'))->toBe(12);
    });

    it('evaluates division', function () {
        expect($this->evaluator->evaluate('12 / 4'))->toBe(3);
    });

    it('evaluates decimal division', function () {
        expect($this->evaluator->evaluate('10 / 4'))->toBe(2.5);
    });

    it('evaluates simple number', function () {
        expect($this->evaluator->evaluate('42'))->toBe(42);
    });

    it('evaluates negative number', function () {
        expect($this->evaluator->evaluate('-42'))->toBe(-42);
    });

    it('evaluates decimal number', function () {
        expect($this->evaluator->evaluate('3.14'))->toBe(3.14);
    });
});

describe('MathExpressionEvaluator → operator precedence', function () {
    it('respects multiplication before addition', function () {
        expect($this->evaluator->evaluate('2 + 3 * 4'))->toBe(14);
    });

    it('respects division before subtraction', function () {
        expect($this->evaluator->evaluate('10 - 8 / 2'))->toBe(6);
    });

    it('evaluates left to right for same precedence', function () {
        expect($this->evaluator->evaluate('10 - 3 - 2'))->toBe(5);
    });

    it('evaluates complex expression', function () {
        expect($this->evaluator->evaluate('2 + 3 * 4 - 6 / 2'))->toBe(11);
    });
});

describe('MathExpressionEvaluator → parentheses', function () {
    it('respects parentheses', function () {
        expect($this->evaluator->evaluate('(2 + 3) * 4'))->toBe(20);
    });

    it('handles nested parentheses', function () {
        expect($this->evaluator->evaluate('((2 + 3) * (4 - 1))'))->toBe(15);
    });

    it('handles complex nested expression', function () {
        expect($this->evaluator->evaluate('(10 - (3 + 2)) * 2'))->toBe(10);
    });

    it('handles real-world power calculation', function () {
        // Example: power < (max_power * 0.9)
        expect($this->evaluator->evaluate('500 * 0.9'))->toBe(450.0);
    });
});

describe('MathExpressionEvaluator → whitespace handling', function () {
    it('handles no whitespace', function () {
        expect($this->evaluator->evaluate('2+3*4'))->toBe(14);
    });

    it('handles extra whitespace', function () {
        expect($this->evaluator->evaluate('  2  +  3  '))->toBe(5);
    });

    it('handles tabs and newlines', function () {
        expect($this->evaluator->evaluate("2\t+\n3"))->toBe(5);
    });
});

describe('MathExpressionEvaluator → edge cases', function () {
    it('handles empty expression', function () {
        expect($this->evaluator->evaluate(''))->toBe(0);
    });

    it('handles whitespace-only expression', function () {
        expect($this->evaluator->evaluate('   '))->toBe(0);
    });

    it('handles positive sign', function () {
        expect($this->evaluator->evaluate('+5'))->toBe(5);
    });

    it('handles double negative', function () {
        expect($this->evaluator->evaluate('--5'))->toBe(5);
    });

    it('handles zero', function () {
        expect($this->evaluator->evaluate('0'))->toBe(0);
    });
});

describe('MathExpressionEvaluator → error handling', function () {
    it('throws on division by zero', function () {
        expect(fn () => $this->evaluator->evaluate('10 / 0'))
            ->toThrow(MathExpressionException::class, 'Division by zero');
    });

    it('throws on invalid characters', function () {
        expect(fn () => $this->evaluator->evaluate('2 + x'))
            ->toThrow(MathExpressionException::class);
    });

    it('throws on too long expression', function () {
        $longExpr = str_repeat('1+', 300).'1';
        expect(fn () => $this->evaluator->evaluate($longExpr))
            ->toThrow(MathExpressionException::class, 'maximum length');
    });

    it('throws on too deep nesting', function () {
        $deepExpr = str_repeat('(', 15).'1'.str_repeat(')', 15);
        expect(fn () => $this->evaluator->evaluate($deepExpr))
            ->toThrow(MathExpressionException::class, 'maximum nesting depth');
    });

    it('throws on missing closing parenthesis', function () {
        expect(fn () => $this->evaluator->evaluate('(2 + 3'))
            ->toThrow(MathExpressionException::class);
    });

    it('throws on unexpected operator', function () {
        expect(fn () => $this->evaluator->evaluate('2 + * 3'))
            ->toThrow(MathExpressionException::class);
    });
});

describe('MathExpressionEvaluator → hasExpression', function () {
    it('detects addition', function () {
        expect($this->evaluator->hasExpression('5 + 3'))->toBeTrue();
    });

    it('detects multiplication', function () {
        expect($this->evaluator->hasExpression('5 * 3'))->toBeTrue();
    });

    it('detects parentheses', function () {
        expect($this->evaluator->hasExpression('(5)'))->toBeTrue();
    });

    it('returns false for simple number', function () {
        expect($this->evaluator->hasExpression('42'))->toBeFalse();
    });

    it('returns false for decimal number', function () {
        expect($this->evaluator->hasExpression('3.14'))->toBeFalse();
    });
});

describe('MathExpressionEvaluator → real world examples', function () {
    it('evaluates power threshold calculation', function () {
        // power < (max_power * 0.9) where max_power = 500
        expect($this->evaluator->evaluate('500 * 0.9'))->toBe(450.0);
    });

    it('evaluates timestamp offset', function () {
        // timestamp + 180 seconds
        expect($this->evaluator->evaluate('1718456400 + 180'))->toBe(1718456580);
    });

    it('evaluates percentage calculation', function () {
        // (total * 15) / 100
        expect($this->evaluator->evaluate('(200 * 15) / 100'))->toBe(30);
    });

    it('evaluates complex formula', function () {
        // ((base + adjustment) * multiplier) / divisor
        expect($this->evaluator->evaluate('((100 + 50) * 2) / 3'))->toEqual(100);
    });
});
