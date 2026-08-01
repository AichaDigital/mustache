<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Core\Parser\MustacheParser;
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;

it('the published config file ships the v3 security surface', function () {
    /** @var array<string, mixed> $file */
    $file = require dirname(__DIR__, 3).'/config/mustache-resolver.php';

    /** @var array<string, mixed> $security */
    $security = $file['security'];

    expect($security['mode'])->toBe('enforce');
    expect($security)->not->toHaveKey('allowed_tables');
    expect($security)->not->toHaveKey('allowed_models');
    expect($security)->toHaveKey('allowed_root_models');
    expect($security['blacklisted_attributes'])->toBe(SecurityValidator::DEFAULT_BLACKLISTED_ATTRIBUTES);
    expect($security['blacklisted_patterns'])->toBe(SecurityValidator::DEFAULT_BLACKLISTED_PATTERNS);
    expect($security['limits'])->toBe([
        'max_template_length' => MustacheParser::DEFAULT_MAX_TEMPLATE_LENGTH,
        'max_tokens' => MustacheParser::DEFAULT_MAX_TOKENS,
    ]);
});
