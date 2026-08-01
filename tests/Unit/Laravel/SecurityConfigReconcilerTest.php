<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Parser\MustacheParser;
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;
use AichaDigital\MustacheResolver\Laravel\SecurityConfigReconciler;

describe('SecurityConfigReconciler', function () {
    it('fills every absent key with the v3 default and records it', function () {
        $result = SecurityConfigReconciler::reconcile([]);

        expect($result['security']['mode'])->toBe(SecurityValidator::MODE_ENFORCE);
        expect($result['security']['blacklisted_attributes'])->toBe(SecurityValidator::DEFAULT_BLACKLISTED_ATTRIBUTES);
        expect($result['security']['blacklisted_patterns'])->toBe(SecurityValidator::DEFAULT_BLACKLISTED_PATTERNS);
        expect($result['security']['allowed_root_models'])->toBe([]);
        expect($result['security']['allow_container_serialization'])->toBeFalse();
        expect($result['security']['max_depth'])->toBe(10);
        expect($result['security']['limits'])->toBe([
            'max_template_length' => MustacheParser::DEFAULT_MAX_TEMPLATE_LENGTH,
            'max_tokens' => MustacheParser::DEFAULT_MAX_TOKENS,
        ]);
        expect($result['absent'])->toContain('security.mode', 'security.blacklisted_patterns', 'security.limits');
        expect($result['legacy_allowed_models'])->toBeFalse();
    });

    it('never overrides a present mode, even report', function () {
        $result = SecurityConfigReconciler::reconcile(['mode' => 'report']);

        expect($result['security']['mode'])->toBe('report');
        expect($result['absent'])->not->toContain('security.mode');
    });

    it('honours a deliberate empty value over the default', function () {
        $result = SecurityConfigReconciler::reconcile(['blacklisted_patterns' => []]);

        expect($result['security']['blacklisted_patterns'])->toBe([]);
        expect($result['absent'])->not->toContain('security.blacklisted_patterns');
    });

    it('maps legacy allowed_models into allowed_root_models and flags it', function () {
        $result = SecurityConfigReconciler::reconcile([
            'allowed_models' => ['App\\Models\\User'],
        ]);

        expect($result['security']['allowed_root_models'])->toBe(['App\\Models\\User']);
        expect($result['security'])->not->toHaveKey('allowed_models');
        expect($result['legacy_allowed_models'])->toBeTrue();
    });

    it('lets an explicit allowed_root_models win over the legacy key', function () {
        $result = SecurityConfigReconciler::reconcile([
            'allowed_models' => ['App\\Models\\Old'],
            'allowed_root_models' => ['App\\Models\\NewUser'],
        ]);

        expect($result['security']['allowed_root_models'])->toBe(['App\\Models\\NewUser']);
        expect($result['legacy_allowed_models'])->toBeTrue();
    });

    it('drops the removed allowed_tables key', function () {
        $result = SecurityConfigReconciler::reconcile(['allowed_tables' => ['users']]);

        expect($result['security'])->not->toHaveKey('allowed_tables');
    });

    it('reconciling the real v2.1.0 published security block preserves report mode', function () {
        /** @var array{security: array<string, mixed>} $v2 */
        $v2 = require __DIR__.'/../../Fixtures/config/mustache-resolver-v2.php';

        $result = SecurityConfigReconciler::reconcile($v2['security']);

        expect($result['security']['mode'])->toBe('report');
        expect($result['security']['blacklisted_patterns'])->toBe(SecurityValidator::DEFAULT_BLACKLISTED_PATTERNS);
        expect($result['absent'])->toContain('security.blacklisted_patterns', 'security.limits');
    });

    it('reconciling the shipped config file itself is a no-op (drift guard)', function () {
        // Reads the FILE, not config() (AID-589 lesson): the strongest
        // guard against SecurityConfigReconciler::defaults() and the
        // shipped config/mustache-resolver.php falling out of sync on key
        // names. If a future rename touches one but not the other (e.g.
        // allow_container_serialization renamed in the file only), that
        // key reads as "absent" here and every fresh install would warn
        // spuriously despite shipping a fully populated config.
        /** @var array{security: array<string, mixed>} $file */
        $file = require __DIR__.'/../../../config/mustache-resolver.php';

        $result = SecurityConfigReconciler::reconcile($file['security']);

        expect($result['absent'])->toBe([]);
        expect($result['legacy_allowed_models'])->toBeFalse();
    });
});
