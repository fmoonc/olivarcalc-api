<?php

namespace App\Filament\Resources\AffiliateProducts;

use App\Filament\Resources\AffiliateProducts\Pages\CreateAffiliateProduct;
use App\Filament\Resources\AffiliateProducts\Pages\EditAffiliateProduct;
use App\Filament\Resources\AffiliateProducts\Pages\ListAffiliateProducts;
use App\Filament\Resources\AffiliateProducts\Schemas\AffiliateProductForm;
use App\Filament\Resources\AffiliateProducts\Tables\AffiliateProductsTable;
use App\Models\AffiliateProduct;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AffiliateProductResource extends Resource
{
    protected static ?string $model = AffiliateProduct::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return AffiliateProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliateProductsTable::configure($table);
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
            'index' => ListAffiliateProducts::route('/'),
            'create' => CreateAffiliateProduct::route('/create'),
            'edit' => EditAffiliateProduct::route('/{record}/edit'),
        ];
    }
}
