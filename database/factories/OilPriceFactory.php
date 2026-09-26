<?php

namespace Database\Factories;

use App\Models\OilPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OilPrice>
 */
class OilPriceFactory extends Factory
{
    protected $model = OilPrice::class;

    /**
     * A counter so every price created in one test lands on a different week.
     *
     * `oil_prices` has `unique(week_start, quality)`, and a purely random week
     * plus a random quality collides often enough to make the suite flaky.
     */
    protected static int $weekCursor = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $faker = fake();
        $start = now()
            ->subWeeks(static::$weekCursor++)
            ->startOfWeek()
            ->addDays($faker->numberBetween(0, 6));

        return [
            'week_start' => $start->format('Y-m-d'),
            'week_end' => $start->copy()->addDays(6)->format('Y-m-d'),
            'quality' => $faker->randomElement(['extra', 'virgin', 'lampante']),
            // Factories produce strings for DECIMAL columns on purpose: money
            // must never enter the app as a PHP float (spec §0.7).
            'avg_price_eur_t' => number_format($faker->randomFloat(2, 2500, 4000), 2, '.', ''),
            'volume_t' => number_format($faker->randomFloat(2, 20000, 60000), 2, '.', ''),
            'provisional' => true,
            'source' => 'manual',
            'published_at' => now(),
        ];
    }

    public function provisional(): static
    {
        return $this->state(fn () => ['provisional' => true]);
    }

    public function definitive(): static
    {
        return $this->state(fn () => ['provisional' => false]);
    }
}
