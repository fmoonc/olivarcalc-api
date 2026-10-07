<?php

namespace App\Console\Commands;

use App\Actions\ImportPoolredIndex;
use Illuminate\Console\Command;
use Throwable;

/**
 * Import the POOLred index (spec §8).
 *
 * Disabled by default behind POOLRED_IMPORT_ENABLED, because it reaches out to a
 * third party. The owner obtained permission on 2026-10-07, but the flag stays as
 * the switch it was designed to be: a local checkout, a test run or a deploy with
 * no permission should not be calling someone else's API on a schedule.
 */
class PoolredImportCommand extends Command
{
    protected $signature = 'poolred:import
                            {--days=365 : Cuántos días de histórico traer}
                            {--force : Ejecuta aunque POOLRED_IMPORT_ENABLED esté a false}';

    protected $description = 'Importa el índice iPOOL de POOLred (precios semanales y variación de los cuatro índices)';

    public function handle(ImportPoolredIndex $importer): int
    {
        if (! config('services.poolred.enabled') && ! $this->option('force')) {
            $this->warn('Importador desactivado. Poner POOLRED_IMPORT_ENABLED=true para activarlo.');

            return self::SUCCESS;
        }

        $days = max(1, (int) $this->option('days'));

        $this->info("Importando el índice de POOLred, últimos {$days} días…");

        try {
            $result = $importer->handle($days);
        } catch (Throwable $exception) {
            // A failed import must leave the previous figures in place. The write
            // is transactional, so a partial import cannot half-apply.
            $this->error('No se ha podido importar: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%d semanas del índice %s entre %s y %s (%s).',
            $result['weeks'],
            config('services.poolred.index'),
            $result['from'],
            $result['to'],
            $result['finalized'] ? 'definitivo' : 'provisional, 95 % de confianza según POOLred',
        ));
        $this->info(sprintf('%d variaciones de índice guardadas.', $result['quotes']));

        return self::SUCCESS;
    }
}
