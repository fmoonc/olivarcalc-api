<?php

namespace App\Filament\Resources\ToolPages\Pages;

use App\Filament\Resources\ToolPages\ToolPageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditToolPage extends EditRecord
{
    protected static string $resource = ToolPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
