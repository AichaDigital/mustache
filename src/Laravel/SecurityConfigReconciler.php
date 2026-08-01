<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Laravel;

use AichaDigital\MustacheResolver\Core\Parser\MustacheParser;
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;

/**
 * Reconciles a consumer's security config block against the v3 defaults.
 *
 * Why this exists: mergeConfigFrom() uses a shallow array_merge and
 * `security` is a top-level key, so a config block published under v2
 * replaces the package block ENTIRELY — the v3 keys simply do not exist
 * for that consumer, and under config:cache the merge never runs at all.
 * Stating "v3 defaults to enforce" without this would repeat the exact
 * defect AID-632 exposed: trusting a default an intermediate layer masks.
 *
 * Rules (spec §11.1):
 * - A key that is ABSENT takes the v3 default (recorded, warned at boot).
 * - A key that is PRESENT — including as [] — is a deliberate choice and
 *   is never touched. array_key_exists(), never ??.
 * - `mode` follows the same absence rule but is NEVER rewritten when
 *   present: there is no way to tell an inherited `report` from a chosen one.
 * - Legacy `allowed_models` is carried into `allowed_root_models` (unless
 *   the new key is present) and flagged so the boot warning names the
 *   rename — silently ignoring it would drop a hardening control.
 * - Removed keys (`allowed_models`, `allowed_tables`) are stripped.
 */
final class SecurityConfigReconciler
{
    /**
     * @param  array<string, mixed>  $security
     * @return array{security: array<string, mixed>, absent: list<string>, legacy_allowed_models: bool}
     */
    public static function reconcile(array $security): array
    {
        $absent = [];
        $legacyAllowedModels = array_key_exists('allowed_models', $security);

        if ($legacyAllowedModels && ! array_key_exists('allowed_root_models', $security)) {
            $security['allowed_root_models'] = $security['allowed_models'];
        }

        unset($security['allowed_models'], $security['allowed_tables']);

        foreach (self::defaults() as $key => $default) {
            if (! array_key_exists($key, $security)) {
                $security[$key] = $default;
                $absent[] = 'security.'.$key;
            }
        }

        return [
            'security' => $security,
            'absent' => $absent,
            'legacy_allowed_models' => $legacyAllowedModels,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaults(): array
    {
        return [
            'mode' => SecurityValidator::MODE_ENFORCE,
            'allowed_root_models' => [],
            'max_depth' => 10,
            'allow_container_serialization' => false,
            'blacklisted_attributes' => SecurityValidator::DEFAULT_BLACKLISTED_ATTRIBUTES,
            'blacklisted_patterns' => SecurityValidator::DEFAULT_BLACKLISTED_PATTERNS,
            'limits' => [
                'max_template_length' => MustacheParser::DEFAULT_MAX_TEMPLATE_LENGTH,
                'max_tokens' => MustacheParser::DEFAULT_MAX_TOKENS,
            ],
        ];
    }
}
