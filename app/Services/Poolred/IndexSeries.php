<?php

namespace App\Services\Poolred;

/**
 * One response from POOLred's index endpoint: the daily points and whether they
 * are settled.
 *
 * Grouped rather than fetched twice. The two live in the same JSON document, so
 * splitting them into two methods meant asking their API for the same week twice
 * on every import.
 */
final readonly class IndexSeries
{
    /**
     * @param  list<DailyPoint>  $points
     */
    public function __construct(
        public array $points,
        /** True when POOLred considers the period closed. */
        public bool $isFinalized,
    ) {}
}
