<?php

use App\Enums\ParameterSetStatus;
use App\Models\AffiliateProduct;
use App\Models\Article;
use App\Models\Category;
use App\Models\OilPrice;
use App\Models\ParameterSet;
use App\Models\ToolPage;

/**
 * The public API, spec §7.2. One test per endpoint, plus the contract details
 * that would otherwise be easy to regress: decimal strings, ISO dates, cache
 * headers, and the 404s.
 */
describe('GET /api/v1/parameters/current', function () {
    it('404s when nothing is published, rather than serving a draft', function () {
        ParameterSet::factory()->create(['status' => ParameterSetStatus::Draft]);

        $this->getJson('/api/v1/parameters/current')->assertNotFound();
    });

    it('serves the published set in the exact shape the calculators expect', function () {
        $set = publishedSet();

        $response = $this->getJson('/api/v1/parameters/current')->assertOk();

        // Top level, not nested under `data`: @olivar/calculations reads
        // `.version` and `.params` directly.
        $response->assertJsonStructure(['version', 'campaign', 'published_at', 'params']);
        expect($response->json('version'))->toBe($set->version);

        // Keyed by parameter key, not a list. Note the bracket access:
        // assertJsonPath would read the dots in the key as a nested path.
        expect($response->json('params'))->toHaveKey('oil.density_kg_per_l')
            ->and($response->json('params')['oil.density_kg_per_l']['key'])
            ->toBe('oil.density_kg_per_l');
    });

    it('emits amounts as decimal strings and ISO dates', function () {
        publishedSet();

        $response = $this->getJson('/api/v1/parameters/current')->assertOk();

        expect($response->json('params')['oil.density_kg_per_l']['value'])->toBeString()
            ->and($response->json('params')['vat.reagp_compensation_pct']['value'])->toBeString()
            ->and($response->json('published_at'))->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+\-]\d{2}:\d{2}$/');
    });

    it('carries the verified flag and source so the calculator can warn', function () {
        publishedSet(verified: false);

        $params = $this->getJson('/api/v1/parameters/current')->assertOk()->json('params');

        expect($params['oil.density_kg_per_l']['verified'])->toBeFalse()
            ->and($params['oil.density_kg_per_l']['source_label'])->toBe('AEAT');
    });

    it('narrows to a campaign when asked', function () {
        $target = publishedSet(campaign: '2026-27', version: '2026-27.1');
        publishedSet(campaign: '2025-26', version: '2025-26.1');

        $this->getJson('/api/v1/parameters/current?campaign=2026-27')
            ->assertOk()
            ->assertJsonPath('version', $target->version);
    });

    it('rejects a malformed campaign instead of ignoring it', function () {
        $this->getJson('/api/v1/parameters/current?campaign=2026')
            ->assertStatus(422)
            ->assertJsonValidationErrors('campaign');
    });

    it('sets a cacheable Cache-Control and an ETag (spec §7.1)', function () {
        publishedSet();

        $response = $this->getJson('/api/v1/parameters/current')->assertOk();

        expect($response->headers->get('Cache-Control'))->toContain('public')
            ->and($response->headers->get('ETag'))->not->toBeNull();
    });
});

describe('GET /api/v1/parameters/{version}', function () {
    it('serves an archived set so an old calculation can be shown again', function () {
        $set = publishedSet();
        $set->update(['status' => ParameterSetStatus::Archived]);

        $this->getJson("/api/v1/parameters/{$set->version}")
            ->assertOk()
            ->assertJsonPath('version', $set->version);
    });

    it('404s for an unknown version', function () {
        $this->getJson('/api/v1/parameters/1999-00.1')->assertNotFound();
    });
});

