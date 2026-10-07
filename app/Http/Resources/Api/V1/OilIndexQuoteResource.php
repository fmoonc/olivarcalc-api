<?php

namespace App\Http\Resources\Api\V1;

use App\Models\OilIndexQuote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OilIndexQuote
 */
class OilIndexQuoteResource extends JsonResource
{
    /**
     * snake_case to match the rest of the API (spec §7.1).
     *
     * `name` and `poolred_id` are POOLred's, not ours. Translating them here
     * would make the record no longer match the source it claims to reproduce,
     * and the frontend already has to show the attribution either way.
     */
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'poolred_id' => $this->poolred_id,
            'name' => $this->name,
            // Decimal string, never a JSON number: the frontend formats it and
            // must not see a float (spec §0.7).
            'variation_pct' => (string) $this->variation_pct,
            'quoted_on' => $this->quoted_on->toDateString(),
            'period' => (string) ($this->period ?? ''),
            'source_stamp' => (string) ($this->source_stamp ?? ''),
            'is_finalized' => (bool) $this->is_finalized,
            'source_url' => (string) ($this->source_url ?? ''),
        ];
    }
}
