<?php

namespace Database\Factories;

use App\Models\ToolPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ToolPage>
 */
class ToolPageFactory extends Factory
{
    protected $model = ToolPage::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'tool_key' => fake()->unique()->randomElement([
                'settlement', 'yield_converter', 'cost_per_hectare', 'harvest_crew',
            ]),
            'title' => $title,
            'intro_markdown' => fake()->sentence(12),
            'body_markdown' => fake()->paragraphs(3, true),
            'faq' => [['question' => fake()->sentence(4), 'answer' => fake()->sentence(10)]],
            'meta_title' => $title,
            'meta_description' => fake()->sentence(20),
        ];
    }
}
