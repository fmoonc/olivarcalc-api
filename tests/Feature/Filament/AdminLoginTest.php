<?php

use App\Models\User;
use Filament\Auth\Pages\Login;
use Livewire\Livewire;

/**
 * A real login through the panel's own Livewire component.
 *
 * Worth having: `canAccessPanel()` decides who gets in, and the seeded admin
 * is the only way the owner reaches the parameter verification workflow. A
 * failure here means the whole admin panel is unreachable, and it would not
 * show up in any other test.
 */
describe('admin login', function () {
    it('logs in with the seeded admin credentials', function () {
        $admin = User::factory()->admin()->create([
            'email' => 'admin@olivarcalc.test',
            'password' => 'password',
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'admin@olivarcalc.test',
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        expect(auth()->check())->toBeTrue()
            ->and(auth()->user()?->id)->toBe($admin->id);
    });

    it('refuses a user who is not an admin, even with the right password', function () {
        User::factory()->create([
            'email' => 'grower@olivarcalc.test',
            'password' => 'password',
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'grower@olivarcalc.test',
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        expect(auth()->check())->toBeFalse();
    });

    it('refuses a wrong password', function () {
        User::factory()->admin()->create([
            'email' => 'admin@olivarcalc.test',
            'password' => 'password',
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'admin@olivarcalc.test',
                'password' => 'no-es-la-contrasena',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        expect(auth()->check())->toBeFalse();
    });
});
