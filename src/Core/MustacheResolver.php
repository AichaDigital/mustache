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
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;
use AichaDigital\MustacheResolver\Exceptions\ResolutionException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;

/**
 * Main entry point for mustache template resolution.
 */
final class MustacheResolver
{
    public function __construct(
        private readonly ParserInterface $parser,
        private readonly ResolutionPipeline $pipeline,
        private readonly CacheInterface $cache,
        private readonly ?SecurityValidator $securityValidator = null,
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
                $this->reportContainerViolations($token, $value);
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
            return ResolutionContext::fromArray($data, $this->securityValidator)
                ->withStrict($strict);
        }

        // Assume it's an object, wrap it in accessor
        return ResolutionContext::fromArray(['model' => $data])
            ->withStrict($strict);
    }

    /**
     * Report containers whose contents include blacklisted attributes.
     *
     * The output is intentionally left intact in 2.1; the warning tells
     * consumers that a future major version will filter these values.
     * Only active in report mode.
     */
    private function reportContainerViolations(TokenInterface $token, mixed $value): void
    {
        if ($this->securityValidator === null
            || $this->securityValidator->getMode() !== SecurityValidator::MODE_REPORT) {
            return;
        }

        if ($value instanceof Model) {
            return; // handled by modelToString()
        }

        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        }

        if (! is_array($value)) {
            return;
        }

        $found = $this->findBlacklistedKeys($value);

        if ($found !== []) {
            $this->securityValidator->reportViolation(
                'mustache-resolver: resolved container contains blacklisted attribute(s), it would be filtered in a future major version',
                ['path' => $token->getRaw(), 'blacklisted_attributes' => array_values(array_unique($found))],
            );
        }
    }

    /**
     * Collect blacklisted keys present in an array, recursively.
     *
     * @param  array<mixed>  $data
     * @return array<int, string>
     */
    private function findBlacklistedKeys(array $data): array
    {
        $found = [];

        foreach ($data as $key => $item) {
            if (is_string($key) && $this->securityValidator?->isAttributeBlacklisted($key)) {
                $found[] = $key;
            }

            if ($item instanceof Arrayable) {
                $item = $item->toArray();
            }

            if (is_array($item)) {
                $found = array_merge($found, $this->findBlacklistedKeys($item));
            }
        }

        return $found;
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
            if ($value instanceof Model) {
                return $this->modelToString($value);
            }

            if (method_exists($value, '__toString')) {
                return (string) $value;
            }

            return '';
        }

        return (string) $value;
    }

    /**
     * Serialize a whole Eloquent model for template replacement.
     *
     * Serializing a model dumps all its attributes, bypassing path-based
     * checks: in enforce mode the blacklist is applied to the serialized
     * output, in report mode a warning is logged and output is unchanged.
     * When nothing would be filtered, native serialization is kept and
     * no warning is emitted.
     */
    private function modelToString(Model $model): string
    {
        if ($this->securityValidator === null
            || $this->securityValidator->getMode() === SecurityValidator::MODE_OFF) {
            return (string) $model;
        }

        $attributes = $model->toArray();
        $stripped = $this->stripBlacklistedAttributes($attributes);

        // Nothing to filter: keep Eloquent's native serialization untouched
        if ($stripped === $attributes) {
            return (string) $model;
        }

        if ($this->securityValidator->getMode() === SecurityValidator::MODE_REPORT) {
            $this->securityValidator->reportViolation(
                'mustache-resolver: whole model serialization would be filtered in enforce mode',
                ['model' => get_class($model)],
            );

            return (string) $model;
        }

        $this->securityValidator->reportViolation(
            'mustache-resolver: blacklisted attributes stripped from serialized model',
            ['model' => get_class($model)],
        );

        // Preserve Eloquent's escapeWhenCastingToString() behavior: the flag
        // is protected, so it is read through a closure bound to the model
        $reader = function (): bool {
            // @phpstan-ignore-next-line — bound to the model to read its protected flag
            return (bool) $this->escapeWhenCastingToString;
        };

        $json = json_encode($stripped) ?: '';

        return $reader->call($model) ? e($json) : $json;
    }

    /**
     * Remove blacklisted keys from an array representation, recursively.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function stripBlacklistedAttributes(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($this->securityValidator?->isAttributeBlacklisted((string) $key)) {
                unset($data[$key]);

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->stripBlacklistedAttributes($value);
            }
        }

        return $data;
    }
}
