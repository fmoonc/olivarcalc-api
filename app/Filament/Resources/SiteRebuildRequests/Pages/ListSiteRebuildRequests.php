<?php

namespace App\Filament\Resources\SiteRebuildRequests\Pages;

use App\Filament\Resources\SiteRebuildRequests\SiteRebuildRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSiteRebuildRequests extends ListRecords
{
    protected static string $resource = SiteRebuildRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
