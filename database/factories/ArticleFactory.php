<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    protected $model = Article::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $title = fake()->sentence(6);

        return [
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 9999),
            'title' => $title,
            'excerpt' => fake()->paragraph(),
            'body_markdown' => '# '.fake()->sentence(4)."\n\n".fake()->paragraphs(3, true),
            'category_id' => Category::factory(),
            'related_tool' => null,
            'meta_title' => Str::limit($title, 60, ''),
            'meta_description' => Str::limit(fake()->sentence(20), 155, ''),
            'faq' => [['question' => fake()->sentence(4), 'answer' => fake()->sentence(12)]],
            'status' => 'published',
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft', 'published_at' => null]);
    }
}
