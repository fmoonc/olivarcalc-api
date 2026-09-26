<?php

namespace App\Events;

use App\Models\OilPrice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A week of prices went live (spec §8).
 *
 * Publishing is the moment the site has to be rebuilt, because the Astro build
 * reads prices at build time and the build fails if it cannot (spec §10.1).
 *
 * Price alerts (`CheckPriceAlerts`) are deliberately NOT fired here yet: they
 * belong to Fase 5, and a half-implemented listener would silently never send
 * a notification while looking like it worked. See DECISIONS.md.
 */
class OilPricesPublished
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  OilPrice|null  $price  the row that was published, or null when a
     *                                batch was published at once
     */
    public function __construct(public readonly ?OilPrice $price = null) {}
}
