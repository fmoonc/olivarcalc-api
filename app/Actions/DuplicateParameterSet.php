<?php

namespace App\Actions;

use App\Enums\ParameterSetStatus;
use App\Models\Parameter;
use App\Models\ParameterSet;

/**
 * Copy the live parameter set into a new draft (spec §5.1, §9.1).
 *
 * Editing a published set in place would change the meaning of every saved
 * calculation that referenced it: those rows store `parameter_set_version` and
 * expect the figures they were produced with to still be there. A correction
 * therefore becomes a new version, and the old one is archived rather than
 * deleted.
 */
final class DuplicateParameterSet
{
    public function handle(ParameterSet $set): ParameterSet
    {
        $copy = $set->replicate(['version', 'status', 'published_at', 'published_by']);
        $copy->version = $this->nextVersion($set->version);
        $copy->status = ParameterSetStatus::Draft;
        $copy->published_at = null;
        $copy->published_by = null;
        $copy->save();

        // `replicate()` drops the primary key but keeps the foreign one, so the
        // parent has to be reassigned. Note that `createMany()` does not accept
        // models, only arrays.
        $set->parameters->each(function (Parameter $parameter) use ($copy): void {
            $clone = $parameter->replicate();
            $clone->parameter_set_id = $copy->getKey();
            $clone->save();
        });

        return $copy;
    }

    /**
     * Bumps `2026-27.1` to `2026-27.2`, and `2026-27.2` to `2026-27.3`.
     *
     * The revision is read off the last digit rather than a counter column so
     * that the version a farmer sees in the URL matches the order of publication.
     */
    private function nextVersion(string $current): string
    {
        if (preg_match('/^(?<base>.+)\.(?<revision>\d+)$/', $current, $matches) === 1) {
            return $matches['base'].'.'.((int) $matches['revision'] + 1);
        }

        return $current.'.1';
    }
}
