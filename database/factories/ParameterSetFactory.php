<?php

namespace Database\Factories;

use App\Enums\ParameterSetStatus;
use App\Models\ParameterSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParameterSet>
 */
class ParameterSetFactory extends Factory
{
    protected $model = ParameterSet::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $campaign = fake()->randomElement(['2025-26', '2026-27']);

        return [
            'version' => $campaign.'.'.fake()->unique()->numberBetween(1, 99),
            'campaign' => $campaign,
            'status' => ParameterSetStatus::Draft,
            'notes' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => ParameterSetStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => ParameterSetStatus::Archived]);
    }
}
