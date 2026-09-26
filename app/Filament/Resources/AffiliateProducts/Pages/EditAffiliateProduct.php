<?php

namespace App\Filament\Resources\AffiliateProducts\Pages;

use App\Filament\Resources\AffiliateProducts\AffiliateProductResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAffiliateProduct extends EditRecord
{
    protected static string $resource = AffiliateProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
