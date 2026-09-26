<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Filament\Resources\Categories\CategoryResource;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set as SetUtil;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * Guides (spec §9.3).
 *
 * The SEO limits of §9.3 are enforced with live counters rather than left as
 * advice: a meta title over 60 characters is truncated in Google, and the
 * owner should see that while typing, not after publishing.
 */
class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contenido')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(120)
                            ->live(onBlur: true)
                            // The slug follows the title, as a farmer would
                            // expect, but stays editable for edge cases.
                            ->afterStateUpdated(fn (SetUtil $set, ?string $state) => $set(
                                'slug',
                                Str::slug((string) $state),
                            )),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(191)
                            ->unique(ignoreRecord: true)
                            ->rule('regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->helperText('Minúsculas, sin acentos y con guiones (spec §11).')
                            // A published guide is already indexed: changing
                            // its slug would break the URL Google knows.
                            ->disabled(fn (Get $get): bool => $get('status') === 'published'),

                        Textarea::make('excerpt')
                            ->required()
                            ->rows(2)
                            ->maxLength(300)
                            ->helperText('Aparece en el listado y en el resumen de búsqueda.'),

                        RichEditor::make('body_markdown')
                            ->required()
                            // Toolbar limited to what the site actually renders.
                            ->toolbarButtons([
                                'bold', 'italic', 'strike', 'link',
                                'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'codeBlock',
                            ])
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('articles')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Clasificación')
                    ->schema([
                        Select::make('category_id')
                            ->required()
                            ->options(fn (): array => CategoryResource::getEloquentQuery()
                                ->orderBy('sort_order')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->native(false),

                        // Cross-links the guide to its calculator, in both
                        // directions (spec §11).
                        Select::make('related_tool')
                            ->label('Herramienta relacionada')
                            ->options([
                                'settlement' => 'Liquidación de aceituna',
                                'yield_converter' => 'Conversión de rendimiento',
                                'cost_per_hectare' => 'Coste por hectárea',
                                'harvest_crew' => 'Coste de la cuadrilla',
                            ])
                            ->native(false)
                            ->helperText('Enlaza la guía con la herramienta y viceversa.'),
                    ]),

                Section::make('Estado')
                    ->schema([
                        Select::make('status')
                            ->options(['draft' => 'Borrador', 'published' => 'Publicado'])
                            ->default('draft')
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (SetUtil $set, string $state): void {
                                // Publishing without a date would hide the
                                // guide: the API only serves published_at rows.
                                $set('published_at', $state === 'published' ? now() : null);
                            }),

                        DateTimePicker::make('published_at')
                            ->label('Fecha de publicación')
                            ->seconds(false),

                        DateTimePicker::make('reviewed_at')
                            ->label('Revisada por última vez')
                            ->seconds(false)
                            ->helperText('Spec §9.3: la revisión periódica sostiene la credibilidad.'),
                    ]),

                Section::make('SEO')
                    ->description('Límites de spec §9.3. El contador avisa antes de que Google recorte el texto.')
                    ->schema([
                        TextInput::make('meta_title')
                            ->maxLength(70)
                            ->live()
                            // Mirror the 60-character guidance without blocking
                            // the field outright; the counter is the warning.
                            ->helperText(fn (?string $state): string => self::counter($state, 60, 'título'))
                            ->columnSpanFull(),

                        Textarea::make('meta_description')
                            ->rows(2)
                            ->maxLength(180)
                            ->live()
                            ->helperText(fn (?string $state): string => self::counter($state, 155, 'descripción'))
                            ->columnSpanFull(),

                        /**
                         * A Repeater, not a KeyValue: the API emits
                         * `[{question, answer}]` and the web maps those exact
                         * fields into FAQPage JSON-LD (spec §11). A KeyValue
                         * would store `{question: answer}` and break that.
                         */
                        Repeater::make('faq')
                            ->label('Preguntas frecuentes')
                            ->schema([
                                TextInput::make('question')
                                    ->required()
                                    ->maxLength(191),
                                Textarea::make('answer')
                                    ->required()
                                    ->rows(2),
                            ])
                            ->columns(1)
                            ->reorderable()
                            ->itemLabel(fn (array $state): string => (string) ($state['question'] ?? 'Pregunta'))
                            ->addActionLabel('Añadir pregunta')
                            ->columnSpanFull()
                            ->helperText('Se convierten en el bloque FAQPage de los datos estructurados.'),
                    ]),
            ]);
    }

    private static function counter(?string $state, int $limit, string $what): string
    {
        $length = mb_strlen((string) $state);

        if ($length === 0) {
            return "Se rellenará con el $what si lo dejas vacío.";
        }

        if ($length <= $limit) {
            return sprintf('%d de %d caracteres.', $length, $limit);
        }

        return sprintf(
            '%d de %d caracteres: se recortará. %d de más.',
            $length,
            $limit,
            $length - $limit,
        );
    }
}
