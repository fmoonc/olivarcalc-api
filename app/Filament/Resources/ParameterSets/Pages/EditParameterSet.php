<?php

namespace App\Filament\Resources\ParameterSets\Pages;

use App\Filament\Resources\ParameterSets\ParameterSetResource;
use App\Models\ParameterSet;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Editing a parameter set.
 *
 * No delete action, anywhere: spec §5.1 says a set is never deleted, because
 * saved calculations reference the version they were computed against.
 */
class EditParameterSet extends EditRecord
{
    protected static string $resource = ParameterSetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            /**
             * Spec §9.1: a diff view against the previous set. The owner needs
             * to see what changed before publishing, because publishing
             * changes what every calculator on the live site produces.
             */
            Action::make('diff')
                ->label('Comparar con el anterior')
                ->icon('heroicon-o-arrows-right-left')
                ->color('gray')
                ->modalHeading('Diferencias respecto al conjunto anterior')
                ->modalContent(fn (ParameterSet $record): View => view('filament.parameter-sets.diff', [
                    'set' => $record,
                    'rows' => $this->diffAgainstPrevious($record),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar'),
        ];
    }

    /**
     * Values that differ from the previous set of the same campaign.
     *
     * @return Collection<int, array{key: string, before: string, after: string}>
     */
    private function diffAgainstPrevious(ParameterSet $set): Collection
    {
        $previous = ParameterSet::query()
            ->where('campaign', $set->campaign)
            ->whereKeyNot($set->getKey())
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->first();

        if ($previous === null) {
            return new Collection;
        }

        $before = $previous->parameters()->pluck('value', 'key');
        $after = $set->parameters()->pluck('value', 'key');

        return $before->keys()
            ->merge($after->keys())
            ->unique()
            ->sort()
            ->map(fn (string $key): array => [
                'key' => $key,
                'before' => (string) ($before[$key] ?? '(no existía)'),
                'after' => (string) ($after[$key] ?? '(eliminado)'),
            ])
            ->reject(fn (array $pair): bool => $pair['before'] === $pair['after'])
            ->values();
    }
}
