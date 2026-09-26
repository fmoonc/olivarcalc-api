<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot the full framework (DB, cache, queue). Unit tests stay
| framework-free. Keep it that way: if a test only needs a pure class, it
| belongs in Unit so it stays fast.
|
| RefreshDatabase is applied to every Feature test because phpunit.xml points
| at the `olivarcalc_test` MySQL database (ADR-0003). Without it, seeded rows
| leak between tests and unique keys collide.
|
*/

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
