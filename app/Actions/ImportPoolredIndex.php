<?php

namespace App\Actions;

use App\Models\OilIndexQuote;
use App\Models\OilPrice;
use App\Services\Poolred\Client;
use App\Services\Poolred\DailyPoint;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Import the POOLred index into the price and quote tables (spec §8).
 *
 * POOLred publishes daily index values and our model is weekly, so the series is
 * averaged per ISO week. Averaging is deliberate rather than sampling the last
 * day of each week: an index is a level, not a transaction count, and a grower
 * reading "what was the price that week" wants the level that week held at, not
 * whatever the Friday close happened to be.
 */
final class ImportPoolredIndex
{
    public function __construct(private readonly Client $client) {}

    /**
     * @return array{weeks: int, quotes: int, from: string, to: string, finalized: bool}
     */
    public function handle(int $daysBack = 365): array
    {
        $to = CarbonImmutable::now()->startOfDay();
        $from = $to->subDays($daysBack);

        $series = $this->client->indexSeries($from, $to);

        if ($series->points === []) {
            // Refuse rather than truncate. One empty response must not delete a
            // year of history that is still perfectly good.
            throw new \RuntimeException('POOLred devolvió una serie vacía; no se toca la base.');
        }

        // Their flag covers the whole window they were asked about, so it applies
        // to every week we store. A per-week flag would be nicer, but they only
        // report it per period and claiming finer precision would be invention.
        $finalized = $series->isFinalized;

        $weeks = $this->weeklyAverages($series->points);

        DB::transaction(function () use ($weeks, $finalized): void {
            foreach ($weeks as $week) {
                OilPrice::updateOrCreate(
                    ['week_start' => $week['start']->toDateString(), 'quality' => 'extra'],
                    [
                        'week_end' => $week['end']->toDateString(),
                        'avg_price_eur_t' => $week['average'],
                        // Their public series carries no traded volume, and inventing
                        // one would be exactly the sin this project avoids.
                        'volume_t' => null,
                        'provisional' => ! $finalized,
                        'source' => 'poolred',
                        'source_url' => config('services.poolred.source_url'),
                        'published_at' => now(),
                    ],
                );
            }
        });

        $quotes = $this->importQuotes();

        return [
            'weeks' => count($weeks),
            'quotes' => $quotes,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'finalized' => $finalized,
        ];
    }

    /**
     * Average the daily points into calendar weeks, Monday to Sunday.
     *
     * A partial week at the end is kept rather than dropped: the days POOLred has
     * published are real days, and averaging them is more honest than pretending
     * the week has not started.
     *
     * @param  list<DailyPoint>  $points
     * @return array<string, array{start: CarbonImmutable, end: CarbonImmutable, average: string}>
     */
    private function weeklyAverages(array $points): array
    {
        /** @var array<string, array{start: CarbonImmutable, end: CarbonImmutable, values: list<float>, sum: float, n: int}> $buckets */
        $buckets = [];

        foreach ($points as $point) {
            // Monday as the week start, the Spanish convention and the ISO one.
            $start = $point->date->startOfWeek(CarbonImmutable::MONDAY);
            $key = $start->toDateString();

            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'start' => $start,
                    'end' => $start->endOfWeek(CarbonImmutable::SUNDAY),
                    'values' => [],
                    'sum' => 0.0,
                    'n' => 0,
                ];
            }

            $value = (float) $point->indexEurT;
            $buckets[$key]['values'][] = $value;
            $buckets[$key]['sum'] += $value;
            $buckets[$key]['n']++;
        }

        ksort($buckets);

        $weeks = [];

        foreach ($buckets as $bucket) {
            // Two decimals, which is the scale POOLred publishes and the scale
            // DECIMAL(10,2) holds. Rounding once here means the stored value is
            // the one a reader sees, rather than being re-rounded downstream.
            $weeks[(string) $bucket['start']->toDateString()] = [
                'start' => $bucket['start'],
                'end' => $bucket['end'],
                'average' => number_format($bucket['sum'] / $bucket['n'], 2, '.', ''),
            ];
        }

        return $weeks;
    }

    /**
     * Store the variation of the four indices from POOLred's own ticker.
     *
     * @return int rows written
     */
    private function importQuotes(): int
    {
        $written = 0;

        foreach ($this->client->quotes() as $quote) {
            OilIndexQuote::updateOrCreate(
                ['poolred_id' => $quote->poolredId, 'quoted_on' => $quote->quotedOn->toDateString()],
                [
                    'name' => $quote->name,
                    'variation_pct' => $quote->variationPct,
                    'period' => Str::limit($quote->period, 120, ''),
                    'source_stamp' => Str::limit($quote->updatedAt, 60, ''),
                    'is_finalized' => false,
                    'source_url' => config('services.poolred.source_url'),
                ],
            );

            $written++;
        }

        return $written;
    }
}
