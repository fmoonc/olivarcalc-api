<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(120)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($set, ?string $state) => $set(
                        'slug',
                        Str::slug((string) $state),
                    )),

                TextInput::make('slug')
                    ->required()
                    ->maxLength(191)
                    ->unique(ignoreRecord: true)
                    // Lowercase, no accents, hyphens: the slug is in the URL
                    // and in the sitemap (spec §11).
                    ->rule('regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->helperText('Es la parte de la URL /guias/categoria/...'),

                Textarea::make('description')
                    ->rows(2)
                    ->maxLength(300),

                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->helperText('Menor número, antes en el menú.'),
            ])
            ->columns(2);
    }
}
