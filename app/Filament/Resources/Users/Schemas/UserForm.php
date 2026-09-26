<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

/**
 * Users (spec §9.7).
 *
 * Deliberately read-only on the important parts: no password field is ever
 * rendered, and role changes are an explicit action on the list page rather
 * than an editable column.
 */
class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(120)
                    ->disabled(),

                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(191)
                    // Spec §9.7: never show passwords; an email is the only
                    // identifier the panel needs to identify someone.
                    ->disabled(),

                Select::make('role')
                    ->options(UserRole::class)
                    ->disabled()
                    ->helperText('El rol se cambia con una acción explícita en la lista.'),

                Select::make('province')
                    ->options([
                        'jaen' => 'Jaén', 'cordoba' => 'Córdoba', 'granada' => 'Granada',
                        'sevilla' => 'Sevilla', 'malaga' => 'Málaga', 'badajoz' => 'Badajoz',
                        'ciudad_real' => 'Ciudad Real', 'toledo' => 'Toledo',
                    ])
                    ->native(false)
                    ->disabled(),

                Toggle::make('marketing_opt_in')
                    ->label('Acepta recibir comunicaciones')
                    ->disabled(),

                DateTimePicker::make('email_verified_at')
                    ->label('Email verificado')
                    ->disabled()
                    ->seconds(false),

                DateTimePicker::make('last_login_at')
                    ->label('Último acceso')
                    ->disabled()
                    ->seconds(false),
            ])
            ->columns(2);
    }
}
