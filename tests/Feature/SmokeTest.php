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

it('stores the users table as utf8mb4 with the spec collations', function () {
    $connection = DB::connection();
    $database = $connection->getDatabaseName();

    $charset = DB::selectOne(
        'SELECT DEFAULT_CHARACTER_SET_NAME AS cs, DEFAULT_COLLATION_NAME AS co
           FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
        [$database],
    );

    // Spec §6. A SQLite test run would never catch this.
    expect($charset->cs)->toBe('utf8mb4')
        ->and($charset->co)->toBe('utf8mb4_unicode_ci');
});

it('soft deletes users', function () {
    $user = User::factory()->create();

    expect($user->delete())->toBeTrue()
        ->and(User::withTrashed()->find($user->id))->not->toBeNull()
        ->and(User::find($user->id))->toBeNull();
});
