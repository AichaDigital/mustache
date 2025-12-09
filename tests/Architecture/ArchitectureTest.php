<?php

declare(strict_types=1);

arch('contracts should be interfaces')
    ->expect('AichaDigital\MustacheResolver\Contracts')
    ->toBeInterfaces();

arch('exceptions should extend base exception')
    ->expect('AichaDigital\MustacheResolver\Exceptions')
    ->toExtend('AichaDigital\MustacheResolver\Exceptions\MustacheException')
    ->ignoring('AichaDigital\MustacheResolver\Exceptions\MustacheException');

arch('source code should use strict types')
    ->expect('AichaDigital\MustacheResolver')
    ->toUseStrictTypes();

arch('no debugging statements')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();

arch('no eval usage')
    ->expect('eval')
    ->not->toBeUsed();

arch('token classes should be in Core\Token namespace')
    ->expect('AichaDigital\MustacheResolver\Core\Token')
    ->toBeClasses()
    ->ignoring('AichaDigital\MustacheResolver\Core\Token\TokenType');

arch('TokenType should be an enum')
    ->expect('AichaDigital\MustacheResolver\Core\Token\TokenType')
    ->toBeEnum();
