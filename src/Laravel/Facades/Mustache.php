<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \AichaDigital\MustacheResolver\Contracts\ResultInterface translate(string $template, mixed $data, array<string, mixed> $variables = [], bool $strict = true)
 * @method static array<int, \AichaDigital\MustacheResolver\Contracts\ResultInterface> translateBatch(array<int, string> $templates, mixed $data, array<string, mixed> $variables = [], bool $strict = true)
 * @method static array<int, \AichaDigital\MustacheResolver\Contracts\TokenInterface> parse(string $template)
 * @method static bool hasMustaches(string $template)
 *
 * @see \AichaDigital\MustacheResolver\Core\MustacheResolver
 */
class Mustache extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'mustache';
    }
}
