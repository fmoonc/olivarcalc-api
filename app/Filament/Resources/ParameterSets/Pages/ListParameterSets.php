<?php

namespace App\Filament\Resources\ParameterSets\Pages;

use App\Actions\PublishParameterSet;
use App\Enums\ParameterSetStatus;
use App\Filament\Resources\ParameterSets\ParameterSetResource;
use App\Models\ParameterSet;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ListParameterSets extends ListRecords
{
    protected static string $resource = ParameterSetResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('duplicate')
                ->label('Duplicar el vigente')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                // Spec §9.1: start a new draft from the live set instead of
                // editing it, so the published figures stay reproducible.
                ->action(function (): void {
                    $current = ParameterSet::publishedFor();

                    if ($current === null) {
                        Notification::make()
                            ->title('No hay un conjunto publicado que duplicar')
                            ->warning()
                            ->send();

                        return;
                    }

                    $copy = $current->replicate(['version', 'status', 'published_at', 'published_by']);
                    $copy->version = $this->nextVersion($current->version);
                    $copy->status = ParameterSetStatus::Draft;
                    $copy->save();

                    $copy->parameters()->createMany(
                        $current->parameters->map(fn ($p) => $p->replicate())->all()
                    );

                    ParameterSetResource::getUrl('edit', ['record' => $copy]);
                })
                ->requiresConfirmation()
                ->modalHeading('Duplicar el conjunto vigente')
                ->modalDescription('Se creará un borrador con los mismos valores. El conjunto publicado seguirá intacto.'),

            Action::make('create')
                ->label('Nuevo conjunto')
                ->icon('heroicon-o-plus')
                ->url(ParameterSetResource::getUrl('create')),
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getRowActions(): array
    {
        return [
            PublishAction::make('publish'),
            EditAction::make(),
        ];
    }

    /** "2026-27.1" -> "2026-27.2" */
    private function nextVersion(string $current): string
    {
        if (preg_match('/^(?<base>.+)\.(?<n>\d+)$/', $current, $m) === 1) {
            return $m['base'].'.'.((int) $m['n'] + 1);
        }

        return $current.'.1';
    }
}

/**
 * The publish action, kept in its own class so EditParameterSets can reuse it.
 *
 * The spec §5.2 rule lives in PublishParameterSet; this is only the UI around
 * it, including the explicit override.
 */
class PublishAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Publicar')
            ->icon('heroicon-o-megaphone')
            ->color('success')
            ->requiresConfirmation()
            ->action(function (ParameterSet $record): void {
                try {
                    (new PublishParameterSet)->handle($record, auth()->user());
                } catch (ValidationException $e) {
                    Notification::make()
                        ->title('No se puede publicar todavía')
                        ->body(collect($e->errors())->flatten()->implode(' '))
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title("«{$record->version}» publicado")
                    ->success()
                    ->send();
            })
            ->modalHeading('Publicar este conjunto de parámetros')
            ->modalDescription('Solo uno puede estar vigente por campaña; el anterior se archivará.')
            // The deliberate escape hatch from the verification rule: it has to
            // be a conscious choice, not a side effect.
            ->extraModalFooterActions([
                Action::make('publish_unverified')
                    ->label('Publicar con valores sin verificar')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (ParameterSet $record): void {
                        try {
                            (new PublishParameterSet)->handle($record, auth()->user(), force: true);
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('No se puede publicar')
                                ->body(collect($e->errors())->flatten()->implode(' '))
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title("«{$record->version}» publicado sin verificar")
                            ->warning()
                            ->body('El frontend mostrará el aviso de valores pendientes de verificación.')
                            ->persistent()
                            ->send();
                    }),
            ])
            ->visible(fn (Model $record): bool => ! ($record instanceof ParameterSet
                && $record->status === ParameterSetStatus::Published));
    }
}
