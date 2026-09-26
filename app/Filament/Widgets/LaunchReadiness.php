<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\OilPrices\OilPriceResource;
use App\Filament\Resources\ParameterSets\Pages\ListParameterSets;
use App\Models\Article;
use App\Models\OilPrice;
use App\Models\ParameterSet;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Launch readiness (spec §9.9).
 *
 * The numbers here are the Fase 8 checklist rendered as data: unverified
 * parameters, indexable pages, and the last published price week. Each links
 * to the screen where the work is done, so the dashboard is a to-do list
 * rather than decoration.
 */
class LaunchReadiness extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $publishedSet = ParameterSet::withUnverifiedCount()
            ->where('status', 'published')
            ->latest('published_at')
            ->first();

        $unverified = $publishedSet === null ? 0 : $publishedSet->unverified_count;

        $indexablePages = Article::query()->published()->count()
            + DB::table('tool_pages')->count()
            + 1; // the home page

        $latestWeek = OilPrice::query()
            ->published()
            ->max('week_start');

        $latestPrice = $latestWeek === null
            ? null
            : OilPrice::query()->where('week_start', $latestWeek)->where('quality', 'virgin')->first();

        $unverifiedDescription = match (true) {
            $publishedSet === null => 'Publica un conjunto de parámetros en /admin',
            $unverified === 0 => 'Todos verificados',
            default => 'El sitio avisa al usuario hasta que se verifiquen',
        };

        $unverifiedColor = $publishedSet !== null && $unverified === 0 ? 'success' : 'danger';

        return [
            Stat::make(
                'Parámetros sin verificar',
                $publishedSet === null ? 'Sin conjunto publicado' : (string) $unverified,
            )
                ->description($unverifiedDescription)
                ->descriptionIcon($unverifiedColor === 'success'
                    ? 'heroicon-o-shield-check'
                    : 'heroicon-o-exclamation-triangle')
                ->color($unverifiedColor)
                ->url(ListParameterSets::getUrl()),

            Stat::make(
                'Páginas indexables',
                $indexablePages.' de 15',
            )
                ->description('Mínimo del checklist de lanzamiento (spec §14 Fase 8)')
                ->descriptionIcon($indexablePages >= 15 ? 'heroicon-o-check-badge' : 'heroicon-o-document-text')
                ->color($indexablePages >= 15 ? 'success' : 'warning')
                ->url(ArticleResource::getUrl('index')),

            Stat::make(
                'Última semana de precios',
                $latestWeek === null ? 'Ninguna' : Carbon::parse($latestWeek)->translatedFormat('d/m/Y'),
            )
                ->description($latestPrice === null
                    ? 'Publica una semana para que la gráfica tenga datos'
                    : 'Virgen: '.$latestPrice->avgPriceEurKg().' €/kg'.($latestPrice->provisional ? ' (provisional)' : ''))
                ->descriptionIcon($latestPrice?->provisional ? 'heroicon-o-clock' : 'heroicon-o-check-badge')
                ->color($latestPrice === null ? 'danger' : ($latestPrice->provisional ? 'warning' : 'success'))
                ->url(OilPriceResource::getUrl('index')),
        ];
    }
}
