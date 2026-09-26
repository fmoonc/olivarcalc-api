<?php

namespace App\Http\Resources\Api\V1;

use App\Models\OilPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OilPrice
 */
class OilPriceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'week_start' => $this->week_start->toDateString(),
            'week_end' => $this->week_end->toDateString(),
            'quality' => $this->quality,
            // Decimal strings, per spec §7.1.
            'avg_price_eur_t' => (string) $this->avg_price_eur_t,
            'avg_price_eur_kg' => $this->avgPriceEurKg(),
            'volume_t' => $this->volume_t === null ? null : (string) $this->volume_t,
            'provisional' => (bool) $this->provisional,
            'source' => $this->source,
            'source_url' => $this->source_url,
        ];
    }
}
