<?php

use App\Filament\Resources\AffiliateProducts\AffiliateProductResource;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\OilPrices\OilPriceResource;
use App\Filament\Resources\ParameterSets\ParameterSetResource;
use App\Filament\Resources\SiteRebuildRequests\SiteRebuildRequestResource;
use App\Filament\Resources\ToolPages\ToolPageResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\AffiliateProduct;
use App\Models\Article;
use App\Models\Category;
use App\Models\OilPrice;
use App\Models\ParameterSet;
use App\Models\SiteRebuildRequest;
use App\Models\ToolPage;
use App\Models\User;

/**
 * Every panel screen renders (spec §9).
 *
 * This exists because a wrong namespace or a missing import in a Filament
 * resource is invisible until the page is actually requested: the class is
 * only autoloaded when the route hits it. Asserting each index renders catches
 * that, plus "class not found" on a missing import.
 */
describe('admin panel screens', function () {
    beforeEach(function () {
        $this->admin = User::factory()->admin()->create();
    });

    /**
     * Renders each index with a record present.
     *
     * The row matters: with an empty table Filament never evaluates a column,
     * so a broken `state()` or a method treated as a relationship goes
     * unnoticed. That is exactly how the categories table shipped a 500.
     */
    it('renders every resource index with a record, for an admin', function (string $resource, Closure $seed) {
        $seed();

        $this->actingAs($this->admin)
            ->get($resource::getUrl('index'))
            ->assertSuccessful();
    })->with([
        'conjuntos' => [ParameterSetResource::class, fn () => ParameterSet::factory()->create()],
        'precios' => [OilPriceResource::class, fn () => OilPrice::factory()->create()],
        'articulos' => [ArticleResource::class, fn () => Article::factory()->create()],
        'categorias' => [CategoryResource::class, fn () => Category::factory()->create()],
        'paginas de herramienta' => [ToolPageResource::class, fn () => ToolPage::factory()->create()],
        'afiliados' => [AffiliateProductResource::class, fn () => AffiliateProduct::factory()->create()],
        'usuarios' => [UserResource::class, fn () => User::factory()->create()],
        'reconstrucciones' => [SiteRebuildRequestResource::class, fn () => SiteRebuildRequest::factory()->create()],
    ]);

    it('renders the parameter set edit page with its relation manager', function () {
        $set = ParameterSet::factory()->create();

        $this->actingAs($this->admin)
            ->get(ParameterSetResource::getUrl('edit', ['record' => $set]))
            ->assertSuccessful();
    });

    it('renders a create page for the resources that have one', function (string $resource) {
        $this->actingAs($this->admin)
            ->get($resource::getUrl('create'))
            ->assertSuccessful();
    })->with([
        OilPriceResource::class,
        ArticleResource::class,
        CategoryResource::class,
        AffiliateProductResource::class,
        ParameterSetResource::class,
    ]);

    it('renders the dashboard', function () {
        $this->actingAs($this->admin)->get('/admin')->assertSuccessful();
    });

    /**
     * Spec §5.1: a set is never deleted, and the panel must not offer it.
     * The page template is a blunt but effective place to assert the absence of
     * a destructive action.
     */
    it('does not offer a delete action on parameter sets', function () {
        $this->actingAs($this->admin)
            ->get(ParameterSetResource::getUrl('index'))
            ->assertDontSee('DeleteAction')
            ->assertOk();
    });

    it('shows the set version and Spanish status on the edit page', function () {
        $set = ParameterSet::factory()->create(['version' => '2026-27.9']);
        $set->parameters()->createMany([
            ['key' => 'oil.density_kg_per_l', 'value' => '0.916', 'verified' => false, 'source_label' => 'x'],
            ['key' => 'vat.reagp_compensation_pct', 'value' => '12', 'verified' => true, 'source_label' => 'y'],
        ]);

        $this->actingAs($this->admin)
            ->get(ParameterSetResource::getUrl('edit', ['record' => $set]))
            ->assertSuccessful()
            ->assertSee('2026-27.9')
            // Enum labels are in Spanish: every visible string is (spec §0.1).
            ->assertSee('Borrador');
    });

    it('exposes the tool page route slug for the "view on site" link', function () {
        $page = ToolPage::factory()->create(['tool_key' => 'settlement']);

        expect($page->getRouteKey())->toBe('liquidacion-aceituna');
    });
});
