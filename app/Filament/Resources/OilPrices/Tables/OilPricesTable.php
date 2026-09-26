<?php

namespace App\Filament\Resources\OilPrices\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OilPricesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('week_start')
                    ->date()
                    ->sortable(),
                TextColumn::make('week_end')
                    ->date()
                    ->sortable(),
                TextColumn::make('quality')
                    ->badge(),
                TextColumn::make('avg_price_eur_t')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('volume_t')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('provisional')
                    ->boolean(),
                TextColumn::make('source')
                    ->badge(),
                TextColumn::make('source_url')
                    ->searchable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
