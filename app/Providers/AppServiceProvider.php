<?php

namespace App\Providers;

use App\Events\OilPricesPublished;
use App\Jobs\RequestSiteRebuild;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /**
         * Spec §8: publishing a week of prices asks Cloudflare Pages to rebuild
         * the static site, because the Astro build reads prices at build time
         * and fails if it cannot reach the API.
         *
         * `CheckPriceAlerts` is intentionally absent until Fase 5: a listener
         * that silently never runs is worse than no listener at all.
         */
        Event::listen(OilPricesPublished::class, function (): void {
            RequestSiteRebuild::dispatch('oil-prices-published');
        });
    }
}
