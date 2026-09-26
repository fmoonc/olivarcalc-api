<?php

namespace App\Models;

use App\Enums\ParameterSetStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ParameterSetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A versioned bundle of official parameters (spec §5.1).
 *
 * Sets are never deleted. Saved calculations point at one, and a set that
 * describes how a number was produced has to stay readable forever.
 *
 * @property int $id
 * @property string $version
 * @property string $campaign
 * @property ParameterSetStatus $status
 * @property string|null $notes
 * @property CarbonImmutable|null $published_at
 */
class ParameterSet extends Model
{
    /** @use HasFactory<ParameterSetFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'version',
        'campaign',
        'status',
        'notes',
        'published_at',
        'published_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ParameterSetStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /** @return HasMany<Parameter, $this> */
    public function parameters(): HasMany
    {
        return $this->hasMany(Parameter::class);
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function isPublished(): bool
    {
        return $this->status->isPublished();
    }

    /** The set currently serving a campaign, if there is one. */
    public static function publishedFor(?string $campaign = null): ?self
    {
        return static::query()
            ->where('status', ParameterSetStatus::Published)
            ->when(
                $campaign,
                fn ($query) => $query->where('campaign', $campaign),
            )
            ->latest('published_at')
            ->first();
    }

    /**
     * Parameter keys in this set that the owner has not verified.
     *
     * This is what the publish rule consults (spec §5.2), and what the admin
     * dashboard counts.
     *
     * @return list<string>
     */
    public function unverifiedKeys(): array
    {
        return $this->parameters()
            ->where('verified', false)
            ->pluck('key')
            ->sort()
            ->values()
            ->all();
    }
}
