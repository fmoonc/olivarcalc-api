<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Parameter;
use App\Models\ParameterSet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The ParamSet consumed by `@olivar/calculations`.
 *
 * Spec §7.1 requires ISO 8601 dates; toIso8601String() gives that.
 *
 * @mixin ParameterSet
 */
class ParameterSetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        // A ParamSet is useless without its parameters, so this is not
        // conditional: the controller eager-loads them.
        return [
            'version' => $this->version,
            'campaign' => $this->campaign,
            'published_at' => $this->published_at?->toIso8601String(),
            // Keyed by parameter key, because that is the shape
            // `@olivar/calculations` reads: `params['oil.density_kg_per_l']`.
            // A bare list would force the web to re-index on every call.
            'params' => $this->parameters
                ->mapWithKeys(fn (Parameter $parameter) => [
                    $parameter->key => (new ParameterResource($parameter))->resolve($request),
                ])
                ->all(),
        ];
    }

    /**
     * Deliberately unwrapped.
     *
     * `@olivar/calculations` consumes this as a `ParamSet` object, reading
     * `.version` and `.params` at the top level. Laravel's default `data`
     * wrapper would force the web to unwrap every call site, so a single-object
     * response goes out bare. Collections keep the standard paginated shape
     * (spec §7.1).
     */
    public static $wrap = null;
}
