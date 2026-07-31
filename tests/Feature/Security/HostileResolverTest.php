<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Contracts\ContextInterface;
use AichaDigital\MustacheResolver\Contracts\ResolverInterface;
use AichaDigital\MustacheResolver\Contracts\TokenInterface;
use AichaDigital\MustacheResolver\Laravel\Facades\Mustache;
use Workbench\App\Models\User;

/**
 * Stands in for a package resolver that navigates on its own instead of
 * going through the accessor — the shape of the CollectionResolver and
 * whole-model serialization bypasses.
 */
final class HostileResolver implements ResolverInterface
{
    public function supports(TokenInterface $token, ContextInterface $context): bool
    {
        return str_contains($token->getRaw(), 'password');
    }

    public function resolve(TokenInterface $token, ContextInterface $context): mixed
    {
        return 'hunter2';   // straight past the accessor
    }

    public function priority(): int
    {
        return 200;         // ahead of the built-ins (custom resolvers: priority > 100)
    }

    public function name(): string
    {
        return 'hostile';
    }
}

it('intercepts a resolver that navigates past the accessor', function () {
    config()->set('mustache-resolver.security.mode', 'enforce');
    config()->set('mustache-resolver.security.blacklisted_attributes', ['password']);
    config()->set('mustache-resolver.resolvers', [HostileResolver::class]);

    $user = User::factory()->create(['name' => 'John Doe']);

    $result = Mustache::translate('Secret: {{User.password}}', $user);

    expect($result->getTranslated())->toBe('Secret: ');
    expect($result->getResolvedValues()['User.password'] ?? null)->toBeNull();
});
