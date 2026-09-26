<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SiteRebuildRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Audit trail of Cloudflare Pages rebuild requests (spec §6, §9.8).
 *
 * A row per request, not per actual call: the debounce in RequestSiteRebuild
 * absorbs bursts, and this history is how the owner sees that it happened.
 *
 * @property int $id
 * @property string $reason
 * @property string $status
 * @property CarbonImmutable $requested_at
 * @property CarbonImmutable|null $dispatched_at
 */
class SiteRebuildRequest extends Model
{
    /** @use HasFactory<SiteRebuildRequestFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'reason',
        'requested_at',
        'dispatched_at',
        'status',
        'response',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'dispatched_at' => 'datetime',
        ];
    }
}