describe('GET /api/v1/oil-prices', function () {
    it('returns the weekly series with both €/t and €/kg', function () {
        OilPrice::factory()->create([
            'week_start' => '2026-10-05', 'week_end' => '2026-10-11',
            'quality' => 'virgin', 'avg_price_eur_t' => '3200.00',
        ]);

        $this->getJson('/api/v1/oil-prices')
            ->assertOk()
            ->assertJsonPath('data.0.avg_price_eur_t', '3200.00')
            // Divided on the server so no client does it in float (spec §4.5).
            ->assertJsonPath('data.0.avg_price_eur_kg', '3.2000');
    });

    it('filters by quality', function () {
        OilPrice::factory()->create(['week_start' => '2026-10-05', 'quality' => 'extra']);
        OilPrice::factory()->create(['week_start' => '2026-10-05', 'quality' => 'lampante']);

        $this->getJson('/api/v1/oil-prices?quality=lampante')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.quality', 'lampante');
    });

    it('filters by date range', function () {
        OilPrice::factory()->create(['week_start' => '2026-10-05']);
        OilPrice::factory()->create(['week_start' => '2026-11-02']);

        $this->getJson('/api/v1/oil-prices?from=2026-11-01&to=2026-11-30')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('rejects an unknown quality and an inverted range', function () {
        $this->getJson('/api/v1/oil-prices?quality=suave')->assertStatus(422);
        $this->getJson('/api/v1/oil-prices?from=2026-11-30&to=2026-10-01')->assertStatus(422);
    });

    it('hides unpublished rows', function () {
        OilPrice::factory()->create(['week_start' => '2026-10-05', 'published_at' => null]);

        $this->getJson('/api/v1/oil-prices')->assertOk()->assertJsonCount(0, 'data');
    });
});

describe('GET /api/v1/oil-prices/latest', function () {
    it('returns the newest week per quality with the weekly change', function () {
        OilPrice::factory()->create([
            'week_start' => '2026-10-05', 'quality' => 'virgin', 'avg_price_eur_t' => '3000.00',
        ]);
        OilPrice::factory()->create([
            'week_start' => '2026-10-12', 'quality' => 'virgin', 'avg_price_eur_t' => '3300.00',
        ]);

        $this->getJson('/api/v1/oil-prices/latest')
            ->assertOk()
            ->assertJsonPath('data.0.avg_price_eur_t', '3300.00')
            // (3300 - 3000) / 3000 = 10.00 %
            ->assertJsonPath('data.0.weekly_change_pct', '10.00');
    });

    it('reports a null change when there is no previous week', function () {
        OilPrice::factory()->create([
            'week_start' => '2026-10-12', 'quality' => 'virgin', 'avg_price_eur_t' => '3300.00',
        ]);

        $this->getJson('/api/v1/oil-prices/latest')
            ->assertOk()
            ->assertJsonPath('data.0.weekly_change_pct', null);
    });
});

describe('GET /api/v1/oil-prices/campaigns/{campaign}/summary', function () {
    it('summarises mean, max, min and last per quality', function () {
        foreach ([['2026-10-05', '3000.00'], ['2026-10-12', '3200.00'], ['2026-10-19', '2800.00']] as [$week, $price]) {
            OilPrice::factory()->create([
                'week_start' => $week, 'quality' => 'virgin', 'avg_price_eur_t' => $price,
            ]);
        }

        $this->getJson('/api/v1/oil-prices/campaigns/2026-27/summary')
            ->assertOk()
            ->assertJsonPath('data.0.mean_eur_t', '3000.00')
            ->assertJsonPath('data.0.max_eur_t', '3200.00')
            ->assertJsonPath('data.0.min_eur_t', '2800.00')
            ->assertJsonPath('data.0.last_eur_t', '2800.00');
    });

    it('excludes weeks from the previous campaign', function () {
        OilPrice::factory()->create(['week_start' => '2026-09-21', 'avg_price_eur_t' => '9999.00']);
        OilPrice::factory()->create(['week_start' => '2026-10-05', 'avg_price_eur_t' => '3000.00']);

        $this->getJson('/api/v1/oil-prices/campaigns/2026-27/summary')
            ->assertOk()
            ->assertJsonPath('data.0.max_eur_t', '3000.00');
    });

    it('rejects a malformed campaign label', function () {
        $this->getJson('/api/v1/oil-prices/campaigns/2026/summary')->assertStatus(422);
    });
});

describe('GET /api/v1/categories', function () {
    it('lists categories with their published article counts', function () {
        $category = Category::factory()->create();
        Article::factory()->count(2)->create(['category_id' => $category->id]);
        Article::factory()->draft()->create(['category_id' => $category->id]);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            // The draft does not count: only published guides are listed.
            ->assertJsonPath('data.0.articles_count', 2);
    });
});

describe('GET /api/v1/articles', function () {
    it('paginates in the standard Laravel shape (spec §7.1)', function () {
        Article::factory()->count(3)->create();

        $this->getJson('/api/v1/articles')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonCount(3, 'data');
    });

    it('never leaks drafts', function () {
        Article::factory()->create();
        Article::factory()->draft()->create();

        $this->getJson('/api/v1/articles')->assertOk()->assertJsonCount(1, 'data');
    });

    it('omits the body from the list to keep the payload small', function () {
        Article::factory()->create();

        $this->getJson('/api/v1/articles')
            ->assertOk()
            ->assertJsonMissingPath('data.0.body_markdown');
    });

    it('filters by category and by related tool', function () {
        $cultivo = Category::factory()->create(['slug' => 'cultivo']);
        Article::factory()->create(['category_id' => $cultivo->id, 'related_tool' => 'settlement']);
        Article::factory()->create(['related_tool' => 'yield_converter']);

        $this->getJson('/api/v1/articles?category=cultivo')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/articles?tool=yield_converter')->assertOk()->assertJsonCount(1, 'data');
    });

    it('rejects an unknown tool filter', function () {
        $this->getJson('/api/v1/articles?tool=inexistente')->assertStatus(422);
    });
});

describe('GET /api/v1/articles/{slug}', function () {
    it('returns the full guide with body and FAQ', function () {
        $article = Article::factory()->create(['slug' => 'indice-corrector']);

        $this->getJson('/api/v1/articles/indice-corrector')
            ->assertOk()
            ->assertJsonPath('data.slug', 'indice-corrector')
            ->assertJsonStructure(['data' => ['body_markdown', 'faq', 'meta_title']]);
    });

    it('404s for an unknown slug', function () {
        $this->getJson('/api/v1/articles/no-existe')->assertNotFound();
    });
});

describe('GET /api/v1/tool-pages/{tool_key}', function () {
    it('returns the SEO copy for a tool', function () {
        ToolPage::factory()->create(['tool_key' => 'settlement']);

        $this->getJson('/api/v1/tool-pages/settlement')
            ->assertOk()
            ->assertJsonPath('data.tool_key', 'settlement')
            ->assertJsonStructure(['data' => ['title', 'intro_markdown', 'body_markdown', 'faq']]);
    });

    it('404s for an unknown tool', function () {
        $this->getJson('/api/v1/tool-pages/inventada')->assertNotFound();
    });
});

describe('GET /api/v1/affiliate-products', function () {
    it('lists only active products', function () {
        AffiliateProduct::factory()->create();
        AffiliateProduct::factory()->inactive()->create();

        $this->getJson('/api/v1/affiliate-products')->assertOk()->assertJsonCount(1, 'data');
    });

    it('filters by category', function () {
        AffiliateProduct::factory()->create(['category' => 'mallas']);
        AffiliateProduct::factory()->create(['category' => 'poda']);

        $this->getJson('/api/v1/affiliate-products?category=mallas')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });
});

describe('GET /api/v1/build-manifest', function () {
    it('tells the Astro build which slugs exist and when they changed', function () {
        Article::factory()->create(['slug' => 'guia-uno']);
        ToolPage::factory()->create(['tool_key' => 'settlement']);
        Category::factory()->create(['slug' => 'cultivo']);

        $this->getJson('/api/v1/build-manifest')
            ->assertOk()
            ->assertJsonStructure([
                'generated_at', 'campaign', 'latest_price_week',
                'articles' => [['slug', 'lastmod', 'related_tool']],
                'categories' => [['slug', 'name']],
                'tool_pages' => [['tool_key', 'lastmod']],
            ])
            ->assertJsonPath('articles.0.slug', 'guia-uno');
    });

    it('is not cached: a stale manifest means stale slugs at build time', function () {
        $response = $this->getJson('/api/v1/build-manifest')->assertOk();

        // Symfony appends `private` on its own whenever Cache-Control does not
        // say `public` or `private` explicitly, so assert the directive that
        // matters rather than the exact string.
        expect($response->headers->get('Cache-Control'))->toContain('no-store');
    });
});

/** A published set carrying every parameter the calculations read. */
function publishedSet(
    string $campaign = '2026-27',
    string $version = '2026-27.1',
    bool $verified = true,
): ParameterSet {
    $set = ParameterSet::factory()->create([
        'campaign' => $campaign,
        'version' => $version,
        'status' => ParameterSetStatus::Published,
        'published_at' => now(),
    ]);

    $set->parameters()->createMany([
        ['key' => 'vat.reagp_compensation_pct', 'value' => '12', 'verified' => $verified, 'unit' => '%', 'source_label' => 'AEAT'],
        ['key' => 'irpf.agricultural_withholding_pct', 'value' => '2', 'verified' => $verified, 'unit' => '%', 'source_label' => 'AEAT'],
        ['key' => 'irpf.withholding_base_includes_vat', 'value' => 'false', 'verified' => $verified, 'unit' => '', 'source_label' => 'AEAT'],
        ['key' => 'oil.density_kg_per_l', 'value' => '0.916', 'verified' => $verified, 'unit' => 'kg/L', 'source_label' => 'AEAT'],
    ]);

    return $set->refresh();
}
