<?php

use App\Http\Controllers\Api\V1\BuildManifestController;
use App\Http\Controllers\Api\V1\ContentController;
use App\Http\Controllers\Api\V1\OilPriceController;
use App\Http\Controllers\Api\V1\ParameterController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API, version 1 (spec §7.2)
|--------------------------------------------------------------------------
|
| Everything here is unauthenticated, rate limited to 120 req/min per IP, and
| cacheable. The browser never touches the database: it goes through these
| endpoints, which is also what lets the future mobile app reuse them with a
| Sanctum token instead of cookies (spec §15).
|
*/

Route::prefix('v1')->group(function (): void {
    // Parameters: the calculators' only source of official figures.
    Route::get('parameters/current', [ParameterController::class, 'current'])
        ->name('api.v1.parameters.current');
    Route::get('parameters/{version}', [ParameterController::class, 'show'])
        ->name('api.v1.parameters.show');

    // Oil prices.
    Route::get('oil-prices', [OilPriceController::class, 'index'])
        ->name('api.v1.oil-prices.index');
    Route::get('oil-prices/latest', [OilPriceController::class, 'latest'])
        ->name('api.v1.oil-prices.latest');
    Route::get('oil-prices/campaigns/{campaign}/summary', [OilPriceController::class, 'campaignSummary'])
        ->name('api.v1.oil-prices.campaign-summary');

    // Editorial content.
    Route::get('categories', [ContentController::class, 'categories'])
        ->name('api.v1.categories');
    Route::get('articles', [ContentController::class, 'articles'])
        ->name('api.v1.articles.index');
    Route::get('articles/{slug}', [ContentController::class, 'article'])
        ->name('api.v1.articles.show');
    Route::get('tool-pages/{toolKey}', [ContentController::class, 'toolPage'])
        ->name('api.v1.tool-pages.show');
    Route::get('affiliate-products', [ContentController::class, 'affiliateProducts'])
        ->name('api.v1.affiliate-products');

    // One call the Astro build makes to learn the shape of the site.
    Route::get('build-manifest', BuildManifestController::class)
        ->name('api.v1.build-manifest');
});
