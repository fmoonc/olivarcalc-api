<?php

namespace App\Filament\Resources\ToolPages\Tables;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ToolPagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tool_key')
                    ->label('Clave')
                    ->badge()
                    ->fontFamily('mono')
                    ->description(fn ($record): string => $record->getRouteKey()),

                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('faq')
                    ->label('FAQ')
                    ->state(fn ($record): int => count($record->faq ?? []))
                    ->suffix(' preguntas')
                    ->badge()
                    ->color(fn ($record): string => count($record->faq ?? []) > 0 ? 'success' : 'gray'),

                TextColumn::make('body_markdown')
                    ->label('Contenido SEO')
                    // A short page here means thin content, which is one of the
                    // launch blockers in spec §14 Fase 8.
                    ->state(fn ($record): string => number_format(mb_strlen((string) $record->body_markdown)).' caracteres')
                    ->color(fn ($record): string => mb_strlen((string) $record->body_markdown) < 500 ? 'warning' : 'gray'),

                TextColumn::make('updated_at')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('Ver en el sitio')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn ($record): string => rtrim(config('app.frontend_url', 'http://localhost:4321'), '/').'/herramientas/'.$record->getRouteKey())
                    ->openUrlInNewTab(),

                EditAction::make(),
            ])
            // The four tools are fixed by the spec, so there is no create action.
            ->headerActions([])
            ->paginated(false);
    }
}
