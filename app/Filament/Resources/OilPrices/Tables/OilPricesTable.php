<?php

namespace App\Filament\Resources\OilPrices\Tables;

use App\Events\OilPricesPublished;
use App\Models\OilPrice;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OilPricesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('week_start')
                    ->label('Semana')
                    ->date('d/m/Y')
                    ->sortable()
                    ->description(fn (OilPrice $record): string => 'hasta '.$record->week_end->format('d/m/Y')),

                TextColumn::make('quality')
                    ->label('Calidad')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'extra' => 'Extra',
                        'virgin' => 'Virgen',
                        default => 'Lampante',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'extra' => 'success',
                        'virgin' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('avg_price_eur_t')
                    ->label('€/t')
                    // money(currency, divideBy, locale, decimals). The stored
                    // value is already in euros per tonne, so divideBy is 1.
                    ->money('EUR', 1, 'es_ES', 2)
                    ->alignEnd(),

                // Derived on the server so no client divides by 1000 in float.
                TextColumn::make('avg_price_eur_kg')
                    ->label('€/kg')
                    ->state(fn (OilPrice $record): string => $record->avgPriceEurKg())
                    ->alignEnd(),

                TextColumn::make('volume_t')
                    ->label('Volumen')
                    ->formatStateUsing(fn (OilPrice $record): string => $record->volume_t === null
                        ? '—'
                        : number_format((float) $record->volume_t, 0, ',', '.').' t')
                    ->toggleable(),

                IconColumn::make('provisional')
                    ->boolean()
                    ->label('Provisional'),

                TextColumn::make('source')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'poolred' ? 'info' : 'gray'),
            ])
            ->defaultSort('week_start', 'desc')
            ->filters([
                SelectFilter::make('quality')
                    ->options([
                        'extra' => 'Extra',
                        'virgin' => 'Virgen',
                        'lampante' => 'Lampante',
                    ]),

                Filter::make('provisional')
                    ->label('Solo provisionales')
                    ->query(fn (Builder $query): Builder => $query->where('provisional', true)),

                Filter::make('unpublished')
                    ->label('Sin publicar')
                    ->query(fn (Builder $query): Builder => $query->whereNull('published_at')),
            ])
            ->headerActions([
                CreateAction::make()->label('Nueva semana'),
            ])
            ->recordActions([
                /**
                 * Spec §9.2: publishing a week is an explicit act, and it fires
                 * the event that makes price alerts and the site rebuild happen
                 * (spec §8). One call does both.
                 */
                Action::make('publish')
                    ->label('Publicar')
                    ->icon('heroicon-o-megaphone')
                    ->color('success')
                    ->visible(fn (OilPrice $record): bool => $record->published_at === null)
                    ->requiresConfirmation()
                    ->modalDescription('Publicar dispara la comprobación de alertas de precio y pide la reconstrucción del sitio.')
                    ->action(function (OilPrice $record): void {
                        $record->forceFill(['published_at' => now()])->save();

                        OilPricesPublished::dispatch($record);

                        Notification::make()
                            ->title('Semana publicada')
                            ->success()
                            ->send();
                    }),

                Action::make('unpublish')
                    ->label('Despublicar')
                    ->icon('heroicon-o-eye-slash')
                    ->color('gray')
                    ->visible(fn (OilPrice $record): bool => $record->published_at !== null)
                    ->requiresConfirmation()
                    ->action(function (OilPrice $record): void {
                        $record->forceFill(['published_at' => null])->save();

                        Notification::make()
                            ->title('Semana despublicada')
                            ->warning()
                            ->body('La web dejará de mostrarla en el siguiente build.')
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('publish')
                        ->label('Publicar seleccionadas')
                        ->icon('heroicon-o-megaphone')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $count = 0;

                            foreach ($records as $record) {
                                if ($record->published_at !== null) {
                                    continue;
                                }

                                $record->forceFill(['published_at' => now()])->save();
                                $count++;
                            }

                            // One event for the batch, so the rebuild and the
                            // alert check are not repeated per row.
                            if ($count > 0) {
                                OilPricesPublished::dispatch(null);
                            }

                            Notification::make()
                                ->title("{$count} semana(s) publicadas")
                                ->success()
                                ->send();
                        }),

                    BulkAction::make('mark_definitive')
                        ->label('Marcar como definitivas')
                        ->icon('heroicon-o-check-badge')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['provisional' => false])),

                    BulkAction::make('mark_provisional')
                        ->label('Marcar como provisionales')
                        ->icon('heroicon-o-clock')
                        ->action(fn ($records) => $records->each->update(['provisional' => true])),
                ]),
            ]);
    }
}
