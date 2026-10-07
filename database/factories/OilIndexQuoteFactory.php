<?php

namespace Database\Factories;

use App\Models\OilIndexQuote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OilIndexQuote>
 */
class OilIndexQuoteFactory extends Factory
{
    protected $model = OilIndexQuote::class;

    /**
     * The four indices POOLred publishes, with the names their own widget shows.
     *
     * Both `poolred_id` and `name` come from the source, so the factory reproduces
     * them rather than inventing plausible-sounding ones. Anything else would let
     * a test pass against a name POOLred does not use.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $indexes = [
            'virgenExtra' => 'iPOOL Extra',
            'virgen' => 'iPOOL Virgen',
            'lampante' => 'iPOOL Lampante',
            'nacional' => 'iPOOL España',
        ];

        $id = $this->faker->randomElement(array_keys($indexes));

        return [
            'poolred_id' => $id,
            'name' => $indexes[$id],
            // Decimal string, never a float (spec §0.7).
            'variation_pct' => $this->faker->randomFloat(2, -15, 15),
            'quoted_on' => now()->startOfWeek(),
            'period' => '7 sept - 7 oct 2026',
            'source_stamp' => '2026-10-07 12:58',
            'is_finalized' => false,
            'source_url' => 'https://poolred.com/',
        ];
    }
}
