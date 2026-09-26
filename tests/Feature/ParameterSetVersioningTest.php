<?php

use App\Actions\DuplicateParameterSet;
use App\Enums\ParameterSetStatus;
use App\Enums\ParameterValueType;
use App\Models\ParameterSet;
use Database\Seeders\ParameterSetSeeder;

/**
 * Duplicating the live set, and what re-seeding is allowed to touch.
 *
 * Both guards here exist because the obvious implementation was wrong and
 * nothing noticed:
 *
 * - `createMany()` does not accept models, only arrays, so the original
 *   "Duplicar el vigente" action died with a TypeError the first time anyone
 *   pressed it in the panel.
 * - `updateOrCreate()` in the seeder reset a published set's status, which took
 *   the live set offline and, worse, would have overwritten the values that
 *   saved calculations point at.
 */
describe('duplicating a parameter set', function () {
    beforeEach(function () {
        $this->set = ParameterSet::factory()->create([
            'version' => '2026-27.1',
            'campaign' => '2026-27',
            'status' => ParameterSetStatus::Published,
        ]);

        foreach (['vat.reagp_compensation_pct', 'wages.jaen.peon.daily_eur'] as $key) {
            $this->set->parameters()->create([
                'key' => $key,
                'value' => $key === 'vat.reagp_compensation_pct' ? '12' : '60',
                'value_type' => ParameterValueType::Decimal,
                'verified' => $key === 'vat.reagp_compensation_pct',
                'source_url' => $key === 'vat.reagp_compensation_pct' ? 'https://example.test/ley' : null,
            ]);
        }

        $this->action = new DuplicateParameterSet;
    });

    it('copies every parameter, not just the required ones', function () {
        $copy = $this->action->handle($this->set);

        expect($copy->parameters()->pluck('key')->all())
            ->toEqualCanonicalizing($this->set->parameters()->pluck('key')->all());
    });

    it('reassigns each copy to the new set', function () {
        $copy = $this->action->handle($this->set);

        // `replicate()` keeps the foreign key from the original, so a naive copy
        // would leave both rows pointing at the published set.
        expect($copy->parameters()->pluck('parameter_set_id')->unique()->all())
            ->toBe([$copy->getKey()]);
    });

    it('keeps the values, including a verified one with its source', function () {
        $copy = $this->action->handle($this->set);

        $reagp = $copy->parameters()->where('key', 'vat.reagp_compensation_pct')->firstOrFail();

        expect($reagp->value)->toBe('12')
            ->and($reagp->verified)->toBeTrue()
            ->and($reagp->source_url)->toBe('https://example.test/ley');
    });

    it('opens a draft with a bumped version and no publication stamp', function () {
        $copy = $this->action->handle($this->set);

        expect($copy->status)->toBe(ParameterSetStatus::Draft)
            ->and($copy->version)->toBe('2026-27.2')
            ->and($copy->published_at)->toBeNull()
            ->and($copy->published_by)->toBeNull();
    });

    it('leaves the published set exactly as it was', function () {
        $this->action->handle($this->set);

        $this->set->refresh();

        expect($this->set->status)->toBe(ParameterSetStatus::Published)
            ->and($this->set->version)->toBe('2026-27.1')
            ->and($this->set->parameters()->count())->toBe(2);
    });

    it('keeps counting up when the version is bumped twice', function () {
        $second = $this->action->handle($this->set);
        $third = $this->action->handle($second);

        expect($third->version)->toBe('2026-27.3');
    });
});

