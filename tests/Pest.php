<?php

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
*/

uses(TestCase::class)->in('Feature');
