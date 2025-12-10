<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Context\ResolutionContext;
use AichaDigital\MustacheResolver\Core\Token\Token;
use AichaDigital\MustacheResolver\Core\Token\TokenClassifier;
use AichaDigital\MustacheResolver\Core\Token\TokenType;
use AichaDigital\MustacheResolver\Exceptions\ResolutionException;
use AichaDigital\MustacheResolver\Resolvers\TemporalResolver;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2025-12-10 14:30:00'); // Wednesday
    $this->resolver = new TemporalResolver;
    $this->classifier = new TokenClassifier;
    $this->context = ResolutionContext::fromArray([]);
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('TemporalResolver → Token Classification', function () {
    it('classifies TEMPORAL prefix', function () {
        $token = $this->classifier->classify("TEMPORAL:isDue('weekday')");

        expect($token->getType())->toBe(TokenType::TEMPORAL);
    });

    it('classifies NOW prefix', function () {
        $token = $this->classifier->classify('NOW:format(Y-m-d)');

        expect($token->getType())->toBe(TokenType::TEMPORAL);
    });

    it('classifies simple NOW', function () {
        $token = $this->classifier->classify('NOW');

        expect($token->getType())->toBe(TokenType::TEMPORAL);
    });

    it('classifies TODAY prefix', function () {
        $token = $this->classifier->classify('TODAY:startOfDay');

        expect($token->getType())->toBe(TokenType::TEMPORAL);
    });

    it('classifies simple TODAY', function () {
        $token = $this->classifier->classify('TODAY');

        expect($token->getType())->toBe(TokenType::TEMPORAL);
    });
});

describe('TemporalResolver → Supports', function () {
    it('supports temporal tokens', function () {
        $token = $this->classifier->classify("TEMPORAL:isDue('weekday')");

        expect($this->resolver->supports($token, $this->context))->toBeTrue();
    });

    it('does not support model tokens', function () {
        $token = $this->classifier->classify('User.name');

        expect($this->resolver->supports($token, $this->context))->toBeFalse();
    });
});

describe('TemporalResolver → TEMPORAL:isDue', function () {
    it('resolves isDue with weekday expression', function () {
        Carbon::setTestNow('2025-12-10 14:30:00'); // Wednesday
        $token = $this->classifier->classify("TEMPORAL:isDue('weekday')");

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBeTrue();
    });

    it('resolves isDue with time range', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $token = $this->classifier->classify("TEMPORAL:isDue('08:00-18:00')");

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBeTrue();
    });

    it('resolves isDue with complex expression', function () {
        Carbon::setTestNow('2025-12-10 14:30:00'); // Wednesday 14:30
        $token = $this->classifier->classify("TEMPORAL:isDue('weekday && 08:00-18:00')");

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBeTrue();
    });

    it('resolves isDue with custom evaluator', function () {
        $this->resolver->registerEvaluator('holiday', fn () => false);
        $token = $this->classifier->classify("TEMPORAL:isDue('weekday && !holiday')");

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBeTrue();
    });
});

describe('TemporalResolver → TEMPORAL:nextRun', function () {
    it('resolves nextRun with cron expression', function () {
        Carbon::setTestNow('2025-12-10 09:00:00');
        $token = $this->classifier->classify("TEMPORAL:nextRun('cron:0 8 * * *')");

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBe('2025-12-11 08:00:00');
    });

    it('throws on nextRun without cron prefix', function () {
        $token = $this->classifier->classify("TEMPORAL:nextRun('weekday')");

        expect(fn () => $this->resolver->resolve($token, $this->context))
            ->toThrow(ResolutionException::class, 'nextRun requires a CRON expression');
    });
});

describe('TemporalResolver → TEMPORAL:previousRun', function () {
    it('resolves previousRun with cron expression', function () {
        Carbon::setTestNow('2025-12-10 09:00:00');
        $token = $this->classifier->classify("TEMPORAL:previousRun('cron:0 8 * * *')");

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBe('2025-12-10 08:00:00');
    });

    it('throws on previousRun without cron prefix', function () {
        $token = $this->classifier->classify("TEMPORAL:previousRun('weekday')");

        expect(fn () => $this->resolver->resolve($token, $this->context))
            ->toThrow(ResolutionException::class, 'previousRun requires a CRON expression');
    });
});

