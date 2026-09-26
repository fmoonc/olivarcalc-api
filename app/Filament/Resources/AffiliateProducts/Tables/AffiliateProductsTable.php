<?php

namespace App\Filament\Resources\AffiliateProducts\Tables;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AffiliateProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable()
                    ->width('70px'),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'vareadoras' => 'Vareadoras',
                        'mallas' => 'Mallas',
                        default => 'Poda',
                    }),

                TextColumn::make('price_text')
                    ->label('Precio')
                    ->placeholder('—'),

                IconColumn::make('active')
                    ->boolean()
                    ->label('Activo'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                CreateAction::make()->label('Nuevo producto'),
            ])
            ->recordActions([
                Action::make('visit')
                    ->label('Abrir enlace')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn ($record): string => $record->url)
                    ->openUrlInNewTab(),

                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
