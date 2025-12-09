<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Compound;

use AichaDigital\MustacheResolver\Contracts\ContextInterface;
use AichaDigital\MustacheResolver\Core\Parser\MustacheParser;
use AichaDigital\MustacheResolver\Core\Pipeline\ResolutionPipeline;
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

    public function __construct(
        private readonly ResolutionPipeline $pipeline,
    ) {
        $this->conditionEvaluator = new ConditionEvaluator;
        $this->parser = new MustacheParser;
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
