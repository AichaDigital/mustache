<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Compound;

use AichaDigital\MustacheResolver\Contracts\ContextInterface;
use AichaDigital\MustacheResolver\Core\Pipeline\ResolutionPipeline;
use AichaDigital\MustacheResolver\Exceptions\ConditionNotMetException;
use AichaDigital\MustacheResolver\Exceptions\InvalidUseSyntaxException;
use AichaDigital\MustacheResolver\Exceptions\VariableNotResolvedException;

/**
 * Main resolver for compound expressions with USE clauses.
 *
 * Orchestrates the full resolution process:
 * 1. Parse the compound expression
 * 2. Resolve USE clause variables
 * 3. Validate conditions
 * 4. Substitute local variables in statement
 * 5. Return the final statement
 */
final class CompoundResolver
{
    private CompoundExpressionParser $parser;

    private UseVariableResolver $variableResolver;

    private LocalVariableReplacer $replacer;

    public function __construct(
        private readonly ResolutionPipeline $pipeline,
    ) {
        $this->parser = new CompoundExpressionParser;
        $this->variableResolver = new UseVariableResolver($pipeline);
        $this->replacer = new LocalVariableReplacer;
    }

    /**
     * Check if a template is a compound expression.
     */
    public function isCompound(string $template): bool
    {
        return $this->parser->isCompound($template);
    }

    /**
     * Resolve a compound expression template.
     *
     * @return string The resolved statement with substituted values
     *
     * @throws InvalidUseSyntaxException If syntax is invalid
     * @throws VariableNotResolvedException If a variable cannot be resolved
     * @throws ConditionNotMetException If a condition fails
     */
    public function resolve(string $template, ContextInterface $context): string
    {
        // Parse the compound expression
        $compound = $this->parser->parse($template);

        // Resolve all USE variables
        $resolvedVariables = $this->variableResolver->resolveAll($compound, $context);

        // Replace local variables in statement
        $statement = $this->replacer->replace(
            $compound->getStatement(),
            $resolvedVariables,
        );

        return $statement;
    }

    /**
     * Resolve a compound expression and return detailed result.
     *
     * @return array{statement: string, variables: array<string, mixed>, original: string}
     *
     * @throws InvalidUseSyntaxException
     * @throws VariableNotResolvedException
     * @throws ConditionNotMetException
     */
    public function resolveDetailed(string $template, ContextInterface $context): array
    {
        $compound = $this->parser->parse($template);
        $resolvedVariables = $this->variableResolver->resolveAll($compound, $context);

        $statement = $this->replacer->replace(
            $compound->getStatement(),
            $resolvedVariables,
        );

        return [
            'statement' => $statement,
            'variables' => $resolvedVariables,
            'original' => $compound->getOriginal(),
        ];
    }

    /**
     * Try to resolve a compound expression, returning null on condition failure.
     *
     * This is useful when you want to silently skip failed conditions
     * rather than throwing exceptions.
     */
    public function tryResolve(string $template, ContextInterface $context): ?string
    {
        try {
            return $this->resolve($template, $context);
        } catch (ConditionNotMetException) {
            return null;
        }
    }

    /**
     * Validate a compound expression without resolving.
     *
     * @return array{valid: bool, errors: string[]}
     */
    public function validate(string $template): array
    {
        $errors = [];

        try {
            $compound = $this->parser->parse($template);

            // Check for undeclared variables
            $declared = $compound->getVariableNames();
            $used = $this->replacer->extractVariableNames($compound->getStatement());
            $undeclared = $this->parser->findUndeclaredVariables($declared, $used);

            if (! empty($undeclared)) {
                $errors[] = sprintf(
                    'Undeclared variables used in statement: %s',
                    implode(', ', array_map(fn ($v) => '{'.$v.'}', $undeclared)),
                );
            }
        } catch (InvalidUseSyntaxException $e) {
            $errors[] = $e->getMessage();
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
