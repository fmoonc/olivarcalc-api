<?php

namespace App\Models;

use App\Models\Concerns\GuardsDecimalInput;
use Carbon\CarbonImmutable;
use Database\Factories\OilPriceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A week of origin olive-oil prices for one quality (spec §6).
 *
 * @property int $id
 * @property CarbonImmutable $week_start
 * @property CarbonImmutable $week_end
 * @property string $quality
 * @property string $avg_price_eur_t
 * @property string|null $volume_t
 * @property bool $provisional
 * @property string $source
 */
class OilPrice extends Model
{
    /** @use HasFactory<OilPriceFactory> */
    use GuardsDecimalInput, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'week_start',
        'week_end',
        'quality',
        'avg_price_eur_t',
        'volume_t',
        'provisional',
        'source',
        'source_url',
        'published_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'week_end' => 'date',
            // Laravel's native decimal cast: reads back as a fixed-scale
            // string, never a float (spec §0.7, §7.1).
            'avg_price_eur_t' => 'decimal:2',
            'volume_t' => 'decimal:2',
            'provisional' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /** @var array<string, int> */
    protected array $decimalFields = [
        'avg_price_eur_t' => 2,
        'volume_t' => 2,
    ];

    protected static function booted(): void
    {
        static::saving(fn (self $price) => $price->guardDecimalInput($price->decimalFields));
    }

    /**
     * €/kg, derived from the €/tonne the source reports.
     *
     * The division happens here on purpose, so a client never divides a price
     * by 1000 in floating point (spec §4.5).
     */
    public function avgPriceEurKg(): string
    {
        return number_format(((float) $this->avg_price_eur_t) / 1000, 4, '.', '');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at');
    }
}
