<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Resolvers;

use AichaDigital\MustacheResolver\Contracts\ContextInterface;
use AichaDigital\MustacheResolver\Contracts\TokenInterface;
use AichaDigital\MustacheResolver\Core\Temporal\CronWrapper;
use AichaDigital\MustacheResolver\Core\Temporal\TemporalExpression;
use AichaDigital\MustacheResolver\Core\Token\TokenType;
use AichaDigital\MustacheResolver\Exceptions\ResolutionException;
use Carbon\Carbon;
use DateTimeInterface;

/**
 * Resolver for temporal expressions.
 *
 * Handles tokens like:
 * - {{TEMPORAL:isDue('weekday && 08:00-18:00')}} - Returns boolean
 * - {{TEMPORAL:nextRun('cron:0 8 * * 1')}} - Returns datetime string
 * - {{NOW}} - Returns current datetime
 * - {{NOW:format('Y-m-d')}} - Returns formatted datetime
 * - {{NOW:timestamp}} - Returns Unix timestamp
 * - {{TODAY}} - Returns today's date
 * - {{TODAY:startOfDay}} - Returns start of today
 * - {{TODAY:endOfDay}} - Returns end of today
 */
final class TemporalResolver extends AbstractResolver
{
    /** @var array<string, callable(DateTimeInterface): bool> */
    private array $customEvaluators = [];

    private ?DateTimeInterface $testNow = null;

    protected function supportedTypes(): array
    {
        return [TokenType::TEMPORAL];
    }

    public function resolve(TokenInterface $token, ContextInterface $context): mixed
    {
        $metadata = $token->getMetadata();
        $temporalType = $metadata['temporal_type'] ?? 'unknown';
        $functionName = $token->getFunctionName();

        return match ($temporalType) {
            'temporal' => $this->resolveTemporalExpression($token, $context),
            'now' => $this->resolveNow($functionName, $token),
            'today' => $this->resolveToday($functionName, $token),
            default => throw new ResolutionException(
                sprintf('Unknown temporal type: %s', $temporalType)
            ),
        };
    }

    public function priority(): int
    {
        return 90; // High priority, before other resolvers
    }

    public function name(): string
    {
        return 'temporal';
    }

    /**
     * Register a custom evaluator for domain-specific conditions.
     *
     * @param  callable(DateTimeInterface): bool  $evaluator
     */
    public function registerEvaluator(string $keyword, callable $evaluator): self
    {
        $this->customEvaluators[$keyword] = $evaluator;

        return $this;
    }

    /**
     * Set a fixed "now" time for testing.
     */
    public function setTestNow(?DateTimeInterface $now): self
    {
        $this->testNow = $now;

        return $this;
    }

    /**
     * Get the current time (or test time if set).
     */
    private function getNow(): Carbon
    {
        if ($this->testNow !== null) {
            return Carbon::instance($this->testNow);
        }

        return Carbon::now();
    }

    private function resolveTemporalExpression(TokenInterface $token, ContextInterface $context): mixed
    {
        $functionName = $token->getFunctionName();
        $functionArgs = $token->getFunctionArgs();
        $expression = $functionArgs[0] ?? ($token->getMetadata()['expression'] ?? '');

        return match ($functionName) {
            'isDue' => $this->evaluateIsDue($expression),
            'nextRun' => $this->evaluateNextRun($expression),
            'previousRun' => $this->evaluatePreviousRun($expression),
            'isNthWeekday' => $this->evaluateIsNthWeekday($functionArgs),
            'isLastWeekday' => $this->evaluateIsLastWeekday($functionArgs),
            default => throw new ResolutionException(
                sprintf('Unknown TEMPORAL function: %s', $functionName)
            ),
        };
    }

    private function evaluateIsDue(string $expression): bool
    {
        $temporal = new TemporalExpression($expression);

        // Register custom evaluators
        foreach ($this->customEvaluators as $keyword => $evaluator) {
            $temporal->registerEvaluator($keyword, $evaluator);
        }

        return $temporal->evaluate($this->getNow());
    }

