<?php

use App\Actions\PublishParameterSet;
use App\Enums\ParameterSetStatus;
use App\Enums\ParameterValueType;
use App\Models\ParameterSet;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * The spec §5.2 publish rule.
 *
 * This is the guard that stops an invented official figure from reaching a
 * live calculator, so the tests are about refusals more than successes.
 */
describe('publishing a parameter set', function () {
    beforeEach(function () {
        $this->set = ParameterSet::factory()->create(['version' => '2026-27.1', 'campaign' => '2026-27']);
        $this->action = new PublishParameterSet;
    });

    /** A complete set, verified unless told otherwise. */
    function seedRequired(ParameterSet $set, bool $verified = true, array $overrides = []): void
    {
        foreach (PublishParameterSet::REQUIRED_KEYS as $index => $key) {
            $set->parameters()->create(array_merge([
                'key' => $key,
                'value' => $key === 'campaign.start_month_day' ? '10-01' : '1',
                'value_type' => ParameterValueType::String,
                'unit' => '',
                'verified' => $verified,
                'source_url' => $verified ? 'https://example.test/source' : null,
            ], $overrides[$key] ?? []));
        }
    }

    it('refuses to publish when a required parameter is missing', function () {
        $set = $this->set;
        $set->parameters()->create([
            'key' => 'oil.density_kg_per_l',
            'value' => '0.916',
            'value_type' => ParameterValueType::Decimal,
            'verified' => true,
        ]);

        expect(fn () => $this->action->handle($set))
            ->toThrow(ValidationException::class);
    });

    it('refuses to publish a set with unverified parameters', function () {
        seedRequired($this->set, verified: false);

        expect(fn () => $this->action->handle($this->set))
            ->toThrow(ValidationException::class, 'No se puede publicar con valores sin verificar');
    });

    it('names every unverified key so the owner knows what to check', function () {
        seedRequired($this->set, verified: false);

        try {
            $this->action->handle($this->set);
            $this->fail('Publishing should have been refused.');
        } catch (ValidationException $e) {
            expect($e->errors()['parameters'][0])
                ->toContain('vat.reagp_compensation_pct')
                ->toContain('oil.density_kg_per_l');
        }
    });

    it('publishes when everything required is present and verified', function () {
        seedRequired($this->set);

        $admin = User::factory()->admin()->create();
        $this->action->handle($this->set, $admin);

        $this->set->refresh();
        expect($this->set->status)->toBe(ParameterSetStatus::Published)
            ->and($this->set->published_at)->not->toBeNull()
            ->and($this->set->published_by)->toBe($admin->id);
    });

    it('publishes an unverified set only when the override is explicit', function () {
        seedRequired($this->set, verified: false);

        $this->action->handle($this->set, force: true);

        expect($this->set->refresh()->status)->toBe(ParameterSetStatus::Published);
    });

    it('archives the previous published set for the same campaign, never deletes it', function () {
        $previous = ParameterSet::factory()->create([
            'version' => '2026-27.0',
            'campaign' => '2026-27',
        ]);
        seedRequired($previous);
        $this->action->handle($previous);

        expect($previous->refresh()->status)->toBe(ParameterSetStatus::Published);

        seedRequired($this->set);
        $this->action->handle($this->set);

        expect($this->set->refresh()->status)->toBe(ParameterSetStatus::Published)
            ->and($previous->refresh()->status)->toBe(ParameterSetStatus::Archived)
            // Still on disk: saved calculations reference it (spec §5.1).
            ->and(ParameterSet::find($previous->id))->not->toBeNull();
    });

    it('leaves another campaign untouched', function () {
        $other = ParameterSet::factory()->create([
            'version' => '2025-26.4',
            'campaign' => '2025-26',
        ]);
        seedRequired($other);
        $this->action->handle($other);

        seedRequired($this->set);
        $this->action->handle($this->set);

        expect($other->refresh()->status)->toBe(ParameterSetStatus::Published);
    });

    it('reports the unverified keys for the admin dashboard', function () {
        seedRequired($this->set, verified: false, overrides: [
            'oil.density_kg_per_l' => ['verified' => true],
        ]);

        expect($this->set->unverifiedKeys())->not->toContain('oil.density_kg_per_l')
            ->toContain('vat.reagp_compensation_pct');
    });
});
