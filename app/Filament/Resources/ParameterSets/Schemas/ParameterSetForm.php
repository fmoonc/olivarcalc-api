<?php

namespace App\Filament\Resources\ParameterSets\Schemas;

use App\Models\ParameterSet;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * The set itself. Deliberately not editable: `status`, `published_at` and
 * `published_by` are set by PublishParameterSet, because publishing is the one
 * action that enforces the spec §5.2 rule. Letting someone flip the status
 * dropdown would route around that guard.
 */
class ParameterSetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación')
                    ->description('La versión identifica el conjunto en /api/v1/parameters/{version} y no debería cambiarse una vez publicada.')
                    ->schema([
                        TextInput::make('version')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->helperText('Por ejemplo 2026-27.1. Es la clave con la que se recovers un cálculo antiguo.')
                            ->disabled(fn ($record) => $record?->isPublished()),

                        Select::make('campaign')
                            ->required()
                            // Built from the convention of spec §5.2: the
                            // campaign runs 1 October to 30 September.
                            ->options(self::campaignOptions())
                            ->native(false)
                            ->disabled(fn ($record) => $record?->isPublished()),

                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Qué cambió respecto al conjunto anterior y por qué.'),
                    ]),

                Section::make('Publicación')
                    ->description('El estado lo fija la acción «Publicar», que impide publicar con parámetros sin verificar.')
                    ->schema([
                        Placeholder::make('publication_state')
                            ->label('Estado')
                            ->content(fn (?ParameterSet $record): string => match (true) {
                                $record === null => '—',
                                $record->published_at !== null => sprintf(
                                    '%s · publicado el %s',
                                    $record->status->getLabel(),
                                    $record->published_at->translatedFormat('d/m/Y H:i'),
                                ),
                                default => $record->status->getLabel(),
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /** @return array<string, string> */
    public static function campaignOptions(): array
    {
        $currentYear = (int) date('Y');
        // The current campaign plus the next two, so a year is already chosen
        // without the owner needing to type one.
        $options = [];

        for ($offset = -1; $offset <= 2; $offset++) {
            $start = $currentYear + $offset;
            $options[sprintf('%d-%02d', $start, ($start + 1) % 100)] = sprintf('%d-%02d', $start, ($start + 1) % 100);
        }

        return $options;
    }
}
