<?php

namespace App\Filament\Resources\OilPrices\Pages;

use App\Filament\Resources\OilPrices\OilPriceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOilPrices extends ListRecords
{
    protected static string $resource = OilPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
