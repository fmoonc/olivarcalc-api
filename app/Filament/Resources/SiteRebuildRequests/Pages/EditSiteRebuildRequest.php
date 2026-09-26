<?php

namespace App\Filament\Resources\SiteRebuildRequests\Pages;

use App\Filament\Resources\SiteRebuildRequests\SiteRebuildRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSiteRebuildRequest extends EditRecord
{
    protected static string $resource = SiteRebuildRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
