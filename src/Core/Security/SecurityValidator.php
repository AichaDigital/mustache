<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Security;

use AichaDigital\MustacheResolver\Exceptions\ModelNotAllowedException;
use Closure;

/**
 * Validates security constraints for model and attribute access.
 *
 * Modes:
 * - off: no checks are applied
 * - report: violations are reported through the reporter callable but resolution proceeds
 * - enforce: violations block resolution (paths) or throw (model validation)
 */
final readonly class SecurityValidator
{
    public const MODE_OFF = 'off';

    public const MODE_REPORT = 'report';

    public const MODE_ENFORCE = 'enforce';

    /**
     * @param  array<string>  $allowedModels
     * @param  array<string>  $blacklistedAttributes
     * @param  (Closure(string, array<string, mixed>): void)|null  $reporter
     */
    public function __construct(
        private array $allowedModels = [],
        private array $blacklistedAttributes = [],
        private int $maxDepth = 10,
        private string $mode = self::MODE_ENFORCE,
        private ?Closure $reporter = null,
    ) {}

    /**
     * Check if a model class is allowed.
     *
     * In report mode, violations are reported instead of throwing.
     *
     * @throws ModelNotAllowedException
     */
    public function validateModel(string $modelClass): void
    {
        if (empty($this->allowedModels)) {
            return; // All models allowed when list is empty
        }

        // Check full class name
        if (in_array($modelClass, $this->allowedModels, true)) {
            return;
        }

        // Check short class name
        $shortName = class_basename($modelClass);
        if (in_array($shortName, $this->allowedModels, true)) {
            return;
        }

        if ($this->mode === self::MODE_OFF) {
            return;
        }

        if ($this->mode === self::MODE_REPORT) {
            $this->report('mustache-resolver: model access would be blocked in enforce mode', [
                'model' => $modelClass,
                'allowed_models' => $this->allowedModels,
            ]);

            return;
        }

        throw new ModelNotAllowedException($modelClass, $this->allowedModels);
    }

    /**
     * Check whether a dot-notation path may be resolved.
     *
     * Every segment is checked against the blacklist and the path
     * depth against max_depth. In report mode violations are reported
     * and the path is allowed; in enforce mode they block it.
     */
    public function allowsPath(string $path): bool
    {
        if ($this->mode === self::MODE_OFF) {
            return true;
        }

        $segments = explode('.', $path);

        $blacklistedSegments = array_values(array_filter(
            $segments,
            fn (string $segment): bool => $this->isAttributeBlacklisted($segment)
        ));

        $depthExceeded = $this->isDepthExceeded(count($segments));

        if ($blacklistedSegments === [] && ! $depthExceeded) {
            return true;
        }

        if ($blacklistedSegments !== []) {
            $this->report($this->mode === self::MODE_ENFORCE
                ? 'mustache-resolver: path blocked by security policy (blacklisted attribute)'
                : 'mustache-resolver: path contains blacklisted attribute(s), it would be blocked in enforce mode', [
                    'path' => $path,
                    'blacklisted_segments' => $blacklistedSegments,
                ]);
        }

        if ($depthExceeded) {
            $this->report($this->mode === self::MODE_ENFORCE
                ? 'mustache-resolver: path blocked by security policy (max_depth exceeded)'
                : 'mustache-resolver: path exceeds max_depth, it would be blocked in enforce mode', [
                    'path' => $path,
                    'depth' => count($segments),
                    'max_depth' => $this->maxDepth,
                ]);
        }

        if ($this->mode === self::MODE_REPORT) {
            return true;
        }

        return false;
    }

    /**
     * Check if an attribute is blacklisted (case-insensitive).
     */
    public function isAttributeBlacklisted(string $attribute): bool
    {
        $attribute = strtolower($attribute);

        foreach ($this->blacklistedAttributes as $blacklisted) {
            if (strtolower($blacklisted) === $attribute) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if depth exceeds maximum.
     */
    public function isDepthExceeded(int $depth): bool
    {
        return $depth > $this->maxDepth;
    }

    /**
     * Get allowed models list.
     *
     * @return array<string>
     */
    public function getAllowedModels(): array
    {
        return $this->allowedModels;
    }

    /**
     * Get the current enforcement mode.
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * Get the configured maximum depth.
     */
    public function getMaxDepth(): int
    {
        return $this->maxDepth;
    }

    /**
     * Report a security violation through the configured reporter.
     *
     * Public entry point for components outside the validator (e.g. the
     * resolver) that need to log a policy event through the same channel.
     *
     * @param  array<string, mixed>  $context
     */
    public function reportViolation(string $message, array $context = []): void
    {
        $this->report($message, $context);
    }

    /**
     * Report a security violation through the configured reporter.
     *
     * @param  array<string, mixed>  $context
     */
    private function report(string $message, array $context = []): void
    {
        if ($this->reporter !== null) {
            ($this->reporter)($message, $context);
        }
    }
}
