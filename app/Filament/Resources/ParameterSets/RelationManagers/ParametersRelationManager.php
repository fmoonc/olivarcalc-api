<?php

namespace App\Filament\Resources\ParameterSets\RelationManagers;

use App\Enums\ParameterValueType;
use App\Models\Parameter;
use App\Models\ParameterSet;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Where official figures actually get confirmed.
 *
 * Spec §9.1: edit the values, mark them verified (storing verified_at and
 * requiring a source URL), and diff against the previous set. Spec §0.2 is why
 * `verified` starts false and why a source is mandatory before flipping it.
 */
class ParametersRelationManager extends RelationManager
{
    protected static string $relationship = 'parameters';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->required()
                    ->maxLength(191)
                    ->unique(ignoreRecord: true)
                    // Once published, a key is a contract: the web reads
                    // params['oil.density_kg_per_l'] by name.
                    ->disabled(fn (): bool => $this->isPublished())
                    ->helperText('Clave estable, por ejemplo oil.density_kg_per_l. Sin espacios.'),

                TextInput::make('value')
                    ->required()
                    ->maxLength(2000)
                    // DECIMAL columns are decimal strings (spec §0.7). Accept the
                    // Spanish comma and normalise it on save.
                    ->rule('regex:/^-?\d+([.,]\d+)?$/')
                    ->dehydrateStateUsing(fn (string $state): string => str_replace(',', '.', trim($state)))
                    ->helperText('Valor decimal. Se acepta la coma decimal; se guarda con punto.'),

                Select::make('value_type')
                    ->options(ParameterValueType::class)
                    ->required()
                    ->native(false),

                TextInput::make('unit')
                    ->maxLength(30)
                    ->placeholder('%'),

                Toggle::make('verified')
                    ->label('Verificado contra la fuente oficial')
                    // The publish rule of §5.2 consults this flag, so make the
                    // consequence obvious rather than leaving it as a guess.
                    ->helperText('Mientras no esté verificado, la web muestra un aviso al usuario y no se puede publicar el conjunto.'),

                TextInput::make('source_label')
                    ->maxLength(191)
                    ->placeholder('Ley 37/1992 del IVA, art. 130')
                    ->required(fn (callable $get): bool => (bool) $get('verified')),

                TextInput::make('source_url')
                    ->url()
                    ->maxLength(191)
                    ->required(fn (callable $get): bool => (bool) $get('verified'))
                    ->helperText('Obligatorio para marcar como verificado: sin enlace el valor no es trazable.'),

                Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->wrap(),

                TextColumn::make('value')
                    ->fontFamily('mono')
                    ->searchable(),

                TextColumn::make('unit')
                    ->placeholder('—'),

                IconColumn::make('verified')
                    ->boolean()
                    // Green ticks hide the one thing that matters here, so the
                    // unverified ones get a real label.
                    ->getStateUsing(fn (Parameter $record): bool => $record->verified),

                TextColumn::make('verified_at')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('source_label')
                    ->limit(40)
                    ->placeholder('Sin fuente')
                    ->tooltip(fn (Parameter $record): ?string => $record->source_url),
            ])
            ->defaultSort('key')
            ->filters([
                Filter::make('unverified')
                    ->label('Solo sin verificar')
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->where('verified', false)),

                SelectFilter::make('value_type')
                    ->options(ParameterValueType::class),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Añadir parámetro')
                    // The calculators read fixed keys; adding a new one only
                    // makes sense alongside a code change.
                    ->disabled(fn (): bool => $this->isPublished()),
            ])
            ->recordActions([
                // One-click confirm for the common case: the value was checked
                // against the source linked in the row.
                Action::make('mark_verified')
                    ->label('Marcar verificado')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->visible(fn (Parameter $record): bool => ! $record->verified)
                    ->requiresConfirmation()
                    ->modalHeading('¿Confirmas este valor?')
                    ->modalDescription('Queda registrado quién lo verificó y cuándo. Solo marca aquí lo que hayas contrastado con la fuente oficial.')
                    ->action(function (Parameter $record): void {
                        $url = $record->source_url;

                        if ($url === null || $url === '') {
                            Notification::make()
                                ->title('Falta la URL de la fuente')
                                ->body('Un valor verificado necesita un enlace donde se pueda consultar (spec §9).')
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        }

                        $record->markVerified($url);

                        Notification::make()
                            ->title('Parámetro marcado como verificado')
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (): bool => ! $this->isPublished()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('mark_verified')
                        ->label('Marcar verificados')
                        ->icon('heroicon-o-shield-check')
                        ->color('success')
                        ->requiresConfirmation()
                        // Refuses the ones without a source rather than
                        // silently verifying an untraceable value.
                        ->action(function (Collection $records): void {
                            $marked = 0;
                            $skipped = [];

                            foreach ($records as $parameter) {
                                if ($parameter->source_url === null || $parameter->source_url === '') {
                                    $skipped[] = $parameter->key;

                                    continue;
                                }

                                $parameter->markVerified($parameter->source_url);
                                $marked++;
                            }

                            $notification = Notification::make()
                                ->title("{$marked} parámetro(s) marcados como verificados")
                                ->success();

                            if ($skipped !== []) {
                                $notification->warning()
                                    ->body('Sin URL de fuente, se saltaron: '.implode(', ', $skipped));
                            }

                            $notification->send();
                        }),
                ]),
            ]);
    }

    private function isPublished(): bool
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof ParameterSet && $owner->isPublished();
    }
}
