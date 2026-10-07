<?php

use App\Actions\ImportPoolredIndex;
use App\Models\OilIndexQuote;
use App\Models\OilPrice;
use App\Services\Poolred\Client;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * The POOLred importer (spec §8).
 *
 * The HTTP layer is faked throughout. These tests are about what we store and how
 * we behave when the source misbehaves, and faking means they do not depend on a
 * third party's availability or on their data staying the same.
 */
describe('importing the POOLred index', function () {
    /** One daily point per date, all at the same index level. */
    function series(string $from, string $to, string $index = '3000.00'): string
    {
        $start = CarbonImmutable::parse($from);
        $end = CarbonImmutable::parse($to);
        $result = [];

        while ($start->lte($end)) {
            $result[] = ['date' => $start->toDateString(), 'index' => (float) $index];
            $start = $start->addDay();
        }

        return json_encode([
            'startDate' => $from,
            'endDate' => $to,
            'variation' => -0.0685,
            'isFinalized' => false,
            'result' => $result,
        ], JSON_THROW_ON_ERROR);
    }

    /** The official ticker payload. */
    function ticker(array $variations = []): string
    {
        $default = ['virgenExtra', 'virgen', 'lampante', 'nacional'];
        $items = [];

        foreach ($variations ?: array_fill_keys($default, -6.2) as $id => $value) {
            $items[] = ['id' => $id, 'nombre' => "iPOOL {$id}", 'variacion' => $value, 'fecha' => '2026-10-07'];
        }

        return json_encode([
            'items' => $items,
            'periodo' => '7 sept - 7 oct 2026',
            'actualizado' => '2026-10-07 12:58',
        ], JSON_THROW_ON_ERROR);
    }

    /** Fakes both endpoints and returns a fresh importer. */
    function importer(string $indexBody, string $tickerBody): ImportPoolredIndex
    {
        Http::fake([
            'api.poolred.com/*' => Http::response($indexBody),
            'poolred.com/*' => Http::response($tickerBody),
        ]);

        return new ImportPoolredIndex(new Client);
    }

    it('averages the daily series into calendar weeks', function () {
        $body = series('2026-09-28', '2026-10-11');

        // Two flat weeks at different levels, so a wrong bucket shows up.
        Http::fake([
            'api.poolred.com/*' => Http::response(json_encode(['isFinalized' => true, 'result' => [
                ['date' => '2026-09-28', 'index' => 3000.00],
                ['date' => '2026-10-04', 'index' => 3200.00],
                ['date' => '2026-10-05', 'index' => 3400.00],
                ['date' => '2026-10-11', 'index' => 3600.00],
            ]])),
            'poolred.com/*' => Http::response(ticker()),
        ]);

        $result = (new ImportPoolredIndex(new Client))->handle(30);

        expect($result['weeks'])->toBe(2);

        $first = OilPrice::where('week_start', '2026-09-28')->firstOrFail();
        $second = OilPrice::where('week_start', '2026-10-05')->firstOrFail();

        // (3000 + 3200) / 2, and (3400 + 3600) / 2.
        expect($first->avg_price_eur_t)->toBe('3100.00')
            ->and($first->week_end->toDateString())->toBe('2026-10-04')
            ->and($second->avg_price_eur_t)->toBe('3500.00')
            ->and($second->week_end->toDateString())->toBe('2026-10-11');
    });

    it('records the source instead of leaving it manual', function () {
        importer(series('2026-10-05', '2026-10-11'), ticker())->handle(30);

        $price = OilPrice::where('week_start', '2026-10-05')->firstOrFail();

        expect($price->source)->toBe('poolred')
            ->and($price->source_url)->toBe(config('services.poolred.source_url'))
            ->and($price->published_at)->not->toBeNull();
    });

    it('leaves the volume null rather than inventing one', function () {
        importer(series('2026-10-05', '2026-10-11'), ticker())->handle(30);

        // POOLred's public series carries no traded volume. A number here would be
        // made up, which is the one thing this project does not do.
        expect(OilPrice::where('week_start', '2026-10-05')->firstOrFail()->volume_t)->toBeNull();
    });

    it('mirrors their provisional flag instead of deciding on its own', function () {
        Http::fake([
            'api.poolred.com/*' => Http::response(json_encode(['isFinalized' => true, 'result' => [
                ['date' => '2026-10-05', 'index' => 3000.0],
            ]])),
            'poolred.com/*' => Http::response(ticker()),
        ]);

        (new ImportPoolredIndex(new Client))->handle(30);

        // They said settled, so we do not add a provisional notice of our own.
        expect(OilPrice::where('week_start', '2026-10-05')->firstOrFail()->provisional)->toBeFalse();
    });

    it('is idempotent, because it runs twice a day for years', function () {
        $import = importer(series('2026-10-05', '2026-10-11'), ticker());

        $import->handle(30);
        $import->handle(30);

        expect(OilPrice::where('week_start', '2026-10-05')->count())->toBe(1)
            ->and(OilIndexQuote::count())->toBe(4);
    });

    it('updates a week it already stored rather than appending', function () {
        OilPrice::create([
            'week_start' => '2026-10-05',
            'week_end' => '2026-10-11',
            'quality' => 'extra',
            'avg_price_eur_t' => '9999.00',
            'provisional' => true,
            'source' => 'manual',
            'published_at' => now(),
        ]);

        importer(series('2026-10-05', '2026-10-11'), ticker())->handle(30);

        $price = OilPrice::where('week_start', '2026-10-05')->firstOrFail();

        expect(OilPrice::count())->toBe(1)
            ->and($price->avg_price_eur_t)->not->toBe('9999.00')
            ->and($price->source)->toBe('poolred');
    });

    it('keeps the variation as a percentage, not a fraction', function () {
        // The two POOLred endpoints disagree on units: the REST series returns a
        // fraction (-0.0685) and the ticker a percentage (-6.85). Scaling the
        // ticker turns a 6.85 % fall into 685 %.
        importer(series('2026-10-05', '2026-10-11'), ticker(['virgen' => -6.85]))->handle(30);

        expect(OilIndexQuote::where('poolred_id', 'virgen')->firstOrFail()->variation_pct)
            ->toBe('-6.850');
    });

    it('keeps POOLred\'s own names for its four indices', function () {
        importer(series('2026-10-05', '2026-10-11'), ticker())->handle(30);

        expect(OilIndexQuote::orderBy('poolred_id')->pluck('poolred_id')->all())
            ->toBe(['lampante', 'nacional', 'virgen', 'virgenExtra']);
    });

    it('refuses an empty series instead of wiping the prices', function () {
        OilPrice::factory()->create([
            'week_start' => '2026-01-05',
            'week_end' => '2026-01-11',
            'quality' => 'extra',
            'avg_price_eur_t' => '3000.00',
            'published_at' => now(),
        ]);

        importer(json_encode(['result' => []]), ticker());

        // The import must refuse, loudly, rather than quietly store nothing.
        expect(fn () => (new ImportPoolredIndex(new Client))->handle(30))
            ->toThrow(RuntimeException::class, 'serie vacía');

        // Nothing was deleted. Losing a year of history to one bad response would
        // be worse than showing a stale figure.
        expect(OilPrice::count())->toBe(1);
    });

    it('skips a malformed point rather than storing a non-number', function () {
        Http::fake([
            'api.poolred.com/*' => Http::response(json_encode(['isFinalized' => true, 'result' => [
                ['date' => '2026-10-05', 'index' => 3000.0],
                ['date' => null, 'index' => 9999.0],
                ['date' => '2026-10-06', 'index' => 'no es un numero'],
                ['date' => '2026-10-07'],
            ]])),
            'poolred.com/*' => Http::response(ticker()),
        ]);

        (new ImportPoolredIndex(new Client))->handle(30);

        // Only the one good day survived, and no text reached a DECIMAL column.
        expect(OilPrice::where('week_start', '2026-10-05')->firstOrFail()->avg_price_eur_t)
            ->toBe('3000.00');
    });

    it('stores the price as a decimal string, never a float', function () {
        importer(series('2026-10-05', '2026-10-11'), ticker())->handle(30);

        $raw = DB::table('oil_prices')->where('week_start', '2026-10-05')->first();

        expect($raw->avg_price_eur_t)->toBeString();
    });

    it('sends an identifiable user agent', function () {
        importer(series('2026-10-05', '2026-10-11'), ticker())->handle(30);

        Http::assertSent(fn (Request $request): bool => str_contains(
            implode(' ', (array) $request->header('User-Agent')),
            'OlivarCalc',
        ));
    });

    it('never sends a bearer token', function () {
        importer(series('2026-10-05', '2026-10-11'), ticker())->handle(30);

        // Three of their indices need a session token that the public widget holds.
        // This importer must not be the thing that replays it.
        Http::assertSent(fn (Request $request): bool => $request->header('Authorization') === []);
    });

    it('asks only for the index that answers without credentials', function () {
        importer(series('2026-10-05', '2026-10-11'), ticker())->handle(30);

        $paths = [];

        Http::assertSent(function (Request $request) use (&$paths): bool {
            if (str_contains($request->url(), 'api.poolred.com')) {
                $paths[] = $request->url();
            }

            return true;
        });

        expect($paths)->not->toBeEmpty();

        foreach ($paths as $url) {
            expect($url)->toContain('/ipool/extra');
        }
    });
});
