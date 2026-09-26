<?php

namespace App\Filament\Resources\ToolPages;

use App\Filament\Resources\ToolPages\Pages\CreateToolPage;
use App\Filament\Resources\ToolPages\Pages\EditToolPage;
use App\Filament\Resources\ToolPages\Pages\ListToolPages;
use App\Filament\Resources\ToolPages\Schemas\ToolPageForm;
use App\Filament\Resources\ToolPages\Tables\ToolPagesTable;
use App\Models\ToolPage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ToolPageResource extends Resource
{
    protected static ?string $model = ToolPage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ToolPageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ToolPagesTable::configure($table);
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
            'index' => ListToolPages::route('/'),
            'create' => CreateToolPage::route('/create'),
            'edit' => EditToolPage::route('/{record}/edit'),
        ];
    }
}
