<?php

namespace App\Filament\Resources\ParameterSets\Schemas;

use App\Enums\ParameterSetStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ParameterSetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('version')
                    ->required(),
                TextInput::make('campaign')
                    ->required(),
                Select::make('status')
                    ->options(ParameterSetStatus::class)
                    ->default('draft')
                    ->required(),
                Textarea::make('notes')
                    ->columnSpanFull(),
                DateTimePicker::make('published_at'),
                TextInput::make('published_by')
                    ->numeric(),
            ]);
    }
}
