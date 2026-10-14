<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exposes the health endpoint for Coolify', function () {
    $this->get('/up')->assertSuccessful();
});

it('boots the application and resolves the User model', function () {
    expect(app(User::class))->toBeInstanceOf(User::class);
});

it('casts the user role to the UserRole enum', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    expect($user->refresh()->role)->toBe(UserRole::Admin)
        ->and($user->isAdmin())->toBeTrue();
});

it('mounts the admin panel login route', function () {
    $this->get('/admin/login')->assertSuccessful();
});

it('stores the tables as utf8mb4 with the spec collation', function () {
    $database = DB::connection()->getDatabaseName();

    // Spec §6 names utf8mb4 / utf8mb4_unicode_ci. This asserts the TABLES, not the
    // database default, because the default collation of utf8mb4 changed in MySQL
    // 8.0 to utf8mb4_0900_ai_ci: the same migrations then produced a different
    // schema on CI than on a developer's machine. Every migration now declares the
    // collation, so the tables do not depend on the server default at all.
    $tables = DB::select(
        'SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.TABLES
           WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME',
        [$database, 'BASE TABLE'],
    );

    expect($tables)->not->toBeEmpty();

    foreach ($tables as $table) {
        expect($table->TABLE_COLLATION, $table->TABLE_NAME)
            ->toBe('utf8mb4_unicode_ci');
    }

    // The charset on the connection is still ours to set, and SQLite would not
    // catch it either.
    $charset = DB::selectOne(
        'SELECT DEFAULT_CHARACTER_SET_NAME AS cs FROM information_schema.SCHEMATA
           WHERE SCHEMA_NAME = ?',
        [$database],
    );

    expect($charset->cs)->toBe('utf8mb4');
});

it('soft deletes users', function () {
    $user = User::factory()->create();

    expect($user->delete())->toBeTrue()
        ->and(User::withTrashed()->find($user->id))->not->toBeNull()
        ->and(User::find($user->id))->toBeNull();
});
