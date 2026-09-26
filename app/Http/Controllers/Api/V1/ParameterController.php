<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ParameterSetResource;
use App\Models\ParameterSet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Parameters endpoints, spec §7.2.
 *
 * These are the public calculators' only source of official figures. A
 * calculator that falls back to a hardcoded default would silently ship an
 * invented number, so a missing or unpublished set is a 404, not a default.
 */
class ParameterController extends Controller
{
    /**
     * GET /api/v1/parameters/current
     *
     * The set that is live for a campaign. Optional `?campaign=` narrows it.
     */
    public function current(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => ['sometimes', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ], [
            'campaign.regex' => 'La campaña debe tener el formato AAAA-AA, por ejemplo 2026-27.',
        ]);

        $set = ParameterSet::publishedFor($validated['campaign'] ?? null);

        if ($set === null) {
            return response()->json([
                'message' => 'No hay un conjunto de parámetros publicado'
                    .(isset($validated['campaign']) ? " para la campaña {$validated['campaign']}" : '')
                    .'.',
            ], 404);
        }

        $set->load('parameters');

        return $this->cached(ParameterSetResource::make($set));
    }

    /**
     * GET /api/v1/parameters/{version}
     *
     * A specific set, so an old saved calculation can be shown again with the
     * figures it was actually made with (spec §7.2).
     */
    public function show(string $version): JsonResponse
    {
        $set = ParameterSet::with('parameters')->where('version', $version)->first();

        if ($set === null) {
            return response()->json([
                'message' => "No existe el conjunto de parámetros «{$version}».",
            ], 404);
        }

        // Archived sets stay readable on purpose, but they change less often,
        // so they get a longer cache.
        return $this->cached(ParameterSetResource::make($set), isDraft: $set->isPublished());
    }

    /**
     * Spec §7.1: public endpoints send Cache-Control and an ETag.
     */
    private function cached(mixed $resource, bool $isDraft = false): JsonResponse
    {
        $response = $resource->response();

        $seconds = $isDraft ? 3600 : 300;

        return $response
            ->header('Cache-Control', "public, max-age={$seconds}")
            ->setEtag(md5($response->getContent()));
    }
}
