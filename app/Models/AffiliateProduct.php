<?php

namespace App\Models;

use Database\Factories\AffiliateProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Amazon affiliate products, spec §6. The web renders them with
 * rel="sponsored nofollow noopener" (spec §12).
 *
 * @property int $id
 * @property string $name
 * @property string $category
 * @property string $url
 */
class AffiliateProduct extends Model
{
    /** @use HasFactory<AffiliateProductFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'category',
        'url',
        'image_path',
        'price_text',
        'short_description',
        'active',
        'sort_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
