<?php

namespace Database\Seeders;

use App\Enums\ParameterSetStatus;
use App\Models\ParameterSet;
use Illuminate\Database\Seeder;

/**
 * The initial parameter set from spec §5.2.
 *
 * Every value here is ORIENTATIVE and seeded with verified = false. That is the
 * intended state, not laziness: the owner checks each one against the official
 * source and flips the flag from the admin panel before launch. Spec §0.2 and
 * the Fase 8 checklist both depend on it.
 *
 * The TODO entries are parameters with no defensible placeholder at all; they
 * are seeded empty on purpose so the publish rule blocks a set that would
 * otherwise ship a made-up IVA rate.
 */
class ParameterSetSeeder extends Seeder
{
    /**
     * Display names for the seeded wage rows, keyed by the province slug.
     *
     * The keys, not the display names, go into the parameter key: a key has to
     * survive URL encoding, dot-paths and JSON, so "Ciudad Real" becomes
     * `ciudad_real` (spec §5.2).
     *
     * @var array<string, string>
     */
    private const PROVINCE_LABELS = [
        'jaen' => 'Jaén',
        'cordoba' => 'Córdoba',
        'granada' => 'Granada',
        'sevilla' => 'Sevilla',
        'malaga' => 'Málaga',
        'badajoz' => 'Badajoz',
        'ciudad_real' => 'Ciudad Real',
        'toledo' => 'Toledo',
    ];

    public function run(): void
    {
        $set = ParameterSet::updateOrCreate(
            ['version' => '2026-27.1'],
            [
                'campaign' => '2026-27',
                'status' => ParameterSetStatus::Draft,
                'notes' => 'Conjunto inicial. Todos los valores pendientes de verificar por el propietario.',
            ],
        );

        $parameters = [
            // key => [value, type, unit, source]
            'vat.reagp_compensation_pct' => ['12', 'decimal', '%', 'Ley 37/1992 del IVA, art. 130'],
            'vat.olive_general_rate_pct' => ['', 'decimal', '%', 'AEAT: tipo aplicable a la aceituna para almazara'],
            'irpf.agricultural_withholding_pct' => ['2', 'decimal', '%', 'Reglamento IRPF, art. 95.4'],
            'irpf.withholding_base_includes_vat' => ['false', 'bool', '', 'AEAT'],
            'oil.density_kg_per_l' => ['0.916', 'decimal', 'kg/L', 'Referencia técnica'],
            'campaign.start_month_day' => ['10-01', 'string', 'MM-DD', 'Convención del sector'],
            'labor.employer_cost_pct' => ['', 'decimal', '%', 'Seguridad Social: Sistema Especial Agrario'],
        ];

        // The cost-per-hectare presets are JSON blobs, one per system x
        // irrigation combination the calculators accept (spec §4.6).
        foreach (['traditional', 'intensive', 'superintensive'] as $system) {
            foreach (['dry', 'irrigated'] as $irrigation) {
                $parameters["presets.cost_per_ha.{$system}.{$irrigation}"] = [
                    json_encode(['pruning' => '', 'fertilization' => ''], JSON_THROW_ON_ERROR),
                    'json',
                    '€/ha',
                    'Estudios de costes del MAPA / Junta de Andalucía',
                ];
            }
        }

        foreach ($parameters as $key => [$value, $type, $unit, $sourceLabel]) {
            $set->parameters()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'value_type' => $type,
                    'unit' => $unit,
                    'verified' => false,
                    'verified_at' => null,
                    'source_label' => $sourceLabel,
                    'source_url' => null,
                    'notes' => $value === '' ? 'TODO: el propietario debe confirmarlo antes de publicar.' : null,
                ],
            );
        }

        // Wages are per province and per category, and the value depends on the
        // convenio vigente, so they are seeded empty as well. Iterating the label
        // map keeps the key a plain string for the lookup below.
        foreach (array_keys(self::PROVINCE_LABELS) as $province) {
            $set->parameters()->updateOrCreate(
                ['key' => "wages.{$province}.peon.daily_eur"],
                [
                    'value' => '',
                    'value_type' => 'decimal',
                    'unit' => '€/día',
                    'verified' => false,
                    'source_label' => 'Convenio colectivo del campo de '
                        .self::PROVINCE_LABELS[$province].' (BOP)',
                    'source_url' => null,
                    'notes' => 'TODO: verificar el jornal vigente en el BOP provincial.',
                ],
            );
        }
    }
}