    private function evaluateNextRun(string $expression): string
    {
        // Extract CRON expression
        if (str_starts_with($expression, 'cron:')) {
            $cronExpr = substr($expression, 5);
            $cron = new CronWrapper($cronExpr);

            return $cron->getNextRunDate($this->getNow())->format('Y-m-d H:i:s');
        }

        throw new ResolutionException(
            'nextRun requires a CRON expression (cron:...)'
        );
    }

    private function evaluatePreviousRun(string $expression): string
    {
        if (str_starts_with($expression, 'cron:')) {
            $cronExpr = substr($expression, 5);
            $cron = new CronWrapper($cronExpr);

            return $cron->getPreviousRunDate($this->getNow())->format('Y-m-d H:i:s');
        }

        throw new ResolutionException(
            'previousRun requires a CRON expression (cron:...)'
        );
    }

    /**
     * @param  array<int, mixed>  $args
     */
    private function evaluateIsNthWeekday(array $args): bool
    {
        if (count($args) < 2) {
            throw new ResolutionException(
                'isNthWeekday requires 2 arguments: dayOfWeek, occurrence'
            );
        }

        $dayOfWeek = (string) $args[0];
        $occurrence = (int) $args[1];

        return CronWrapper::isNthWeekday($dayOfWeek, $occurrence, $this->getNow());
    }

    /**
     * @param  array<int, mixed>  $args
     */
    private function evaluateIsLastWeekday(array $args): bool
    {
        if (count($args) < 1) {
            throw new ResolutionException(
                'isLastWeekday requires 1 argument: dayOfWeek'
            );
        }

        $dayOfWeek = (string) $args[0];

        return CronWrapper::isLastWeekday($dayOfWeek, $this->getNow());
    }

    private function resolveNow(?string $functionName, TokenInterface $token): mixed
    {
        $now = $this->getNow();
        $args = $token->getFunctionArgs();

        return match ($functionName) {
            'default', null => $now->toDateTimeString(),
            'format' => $now->format($args[0] ?? 'Y-m-d H:i:s'),
            'timestamp' => $now->timestamp,
            'iso8601' => $now->toIso8601String(),
            'atom' => $now->toAtomString(),
            'rfc3339' => $now->toRfc3339String(),
            'date' => $now->toDateString(),
            'time' => $now->toTimeString(),
            'datetime' => $now->toDateTimeString(),
            'dayOfWeek' => $now->dayOfWeek,
            'dayOfMonth' => $now->day,
            'month' => $now->month,
            'year' => $now->year,
            'hour' => $now->hour,
            'minute' => $now->minute,
            'second' => $now->second,
            'isWeekday' => $now->isWeekday(),
            'isWeekend' => $now->isWeekend(),
            default => throw new ResolutionException(
                sprintf('Unknown NOW function: %s', $functionName)
            ),
        };
    }

    private function resolveToday(?string $functionName, TokenInterface $token): mixed
    {
        $today = $this->getNow()->startOfDay();
        $args = $token->getFunctionArgs();

        return match ($functionName) {
            'default', null => $today->toDateString(),
            'format' => $today->format($args[0] ?? 'Y-m-d'),
            'startOfDay' => $today->toDateTimeString(),
            'endOfDay' => $today->endOfDay()->toDateTimeString(),
            'timestamp' => $today->timestamp,
            'dayOfWeek' => $today->dayOfWeek,
            'dayOfMonth' => $today->day,
            'dayOfYear' => $today->dayOfYear,
            'weekOfYear' => $today->weekOfYear,
            'month' => $today->month,
            'year' => $today->year,
            'isWeekday' => $today->isWeekday(),
            'isWeekend' => $today->isWeekend(),
            'isFirstDayOfMonth' => $today->day === 1,
            'isLastDayOfMonth' => $today->day === $today->daysInMonth,
            default => throw new ResolutionException(
                sprintf('Unknown TODAY function: %s', $functionName)
            ),
        };
    }
}
