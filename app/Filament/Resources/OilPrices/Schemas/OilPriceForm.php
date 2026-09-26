<?php

namespace App\Filament\Resources\OilPrices\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

/**
 * One week's prices (spec §9.2).
 *
 * A grower-facing week is a single concept, so the three qualities sit on one
 * screen rather than in three separate records to create.
 */
class OilPriceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Semana')
                    ->schema([
                        DatePicker::make('week_start')
                            ->required()
                            ->label('Semana del')
                            ->helperText('Un día cualquiera de esa semana. La tabla es única por semana y calidad.')
                            // week_end is derived; letting it drift would break
                            // the campaign summary ranges.
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($set, $state) => $set(
                                'week_end',
                                $state ? Carbon::parse($state)->addDays(6)->format('Y-m-d') : null,
                            )),

                        DatePicker::make('week_end')
                            ->required()
                            ->label('Hasta el')
                            // Always Sunday of that week.
                            ->disabled()
                            ->dehydrateStateUsing(fn ($state) => $state)
                            ->afterStateHydrated(fn ($component) => $component->state(
                                Carbon::parse($component->getState()?->toDateString() ?? 'now')
                                    ->addDays(6)
                                    ->format('Y-m-d'),
                            )),

                        Select::make('quality')
                            ->required()
                            ->options([
                                'extra' => 'Extra',
                                'virgin' => 'Virgen',
                                'lampante' => 'Lampante',
                            ])
                            ->native(false),

                        Select::make('source')
                            ->required()
                            ->options([
                                'manual' => 'Manual',
                                // Only selectable once the owner confirms the
                                // terms of use (spec §8).
                                'poolred' => 'Poolred',
                            ])
                            ->default('manual')
                            ->native(false)
                            ->helperText('El importador de Poolred está desactivado hasta que se confirmen sus condiciones de uso.'),

                        TextInput::make('source_url')
                            ->url()
                            ->maxLength(191)
                            ->helperText('Enlace a la fuente de la cifra.'),
                    ])
                    ->columns(2),

                Section::make('Cifras')
                    ->description('Los importes son decimales, nunca texto con separador de miles (spec §0.7).')
                    ->schema([
                        TextInput::make('avg_price_eur_t')
                            ->label('Precio medio por tonelada (€/t)')
                            ->required()
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->suffix(' €/t')
                            // The Spanish decimal comma the farmer types has to
                            // become a dot before it reaches MySQL.
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state === null
                                ? null
                                : str_replace(',', '.', $state)),

                        TextInput::make('volume_t')
                            ->label('Volumen (t)')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->suffix(' t')
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state === null
                                ? null
                                : str_replace(',', '.', $state)),

                        Toggle::make('provisional')
                            ->label('Provisional')
                            ->default(true)
                            ->helperText('El sitio marca los precios provisionales como pendientes de confirmar (spec §6).'),
                    ])
                    ->columns(2),
            ]);
    }
}
