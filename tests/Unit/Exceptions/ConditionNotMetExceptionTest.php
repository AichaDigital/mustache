<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Exceptions\ConditionNotMetException;
use AichaDigital\MustacheResolver\Exceptions\ResolutionException;

it('extends ResolutionException', function () {
    $exception = new ConditionNotMetException('max_power', 0, '> 0', '{{CommandCenter.max_power}} > 0');

    expect($exception)->toBeInstanceOf(ResolutionException::class);
});

it('formats message correctly', function () {
    $exception = new ConditionNotMetException('max_power', 0, '> 0', '{{CommandCenter.max_power}} > 0');

    expect($exception->getMessage())
        ->toContain('max_power')
        ->toContain('0')
        ->toContain('> 0');
});

it('provides variable name', function () {
    $exception = new ConditionNotMetException('max_power', 0, '> 0', '{{CommandCenter.max_power}} > 0');

    expect($exception->getVariableName())->toBe('max_power');
});

it('provides actual value', function () {
    $exception = new ConditionNotMetException('threshold', -5, '>= 0', '{{Settings.threshold}} >= 0');

    expect($exception->getActualValue())->toBe(-5);
});

it('provides condition', function () {
    $exception = new ConditionNotMetException('status', 'inactive', "== 'active'", "{{User.status}} == 'active'");

    expect($exception->getCondition())->toBe("== 'active'");
});

it('provides expression', function () {
    $exception = new ConditionNotMetException('max_power', 0, '> 0', '{{CommandCenter.max_power}} > 0');

    expect($exception->getExpression())->toBe('{{CommandCenter.max_power}} > 0');
});

it('provides context for logging', function () {
    $exception = new ConditionNotMetException('max_power', 0, '> 0', '{{CommandCenter.max_power}} > 0');

    $context = $exception->getContext();

    expect($context)->toBe([
        'variable' => 'max_power',
        'value' => 0,
        'condition' => '> 0',
        'expression' => '{{CommandCenter.max_power}} > 0',
    ]);
});

it('handles different value types', function () {
    $exceptionWithNull = new ConditionNotMetException('field', null, '!= null', '{{Model.field}} != null');
    expect($exceptionWithNull->getActualValue())->toBeNull();

    $exceptionWithArray = new ConditionNotMetException('items', [], '!= []', '{{Model.items}} != []');
    expect($exceptionWithArray->getActualValue())->toBe([]);

    $exceptionWithString = new ConditionNotMetException('status', 'pending', "== 'active'", "{{Model.status}} == 'active'");
    expect($exceptionWithString->getActualValue())->toBe('pending');
});
