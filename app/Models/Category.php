<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'name',
        'description',
        'sort_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<Article, $this> */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    /**
     * Published guides only.
     *
     * The public API filters drafts, so counting all of them would overstate
     * what a visitor actually sees (spec §7.2).
     */
    public function publishedArticlesCount(): int
    {
        return $this->articles()->where('status', 'published')->count();
    }
}
