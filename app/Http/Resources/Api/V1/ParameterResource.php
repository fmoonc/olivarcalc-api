<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Parameter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Parameter
 */
class ParameterResource extends JsonResource
{
    /**
     * Field names are snake_case to match the rest of the API (spec §7.1).
     *
     * This is the exact shape `@olivar/calculations` expects as its ParamSet,
     * so a change here is a change to the web package's `ParamValue` type.
     * Keep both in step.
     */
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            // Always a string, never a JSON number: the calculator converts it
            // with big.js and must never see a float (spec §0.7, §7.1).
            'value' => (string) $this->value,
            'unit' => (string) ($this->unit ?? ''),
            'verified' => (bool) $this->verified,
            'source_label' => (string) ($this->source_label ?? ''),
            'source_url' => (string) ($this->source_url ?? ''),
        ];
    }
}
