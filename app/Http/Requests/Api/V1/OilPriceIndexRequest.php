<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters for GET /v1/oil-prices. Spec §7.2.
 */
class OilPriceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'quality' => ['sometimes', Rule::in(['extra', 'virgin', 'lampante'])],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'quality.in' => 'La calidad debe ser extra, virgin o lampante.',
            'from.date_format' => 'La fecha inicial debe tener el formato AAAA-MM-DD.',
            'to.date_format' => 'La fecha final debe tener el formato AAAA-MM-DD.',
            'to.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
        ];
    }
}
