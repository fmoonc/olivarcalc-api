<?php

namespace App\Filament\Resources\SiteRebuildRequests;

use App\Filament\Resources\SiteRebuildRequests\Pages\CreateSiteRebuildRequest;
use App\Filament\Resources\SiteRebuildRequests\Pages\EditSiteRebuildRequest;
use App\Filament\Resources\SiteRebuildRequests\Pages\ListSiteRebuildRequests;
use App\Filament\Resources\SiteRebuildRequests\Schemas\SiteRebuildRequestForm;
use App\Filament\Resources\SiteRebuildRequests\Tables\SiteRebuildRequestsTable;
use App\Models\SiteRebuildRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SiteRebuildRequestResource extends Resource
{
    protected static ?string $model = SiteRebuildRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return SiteRebuildRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SiteRebuildRequestsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSiteRebuildRequests::route('/'),
            'create' => CreateSiteRebuildRequest::route('/create'),
            'edit' => EditSiteRebuildRequest::route('/{record}/edit'),
        ];
    }
}
