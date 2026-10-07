<?php

namespace App\Services\Poolred;

use Carbon\CarbonImmutable;

/**
 * The weekly variation POOLred publishes for one of its four indices.
 *
 * The official ticker endpoint gives a percentage change and nothing else: no
 * price, no volume. That is enough to say how the market moved without
 * republishing a figure we are not licensed to fetch, so it is stored on its own
 * rather than squeezed into the price table.
 */
final readonly class IndexQuote
{
    public function __construct(
        /** POOLred's own identifier: virginExtra, virgin, lampante, nacional. */
        public string $poolredId,
        public string $name,
        /** Percentage, two decimals, as a decimal string. -6.85 means -6.85 %. */
        public string $variationPct,
        public CarbonImmutable $quotedOn,
        /** Their label for the window, e.g. "7 sept - 7 oct 2026". */
        public string $period,
        /** Their "actualizado" stamp, kept so the provenance is reproducible. */
        public string $updatedAt,
    ) {}
}
