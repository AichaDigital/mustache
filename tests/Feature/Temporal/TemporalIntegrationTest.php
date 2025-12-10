<?php

declare(strict_types=1);

use AichaDigital\MustacheResolver\Cache\NullCache;
use AichaDigital\MustacheResolver\Core\MustacheResolver;
use AichaDigital\MustacheResolver\Core\Parser\MustacheParser;
use AichaDigital\MustacheResolver\Core\Pipeline\PipelineBuilder;
use AichaDigital\MustacheResolver\Resolvers\TemporalResolver;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2025-12-10 14:30:00'); // Wednesday
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Helper function to create a properly configured MustacheResolver.
 */
function createResolver(?PipelineBuilder $builder = null): MustacheResolver
{
    $pipeline = ($builder ?? PipelineBuilder::create())->build();

    return new MustacheResolver(
        new MustacheParser,
        $pipeline,
        new NullCache,
    );
}

describe('Temporal Integration → Full Mustache Resolution', function () {
    it('resolves NOW in template', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $resolver = createResolver();

        $result = $resolver->translate('Current time: {{NOW}}', []);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getTranslated())->toBe('Current time: 2025-12-10 14:30:00');
    });

    it('resolves NOW:format in template', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $resolver = createResolver();

        $result = $resolver->translate("Date: {{NOW:format('Y-m-d')}}", []);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getTranslated())->toBe('Date: 2025-12-10');
    });

    it('resolves TODAY in template', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $resolver = createResolver();

        $result = $resolver->translate('Today is: {{TODAY}}', []);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getTranslated())->toBe('Today is: 2025-12-10');
    });

    it('resolves TODAY:startOfDay in template', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $resolver = createResolver();

        $result = $resolver->translate('Day starts: {{TODAY:startOfDay}}', []);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getTranslated())->toBe('Day starts: 2025-12-10 00:00:00');
    });

    it('resolves TEMPORAL:isDue in template', function () {
        Carbon::setTestNow('2025-12-10 14:30:00'); // Wednesday 14:30
        $resolver = createResolver();

        $result = $resolver->translate("Is weekday: {{TEMPORAL:isDue('weekday')}}", []);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getTranslated())->toContain('true'); // Boolean true as string
    });

    it('resolves multiple temporal expressions', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $resolver = createResolver();

        $result = $resolver->translate('Date: {{TODAY}}, Time: {{NOW:time}}', []);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getTranslated())->toBe('Date: 2025-12-10, Time: 14:30:00');
    });
});

describe('Temporal Integration → With Custom Evaluators', function () {
    it('uses custom evaluator in resolution', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');

        $temporalResolver = new TemporalResolver;
        $temporalResolver->registerEvaluator('holiday', fn () => false);

        $builder = PipelineBuilder::create()
            ->withoutDefaults()
            ->addResolver($temporalResolver);

        $resolver = createResolver($builder);

        $result = $resolver->translate("Working: {{TEMPORAL:isDue('weekday && !holiday')}}", []);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getTranslated())->toContain('true');
    });
});

describe('Temporal Integration → Real Sitelight Scenarios', function () {
    it('evaluates weekday working hours trigger', function () {
        Carbon::setTestNow('2025-12-10 10:00:00'); // Wednesday 10:00
        $resolver = createResolver();

        $result = $resolver->translate("Active: {{TEMPORAL:isDue('weekday && 08:00-18:00')}}", []);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getTranslated())->toContain('true');
    });

    it('evaluates weekend or day trigger with custom evaluator', function () {
        Carbon::setTestNow('2025-12-10 14:00:00'); // Wednesday

        $temporalResolver = new TemporalResolver;
        $temporalResolver->registerEvaluator('day', fn () => true); // Simulating daylight

        $builder = PipelineBuilder::create()
            ->withoutDefaults()
            ->addResolver($temporalResolver);

        $resolver = createResolver($builder);

        $result = $resolver->translate("Trigger: {{TEMPORAL:isDue('day || weekend')}}", []);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getTranslated())->toContain('true');
    });

    it('evaluates first Saturday trigger', function () {
        Carbon::setTestNow('2025-12-06 10:30:00'); // First Saturday 10:30
        $resolver = createResolver();

        $result = $resolver->translate("First Saturday: {{TEMPORAL:isDue('nth:saturday:1 && 10:00-14:00')}}", []);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getTranslated())->toContain('true');
    });

    it('provides next cron run date', function () {
        Carbon::setTestNow('2025-12-10 09:00:00');
        $resolver = createResolver();

        $result = $resolver->translate("Next run: {{TEMPORAL:nextRun('cron:0 8 * * *')}}", []);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getTranslated())->toBe('Next run: 2025-12-11 08:00:00');
    });
});

describe('Temporal Integration → Date/Time Properties', function () {
    it('resolves NOW:dayOfWeek', function () {
        Carbon::setTestNow('2025-12-10 14:30:00'); // Wednesday = 3
        $resolver = createResolver();

        $result = $resolver->translate('Day of week: {{NOW:dayOfWeek}}', []);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getTranslated())->toBe('Day of week: 3');
    });

    it('resolves NOW:isWeekday', function () {
        Carbon::setTestNow('2025-12-10 14:30:00'); // Wednesday
        $resolver = createResolver();

        $result = $resolver->translate('Is weekday: {{NOW:isWeekday}}', []);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getTranslated())->toContain('true');
    });

    it('resolves TODAY:dayOfMonth', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $resolver = createResolver();

        $result = $resolver->translate('Day: {{TODAY:dayOfMonth}}', []);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getTranslated())->toBe('Day: 10');
    });

    it('resolves TODAY:month', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $resolver = createResolver();

        $result = $resolver->translate('Month: {{TODAY:month}}', []);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getTranslated())->toBe('Month: 12');
    });

    it('resolves TODAY:year', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $resolver = createResolver();

        $result = $resolver->translate('Year: {{TODAY:year}}', []);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getTranslated())->toBe('Year: 2025');
    });
});

describe('Temporal Integration → ISO Formats', function () {
    it('resolves NOW:iso8601', function () {
        Carbon::setTestNow('2025-12-10 14:30:00');
        $resolver = createResolver();

        $result = $resolver->translate('ISO: {{NOW:iso8601}}', []);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getTranslated())->toContain('2025-12-10T14:30:00');
    });
});
