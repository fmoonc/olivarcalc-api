<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OilIndexQuoteResource;
use App\Models\OilIndexQuote;
use Illuminate\Http\JsonResponse;

/**
 * The latest POOLred index variations.
 *
 * Spec §8 and §7.2. Kept apart from OilPriceController because these are
 * percentages published for four POOLred indices, not €/tonne for our qualities.
 */
class IndexQuoteController extends Controller
{
    /**
     * The most recent window POOLred published for all four of its indices.
     *
     * Open to the public like the rest of the price data: the point of
     * republishing an index is that growers can see it. Scramble documents this
     * from the docblock and types.
     */
    public function latest(): JsonResponse
    {
        // The newest window only, so a caller cannot get a mixture of dates.
        $latest = OilIndexQuote::query()
            ->where('quoted_on', OilIndexQuote::query()->max('quoted_on'))
            ->orderBy('poolred_id')
            ->get();

        $response = response()->json([
            'data' => OilIndexQuoteResource::collection($latest),
        ]);

        // Spec §7.1. Cached longer than the price series because the window only
        // changes once a week, and the importer runs twice a day.
        return $response
            ->header('Cache-Control', 'public, max-age=900')
            ->setEtag(md5($response->getContent()));
    }
}
