<?php

namespace App\Filament\Resources\AffiliateProducts\Pages;

use App\Filament\Resources\AffiliateProducts\AffiliateProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAffiliateProducts extends ListRecords
{
    protected static string $resource = AffiliateProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
