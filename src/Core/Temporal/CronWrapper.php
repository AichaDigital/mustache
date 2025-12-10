<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Temporal;

use AichaDigital\MustacheResolver\Exceptions\InvalidSyntaxException;
use Carbon\Carbon;
use Cron\CronExpression;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Wrapper for CronExpression with additional temporal utilities.
 *
 * Provides cron expression evaluation and Nth weekday calculations.
 */
final readonly class CronWrapper
{
    private const array DAY_MAP = [
        'sunday' => 0,
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
        'sun' => 0,
        'mon' => 1,
        'tue' => 2,
        'wed' => 3,
        'thu' => 4,
        'fri' => 5,
        'sat' => 6,
    ];

    private CronExpression $cron;

    public function __construct(string $expression)
    {
        try {
            $this->cron = new CronExpression($expression);
        } catch (InvalidArgumentException $e) {
            throw new InvalidSyntaxException(
                sprintf('Invalid CRON expression: "%s". %s', $expression, $e->getMessage())
            );
        }
    }

    /**
     * Check if the cron expression is due at the given time.
     */
    public function isDue(?DateTimeInterface $at = null): bool
    {
        $at = $at ? Carbon::instance($at) : Carbon::now();

        return $this->cron->isDue($at);
    }

    /**
     * Get the next run date from the given time.
     */
    public function getNextRunDate(?DateTimeInterface $from = null): DateTimeInterface
    {
        $from = $from ? Carbon::instance($from) : Carbon::now();

        return Carbon::instance($this->cron->getNextRunDate($from));
    }

    /**
     * Get the previous run date from the given time.
     */
    public function getPreviousRunDate(?DateTimeInterface $from = null): DateTimeInterface
    {
        $from = $from ? Carbon::instance($from) : Carbon::now();

        return Carbon::instance($this->cron->getPreviousRunDate($from));
    }

    /**
     * Get the underlying CronExpression instance.
     */
    public function getCronExpression(): CronExpression
    {
        return $this->cron;
    }

    /**
     * Check if the given date is the Nth occurrence of a weekday in the month.
     *
     * @param  string  $dayOfWeek  Day name (monday, tuesday, etc.) or abbreviation (mon, tue, etc.)
     * @param  int  $occurrence  Which occurrence (1 = first, 2 = second, etc., -1 = last)
     * @param  DateTimeInterface|null  $at  The date to check. Defaults to now.
     *
     * Examples:
     * - isNthWeekday('saturday', 1) - Is it the first Saturday of the month?
     * - isNthWeekday('friday', -1) - Is it the last Friday of the month?
     */
    public static function isNthWeekday(string $dayOfWeek, int $occurrence, ?DateTimeInterface $at = null): bool
    {
        $dayOfWeek = strtolower(trim($dayOfWeek));

        // Validate parameters first
        if (! isset(self::DAY_MAP[$dayOfWeek])) {
            throw new InvalidSyntaxException(
                sprintf('Invalid day of week: "%s". Expected: monday, tuesday, etc.', $dayOfWeek)
            );
        }

        if ($occurrence !== -1 && ($occurrence < 1 || $occurrence > 5)) {
            throw new InvalidSyntaxException(
                sprintf('Invalid occurrence: %d. Expected 1-5 or -1 for last.', $occurrence)
            );
        }

        $at = $at ? Carbon::instance($at) : Carbon::now();
        $targetDayNumber = self::DAY_MAP[$dayOfWeek];
        $currentDayNumber = (int) $at->format('w'); // 0 = Sunday, 6 = Saturday

        // First check: is today the correct day of the week?
        if ($currentDayNumber !== $targetDayNumber) {
            return false;
        }

        $dayOfMonth = (int) $at->format('j');
        $daysInMonth = (int) $at->format('t');

        if ($occurrence === -1) {
            // Last occurrence: check if there's no more of this weekday in the month
            return ($dayOfMonth + 7) > $daysInMonth;
        }

        // Calculate which occurrence this is
        $currentOccurrence = (int) ceil($dayOfMonth / 7);

        return $currentOccurrence === $occurrence;
    }

    /**
     * Check if the given date matches any of the Nth weekday occurrences.
     *
     * @param  string  $dayOfWeek  Day name (monday, saturday, etc.)
     * @param  array<int, int>  $occurrences  Array of occurrences to check [1, 2] for 1st and 2nd
     * @param  DateTimeInterface|null  $at  The date to check
     */
    public static function isAnyNthWeekday(string $dayOfWeek, array $occurrences, ?DateTimeInterface $at = null): bool
    {
        foreach ($occurrences as $occurrence) {
            if (self::isNthWeekday($dayOfWeek, $occurrence, $at)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the given date is the last occurrence of the weekday in the month.
     *
     * @param  string  $dayOfWeek  Day name (monday, friday, etc.)
     * @param  DateTimeInterface|null  $at  The date to check
     */
    public static function isLastWeekday(string $dayOfWeek, ?DateTimeInterface $at = null): bool
    {
        return self::isNthWeekday($dayOfWeek, -1, $at);
    }

    /**
     * Create a CRON expression for the Nth weekday of the month.
     *
     * @param  string  $dayOfWeek  Day name (monday, saturday, etc.)
     * @param  int  $occurrence  Which occurrence (1-5)
     * @param  string  $time  Time in HH:MM format (defaults to 00:00)
     */
    public static function createNthWeekdayCron(string $dayOfWeek, int $occurrence, string $time = '00:00'): self
    {
        $dayOfWeek = strtolower(trim($dayOfWeek));

        if (! isset(self::DAY_MAP[$dayOfWeek])) {
            throw new InvalidSyntaxException(
                sprintf('Invalid day of week: "%s"', $dayOfWeek)
            );
        }

        if ($occurrence < 1 || $occurrence > 5) {
            throw new InvalidSyntaxException(
                sprintf('Invalid occurrence: %d. Expected 1-5.', $occurrence)
            );
        }

        [$hours, $minutes] = explode(':', $time);
        $dayNumber = self::DAY_MAP[$dayOfWeek];

        // CRON format: minute hour * * day#occurrence
        $expression = sprintf('%d %d * * %d#%d', (int) $minutes, (int) $hours, $dayNumber, $occurrence);

        return new self($expression);
    }
}
