<?php

namespace App\Filament\Resources\ParameterSets\Tables;

use App\Filament\Resources\ParameterSets\ParameterSetResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ParameterSetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('version')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->fontFamily('mono'),

                TextColumn::make('campaign')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state): string => $state->getColor())
                    ->sortable(),

                /**
                 * The most useful number on this screen: how much work is left
                 * before the set can go live (spec §5.2). Backed by
                 * scopeWithUnverifiedCount, so it sorts without an N+1.
                 */
                TextColumn::make('unverified_count')
                    ->label('Sin verificar')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state === 0 => 'success',
                        $state <= 3 => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? 'Todo listo' : (string) $state)
                    ->sortable(),

                TextColumn::make('parameters_count')
                    ->label('Parámetros')
                    ->counts('parameters')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('published_at')
                    ->date('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('version')
            ->modifyQueryUsing(fn ($query) => $query->withUnverifiedCount())
            ->recordActions([
                Action::make('parameters')
                    ->label('Parámetros')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->url(fn ($record): string => ParameterSetResource::getUrl('edit', ['record' => $record])),

                EditAction::make(),
            ])
            // No delete action anywhere: spec §5.1 says sets are never deleted,
            // because saved calculations reference them.
            ->toolbarActions([]);
    }
}
