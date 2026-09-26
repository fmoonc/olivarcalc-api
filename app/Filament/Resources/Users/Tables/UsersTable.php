<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('email')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (UserRole $state): string => $state === UserRole::Admin ? 'Admin' : 'Usuario')
                    ->color(fn (UserRole $state): string => $state === UserRole::Admin ? 'warning' : 'gray'),

                TextColumn::make('province')
                    ->formatStateUsing(fn (?string $state): ?string => $state === null ? null : match ($state) {
                        'jaen' => 'Jaén', 'cordoba' => 'Córdoba', 'granada' => 'Granada',
                        'sevilla' => 'Sevilla', 'malaga' => 'Málaga', 'badajoz' => 'Badajoz',
                        'ciudad_real' => 'Ciudad Real', 'toledo' => 'Toledo',
                        default => $state,
                    })
                    ->placeholder('—')
                    ->toggleable(),

                IconColumn::make('email_verified_at')
                    ->label('Verificado')
                    ->boolean(),

                TextColumn::make('last_login_at')
                    ->label('Último acceso')
                    ->since()
                    ->placeholder('Nunca')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Alta')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        UserRole::Admin->value => 'Admin',
                        UserRole::User->value => 'Usuario',
                    ]),
            ])
            ->recordActions([
                /**
                 * Spec §9.7: "desactivar", not delete. Soft deleting the record
                 * keeps their saved calculations and the audit trail intact
                 * (and RGPD erasure is a separate, explicit flow).
                 */
                Action::make('deactivate')
                    ->label('Desactivar')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn ($record): bool => $record->deleted_at === null)
                    ->requiresConfirmation()
                    ->modalDescription('La cuenta deja de poder entrar. Sus datos se conservan.')
                    ->action(fn ($record) => $record->delete()),

                Action::make('restore')
                    ->label('Reactivar')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->visible(fn ($record): bool => $record->deleted_at !== null)
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->restore()),

                Action::make('toggle_admin')
                    ->label(fn ($record): string => $record->isAdmin() ? 'Quitar admin' : 'Convertir en admin')
                    ->icon('heroicon-o-shield-check')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $record->forceFill([
                            'role' => $record->isAdmin() ? UserRole::User : UserRole::Admin,
                        ])->save();

                        Notification::make()
                            ->title($record->isAdmin() ? 'Ahora es administrador' : 'Ya no es administrador')
                            ->success()
                            ->send();
                    }),
            ])
            // No edit or delete: the panel identifies users, it does not manage
            // their credentials (spec §9.7).
            ->toolbarActions([]);
    }
}
