<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters for GET /v1/articles. Spec §7.2.
 */
class ArticleIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category' => ['sometimes', 'string', 'max:100'],
            'tool' => ['sometimes', 'string', Rule::in([
                'settlement', 'yield_converter', 'cost_per_hectare', 'harvest_crew',
            ])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'tool.in' => 'La herramienta indicada no existe.',
            'per_page.max' => 'No se admiten más de 50 artículos por página.',
        ];
    }
}
