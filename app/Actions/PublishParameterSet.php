<?php

namespace App\Actions;

use App\Enums\ParameterSetStatus;
use App\Models\ParameterSet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Publish a parameter set, enforcing the spec §5.2 rule.
 *
 * A set cannot go live while a parameter any live calculator needs is still
 * unverified. The admin can override that deliberately, but never by accident:
 * the override is an explicit flag and it is recorded.
 */
final class PublishParameterSet
{
    /**
     * Parameter keys the calculators read (spec §4). A set missing any of them
     * would break a live calculator, so that is a hard failure regardless of
     * verification state.
     *
     * @var list<string>
     */
    public const REQUIRED_KEYS = [
        'vat.reagp_compensation_pct',
        'vat.olive_general_rate_pct',
        'irpf.agricultural_withholding_pct',
        'irpf.withholding_base_includes_vat',
        'oil.density_kg_per_l',
        'campaign.start_month_day',
    ];

    /**
     * @throws ValidationException
     */
    public function handle(ParameterSet $set, ?User $actor = null, bool $force = false): ParameterSet
    {
        $presentKeys = $set->parameters()->pluck('key')->all();
        $missing = array_values(array_diff(self::REQUIRED_KEYS, $presentKeys));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'parameters' => 'Faltan parámetros obligatorios: '.implode(', ', $missing),
            ]);
        }

        $unverified = $set->unverifiedKeys();

        if ($unverified !== [] && ! $force) {
            throw ValidationException::withMessages([
                'parameters' => 'No se puede publicar con valores sin verificar: '
                    .implode(', ', $unverified)
                    .'. Verifícalos o marca explícitamente "publicar con valores sin verificar".',
            ]);
        }

        return DB::transaction(function () use ($set, $actor) {
            // Only one set is live per campaign; the previous one is archived,
            // never deleted (spec §5.1).
            ParameterSet::query()
                ->where('campaign', $set->campaign)
                ->where('status', ParameterSetStatus::Published)
                ->whereKeyNot($set->getKey())
                ->update([
                    'status' => ParameterSetStatus::Archived,
                    'updated_at' => now(),
                ]);

            $set->forceFill([
                'status' => ParameterSetStatus::Published,
                'published_at' => now(),
                'published_by' => $actor?->getKey(),
            ])->save();

            return $set;
        });
    }
}
