<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\OilPriceIndexRequest;
use App\Http\Resources\Api\V1\OilPriceResource;
use App\Models\OilPrice;
use App\Support\Campaign;
use Illuminate\Http\JsonResponse;

/**
 * Oil price endpoints, spec §7.2.
 *
 * Prices are the most-read data in the product and change weekly, so the
 * summaries are cheap to cache and never recompute anything a client could do.
 */
class OilPriceController extends Controller
{
    /**
     * GET /api/v1/oil-prices
     *
     * The weekly series, filtered by quality and date range.
     */
    public function index(OilPriceIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $prices = OilPrice::query()
            ->published()
            ->when($validated['quality'] ?? null, fn ($q, $quality) => $q->where('quality', $quality))
            ->when($validated['from'] ?? null, fn ($q, $from) => $q->where('week_start', '>=', $from))
            ->when($validated['to'] ?? null, fn ($q, $to) => $q->where('week_start', '<=', $to))
            ->orderBy('week_start')
            ->orderBy('quality')
            ->get();

        return $this->cached(
            ['data' => OilPriceResource::collection($prices)]
        );
    }

    /**
     * GET /api/v1/oil-prices/latest
     *
     * Latest published week per quality, plus the week-over-week change.
     * The change is computed here, on the server, as a decimal: a client doing
     * it in floating point would drift (spec §4.5).
     */
    public function latest(): JsonResponse
    {
        $latest = OilPrice::query()
            ->published()
            ->orderByDesc('week_start')
            ->get()
            ->groupBy('quality')
            ->map(function ($group) {
                $current = $group->first();
                $previous = $group->get(1);

                $changePct = null;
                if ($previous !== null) {
                    // Integer arithmetic in ten-thousandths of a percent, so
                    // the weekly change cannot drift (spec §0.7).
                    $currentUnits = (int) round(((float) $current->avg_price_eur_t) * 100);
                    $previousUnits = (int) round(((float) $previous->avg_price_eur_t) * 100);

                    if ($previousUnits > 0) {
                        $changePct = number_format(
                            (int) round((($currentUnits - $previousUnits) * 10000) / $previousUnits) / 100,
                            2,
                            '.',
                            ''
                        );
                    }
                }

                return [
                    'quality' => $current->quality,
                    'week_start' => $current->week_start->toDateString(),
                    'week_end' => $current->week_end->toDateString(),
                    'avg_price_eur_t' => (string) $current->avg_price_eur_t,
                    'avg_price_eur_kg' => $current->avgPriceEurKg(),
                    'volume_t' => $current->volume_t === null ? null : (string) $current->volume_t,
                    'provisional' => (bool) $current->provisional,
                    'source' => $current->source,
                    'weekly_change_pct' => $changePct,
                ];
            })
            ->values();

        return $this->cached(['data' => $latest]);
    }

    /**
     * GET /api/v1/oil-prices/campaigns/{campaign}/summary
     *
     * Mean, max and min per quality within a campaign. Spec §4.5.
     */
    public function campaignSummary(string $campaign): JsonResponse
    {
        try {
            $bounds = Campaign::make($campaign);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $summary = OilPrice::query()
            ->published()
            ->whereBetween('week_start', [$bounds->startsOn->toDateString(), $bounds->endsOn->toDateString()])
            ->get()
            ->groupBy('quality')
            ->map(fn ($group, $quality) => [
                'quality' => $quality,
                'weeks' => $group->count(),
                'mean_eur_t' => $this->average($group->pluck('avg_price_eur_t')->all()),
                'max_eur_t' => $this->average([$group->max('avg_price_eur_t')]),
                'min_eur_t' => $this->average([$group->min('avg_price_eur_t')]),
                'last_eur_t' => $this->average([$group->sortBy('week_start')->last()->avg_price_eur_t]),
            ])
            ->values();

        return $this->cached([
            'campaign' => $campaign,
            'data' => $summary,
        ]);
    }

    /**
     * Mean of decimal strings, formatted back to a decimal string.
     *
     * @param  list<string|null>  $values
     */
    private function average(array $values): string
    {
        $filtered = array_values(array_filter($values, static fn ($v) => $v !== null));

        if ($filtered === []) {
            return '0.00';
        }

        // Sum as integers in cents so the mean cannot drift.
        $cents = array_sum(array_map(static fn ($v) => (int) round(((float) $v) * 100), $filtered));

        return number_format($cents / 100 / count($filtered), 2, '.', '');
    }

    /**
     * Spec §7.1: public endpoints send Cache-Control and an ETag.
     *
     * @param  array<string, mixed>  $payload
     */
    private function cached(array $payload): JsonResponse
    {
        $response = response()->json($payload);

        return $response
            ->header('Cache-Control', 'public, max-age=300')
            ->setEtag(md5($response->getContent()));
    }
}
