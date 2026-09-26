<?php

namespace Database\Factories;

use App\Enums\ParameterValueType;
use App\Models\Parameter;
use App\Models\ParameterSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Parameter>
 */
class ParameterFactory extends Factory
{
    protected $model = Parameter::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'parameter_set_id' => ParameterSet::factory(),
            'key' => 'test.parameter.'.fake()->unique()->slug(2),
            'value' => '1',
            'value_type' => ParameterValueType::Decimal,
            'unit' => '%',
            // The honest default: nothing is verified until a human says so.
            'verified' => false,
            'source_label' => 'Test source',
            'source_url' => null,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'verified' => true,
            'verified_at' => now(),
            'source_url' => 'https://example.test/source',
        ]);
    }

    public function decimal(string $value, string $unit = '%'): static
    {
        return $this->state(fn () => [
            'value' => $value,
            'value_type' => ParameterValueType::Decimal,
            'unit' => $unit,
        ]);
    }

    public function bool(string $value): static
    {
        return $this->state(fn () => [
            'value' => $value,
            'value_type' => ParameterValueType::Bool,
        ]);
    }
}
