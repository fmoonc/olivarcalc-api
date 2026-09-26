<?php

namespace App\Filament\Resources\Articles\Tables;

use App\Jobs\RequestSiteRebuild;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(60)
                    ->description(fn ($record): ?string => $record->slug),

                TextColumn::make('category.name')
                    ->label('Categoría')
                    ->badge()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'published' ? 'Publicado' : 'Borrador')
                    ->color(fn (string $state): string => $state === 'published' ? 'success' : 'gray'),

                TextColumn::make('related_tool')
                    ->label('Herramienta')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (?string $state): ?string => $state === null ? null : match ($state) {
                        'settlement' => 'Liquidación',
                        'yield_converter' => 'Rendimiento',
                        'cost_per_hectare' => 'Coste/ha',
                        'harvest_crew' => 'Cuadrilla',
                        default => $state,
                    })
                    ->placeholder('—'),

                /**
                 * E-E-A-T (spec §11): a guide nobody has re-read is worth
                 * less, so staleness is visible in the list.
                 */
                TextColumn::make('reviewed_at')
                    ->label('Revisada')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('Nunca')
                    ->color(fn ($record): string => match (true) {
                        $record->reviewed_at === null => 'danger',
                        $record->reviewed_at->lt(now()->subYear()) => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('published_at')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(['draft' => 'Borrador', 'published' => 'Publicado']),

                SelectFilter::make('category')
                    ->relationship('category', 'name'),
            ])
            ->headerActions([
                CreateAction::make()->label('Nueva guía'),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('Ver en el sitio')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    // Only meaningful once published: a draft has no page.
                    ->visible(fn ($record): bool => $record->status === 'published')
                    ->url(fn ($record): string => rtrim(config('app.frontend_url', 'http://localhost:4321'), '/').'/guias/'.$record->slug)
                    ->openUrlInNewTab(),

                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
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
                                $record->forceFill([
                                    'status' => 'published',
                                    'published_at' => $record->published_at ?? now(),
                                ])->save();
                                $count++;
                            }

                            // One rebuild for the batch (spec §8).
                            if ($count > 0) {
                                RequestSiteRebuild::dispatch('articles-published');
                            }

                            Notification::make()
                                ->title("{$count} guía(s) publicadas")
                                ->success()
                                ->send();
                        }),

                    BulkAction::make('draft')
                        ->label('Convertir en borrador')
                        ->icon('heroicon-o-pencil-square')
                        ->action(fn ($records) => $records->each->update(['status' => 'draft'])),

                    BulkAction::make('mark_reviewed')
                        ->label('Marcar como revisadas')
                        ->icon('heroicon-o-check-badge')
                        ->action(fn ($records) => $records->each->update(['reviewed_at' => now()])),

                    BulkAction::make('delete')
                        ->label('Eliminar')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        // Soft delete, so a guide that was indexed can be
                        // restored if it was removed by mistake.
                        ->action(fn ($records) => $records->each->delete()),
                ]),
            ])
            ->emptyStateHeading('Aún no hay guías')
            ->emptyStateDescription('Crea la primera para que aparezca en la web.');
    }
}
