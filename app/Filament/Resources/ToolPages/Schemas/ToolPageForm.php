<?php

namespace App\Filament\Resources\ToolPages\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The SEO copy wrapped around a calculator (spec §9.4).
 *
 * The `tool_key` is fixed and not editable: the web looks the page up by that
 * exact key, and renaming it would 404 the page.
 */
class ToolPageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación')
                    ->schema([
                        Select::make('tool_key')
                            ->required()
                            ->options([
                                'settlement' => 'Liquidación de aceituna',
                                'yield_converter' => 'Conversión de rendimiento',
                                'cost_per_hectare' => 'Coste por hectárea',
                                'harvest_crew' => 'Coste de la cuadrilla',
                            ])
                            ->native(false)
                            ->disabled()
                            ->helperText('Identificador técnico. No se cambia: la web lo usa para encontrar esta página.'),

                        TextInput::make('title')
                            ->required()
                            ->maxLength(120),
                    ]),

                Section::make('Textos')
                    ->description('Se editan aquí, no en el repositorio web, para poder revisarlos sin desplegar.')
                    ->schema([
                        Textarea::make('intro_markdown')
                            ->label('Introducción')
                            ->rows(3)
                            ->helperText('El párrafo de encima de la calculadora.')
                            ->columnSpanFull(),

                        RichEditor::make('body_markdown')
                            ->label('Contenido SEO')
                            ->required()
                            ->toolbarButtons([
                                'bold', 'italic', 'link', 'h2', 'h3',
                                'bulletList', 'orderedList', 'blockquote', 'codeBlock',
                            ])
                            ->columnSpanFull()
                            ->helperText('Cómo se calcula, un ejemplo real, errores típicos y fuentes oficiales (spec §10.3).'),
                    ]),

                Section::make('Preguntas frecuentes')
                    ->schema([
                        Repeater::make('faq')
                            ->schema([
                                TextInput::make('question')->required()->maxLength(191),
                                Textarea::make('answer')->required()->rows(2),
                            ])
                            ->columns(1)
                            ->itemLabel(fn (array $state): string => (string) ($state['question'] ?? 'Pregunta'))
                            ->addActionLabel('Añadir pregunta')
                            ->columnSpanFull(),
                    ]),

                Section::make('SEO')
                    ->schema([
                        TextInput::make('meta_title')
                            ->maxLength(70)
                            ->live()
                            ->helperText(fn (?string $state): string => counter($state, 60))
                            ->columnSpanFull(),

                        Textarea::make('meta_description')
                            ->rows(2)
                            ->maxLength(180)
                            ->live()
                            ->helperText(fn (?string $state): string => counter($state, 155))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

/** Live counter for the §9.3 character limits. */
function counter(?string $state, int $limit): string
{
    $length = mb_strlen((string) $state);

    if ($length === 0) {
        return "Sin límite alcanzado · se rellenará automáticamente hasta {$limit} caracteres.";
    }

    return $length <= $limit
        ? sprintf('%d de %d caracteres.', $length, $limit)
        : sprintf('%d de %d: se recortará. %d de más.', $length, $limit, $length - $limit);
}
