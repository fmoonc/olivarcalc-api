<?php

namespace App\Models;

use App\Enums\ParameterValueType;
use Database\Factories\ParameterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One official figure inside a set (spec §5.2).
 *
 * `verified = false` is the normal, expected state for a freshly seeded value:
 * the owner must confirm it against the official source before launch. It is
 * not a bug to be tidied away, and nothing here may invent a value.
 *
 * @property int $id
 * @property string $key
 * @property string $value
 * @property ParameterValueType $value_type
 * @property string|null $unit
 * @property bool $verified
 * @property string|null $source_label
 * @property string|null $source_url
 */
class Parameter extends Model
{
    /** @use HasFactory<ParameterFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'parameter_set_id',
        'key',
        'value',
        'value_type',
        'unit',
        'verified',
        'verified_at',
        'source_label',
        'source_url',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'value_type' => ParameterValueType::class,
            'verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ParameterSet, $this> */
    public function parameterSet(): BelongsTo
    {
        return $this->belongsTo(ParameterSet::class);
    }

    /**
     * Mark as verified. A source URL is required, otherwise "verified" is an
     * untraceable claim (spec §9).
     */
    public function markVerified(string $sourceUrl, ?string $sourceLabel = null): void
    {
        $this->forceFill([
            'verified' => true,
            'verified_at' => now(),
            'source_url' => $sourceUrl,
            'source_label' => $sourceLabel ?? $this->source_label,
        ])->save();
    }
}
