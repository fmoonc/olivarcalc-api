<?php

namespace App\Filament\Resources\ParameterSets;

use App\Filament\Resources\ParameterSets\Pages\CreateParameterSet;
use App\Filament\Resources\ParameterSets\Pages\EditParameterSet;
use App\Filament\Resources\ParameterSets\Pages\ListParameterSets;
use App\Filament\Resources\ParameterSets\RelationManagers\ParametersRelationManager;
use App\Filament\Resources\ParameterSets\Schemas\ParameterSetForm;
use App\Filament\Resources\ParameterSets\Tables\ParameterSetsTable;
use App\Models\ParameterSet;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ParameterSetResource extends Resource
{
    protected static ?string $model = ParameterSet::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ParameterSetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ParameterSetsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ParametersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListParameterSets::route('/'),
            'create' => CreateParameterSet::route('/create'),
            'edit' => EditParameterSet::route('/{record}/edit'),
        ];
    }
}