describe('TemporalResolver → TEMPORAL:isNthWeekday', function () {
    it('resolves isNthWeekday correctly', function () {
        Carbon::setTestNow('2025-12-06 10:00:00'); // First Saturday
        $token = Token::create(
            raw: "TEMPORAL:isNthWeekday('saturday', 1)",
            type: TokenType::TEMPORAL,
            path: [],
            functionName: 'isNthWeekday',
            functionArgs: ['saturday', 1],
            metadata: ['temporal_type' => 'temporal', 'expression' => '']
        );

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBeTrue();
    });

    it('throws on isNthWeekday with insufficient args', function () {
        $token = Token::create(
            raw: "TEMPORAL:isNthWeekday('saturday')",
            type: TokenType::TEMPORAL,
            path: [],
            functionName: 'isNthWeekday',
            functionArgs: ['saturday'],
            metadata: ['temporal_type' => 'temporal', 'expression' => '']
        );

        expect(fn () => $this->resolver->resolve($token, $this->context))
            ->toThrow(ResolutionException::class, 'isNthWeekday requires 2 arguments');
    });
});

describe('TemporalResolver → TEMPORAL:isLastWeekday', function () {
    it('resolves isLastWeekday correctly', function () {
        Carbon::setTestNow('2025-12-27 10:00:00'); // Last Saturday of December
        $token = Token::create(
            raw: "TEMPORAL:isLastWeekday('saturday')",
            type: TokenType::TEMPORAL,
            path: [],
            functionName: 'isLastWeekday',
            functionArgs: ['saturday'],
            metadata: ['temporal_type' => 'temporal', 'expression' => '']
        );

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBeTrue();
    });

    it('throws on isLastWeekday with no args', function () {
        $token = Token::create(
            raw: 'TEMPORAL:isLastWeekday()',
            type: TokenType::TEMPORAL,
            path: [],
            functionName: 'isLastWeekday',
            functionArgs: [],
            metadata: ['temporal_type' => 'temporal', 'expression' => '']
        );

        expect(fn () => $this->resolver->resolve($token, $this->context))
            ->toThrow(ResolutionException::class, 'isLastWeekday requires 1 argument');
    });
});

describe('TemporalResolver → Unknown functions', function () {
    it('throws on unknown TEMPORAL function', function () {
        $token = Token::create(
            raw: "TEMPORAL:unknownFunc('test')",
            type: TokenType::TEMPORAL,
            path: [],
            functionName: 'unknownFunc',
            functionArgs: ['test'],
            metadata: ['temporal_type' => 'temporal', 'expression' => '']
        );

        expect(fn () => $this->resolver->resolve($token, $this->context))
            ->toThrow(ResolutionException::class, 'Unknown TEMPORAL function');
    });

    it('throws on unknown temporal type', function () {
        $token = Token::create(
            raw: 'UNKNOWN:test',
            type: TokenType::TEMPORAL,
            path: [],
            functionName: 'test',
            functionArgs: [],
            metadata: ['temporal_type' => 'invalid', 'expression' => '']
        );

        expect(fn () => $this->resolver->resolve($token, $this->context))
            ->toThrow(ResolutionException::class, 'Unknown temporal type');
    });
});

