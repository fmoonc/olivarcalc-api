<?php

namespace App\Filament\Resources\ParameterSets\Pages;

use App\Filament\Resources\ParameterSets\ParameterSetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditParameterSet extends EditRecord
{
    protected static string $resource = ParameterSetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
