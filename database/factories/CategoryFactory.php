<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->randomElement([
            'Cultivo', 'Cosecha y almazara', 'Precios', 'Varios', 'Riego',
        ]);

        // `categories.slug` is unique, and a fixed list of names runs out
        // after a handful of records, so the slug always gets a suffix.
        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'name' => $name,
            'description' => fake()->sentence(10),
            'sort_order' => 0,
        ];
    }

    public function named(string $slug, ?string $name = null): static
    {
        return $this->state(fn () => [
            'slug' => $slug,
            'name' => $name ?? $slug,
        ]);
    }
}
