<?php

namespace App\Filament\Resources\OilPrices\Pages;

use App\Filament\Resources\OilPrices\OilPriceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOilPrice extends EditRecord
{
    protected static string $resource = OilPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
