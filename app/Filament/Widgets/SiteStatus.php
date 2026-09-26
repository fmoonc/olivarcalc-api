<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\OilPrices\OilPriceResource;
use App\Filament\Resources\ParameterSets\ParameterSetResource;
use App\Filament\Resources\SiteRebuildRequests\SiteRebuildRequestResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\OilPrice;
use App\Models\ParameterSet;
use App\Models\SiteRebuildRequest;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Operating figures (spec §9.9): registered users, the last published price
 * week, and the state of the site rebuilds.
 */
class SiteStatus extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $lastRebuild = SiteRebuildRequest::query()->latest('requested_at')->first();

        $lastPriceWeek = OilPrice::query()->published()->max('week_start');

        $liveSet = ParameterSet::publishedFor();

        // Explicit instanceof rather than `?->`: publishedFor() legitimately
        // returns null and this reads clearly.
        $liveVersion = $liveSet instanceof ParameterSet ? $liveSet->version : 'Ninguno';

        return [
            Stat::make('Usuarios registrados', (string) User::query()->count())
                ->description(User::query()->where('role', 'admin')->count().' administrador(es)')
                ->descriptionIcon('heroicon-o-users')
                ->color('info')
                ->url(UserResource::getUrl('index')),

            Stat::make('Conjunto vigente', $liveVersion)
                ->description('Un conjunto por campaña; el anterior queda archivado (spec §5.1)')
                ->descriptionIcon('heroicon-o-adjustments-horizontal')
                ->color($liveSet === null ? 'danger' : 'success')
                ->url(ParameterSetResource::getUrl('index')),

            Stat::make(
                'Última reconstrucción',
                $lastRebuild === null
                    ? 'Ninguna'
                    : $lastRebuild->requested_at->diffForHumans(),
            )
                ->description(match ($lastRebuild?->status) {
                    'dispatched' => 'Enviada a Cloudflare Pages',
                    'failed' => 'Falló: revisa el motivo',
                    'pending' => 'Pendiente de enviar',
                    default => 'Se registra al publicar contenido',
                })
                ->descriptionIcon($lastRebuild?->status === 'failed' ? 'heroicon-o-x-circle' : 'heroicon-o-arrow-path')
                ->color(match ($lastRebuild?->status) {
                    'dispatched' => 'success',
                    'failed' => 'danger',
                    'pending' => 'warning',
                    default => 'gray',
                })
                ->url(SiteRebuildRequestResource::getUrl('index')),

            Stat::make(
                'Semana de precios en el sitio',
                $lastPriceWeek === null ? 'Ninguna' : Carbon::parse($lastPriceWeek)->translatedFormat('d/m/Y'),
            )
                ->description('La web se construye con esta semana; la API no devuelve la anterior')
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color($lastPriceWeek === null ? 'danger' : 'info')
                ->url(OilPriceResource::getUrl('index')),
        ];
    }
}
