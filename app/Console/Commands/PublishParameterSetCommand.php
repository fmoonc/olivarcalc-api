<?php

namespace App\Console\Commands;

use App\Actions\PublishParameterSet;
use App\Models\ParameterSet;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Publish a parameter set from the terminal.
 *
 * The admin panel is the real way to do this (spec §9.1), but a developer
 * needs it too: `astro build` fails on purpose when nothing is published
 * (spec §10.1), and waiting for someone to click a button in Filament makes
 * the frontend unbuildable locally.
 *
 * The §5.2 verification rule still applies. Publishing an unverified set needs
 * the explicit --force, exactly like the panel's override, because the values
 * are orientative placeholders the owner has to confirm.
 */
class PublishParameterSetCommand extends Command
{
    protected $signature = 'parameters:publish
                            {version? : Version to publish; defaults to the only draft}
                            {--force : Publish even with unverified values (spec §5.2 override)}';

    protected $description = 'Publish a parameter set, enforcing the spec §5.2 verification rule';

    public function handle(PublishParameterSet $action): int
    {
        $set = $this->resolveSet();

        if ($set === null) {
            $this->components->error('No parameter set found to publish.');

            return self::FAILURE;
        }

        $unverified = $set->unverifiedKeys();

        if ($unverified !== [] && ! $this->option('force')) {
            $this->components->error(sprintf(
                'Set %s has %d unverified parameter(s): %s',
                $set->version,
                count($unverified),
                implode(', ', $unverified),
            ));
            $this->components->warn('Confirm each against its official source, then re-run with --force to publish anyway.');

            return self::FAILURE;
        }

        try {
            $action->handle($set, null, force: (bool) $this->option('force'));
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->components->error($message);
                }
            }

            return self::FAILURE;
        }

        $this->components->info(sprintf('Published %s (campaign %s).', $set->version, $set->campaign));

        if ($unverified !== []) {
            $this->components->warn('The frontend will show the "unverified values" warning until they are confirmed.');
        }

        return self::SUCCESS;
    }

    private function resolveSet(): ?ParameterSet
    {
        $version = $this->argument('version');

        if (is_string($version) && $version !== '') {
            return ParameterSet::with('parameters')->where('version', $version)->first();
        }

        $drafts = ParameterSet::with('parameters')
            ->where('status', 'draft')
            ->orderByDesc('created_at')
            ->get();

        if ($drafts->count() === 1) {
            return $drafts->first();
        }

        if ($drafts->isEmpty()) {
            $this->components->error('No drafts. Run `php artisan db:seed` first.');

            return null;
        }

        $this->components->error('Several drafts exist. Name the one to publish:');
        foreach ($drafts as $draft) {
            $this->components->twoColumnDetail($draft->version, $draft->campaign);
        }

        return null;
    }
}
