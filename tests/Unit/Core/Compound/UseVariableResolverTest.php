<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Compound\UseVariable;
use AichaDigital\MustacheResolver\Core\Compound\UseVariableResolver;
use AichaDigital\MustacheResolver\Core\Context\ResolutionContext;
use AichaDigital\MustacheResolver\Core\Pipeline\PipelineBuilder;
use AichaDigital\MustacheResolver\Core\Security\OutputSanitizer;
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;
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
});
