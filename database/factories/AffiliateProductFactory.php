<?php

namespace Database\Factories;

use App\Models\AffiliateProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliateProduct>
 */
class AffiliateProductFactory extends Factory
{
    protected $model = AffiliateProduct::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'category' => fake()->randomElement(['vareadoras', 'mallas', 'poda']),
            'url' => 'https://www.amazon.es/s?k='.fake()->slug(2),
            'price_text' => 'desde '.fake()->numberBetween(20, 900).' €',
            'short_description' => fake()->sentence(10),
            'active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
