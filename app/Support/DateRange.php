<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** A reporting window: Today, 7/30/90 days, or a custom from–to range (inclusive, whole days). */
final class DateRange
{
    public const PERIODS = [
        'today' => 'Today',
        '7d' => '7 days',
        '30d' => '30 days',
        '90d' => '90 days',
        'custom' => 'Custom',
    ];

    private const MAX_CUSTOM_DAYS = 3660;

    public function __construct(
        public readonly string $key,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {}

    public static function fromRequest(Request $request, string $default = '30d'): self
    {
        $key = (string) $request->query('period', $default);

        if ($key === 'custom') {
            $from = self::parseDate($request->query('from'));
            $to = self::parseDate($request->query('to'));

            if ($from && $to) {
                [$from, $to] = $from->lte($to) ? [$from, $to] : [$to, $from];
                if ($from->diffInDays($to) <= self::MAX_CUSTOM_DAYS) {
                    return new self('custom', $from->startOfDay(), $to->endOfDay());
                }
            }
            $key = $default;
        }

        return self::preset(array_key_exists($key, self::PERIODS) && $key !== 'custom' ? $key : $default);
    }

    public static function preset(string $key): self
    {
        $now = CarbonImmutable::now();

        return match ($key) {
            'today' => new self('today', $now->startOfDay(), $now->endOfDay()),
            '7d' => new self('7d', $now->subDays(6)->startOfDay(), $now->endOfDay()),
            '90d' => new self('90d', $now->subDays(89)->startOfDay(), $now->endOfDay()),
            default => new self('30d', $now->subDays(29)->startOfDay(), $now->endOfDay()),
        };
    }

    /** The window of equal length immediately before this one (for trend comparisons). */
    public function previous(): self
    {
        $days = $this->days();

        return new self($this->key, $this->from->subDays($days), $this->from->subSecond());
    }

    public function days(): int
    {
        return (int) $this->from->startOfDay()->diffInDays($this->to->startOfDay()) + 1;
    }

    public function label(): string
    {
        return $this->key === 'custom'
            ? $this->from->format('M j, Y').' – '.$this->to->format('M j, Y')
            : (self::PERIODS[$this->key] === 'Today' ? 'Today' : 'Last '.self::PERIODS[$this->key]);
    }

    /** Chart bucket size suited to the window length. */
    public function bucket(): string
    {
        return match (true) {
            $this->days() <= 31 => 'day',
            $this->days() <= 120 => 'week',
            default => 'month',
        };
    }

    /** @return array{period: string, from: string, to: string} */
    public function query(): array
    {
        return ['period' => $this->key, 'from' => $this->from->toDateString(), 'to' => $this->to->toDateString()];
    }

    private static function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
