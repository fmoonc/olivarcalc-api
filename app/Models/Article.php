<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An SEO guide, written and reviewed in Filament (spec §9).
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string $status
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $reviewed_at
 */
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'title',
        'excerpt',
        'body_markdown',
        'cover_image_path',
        'category_id',
        'related_tool',
        'meta_title',
        'meta_description',
        'faq',
        'status',
        'published_at',
        'author_id',
        'reviewed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'faq' => 'array',
            'published_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
