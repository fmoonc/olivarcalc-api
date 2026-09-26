<?php

namespace App\Filament\Resources\SiteRebuildRequests\Tables;

use App\Jobs\RequestSiteRebuild;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SiteRebuildRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('requested_at')
                    ->label('Solicitado')
                    ->date('d/m/Y H:i')
                    ->sortable()
                    ->description(fn ($record): string => $record->dispatched_at
                        ? 'Enviado '.$record->dispatched_at->translatedFormat('d/m/Y H:i')
                        : 'Sin enviar'),

                TextColumn::make('reason')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'oil-prices-published' => 'Precios publicados',
                        'articles-published' => 'Guías publicadas',
                        'manual' => 'Manual',
                        default => $state,
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pendiente',
                        'dispatched' => 'Enviado',
                        default => 'Fallido',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'dispatched' => 'success',
                        default => 'danger',
                    }),

                TextColumn::make('response')
                    ->label('Respuesta')
                    ->limit(50)
                    ->placeholder('—')
                    ->tooltip(fn ($record): ?string => $record->response)
                    ->toggleable(),
            ])
            ->defaultSort('requested_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pendiente',
                        'dispatched' => 'Enviado',
                        'failed' => 'Fallido',
                    ]),
            ])
            ->headerActions([
                /**
                 * Spec §9.8: a "rebuild now" button. Goes through the same job
                 * as an automatic rebuild, so the debounce still applies and
                 * the history records it.
                 */
                Action::make('rebuild_now')
                    ->label('Recompilar ahora')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalDescription('Llama al deploy hook de Cloudflare Pages. Si hay una reconstrucción pendiente en los últimos 5 minutos, se absorberá en esa.')
                    ->action(function (): void {
                        RequestSiteRebuild::dispatch('manual');

                        Notification::make()
                            ->title('Reconstrucción solicitada')
                            ->success()
                            ->body('Si no hay una pendiente, se ha llamado al deploy hook. Revisa el historial en unos segundos.')
                            ->send();
                    }),
            ])
            ->recordActions([])
            ->emptyStateHeading('Sin reconstrucciones registradas')
            ->emptyStateDescription('Se registran aquí al publicar precios, guías o al pulsar «Recompilar ahora».');
    }
}
