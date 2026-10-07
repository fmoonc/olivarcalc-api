<?php

namespace App\Services\Poolred;

use Carbon\CarbonImmutable;

/**
 * One day of the POOLred index.
 *
 * The value arrives as a float from JSON and leaves as a fixed-scale decimal
 * string, because a price must never be a PHP float once it is in the domain
 * (spec §0.7). It reaches the database as a DECIMAL.
 */
final readonly class DailyPoint
{
    public function __construct(
        public CarbonImmutable $date,
        /** €/tonne, two decimals, as a decimal string. */
        public string $indexEurT,
    ) {}
}
