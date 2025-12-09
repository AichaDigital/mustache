<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Contracts\ContextInterface;
use AichaDigital\MustacheResolver\Contracts\ResolverInterface;
use AichaDigital\MustacheResolver\Contracts\TokenInterface;
use AichaDigital\MustacheResolver\Core\Compound\CompoundResolver;
use AichaDigital\MustacheResolver\Core\Context\ResolutionContext;
use AichaDigital\MustacheResolver\Core\Pipeline\ResolutionPipeline;
use AichaDigital\MustacheResolver\Exceptions\ConditionNotMetException;
use AichaDigital\MustacheResolver\Exceptions\InvalidUseSyntaxException;
use AichaDigital\MustacheResolver\Exceptions\VariableNotResolvedException;

beforeEach(function () {
    // Create a test resolver that resolves based on raw expression
    $this->values = [];
    $testValues = &$this->values;

    $testResolver = new class($testValues) implements ResolverInterface
    {
        public function __construct(
            private array &$values,
        ) {}

        public function supports(TokenInterface $token, ContextInterface $context): bool
        {
            return isset($this->values[$token->getRaw()]);
        }

        public function resolve(TokenInterface $token, ContextInterface $context): mixed
        {
            return $this->values[$token->getRaw()] ?? null;
        }

        public function priority(): int
        {
            return 100;
        }

        public function name(): string
        {
            return 'test';
        }
    };

    $this->pipeline = new ResolutionPipeline([$testResolver]);
    $this->resolver = new CompoundResolver($this->pipeline);
    $this->context = ResolutionContext::fromArray([]);
});

describe('CompoundResolver → isCompound', function () {
    it('detects compound expressions', function () {
        expect($this->resolver->isCompound('USE {var} => {{expr}} && SELECT *'))->toBeTrue();
    });

    it('returns false for non-compound', function () {
        expect($this->resolver->isCompound('SELECT * FROM users'))->toBeFalse();
    });
});

describe('CompoundResolver → resolve', function () {
    it('resolves simple compound expression', function () {
        $this->values['CommandCenter.max_power'] = 100;

        $result = $this->resolver->resolve(
            'USE {max_power} => {{CommandCenter.max_power}} && SELECT * WHERE power < {max_power}',
            $this->context,
        );

        expect($result)->toBe('SELECT * WHERE power < 100');
    });

    it('resolves multiple variables', function () {
        $this->values['CC.power'] = 100;
        $this->values['CC.sunset'] = 1718456400;

        $result = $this->resolver->resolve(
            'USE {power} => {{CC.power}}, {sunset} => {{CC.sunset}} && power={power} sunset={sunset}',
            $this->context,
        );

        expect($result)->toBe('power=100 sunset=1718456400');
    });

    it('validates conditions', function () {
        $this->values['CC.power'] = 100;

        $result = $this->resolver->resolve(
            'USE {power} => {{CC.power}} > 0 && SELECT * WHERE power < {power}',
            $this->context,
        );

        expect($result)->toBe('SELECT * WHERE power < 100');
    });

    it('throws on failed condition', function () {
        $this->values['CC.power'] = 0;

        expect(fn () => $this->resolver->resolve(
            'USE {power} => {{CC.power}} > 0 && SELECT *',
            $this->context,
        ))->toThrow(ConditionNotMetException::class);
    });

    it('throws on unresolved variable', function () {
        // values is empty, so resolution will fail
        expect(fn () => $this->resolver->resolve(
            'USE {power} => {{CC.power}} && SELECT *',
            $this->context,
        ))->toThrow(VariableNotResolvedException::class);
    });

    it('throws on invalid syntax', function () {
        expect(fn () => $this->resolver->resolve(
            'USE invalid && SELECT *',
            $this->context,
        ))->toThrow(InvalidUseSyntaxException::class);
    });
});

describe('CompoundResolver → resolveDetailed', function () {
    it('returns detailed result', function () {
        $this->values['Model.field'] = 42;

        $result = $this->resolver->resolveDetailed(
            'USE {value} => {{Model.field}} && WHERE id = {value}',
            $this->context,
        );

        expect($result)->toBeArray();
        expect($result['statement'])->toBe('WHERE id = 42');
        expect($result['variables'])->toBe(['value' => 42]);
        expect($result['original'])->toContain('USE');
    });
});

describe('CompoundResolver → tryResolve', function () {
    it('returns result on success', function () {
        $this->values['CC.power'] = 100;

        $result = $this->resolver->tryResolve(
            'USE {power} => {{CC.power}} > 0 && SELECT *',
            $this->context,
        );

        expect($result)->toBe('SELECT *');
    });

    it('returns null on condition failure', function () {
        $this->values['CC.power'] = 0;

        $result = $this->resolver->tryResolve(
            'USE {power} => {{CC.power}} > 0 && SELECT *',
            $this->context,
        );

        expect($result)->toBeNull();
    });
});

describe('CompoundResolver → validate', function () {
    it('validates correct syntax', function () {
        $result = $this->resolver->validate('USE {var} => {{expr}} && SELECT *');

        expect($result['valid'])->toBeTrue();
        expect($result['errors'])->toBe([]);
    });

    it('detects invalid syntax', function () {
        $result = $this->resolver->validate('USE invalid && SELECT *');

        expect($result['valid'])->toBeFalse();
        expect($result['errors'])->not->toBe([]);
    });

    it('detects undeclared variables', function () {
        $result = $this->resolver->validate('USE {var} => {{expr}} && SELECT {var} AND {other}');

        expect($result['valid'])->toBeFalse();
        expect($result['errors'][0])->toContain('Undeclared');
        expect($result['errors'][0])->toContain('{other}');
    });

    it('accepts when all variables declared', function () {
        $result = $this->resolver->validate('USE {var} => {{expr}} && SELECT {var}');

        expect($result['valid'])->toBeTrue();
    });
});

describe('CompoundResolver → real world example', function () {
    it('resolves Elasticsearch query example', function () {
        $this->values['CommandCenter.max_power'] = 500;
        $this->values['CommandCenter.operationalSunset.timestamp'] = 1718456400;

        $result = $this->resolver->resolve(
            'USE {max_power} => {{CommandCenter.max_power}} > 0, {sunset} => {{CommandCenter.operationalSunset.timestamp}} >= 0 && SELECT log_id FROM "work-analyzers" WHERE power < ({max_power} * 0.9) AND timestamp > {sunset}',
            $this->context,
        );

        expect($result)->toContain('power < (500 * 0.9)');
        expect($result)->toContain('timestamp > 1718456400');
    });
});
