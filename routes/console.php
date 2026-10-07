<?php

use Illuminate\Support\Facades\Schedule;

/**
 * Scheduled work (spec §8).
 *
 * POOLred runs its own import every twelve hours, so there is nothing to gain
 * from checking more often than that: a tighter schedule only adds requests to
 * someone else's API for data that has not changed.
 *
 * The command is inert unless POOLRED_IMPORT_ENABLED is set, so scheduling it
 * here does not mean it runs on a checkout that has no permission to.
 */
Schedule::command('poolred:import')
    // Twice a day, at midnight and midday. `everyTwelveHours()` does not exist
    // in Laravel, and cron is also clearer about *when* we touch someone else's
    // API than an interval would be.
    ->cron('0 0,12 * * *')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Importa el índice iPOOL de POOLred');

Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('queue:prune-failed --hours=48')->daily();
