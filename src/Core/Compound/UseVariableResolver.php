<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Compound;

use AichaDigital\MustacheResolver\Contracts\ContextInterface;
use AichaDigital\MustacheResolver\Contracts\ParserInterface;
use AichaDigital\MustacheResolver\Core\Parser\MustacheParser;
use AichaDigital\MustacheResolver\Core\Pipeline\ResolutionPipeline;
use AichaDigital\MustacheResolver\Core\Security\OutputSanitizer;
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;
use AichaDigital\MustacheResolver\Core\Security\ValidatingContext;
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

    private ParserInterface $parser;

    private readonly OutputSanitizer $sanitizer;

    private readonly SecurityValidator $validator;

    public function __construct(
        private readonly ResolutionPipeline $pipeline,
        ?OutputSanitizer $sanitizer = null,
        ?ParserInterface $parser = null,
    ) {
        $this->conditionEvaluator = new ConditionEvaluator;
        // §11.2: null means the default policy, here too — this class is the
        // one public exit that bypassed barrier 2 in phase 1. The sanitizer
        // itself applies the same rule to its own missing validator, so
        // getValidator() below can never be null: an explicitly-supplied
        // `new OutputSanitizer()` used to read as OFF here, silently
        // skipping barrier 1 and unbounding the parser at once.
        $this->sanitizer = $sanitizer ?? new OutputSanitizer(SecurityValidator::defaultPolicy());
        $this->validator = $this->sanitizer->getValidator();
        $this->parser = $parser ?? self::parserFor($this->validator);
    }

    /**
     * The internal parser must obey the SAME effective mode as the sanitizer.
     *
     * Constructing it with no arguments applied the default ceilings
     * unconditionally, so a consumer who had explicitly opted out (mode:
     * off) — or who was only measuring (report) — still got a hard
     * SecurityException out of a long USE expression, thrown raw from
     * parse() where the only catch below is for UnresolvableException.
     * Limits are an enforce-only control, exactly as the service provider
     * wires them for the main parser.
     *
     * The optional $parser constructor argument is the seam for CONFIGURED
     * ceilings: CompoundResolver forwards its own, and the Laravel provider
     * binds CompoundResolver passing the same ParserInterface the main path
     * uses — so `security.limits` governs both entry points. This
     * mode-derived fallback applies only when nothing was injected
     * (standalone construction without a parser).
     */
    private static function parserFor(SecurityValidator $validator): MustacheParser
    {
        return $validator->getMode() === SecurityValidator::MODE_ENFORCE
            ? new MustacheParser
            : new MustacheParser(maxTemplateLength: null, maxTokens: null);
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

        // CompoundResolver::resolve() is a public entry point of its own: the
        // context arrives straight from the consumer, never through
        // MustacheResolver::createContext(), so barrier 1 has to be applied
        // here too. Without it a NULL_COALESCE or DYNAMIC expression walked
        // an unvalidated accessor and the sanitizer below could not stand in
        // — neither token type carries a static path for it to re-check.
        $context = ValidatingContext::wrap($context, $this->validator);

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
