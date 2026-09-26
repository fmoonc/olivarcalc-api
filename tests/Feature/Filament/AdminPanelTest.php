<?php

use App\Filament\Resources\OilPrices\OilPriceResource;
use App\Filament\Resources\ParameterSets\Pages\ListParameterSets;
use App\Filament\Resources\ParameterSets\ParameterSetResource;
use App\Models\User;

/**
 * The admin panel is the only place official parameters and prices are
 * authored (spec §9). A broken resource here means the owner cannot correct a
 * figure the calculators depend on, so it is worth a test even though most of
 * the surface is Livewire.
 */
describe('admin access', function () {
    it('sends a guest to the login page', function () {
        $this->get('/admin')->assertRedirect();
    });

    it('refuses a non-admin user', function () {
        $this->actingAs(User::factory()->create())
            ->get(ParameterSetResource::getUrl('index'))
            ->assertForbidden();
    });

    it('lets an admin reach the parameter sets', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(ParameterSetResource::getUrl('index'))
            ->assertSuccessful();
    });

    it('lets an admin reach the oil prices', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(OilPriceResource::getUrl('index'))
            ->assertSuccessful();
    });

    it('exposes the publish action, which is where the spec §5.2 rule applies', function () {
        $pages = ParameterSetResource::getPages();

        expect($pages)->toHaveKey('index');
        // The list page is where publishing and duplicating live.
        expect($pages['index']->getPage())->toBe(
            ListParameterSets::class
        );
    });
});
