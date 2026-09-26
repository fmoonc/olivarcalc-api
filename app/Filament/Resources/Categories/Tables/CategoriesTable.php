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

                TextColumn::make('published_count')
                    ->label('Guías')
                    // A subquery alias, not a model method: Filament treats any
                    // method matching the column name as a relationship and
                    // throws "must return a relationship instance".
                    ->badge(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            // Published guides only: the public API filters drafts, so a raw
            // count would overstate what a visitor sees (spec §7.2).
            ->modifyQueryUsing(fn ($query) => $query->withCount([
                'articles as published_count' => fn ($q) => $q->where('status', 'published'),
            ]))
            ->headerActions([
                CreateAction::make()->label('Nueva categoría'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
