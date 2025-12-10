<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Temporal;

use AichaDigital\MustacheResolver\Contracts\TemporalExpressionInterface;
use AichaDigital\MustacheResolver\Exceptions\ResolutionException;
use Carbon\Carbon;
use DateTimeInterface;

/**
 * Evaluates temporal expressions.
 *
 * Combines multiple conditions with logical operators to create complex
 * temporal rules like "weekday && 08:00-18:00 && !holiday".
 *
 * Built-in conditions:
 * - always: Always true
 * - never: Always false
 * - weekday: Monday to Friday
 * - weekend: Saturday and Sunday
 * - HH:MM-HH:MM: Time range (supports overnight)
 * - cron:EXPRESSION: CRON expression
 * - nth:DAY:N: Nth weekday of month (e.g., nth:saturday:1 for first Saturday)
 * - last:DAY: Last weekday of month
 *
 * Operators:
 * - && (AND)
 * - || (OR)
 * - ! (NOT)
 * - () (grouping)
 */
final class TemporalExpression implements TemporalExpressionInterface
{
    /** @var array<string, callable(DateTimeInterface): bool> */
    private array $customEvaluators = [];

    /** @var array<string, mixed>|null */
    private ?array $ast = null;

    public function __construct(
        private readonly string $expression
    ) {}

    public function evaluate(?DateTimeInterface $at = null): bool
    {
        $at = $at ? Carbon::instance($at) : Carbon::now();

        if ($this->ast === null) {
            $parser = new ExpressionParser($this->expression);
            $this->ast = $parser->parse();
        }

        return $this->evaluateNode($this->ast, $at);
    }

    public function registerEvaluator(string $keyword, callable $evaluator): self
    {
        $this->customEvaluators[$keyword] = $evaluator;

        // Reset AST to force re-parsing with new evaluator
        $this->ast = null;

        return $this;
    }

    public function hasEvaluator(string $keyword): bool
    {
        return isset($this->customEvaluators[$keyword]);
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    public function getRegisteredKeywords(): array
    {
        return array_keys($this->customEvaluators);
    }

    /**
     * Get all keywords used in the expression (for validation).
     *
     * @return array<int, string>
     */
    public function getUsedKeywords(): array
    {
        $parser = new ExpressionParser($this->expression);

        return $parser->extractKeywords();
    }

    /**
     * Validate that all custom keywords in the expression have registered evaluators.
     *
     * @return array<int, string> List of missing evaluators
     */
    public function getMissingEvaluators(): array
    {
        $builtIn = ['always', 'never', 'weekday', 'weekend'];
        $missing = [];

        foreach ($this->getUsedKeywords() as $keyword) {
            // Skip built-in keywords
            if (in_array($keyword, $builtIn, true)) {
                continue;
            }

            // Skip special prefixed conditions
            if (str_starts_with($keyword, 'cron:') ||
                str_starts_with($keyword, 'nth:') ||
                str_starts_with($keyword, 'last:') ||
                preg_match('/^\d{1,2}:\d{2}-\d{1,2}:\d{2}$/', $keyword)) {
                continue;
            }

            // Check if custom evaluator exists
            if (! isset($this->customEvaluators[$keyword])) {
                $missing[] = $keyword;
            }
        }

        return $missing;
    }

    /**
     * Evaluate an AST node.
     *
     * @param  array<string, mixed>  $node
     */
    private function evaluateNode(array $node, DateTimeInterface $at): bool
    {
        return match ($node['type']) {
            'and' => $this->evaluateNode($node['left'], $at) && $this->evaluateNode($node['right'], $at),
            'or' => $this->evaluateNode($node['left'], $at) || $this->evaluateNode($node['right'], $at),
            'not' => ! $this->evaluateNode($node['operand'], $at),
            'keyword' => $this->evaluateKeyword($node['keyword'], $at),
            'time_range' => $this->evaluateTimeRange($node['range'], $at),
            'cron' => $this->evaluateCron($node['expression'], $at),
            'nth_weekday' => $this->evaluateNthWeekday($node['day'], $node['occurrences'], $at),
            'last_weekday' => CronWrapper::isLastWeekday($node['day'], $at),
            'custom' => $this->evaluateCustom($node['keyword'], $at),
            'literal' => $node['value'] === 'always',
            default => throw new ResolutionException(sprintf('Unknown AST node type: %s', $node['type'])),
        };
    }

    private function evaluateKeyword(string $keyword, DateTimeInterface $at): bool
    {
        $carbon = Carbon::instance($at);

        return match ($keyword) {
            'always' => true,
            'never' => false,
            'weekday' => $carbon->isWeekday(),
            'weekend' => $carbon->isWeekend(),
            default => throw new ResolutionException(sprintf('Unknown keyword: %s', $keyword)),
        };
    }

    private function evaluateTimeRange(string $range, DateTimeInterface $at): bool
    {
        $timeRange = TimeRange::fromString($range);

        return $timeRange->contains($at);
    }

    private function evaluateCron(string $expression, DateTimeInterface $at): bool
    {
        $cron = new CronWrapper($expression);

        return $cron->isDue($at);
    }

    /**
     * @param  array<int, int>  $occurrences
     */
    private function evaluateNthWeekday(string $day, array $occurrences, DateTimeInterface $at): bool
    {
        return CronWrapper::isAnyNthWeekday($day, $occurrences, $at);
    }

    private function evaluateCustom(string $keyword, DateTimeInterface $at): bool
    {
        if (! isset($this->customEvaluators[$keyword])) {
            throw new ResolutionException(
                sprintf('No evaluator registered for custom condition: "%s"', $keyword)
            );
        }

        return (bool) ($this->customEvaluators[$keyword])($at);
    }
}
