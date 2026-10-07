<?php

use App\Models\OilIndexQuote;

/**
 * The POOLred variation endpoint (spec §7.2, §8).
 *
 * These four indices are POOLred's, not ours, so the tests care about that as much
 * as about the numbers: a translated name would be a record that no longer matches
 * the source it credits.
 */
describe('the index quotes endpoint', function () {
    it('serves the four POOLred indices with their own names', function () {
        $expected = [
            'virgenExtra' => 'iPOOL Extra',
            'virgen' => 'iPOOL Virgen',
            'lampante' => 'iPOOL Lampante',
            'nacional' => 'iPOOL España',
        ];

        foreach ($expected as $id => $name) {
            // The name is set explicitly rather than left to the factory: this test
            // is about the API reproducing POOLred's wording, so the factory must
            // not be the thing under test.
            OilIndexQuote::factory()->create([
                'poolred_id' => $id,
                'name' => $name,
                'quoted_on' => '2026-10-07',
            ]);
        }

        $rows = $this->getJson('/api/v1/index-quotes/latest')->assertOk()->json('data');

        expect(array_column($rows, 'poolred_id'))->toEqualCanonicalizing(array_keys($expected));

        foreach ($expected as $id => $name) {
            expect(collect($rows)->firstWhere('poolred_id', $id)['name'])->toBe($name);
        }
    });

    it('returns the variation as a decimal string, never a JSON number', function () {
        OilIndexQuote::factory()->create([
            'poolred_id' => 'virgen',
            'variation_pct' => '-6.850',
            'quoted_on' => '2026-10-07',
        ]);

        $raw = $this->get('/api/v1/index-quotes/latest')->getContent();

        // A JSON number here would reach the frontend as a float (spec §0.7).
        expect($raw)->toContain('"variation_pct":"-6.850"')
            ->and($raw)->not->toContain('"variation_pct":-6.85');
    });

    it('serves only the newest window, so dates cannot be mixed', function () {
        OilIndexQuote::factory()->create(['poolred_id' => 'virgen', 'quoted_on' => '2026-09-30']);
        OilIndexQuote::factory()->create(['poolred_id' => 'virgen', 'quoted_on' => '2026-10-07']);
        OilIndexQuote::factory()->create(['poolred_id' => 'extra', 'quoted_on' => '2026-09-30']);

        $dates = array_unique(array_column($this->getJson('/api/v1/index-quotes/latest')->json('data'), 'quoted_on'));

        expect($dates)->toBe(['2026-10-07']);
    });

    it('is public, since republishing an index is the point of having one', function () {
        OilIndexQuote::factory()->create(['poolred_id' => 'virgen', 'quoted_on' => '2026-10-07']);

        $this->getJson('/api/v1/index-quotes/latest')->assertOk();
    });

    it('carries POOLred\'s own window label and their update stamp', function () {
        OilIndexQuote::factory()->create([
            'poolred_id' => 'virgen',
            'quoted_on' => '2026-10-07',
            'period' => '7 sept - 7 oct 2026',
            'source_stamp' => '2026-10-07 12:58',
        ]);

        $row = $this->getJson('/api/v1/index-quotes/latest')->json('data.0');

        // Provenance has to survive to the page, so a grower can see how fresh it is.
        expect($row['period'])->toBe('7 sept - 7 oct 2026')
            ->and($row['source_stamp'])->toBe('2026-10-07 12:58')
            ->and($row['source_url'])->not->toBe('');
    });

    it('returns an empty list rather than failing when nothing is imported', function () {
        // The page has to be able to render its "not yet published" state; a 500
        // here would fail the whole Astro build (spec §10.1).
        $this->getJson('/api/v1/index-quotes/latest')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });
});
