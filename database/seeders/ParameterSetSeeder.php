<?php

namespace Database\Seeders;

use App\Enums\ParameterSetStatus;
use App\Enums\ParameterValueType;
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
        $set = ParameterSet::firstOrNew(['version' => '2026-27.1']);

        // Re-seeding must never touch a set that is live or historical.
        //
        // `updateOrCreate` used to reset the status, which silently took the live
        // set offline and left a stale `published_at` on a draft. Worse, it
        // rewrote the values that saved calculations point at. Corrections are
        // supposed to arrive as a new version (DuplicateParameterSet), not by
        // editing history, so the seeder only ever writes to a draft.
        if ($set->exists && $set->status !== ParameterSetStatus::Draft) {
            $this->command?->warn(
                'El conjunto '.$set->version.' está en '.$set->status->value
                .' y no se toca. Para corregir valores, duplícalo desde el panel y publica el borrador nuevo.'
            );

            return;
        }

        $set->forceFill([
            'campaign' => '2026-27',
            'status' => ParameterSetStatus::Draft,
            'notes' => 'Conjunto inicial. Los valores confirmados llevan su fuente; '
                .'el resto está pendiente de verificar por el propietario.',
        ])->save();

        $parameters = [
            // key => [value, type, unit, source label, note]
            //
            // The note is the owner's to-do, and it is deliberately specific:
            // "verificar en la fuente" on its own says nothing they did not already
            // know. It names the article to open, and flags the trap where there is
            // one, because these are the details that decide whether a figure is right.
            'vat.reagp_compensation_pct' => [
                '12', 'decimal', '%',
                'Ley 37/1992 del IVA, art. 130.5.1.º',
                'Comprobado en el texto consolidado: «El 12 por 100, en las entregas de productos '
                    .'naturales obtenidos en explotaciones agrícolas o forestales». Ojo al detalle del '
                    .'mismo artículo: la compensación se calcula sobre un precio SIN los tributos '
                    .'indirectos, así que no se aplica sobre una base que ya lleve el IVA dentro.',
            ],
            'vat.olive_general_rate_pct' => [
                '10', 'decimal', '%',
                'Ley 37/1992 del IVA, art. 91.Uno.1.1.º y 1.2.º',
                'La spec lo dejó en TODO. El 10 % del apartado Uno.1.1.º cubre los productos '
                    .'destinados a la nutrición humana, y el 1.2.º extiende ese mismo tipo a los '
                    .'vegetales destinados a obtenerlos, que es el caso de la aceituna que va a la '
                    .'almazara. Es un parámetro distinto del REAGP: aquel es una compensación a tanto '
                    .'alzado y este es IVA real, y la calculadora los separa por régimen.',
            ],
            'irpf.agricultural_withholding_pct' => [
                '2', 'decimal', '%',
                'Reglamento del IRPF (RD 439/2007), art. 95',
                'El 2 % viene de la spec como valor orientativo y sigue sin confirmar: no se ha '
                    .'podido leer el artículo 95. Descartado el 15 %, que es para otras actividades. '
                    .'Comprobar el porcentaje aplicable a la entrega de aceituna o de aceite antes '
                    .'de darlo por bueno.',
            ],
            'irpf.withholding_base_includes_vat' => [
                'false', 'bool', '',
                'AEAT — base de la retención en el IRPF',
                'PENDIENTE Y SOSPECHOSO. La spec lo siembra en false, pero para entregas de bienes la '
                    .'base de la retención debería incluir los tributos indirectos, lo que lo haría '
                    .'true. No se ha cambiado a la espera del art. 95. No es un detalle menor: con el '
                    .'caso de referencia de 10.000 kg la retención pasa de 87,20 € a 97,66 €, un 12 % '
                    .'más retenido al agricultor.',
            ],
            'oil.density_kg_per_l' => [
                '0.916', 'decimal', 'kg/L',
                'Reglamento (UE) 1308/2013 — ficha del producto',
                'Pendiente de fuente. El 0,916 sale de la spec como «referencia técnica». El Reglamento '
                    .'(UE) 1308/2013 da un rango de 0,915 a 0,918 g/cm³ a 20 °C, así que el valor cae '
                    .'dentro, pero hay que decidir qué se cita: la norma europea o la ficha del laboratorio '
                    .'que hizo el análisis.',
            ],
            'campaign.start_month_day' => [
                '10-01', 'string', 'MM-DD',
                'Convención del sector — AICA',
                'Pendiente. Es una convención del sector y no una norma, así que no hay artículo que '
                    .'citar. Confirmar con la cooperativa o con la AICA antes de fijarlo.',
            ],
            'labor.employer_cost_pct' => [
                '', 'decimal', '%',
                'Seguridad Social — Sistema Especial Agrario',
                'TODO: el propietario debe confirmarlo antes de publicar. Se mueve cada enero con el '
                    .'salario mínimo y con las cotizaciones, así que envejece deprisa: conviene '
                    .'revisarlo en septiembre, antes de que empiece la campaña.',
            ],
        ];

        // The cost-per-hectare presets are JSON blobs, one per system x
        // irrigation combination the calculators accept (spec §4.6).
        foreach (['traditional', 'intensive', 'superintensive'] as $system) {
            foreach (['dry', 'irrigated'] as $irrigation) {
                $parameters["presets.cost_per_ha.{$system}.{$irrigation}"] = [
                    json_encode(['pruning' => '', 'fertilization' => ''], JSON_THROW_ON_ERROR),
                    'json',
                    '€/ha',
                    'Estudios de costes de producción del MAPA / Junta de Andalucía',
                    'Pendiente. Los estudios de costes son de comarca y de campaña, así que no hay una '
                        .'cifra única que sirva para toda Andalucía. Decidir si se documenta con el '
                        .'estudio más reciente que haya o se deja vacío para que cada agricultor meta '
                        .'sus números. Rellenar solo las líneas que apliquen: las vacías cuentan como 0.',
                ];
            }
        }

        foreach ($parameters as $key => [$value, $type, $unit, $sourceLabel, $note]) {
            // The confirmed pair is seeded further down, already verified. Running
            // it through this loop would reset it to unverified with no source.
            if (in_array($key, ['vat.reagp_compensation_pct', 'vat.olive_general_rate_pct'], true)) {
                continue;
            }

            // A verified row belongs to the owner, so the seeder leaves it
            // entirely alone: value, provenance and note. Verification is the
            // expensive part — someone read the article, noted the date and
            // agreed the figure — and re-seeding must not undo any of it, nor
            // edit a note they may have rewritten themselves.
            if ($set->parameters()->where('key', $key)->where('verified', true)->exists()) {
                continue;
            }

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
                    'notes' => $note,
                ],
            );
        }

        // Wages are per province and per category, and the value depends on the
        // convenio vigente, so they are seeded empty as well. Iterating the label
        // map keeps the key a plain string for the lookup below.
        foreach (array_keys(self::PROVINCE_LABELS) as $province) {
            $key = "wages.{$province}.peon.daily_eur";
            // Same rule as above: a wage the owner already verified is theirs.
            if ($set->parameters()->where('key', $key)->where('verified', true)->exists()) {
                continue;
            }

            $set->parameters()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => '',
                    'value_type' => 'decimal',
                    'unit' => '€/día',
                    'verified' => false,
                    'source_label' => 'Convenio colectivo del campo de '
                        .self::PROVINCE_LABELS[$province].' (BOP)',
                    'source_url' => null,
                    'notes' => 'Pendiente, buscar en el BOP de '
                        .self::PROVINCE_LABELS[$province].'. La trampa: el jornal que se paga en la '
                        .'aceituna suele ser el plus de recolección, que no es el salario base del '
                        .'convenio, y se revisa por temporada. Anotar con qué fecha se tomó, o el '
                        .'cálculo no se puede reproducir dentro de dos años.',
                ],
            );
        }

        // Two parameters are not "orientative": they were read off the
        // consolidated text of Ley 37/1992 and are seeded already verified, with
        // the article they come from. Seeding them unverified would make a fresh
        // environment disagree with the live one and would show the §4.3 warning
        // for figures that are not in doubt.
        $ley = 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740';

        $confirmed = [
            // Art. 130.5.1.º: "El 12 por 100, en las entregas de productos
            // naturales obtenidos en explotaciones agrícolas o forestales".
            'vat.reagp_compensation_pct' => ['12', $ley.'#a130', 'Ley 37/1992 del IVA, art. 130.5.1.º'],
            // Art. 91.Uno.1.1.º covers produce for human nutrition; 1.2.º extends
            // the same rate to the vegetables used to obtain it, which is the
            // olives headed for the mill.
            'vat.olive_general_rate_pct' => ['10', $ley.'#a91', 'Ley 37/1992 del IVA, art. 91.Uno.1.1.º y 1.2.º'],
        ];

        foreach ($confirmed as $key => [$value, $url, $label]) {
            $parameter = $set->parameters()->firstOrNew(['key' => $key]);

            // A value the owner has since re-checked against a later reading
            // wins over the seeded one; re-seeding must not undo their work.
            if ($parameter->exists && $parameter->verified) {
                continue;
            }

            // The note lives with the rest of the parameters so there is one copy
            // of the text. It carries findings that outlive the seed, so a
            // verified figure is not the same as a figure with nothing to say.
            // Every key in $confirmed is also in $parameters; if that ever stops
            // being true this should fail loudly rather than seed a blank note.
            $note = $parameters[$key][4];

            $parameter->fill([
                'value' => $value,
                'value_type' => ParameterValueType::Decimal,
                'unit' => '%',
                'source_label' => $label,
                'source_url' => $url,
                'notes' => $note,
            ])->save();

            $parameter->markVerified($url, $label);
        }
    }
}
