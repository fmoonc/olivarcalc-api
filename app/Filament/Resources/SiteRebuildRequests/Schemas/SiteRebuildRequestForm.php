<?php

namespace App\Filament\Resources\SiteRebuildRequests\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SiteRebuildRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('reason')
                    ->required(),
                DateTimePicker::make('requested_at')
                    ->required(),
                DateTimePicker::make('dispatched_at'),
                Select::make('status')
                    ->options(['pending' => 'Pending', 'dispatched' => 'Dispatched', 'failed' => 'Failed'])
                    ->default('pending')
                    ->required(),
                Textarea::make('response')
                    ->columnSpanFull(),
            ]);
    }
}
