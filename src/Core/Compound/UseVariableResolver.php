<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Compound;

use AichaDigital\MustacheResolver\Contracts\ContextInterface;
use AichaDigital\MustacheResolver\Core\Parser\MustacheParser;
use AichaDigital\MustacheResolver\Core\Pipeline\ResolutionPipeline;
use AichaDigital\MustacheResolver\Core\Security\OutputSanitizer;
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;
use AichaDigital\MustacheResolver\Exceptions\ConditionNotMetException;
use AichaDigital\MustacheResolver\Exceptions\UnresolvableException;
use AichaDigital\MustacheResolver\Exceptions\VariableNotResolvedException;

/**
 * Resolves USE clause variable declarations.
 *
 * Takes a UseVariable and resolves its mustache expression,
 * then optionally validates its condition.
 */
final class UseVariableResolver
{
    private ConditionEvaluator $conditionEvaluator;

    private MustacheParser $parser;

    private readonly OutputSanitizer $sanitizer;

    public function __construct(
        private readonly ResolutionPipeline $pipeline,
        ?OutputSanitizer $sanitizer = null,
    ) {
        $this->conditionEvaluator = new ConditionEvaluator;
        $this->parser = new MustacheParser;
        // §11.2: null means the default policy, here too — this class is the
        // one public exit that bypassed barrier 2 in phase 1.
        $this->sanitizer = $sanitizer ?? new OutputSanitizer(SecurityValidator::defaultPolicy());
    }

    /**
     * Resolve a single USE variable.
     *
     * @throws VariableNotResolvedException If mustache cannot be resolved
     * @throws ConditionNotMetException If condition fails
     */
    public function resolve(UseVariable $variable, ContextInterface $context): mixed
    {
        $expression = $variable->getExpression();

        // Parse the mustache expression to get tokens
        $tokens = $this->parser->parse($expression);

        if (empty($tokens)) {
            throw new VariableNotResolvedException(
                $variable->getName(),
                $expression,
                'Invalid mustache expression',
            );
        }

        // Resolve the first (and should be only) token
        $token = $tokens[0];

        try {
            $value = $this->pipeline->resolve($token, $context);
        } catch (UnresolvableException $e) {
            throw new VariableNotResolvedException(
                $variable->getName(),
                $expression,
                'No resolver could handle the expression: '.$e->getMessage(),
            );
        }

        $sanitized = $this->sanitizer->sanitize($value, $token);

        if ($sanitized->blocked) {
            throw new VariableNotResolvedException(
                $variable->getName(),
                $expression,
                'Expression blocked by security policy',
            );
        }

        $value = $sanitized->value;

        if ($value === null) {
            throw new VariableNotResolvedException(
                $variable->getName(),
                $expression,
                'Mustache expression resolved to null',
            );
        }

        // Validate condition if present
        if ($variable->hasCondition()) {
            $this->conditionEvaluator->evaluate(
                $variable->getName(),
                $value,
                (string) $variable->getCondition(),
                $expression,
            );
        }

        return $value;
    }

    /**
     * Resolve all variables from a CompoundExpression.
     *
     * @return array<string, mixed> Map of variable name to resolved value
     *
     * @throws VariableNotResolvedException
     * @throws ConditionNotMetException
     */
    public function resolveAll(CompoundExpression $compound, ContextInterface $context): array
    {
        $resolved = [];

        foreach ($compound->getVariables() as $variable) {
            $resolved[$variable->getName()] = $this->resolve($variable, $context);
        }

        return $resolved;
    }
}
