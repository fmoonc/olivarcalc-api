<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Date bounds of an olive campaign (spec §5.2, §16).
 *
 * A campaign runs 1 October to 30 September and is labelled by both calendar
 * years it touches, so 2026-27 is 2026-10-01 .. 2027-09-30.
 *
 * The start day is configurable through the `campaign.start_month_day`
 * parameter; this class takes it as an argument so it never reads a
 * parameter set implicitly.
 */
final readonly class Campaign
{
    public function __construct(
        public string $label,
        public CarbonImmutable $startsOn,
        public CarbonImmutable $endsOn,
    ) {}

    /**
     * @param  string  $campaign  e.g. "2026-27"
     * @param  string  $startMonthDay  "MM-DD", spec §5.2 default 10-01
     *
     * @throws \InvalidArgumentException on a malformed label or start date
     */
    public static function make(string $campaign, string $startMonthDay = '10-01'): self
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $campaign, $m)) {
            throw new \InvalidArgumentException(
                "Campaign must look like AAAA-AA, got «{$campaign}»"
            );
        }

        if (! preg_match('/^(\d{1,2})-(\d{1,2})$/', $startMonthDay, $s)) {
            throw new \InvalidArgumentException(
                "Start month-day must look like MM-DD, got «{$startMonthDay}»"
            );
        }

        $year = (int) $m[1];
        $month = (int) $s[1];
        $day = (int) $s[2];

        $start = CarbonImmutable::create($year, $month, $day)->startOfDay();

        return new self(
            label: $campaign,
            startsOn: $start,
            // A campaign is twelve months long, so it ends the day before the
            // next one starts.
            endsOn: $start->addYear()->subDay(),
        );
    }

    /** The campaign a given date falls into, e.g. 2026-09-30 -> 2025-26. */
    public static function of(CarbonImmutable $date, string $startMonthDay = '10-01'): self
    {
        $start = self::make('2000-00', $startMonthDay)->startsOn;
        $year = $date->year;

        $candidate = self::make(sprintf('%d-%02d', $year, ($year + 1) % 100), $startMonthDay);

        return $date->lt($candidate->startsOn)
            ? self::make(sprintf('%d-%02d', $year - 1, $year % 100), $startMonthDay)
            : $candidate;
    }
}
