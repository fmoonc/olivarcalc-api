<?php

namespace App\Filament\Resources\Categories\Tables;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable()
                    ->width('80px'),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record): ?string => $record->slug),

                TextColumn::make('publishedArticlesCount')
                    ->label('Guías')
                    // A model method rather than counts(): the API only serves
                    // published guides, so a raw count would overstate it.
                    ->badge(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                CreateAction::make()->label('Nueva categoría'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
