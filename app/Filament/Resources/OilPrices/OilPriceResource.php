<?php

namespace App\Filament\Resources\OilPrices;

use App\Filament\Resources\OilPrices\Pages\CreateOilPrice;
use App\Filament\Resources\OilPrices\Pages\EditOilPrice;
use App\Filament\Resources\OilPrices\Pages\ListOilPrices;
use App\Filament\Resources\OilPrices\Schemas\OilPriceForm;
use App\Filament\Resources\OilPrices\Tables\OilPricesTable;
use App\Models\OilPrice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OilPriceResource extends Resource
{
    protected static ?string $model = OilPrice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return OilPriceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OilPricesTable::configure($table);
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
            'index' => ListOilPrices::route('/'),
            'create' => CreateOilPrice::route('/create'),
            'edit' => EditOilPrice::route('/{record}/edit'),
        ];
    }
}
