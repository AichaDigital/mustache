<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core;

use AichaDigital\MustacheResolver\Accessors\EloquentAccessor;
use AichaDigital\MustacheResolver\Contracts\CacheInterface;
use AichaDigital\MustacheResolver\Contracts\ContextInterface;
use AichaDigital\MustacheResolver\Contracts\DataAccessorInterface;
use AichaDigital\MustacheResolver\Contracts\ParserInterface;
use AichaDigital\MustacheResolver\Contracts\ResultInterface;
use AichaDigital\MustacheResolver\Contracts\TokenInterface;
use AichaDigital\MustacheResolver\Core\Context\ResolutionContext;
use AichaDigital\MustacheResolver\Core\Pipeline\ResolutionPipeline;
use AichaDigital\MustacheResolver\Core\Result\TranslationResult;
use AichaDigital\MustacheResolver\Core\Security\OutputSanitizer;
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;
use AichaDigital\MustacheResolver\Exceptions\ResolutionException;
use Illuminate\Database\Eloquent\Model;

/**
 * Main entry point for mustache template resolution.
 */
final class MustacheResolver
{
    private readonly SecurityValidator $securityValidator;

    private readonly OutputSanitizer $sanitizer;

    public function __construct(
        private readonly ParserInterface $parser,
        private readonly ResolutionPipeline $pipeline,
        private readonly CacheInterface $cache,
        ?SecurityValidator $securityValidator = null,
        ?OutputSanitizer $sanitizer = null,
    ) {
        // v3 (§11.2): null stops meaning "no policy" and means "the default
        // policy". Opting out requires an explicit mode: off validator.
        $this->securityValidator = $securityValidator ?? SecurityValidator::defaultPolicy();
        $this->sanitizer = $sanitizer ?? new OutputSanitizer($this->securityValidator);
    }

    /**
     * Translate a template string, replacing all mustaches with resolved values.
     *
     * @param  string  $template  The template string containing mustaches
     * @param  mixed  $data  The data source (Model, array, or DataAccessorInterface)
     * @param  array<string, mixed>  $variables  Additional variables
     */
    public function translate(
        string $template,
        mixed $data,
        array $variables = [],
        bool $strict = true,
    ): ResultInterface {
        if (! $this->parser->hasMustaches($template)) {
            return TranslationResult::success($template, $template);
        }

        $context = $this->createContext($data, $variables, $strict);
        $tokens = $this->parser->parse($template);
        $translated = $template;
        $resolvedValues = [];
        $warnings = [];
        $missingFields = [];

        foreach ($tokens as $token) {
            try {
                $raw = $this->pipeline->resolve($token, $context);
                $sanitized = $this->sanitizer->sanitize($raw, $token);
                $translated = str_replace($token->getFull(), $sanitized->text, $translated);
                $resolvedValues[$token->getRaw()] = $sanitized->value;
            } catch (ResolutionException $e) {
                if ($strict) {
                    return TranslationResult::failed(
                        $template,
                        [$token->getRaw()],
                        [$e->getMessage()]
                    );
                }

                $missingFields[] = $token->getRaw();
                $warnings[] = $e->getMessage();
                // Keep mustache as-is or replace with empty string based on config
                $translated = str_replace($token->getFull(), '', $translated);
            }
        }

        if (! empty($missingFields) && $strict) {
            return TranslationResult::failed($template, $missingFields, [], $warnings);
        }

        return TranslationResult::success(
            $template,
            $translated,
            $tokens,
            $resolvedValues,
            $warnings
        );
    }

    /**
     * Translate multiple templates in batch.
     *
     * @param  string[]  $templates
     * @param  array<string, mixed>  $variables
     * @return ResultInterface[]
     */
    public function translateBatch(
        array $templates,
        mixed $data,
        array $variables = [],
        bool $strict = true,
    ): array {
        return array_map(
            fn (string $template) => $this->translate($template, $data, $variables, $strict),
            $templates
        );
    }

    /**
     * Check if a template contains any mustache patterns.
     */
    public function hasMustaches(string $template): bool
    {
        return $this->parser->hasMustaches($template);
    }

    /**
     * Parse a template and return the tokens without resolving.
     *
     * @return TokenInterface[]
     */
    public function parse(string $template): array
    {
        return $this->parser->parse($template);
    }

    /**
     * Create a resolution context from the provided data.
     *
     * @param  array<string, mixed>  $variables
     */
    private function createContext(mixed $data, array $variables, bool $strict): ContextInterface
    {
        if ($data instanceof ContextInterface) {
            return $data;
        }

        if ($data instanceof DataAccessorInterface) {
            return ResolutionContext::create($data)
                ->withStrict($strict);
        }

        if ($data instanceof Model) {
            return ResolutionContext::create(new EloquentAccessor($data, $this->securityValidator))
                ->withStrict($strict);
        }

        if (is_array($data)) {
            $this->warnRootWhitelistInapplicable($data);

            return ResolutionContext::fromArray($data, $this->securityValidator)
                ->withStrict($strict);
        }

        // Assume it's an object, wrap it in accessor. The validator MUST
        // be passed here exactly as the array branch above does: for
        // NULL_COALESCE tokens (hasSecurityPath() is false, so the
        // sanitizer's path check never runs) and for DYNAMIC's runtime
        // field-name resolution, this ArrayAccessor's own allowsPath() is
        // the ONLY barrier protecting a plain-object data source — the
        // sanitizer cannot reconstruct a path DYNAMIC only discovers at
        // runtime, and was never meant to.
        $this->warnRootWhitelistInapplicable($data);

        return ResolutionContext::fromArray(['model' => $data], $this->securityValidator)
            ->withStrict($strict);
    }

    /**
     * A class whitelist cannot be applied when the root datum is not a model
     * (spec §11.4): warn so a consumer who populated allowed_root_models and
     * feeds arrays does not read a guarantee into it that does not exist.
     */
    private function warnRootWhitelistInapplicable(mixed $data): void
    {
        if ($this->securityValidator->getMode() === SecurityValidator::MODE_OFF) {
            return;
        }

        if ($this->securityValidator->getAllowedRootModels() === []) {
            return;
        }

        $this->securityValidator->reportViolation(
            'mustache-resolver: allowed_root_models cannot be applied, the root datum is not a model',
            ['type' => get_debug_type($data)],
        );
    }
}
