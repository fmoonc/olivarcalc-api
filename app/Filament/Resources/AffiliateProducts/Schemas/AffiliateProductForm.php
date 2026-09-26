<?php

namespace App\Filament\Resources\AffiliateProducts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AffiliateProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(191),

                Select::make('category')
                    ->required()
                    ->options(['vareadoras' => 'Vareadoras', 'mallas' => 'Mallas', 'poda' => 'Poda'])
                    ->native(false)
                    // Created from the seeded set, so keep the values fixed
                    // rather than accepting a typo that renders as no filter.
                    ->disabled()
                    ->dehydrateStateUsing(fn (?string $state): ?string => $state),

                TextInput::make('url')
                    ->label('URL de Amazon')
                    ->required()
                    ->url()
                    ->maxLength(191)
                    ->helperText('El sitio lo añade con rel="sponsored nofollow noopener" (spec §12).'),

                TextInput::make('price_text')
                    ->label('Precio orientativo')
                    ->maxLength(60)
                    ->placeholder('desde 1.200 €')
                    ->helperText('Texto libre: el precio de Amazon cambia y no queremos automatizarlo.'),

                Textarea::make('short_description')
                    ->required()
                    ->rows(2)
                    ->maxLength(300),

                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),

                Toggle::make('active')
                    ->label('Activo')
                    ->default(true)
                    ->helperText('Los productos inactivos no aparecen en la web ni en la API.'),
            ])
            ->columns(2);
    }
}
