<?php

namespace App\Filament\Resources\ToolPages\Pages;

use App\Filament\Resources\ToolPages\ToolPageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListToolPages extends ListRecords
{
    protected static string $resource = ToolPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
