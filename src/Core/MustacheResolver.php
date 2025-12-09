<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core;

use AichaDigital\MustacheResolver\Contracts\CacheInterface;
use AichaDigital\MustacheResolver\Contracts\ContextInterface;
use AichaDigital\MustacheResolver\Contracts\DataAccessorInterface;
use AichaDigital\MustacheResolver\Contracts\ParserInterface;
use AichaDigital\MustacheResolver\Contracts\ResultInterface;
use AichaDigital\MustacheResolver\Core\Context\ResolutionContext;
use AichaDigital\MustacheResolver\Core\Pipeline\ResolutionPipeline;
use AichaDigital\MustacheResolver\Core\Result\TranslationResult;
use AichaDigital\MustacheResolver\Exceptions\ResolutionException;

/**
 * Main entry point for mustache template resolution.
 */
final class MustacheResolver
{
    public function __construct(
        private readonly ParserInterface $parser,
        private readonly ResolutionPipeline $pipeline,
        private readonly CacheInterface $cache,
    ) {}

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
                $value = $this->pipeline->resolve($token, $context);
                $stringValue = $this->valueToString($value);
                $translated = str_replace($token->getFull(), $stringValue, $translated);
                $resolvedValues[$token->getRaw()] = $value;
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
     * @return \AichaDigital\MustacheResolver\Contracts\TokenInterface[]
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

        if (is_array($data)) {
            return ResolutionContext::fromArray($data)
                ->withStrict($strict);
        }

        // Assume it's an object/model, wrap it in accessor
        return ResolutionContext::fromArray(['model' => $data])
            ->withStrict($strict);
    }

    /**
     * Convert a resolved value to string for template replacement.
     */
    private function valueToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return implode(', ', array_map(fn ($v) => $this->valueToString($v), $value));
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }

            return '';
        }

        return (string) $value;
    }
}
