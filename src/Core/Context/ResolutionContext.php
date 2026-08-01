<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Context;

use AichaDigital\MustacheResolver\Accessors\ArrayAccessor;
use AichaDigital\MustacheResolver\Accessors\EloquentAccessor;
use AichaDigital\MustacheResolver\Contracts\ContextInterface;
use AichaDigital\MustacheResolver\Contracts\DataAccessorInterface;
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;
use AichaDigital\MustacheResolver\Exceptions\ConfigurationException;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Immutable context for resolution operations.
 */
final readonly class ResolutionContext implements ContextInterface
{
    /**
     * @param  array<string, mixed>  $variables
     * @param  array<string, mixed>  $config
     */
    private function __construct(
        private DataAccessorInterface $accessor,
        private array $variables = [],
        private bool $strict = true,
        private ?string $expectedPrefix = null,
        private array $config = [],
    ) {}

    /**
     * Create context with a data accessor.
     */
    public static function create(DataAccessorInterface $accessor): self
    {
        return new self($accessor);
    }

    /**
     * Create context from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, ?SecurityValidator $securityValidator = null): self
    {
        return new self(new ArrayAccessor($data, $securityValidator));
    }

    /**
     * Create context from an Eloquent model with security validation.
     *
     * @param  array<string, mixed>  $securityConfig
     *
     * @throws ConfigurationException If $securityConfig still uses the removed 'allowed_models' key
     */
    public static function fromModel(Model $model, array $securityConfig = []): self
    {
        if (array_key_exists('allowed_models', $securityConfig)) {
            throw ConfigurationException::renamedKey('allowed_models', 'allowed_root_models');
        }

        // v3 (§11.2): no config at all means the default policy. Any
        // explicit config, even a single key, goes through
        // validatorFromConfig() so absent keys fall back to their own
        // per-key defaults rather than silently disabling that guard.
        $validator = $securityConfig === []
            ? SecurityValidator::defaultPolicy()
            : self::validatorFromConfig($securityConfig);

        $accessor = new EloquentAccessor($model, $validator);

        return new self($accessor, [], true, null, $securityConfig);
    }

    /**
     * Build a SecurityValidator from a non-empty $securityConfig, applying
     * the v3 default for every key the caller did not explicitly set
     * (array_key_exists, not ??, so an explicit empty array is honoured
     * as "no blacklist" rather than treated as "not configured").
     *
     * @param  array<string, mixed>  $securityConfig
     */
    private static function validatorFromConfig(array $securityConfig): SecurityValidator
    {
        $reporter = $securityConfig['reporter'] ?? null;

        /** @var array<string> $allowedRootModels */
        $allowedRootModels = $securityConfig['allowed_root_models'] ?? [];

        /** @var array<string> $blacklistedAttributes */
        $blacklistedAttributes = array_key_exists('blacklisted_attributes', $securityConfig)
            ? $securityConfig['blacklisted_attributes']
            : SecurityValidator::DEFAULT_BLACKLISTED_ATTRIBUTES;

        /** @var array<string> $blacklistedPatterns */
        $blacklistedPatterns = array_key_exists('blacklisted_patterns', $securityConfig)
            ? $securityConfig['blacklisted_patterns']
            : SecurityValidator::DEFAULT_BLACKLISTED_PATTERNS;

        return new SecurityValidator(
            allowedRootModels: $allowedRootModels,
            blacklistedAttributes: $blacklistedAttributes,
            blacklistedPatterns: $blacklistedPatterns,
            maxDepth: (int) ($securityConfig['max_depth'] ?? 10),
            mode: (string) ($securityConfig['mode'] ?? SecurityValidator::MODE_ENFORCE),
            reporter: $reporter instanceof Closure ? $reporter : null,
        );
    }

    public function get(string $key): mixed
    {
        if (array_key_exists($key, $this->variables)) {
            return $this->variables[$key];
        }

        return $this->accessor->get($key);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->variables) || $this->accessor->has($key);
    }

    public function with(string $key, mixed $value): static
    {
        $variables = $this->variables;
        $variables[$key] = $value;

        return new self(
            $this->accessor,
            $variables,
            $this->strict,
            $this->expectedPrefix,
            $this->config,
        );
    }

    /**
     * Create context with a different accessor.
     */
    public function withAccessor(DataAccessorInterface $accessor): static
    {
        return new self(
            $accessor,
            $this->variables,
            $this->strict,
            $this->expectedPrefix,
            $this->config,
        );
    }

    /**
     * Create context with strict mode setting.
     */
    public function withStrict(bool $strict): static
    {
        return new self(
            $this->accessor,
            $this->variables,
            $strict,
            $this->expectedPrefix,
            $this->config,
        );
    }

    /**
     * Create context with an expected prefix.
     */
    public function withPrefix(?string $prefix): static
    {
        return new self(
            $this->accessor,
            $this->variables,
            $this->strict,
            $prefix,
            $this->config,
        );
    }

    /**
     * Create context with additional config.
     *
     * @param  array<string, mixed>  $config
     */
    public function withConfig(array $config): static
    {
        return new self(
            $this->accessor,
            $this->variables,
            $this->strict,
            $this->expectedPrefix,
            array_merge($this->config, $config),
        );
    }

    public function getAccessor(): DataAccessorInterface
    {
        return $this->accessor;
    }

    /**
     * @return array<string, mixed>
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    public function isStrict(): bool
    {
        return $this->strict;
    }

    public function getExpectedPrefix(): ?string
    {
        return $this->expectedPrefix;
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}
