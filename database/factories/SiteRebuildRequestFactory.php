<?php

namespace Database\Factories;

use App\Models\SiteRebuildRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteRebuildRequest>
 */
class SiteRebuildRequestFactory extends Factory
{
    protected $model = SiteRebuildRequest::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'reason' => 'manual',
            'requested_at' => now(),
            'status' => 'dispatched',
            'dispatched_at' => now(),
            'response' => 'HTTP 200',
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'dispatched_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'dispatched_at' => now(),
            'response' => 'Hook no configurado',
        ]);
    }
}