describe('TemporalResolver → NOW Functions', function () {
    it('resolves NOW default', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $token = $this->classifier->classify('NOW');

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBe('2025-12-10 14:30:00');
    });

    it('resolves NOW:format', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $token = $this->classifier->classify("NOW:format('Y-m-d')");

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBe('2025-12-10');
    });

    it('resolves NOW:timestamp', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $token = $this->classifier->classify('NOW:timestamp');

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBe(Carbon::now()->timestamp);
    });

    it('resolves NOW:date', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $token = $this->classifier->classify('NOW:date');

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBe('2025-12-10');
    });

    it('resolves NOW:time', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $token = $this->classifier->classify('NOW:time');

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBe('14:30:00');
    });

    it('resolves NOW:isWeekday', function () {
        Carbon::setTestNow('2025-12-10 14:30:00'); // Wednesday
        $token = $this->classifier->classify('NOW:isWeekday');

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBeTrue();
    });

    it('resolves NOW:isWeekend', function () {
        Carbon::setTestNow('2025-12-13 14:30:00'); // Saturday
        $token = $this->classifier->classify('NOW:isWeekend');

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBeTrue();
    });

    it('resolves NOW:dayOfWeek', function () {
        Carbon::setTestNow('2025-12-10 14:30:00'); // Wednesday = 3
        $token = $this->classifier->classify('NOW:dayOfWeek');

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBe(3);
    });

    it('resolves NOW:datetime', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $token = $this->classifier->classify('NOW:datetime');

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBe('2025-12-10 14:30:00');
    });

    it('resolves NOW:dayOfMonth', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $token = $this->classifier->classify('NOW:dayOfMonth');

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBe(10);
    });

    it('resolves NOW:month', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $token = $this->classifier->classify('NOW:month');

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBe(12);
    });

    it('resolves NOW:year', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $token = $this->classifier->classify('NOW:year');

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBe(2025);
    });

    it('resolves NOW:hour', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $token = $this->classifier->classify('NOW:hour');

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBe(14);
    });

    it('resolves NOW:minute', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $token = $this->classifier->classify('NOW:minute');

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBe(30);
    });

    it('resolves NOW:second', function () {
        Carbon::setTestNow('2025-12-10 14:30:45');
        $token = $this->classifier->classify('NOW:second');

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBe(45);
    });

    it('resolves NOW:atom', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $token = $this->classifier->classify('NOW:atom');

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toContain('2025-12-10T14:30:00');
    });

    it('resolves NOW:rfc3339', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $token = $this->classifier->classify('NOW:rfc3339');

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toContain('2025-12-10T14:30:00');
    });

    it('throws on unknown NOW function', function () {
        $token = $this->classifier->classify('NOW:unknownFunc');

        expect(fn () => $this->resolver->resolve($token, $this->context))
            ->toThrow(ResolutionException::class, 'Unknown NOW function');
    });
});

describe('TemporalResolver → TODAY Functions', function () {
    it('resolves TODAY default', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $token = $this->classifier->classify('TODAY');

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBe('2025-12-10');
    });

    it('resolves TODAY:startOfDay', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $token = $this->classifier->classify('TODAY:startOfDay');

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBe('2025-12-10 00:00:00');
    });

    it('resolves TODAY:endOfDay', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $token = $this->classifier->classify('TODAY:endOfDay');

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBe('2025-12-10 23:59:59');
    });

    it('resolves TODAY:dayOfMonth', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $token = $this->classifier->classify('TODAY:dayOfMonth');

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBe(10);
    });

    it('resolves TODAY:isFirstDayOfMonth', function () {
        Carbon::setTestNow('2025-12-01 14:30:00');
        $token = $this->classifier->classify('TODAY:isFirstDayOfMonth');

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBeTrue();
    });

    it('resolves TODAY:isLastDayOfMonth', function () {
        Carbon::setTestNow('2025-12-31 14:30:00');
        $token = $this->classifier->classify('TODAY:isLastDayOfMonth');

        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBeTrue();
    });
});

describe('TemporalResolver → Test Now', function () {
    it('uses setTestNow for evaluation', function () {
        $this->resolver->setTestNow(Carbon::create(2025, 12, 13, 10, 0, 0)); // Saturday

        $token = $this->classifier->classify('NOW:isWeekend');
        $result = $this->resolver->resolve($token, $this->context);

        expect($result)->toBeTrue();
    });
});

describe('TemporalResolver → Priority and Name', function () {
    it('has high priority', function () {
        expect($this->resolver->priority())->toBe(90);
    });

    it('has correct name', function () {
        expect($this->resolver->name())->toBe('temporal');
    });
});
