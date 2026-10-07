<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\OilIndexQuoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The weekly variation of one POOLred index (spec §8).
 *
 * @property int $id
 * @property string $poolred_id
 * @property string $name
 * @property string $variation_pct
 * @property Carbon $quoted_on
 * @property string|null $period
 * @property string|null $source_stamp
 * @property bool $is_finalized
 * @property string|null $source_url
 */
class OilIndexQuote extends Model
{
    /** @use HasFactory<OilIndexQuoteFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'poolred_id',
        'name',
        'variation_pct',
        'quoted_on',
        'period',
        'source_stamp',
        'is_finalized',
        'source_url',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quoted_on' => 'date',
            // Fixed-scale decimal string, never a float (spec §0.7).
            'variation_pct' => 'decimal:3',
            'is_finalized' => 'boolean',
        ];
    }

    /**
     * POOLRED's four index names, kept out of the model so nothing here needs
     * translating and no consumer has to hard-code the mapping.
     *
     * The point of the exercise is that these are their names, not ours.
     *
     * @return list<string>
     */
    public static function poolredIds(): array
    {
        return ['virgenExtra', 'virgen', 'lampante', 'nacional'];
    }
}