describe('re-seeding', function () {
    it('does not take a published set offline', function () {
        $set = ParameterSet::factory()->create([
            'version' => '2026-27.1',
            'campaign' => '2026-27',
            'status' => ParameterSetStatus::Published,
        ]);

        $this->seed(ParameterSetSeeder::class);

        expect($set->fresh()->status)->toBe(ParameterSetStatus::Published)
            ->and(ParameterSet::where('version', '2026-27.1')->count())->toBe(1);
    });

    it('does not rewrite an archived version either', function () {
        // Archived sets are what saved calculations still point at, so history is
        // as untouchable as the live set.
        $set = ParameterSet::factory()->create([
            'version' => '2026-27.1',
            'campaign' => '2026-27',
            'status' => ParameterSetStatus::Archived,
        ]);

        $set->parameters()->create([
            'key' => 'wages.jaen.peon.daily_eur',
            'value' => '58.40',
            'value_type' => ParameterValueType::Decimal,
            'unit' => '€/día',
            'verified' => false,
        ]);

        $this->seed(ParameterSetSeeder::class);

        expect($set->parameters()->where('key', 'wages.jaen.peon.daily_eur')->firstOrFail()->value)
            ->toBe('58.40');
    });

    it('never overwrites a value the owner has already verified', function () {
        $set = ParameterSet::factory()->create([
            'version' => '2026-27.1',
            'campaign' => '2026-27',
            'status' => ParameterSetStatus::Draft,
        ]);

        $set->parameters()->create([
            'key' => 'vat.reagp_compensation_pct',
            'value' => '7.5',
            'value_type' => ParameterValueType::Decimal,
            'unit' => '%',
            'verified' => true,
            'verified_at' => now(),
            'source_label' => 'Fuente que el dueño leyó',
            'source_url' => 'https://example.test/mi-fuente',
        ]);

        $this->seed(ParameterSetSeeder::class);

        $parameter = $set->parameters()->where('key', 'vat.reagp_compensation_pct')->firstOrFail();

        expect($parameter->value)->toBe('7.5')
            ->and($parameter->verified)->toBeTrue()
            ->and($parameter->source_url)->toBe('https://example.test/mi-fuente')
            ->and($parameter->source_label)->toBe('Fuente que el dueño leyó');
    });

    it('leaves a verified row entirely alone, note included', function () {
        $set = ParameterSet::factory()->create([
            'version' => '2026-27.1',
            'campaign' => '2026-27',
            'status' => ParameterSetStatus::Draft,
        ]);

        // The owner may have rewritten the note themselves, so the seeder does not
        // treat a verified row as its own to maintain.
        $set->parameters()->create([
            'key' => 'vat.olive_general_rate_pct',
            'value' => '10',
            'value_type' => ParameterValueType::Decimal,
            'unit' => '%',
            'verified' => true,
            'verified_at' => now(),
            'source_url' => 'https://example.test/ley',
            'notes' => 'La nota que escribió el dueño',
        ]);

        $this->seed(ParameterSetSeeder::class);

        $parameter = $set->parameters()->where('key', 'vat.olive_general_rate_pct')->firstOrFail();

        expect($parameter->notes)->toBe('La nota que escribió el dueño')
            ->and($parameter->value)->toBe('10');
    });

    it('seeds the 21 parameters from spec 5.2, with a note on each', function () {
        $this->seed(ParameterSetSeeder::class);

        $set = ParameterSet::where('version', '2026-27.1')->firstOrFail();

        expect($set->parameters()->count())->toBe(21)
            ->and($set->parameters()->whereNull('notes')->count())->toBe(0);
    });

    it('seeds the two figures read off Ley 37/1992 already verified, with their article', function () {
        $this->seed(ParameterSetSeeder::class);

        $set = ParameterSet::where('version', '2026-27.1')->firstOrFail();

        $reagp = $set->parameters()->where('key', 'vat.reagp_compensation_pct')->firstOrFail();
        $iva = $set->parameters()->where('key', 'vat.olive_general_rate_pct')->firstOrFail();

        // Art. 130.5.1.º says 12 for agricultural holdings; art. 91.Uno puts
        // olive oil, and the olives it is made from, at 10.
        expect($reagp->value)->toBe('12')
            ->and($reagp->verified)->toBeTrue()
            ->and($reagp->source_url)->toContain('#a130')
            ->and($iva->value)->toBe('10')
            ->and($iva->verified)->toBeTrue()
            ->and($iva->source_url)->toContain('#a91');

        expect($set->unverifiedKeys())->toHaveCount(19);
    });

    it('leaves the undefendable values empty rather than plausible', function () {
        $this->seed(ParameterSetSeeder::class);

        $set = ParameterSet::where('version', '2026-27.1')->firstOrFail();

        // Spec §0.2: an invented placeholder is worse than a blank. These three
        // have no figure anyone can defend, so they stay empty and the crew
        // calculator asks the grower for them instead.
        foreach (['labor.employer_cost_pct', 'wages.jaen.peon.daily_eur', 'wages.toledo.peon.daily_eur'] as $key) {
            expect($set->parameters()->where('key', $key)->firstOrFail()->value)->toBe('');
        }
    });
});
