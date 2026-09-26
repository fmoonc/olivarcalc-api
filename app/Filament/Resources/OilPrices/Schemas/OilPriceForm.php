<?php

namespace App\Filament\Resources\OilPrices\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class OilPriceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('week_start')
                    ->required(),
                DatePicker::make('week_end')
                    ->required(),
                Select::make('quality')
                    ->options(['extra' => 'Extra', 'virgin' => 'Virgin', 'lampante' => 'Lampante'])
                    ->required(),
                TextInput::make('avg_price_eur_t')
                    ->required()
                    ->numeric(),
                TextInput::make('volume_t')
                    ->numeric(),
                Toggle::make('provisional')
                    ->required(),
                Select::make('source')
                    ->options(['poolred' => 'Poolred', 'manual' => 'Manual'])
                    ->default('manual')
                    ->required(),
                TextInput::make('source_url')
                    ->url(),
                DateTimePicker::make('published_at'),
            ]);
    }
}
