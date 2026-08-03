<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Accessors\ArrayAccessor;
use AichaDigital\MustacheResolver\Core\Compound\UseVariable;
use AichaDigital\MustacheResolver\Core\Compound\UseVariableResolver;
use AichaDigital\MustacheResolver\Core\Context\ResolutionContext;
use AichaDigital\MustacheResolver\Core\Parser\MustacheParser;
use AichaDigital\MustacheResolver\Core\Pipeline\PipelineBuilder;
use AichaDigital\MustacheResolver\Core\Security\OutputSanitizer;
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;
use AichaDigital\MustacheResolver\Exceptions\SecurityException;
use AichaDigital\MustacheResolver\Exceptions\VariableNotResolvedException;

describe('UseVariableResolver security (v3)', function () {
    it('blocks a blacklisted path through the compound exit', function () {
        $resolver = new UseVariableResolver(PipelineBuilder::create()->build());

        // ModelResolver strips the "User" prefix and navigates
        // getFieldPath() at the accessor root (same convention as
        // ModelResolverTest: a flat root array, not nested under the
        // model name) — this is what makes the raw value actually
        // resolvable, so the block below is provably about the
        // sanitizer, not about the pipeline failing to find anything.
        $context = ResolutionContext::fromArray(
            ['api_token' => 'tok_123'],
            new SecurityValidator(mode: SecurityValidator::MODE_OFF),
        );

        // The accessor is off (barrier 1 disabled on purpose) so the raw
        // value reaches the compound exit; the DEFAULT sanitizer (§11.2)
        // must be the thing that blocks it.
        $variable = new UseVariable('token', '{{User.api_token}}');

        $resolver->resolve($variable, $context);
    })->throws(VariableNotResolvedException::class, 'blocked by security policy');

    it('passes values through untouched with an off-mode sanitizer', function () {
        $resolver = new UseVariableResolver(
            PipelineBuilder::create()->build(),
            new OutputSanitizer(new SecurityValidator(mode: SecurityValidator::MODE_OFF)),
        );

        $context = ResolutionContext::fromArray(
            ['api_token' => 'tok_123'],
            new SecurityValidator(mode: SecurityValidator::MODE_OFF),
        );

        $variable = new UseVariable('token', '{{User.api_token}}');

        expect($resolver->resolve($variable, $context))->toBe('tok_123');
    });

    it('applies barrier 1 to the context it is handed', function () {
        // CompoundResolver::resolve() takes a ContextInterface straight from
        // the consumer, never through MustacheResolver::createContext(), so
        // this is a public entry point of its own. The accessor below has no
        // validator, and NULL_COALESCE carries no static path for the
        // sanitizer to re-check — without barrier 1 here the value walks out.
        $resolver = new UseVariableResolver(PipelineBuilder::create()->build());
        $context = ResolutionContext::create(new ArrayAccessor(['password' => 'COMPOUND_SECRET']));

        expect($resolver->resolve(new UseVariable('v', "{{x.password ?? 'fb'}}"), $context))
            ->toBe('fb');
    });

    it('applies the default policy when the sanitizer was constructed without a validator (§11.2)', function () {
        // The pre-tag gate reproduced this as a fail-open: a null validator
        // used to read as "no checks", so an explicitly-supplied empty
        // sanitizer skipped barrier 1 AND unbounded the parser. §11.2 says
        // null means THE DEFAULT POLICY — the sanitizer now fills it in,
        // and barrier 1 derives from the same instance.
        $resolver = new UseVariableResolver(
            PipelineBuilder::create()->build(),
            new OutputSanitizer(null),
        );
        $context = ResolutionContext::create(new ArrayAccessor(['password' => 'COMPOUND_SECRET']));

        expect($resolver->resolve(new UseVariable('v', "{{x.password ?? 'fb'}}"), $context))
            ->toBe('fb');
    });

    it('reaches that same value when the policy is explicitly off (fixture guard)', function () {
        $off = new SecurityValidator(mode: SecurityValidator::MODE_OFF);
        $resolver = new UseVariableResolver(
            PipelineBuilder::create()->build(),
            new OutputSanitizer($off),
        );
        $context = ResolutionContext::create(new ArrayAccessor(['password' => 'COMPOUND_SECRET']));

        // Proves the expression genuinely resolves through this fixture, so
        // the 'fb' above is the policy acting rather than a dead path.
        expect($resolver->resolve(new UseVariable('v', "{{x.password ?? 'fb'}}"), $context))
            ->toBe('COMPOUND_SECRET');
    });
});

describe('UseVariableResolver internal parser limits (v3)', function () {
    $longExpression = fn (): string => '{{Foo.'.str_repeat('a', MustacheParser::DEFAULT_MAX_TEMPLATE_LENGTH).'}}';

    it('does not throw a raw SecurityException when the policy is off', function () use ($longExpression) {
        $off = new SecurityValidator(mode: SecurityValidator::MODE_OFF);
        $resolver = new UseVariableResolver(
            PipelineBuilder::create()->build(),
            new OutputSanitizer($off),
        );

        // The internal parser used to be constructed with no arguments, so
        // the DEFAULT ceilings applied regardless of mode, and parse()'s
        // SecurityException escaped raw — the only catch in resolve() is for
        // UnresolvableException. A consumer who had opted out entirely got a
        // hard security throw out of a long USE expression.
        expect(fn () => $resolver->resolve(
            new UseVariable('v', $longExpression()),
            ResolutionContext::create(new ArrayAccessor([])),
        ))->toThrow(VariableNotResolvedException::class);
    });

    it('does not throw a raw SecurityException in report mode either', function () use ($longExpression) {
        $report = new SecurityValidator(mode: SecurityValidator::MODE_REPORT);
        $resolver = new UseVariableResolver(
            PipelineBuilder::create()->build(),
            new OutputSanitizer($report),
        );

        // report must never throw where v2.1 did not: a new throw is a
        // behaviour change, which is exactly what report promises not to be.
        expect(fn () => $resolver->resolve(
            new UseVariable('v', $longExpression()),
            ResolutionContext::create(new ArrayAccessor([])),
        ))->toThrow(VariableNotResolvedException::class);
    });

    it('still applies the ceiling in enforce mode', function () use ($longExpression) {
        $resolver = new UseVariableResolver(PipelineBuilder::create()->build());

        // Default policy (§11.2) is enforce, so the limit is live here.
        expect(fn () => $resolver->resolve(
            new UseVariable('v', $longExpression()),
            ResolutionContext::create(new ArrayAccessor([])),
        ))->toThrow(SecurityException::class);
    });

    it('accepts an injected parser so a caller can supply configured ceilings', function () {
        $resolver = new UseVariableResolver(
            PipelineBuilder::create()->build(),
            null,
            new MustacheParser(maxTemplateLength: 10, maxTokens: null),
        );

        // The class defaults are the right standalone behaviour, but the
        // Laravel path has security.limits as consumer configuration; an
        // injected parser is how those reach this collaborator.
        expect(fn () => $resolver->resolve(
            new UseVariable('v', '{{Foo.some_quite_long_field}}'),
            ResolutionContext::create(new ArrayAccessor([])),
        ))->toThrow(SecurityException::class);
    });
});
