<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Role;

it('creates an active manager with a lower-cased email', function () {
    $this->artisan('staff:create-manager', [
        '--name' => 'Peter Mwangi',
        '--email' => 'Peter@Example.com',
        '--password' => 'secret-password',
    ])->assertSuccessful();

    $manager = User::sole();

    expect($manager->email)->toBe('peter@example.com')
        ->and($manager->is_active)->toBeTrue()
        ->and($manager->hasRole(RoleSeeder::MANAGER))->toBeTrue()
        ->and($manager->hasPermissionTo('settings.manage'))->toBeTrue();
});

it('seeds the staff roles when they are missing', function () {
    Role::query()->delete();

    $this->artisan('staff:create-manager', [
        '--name' => 'Peter Mwangi',
        '--email' => 'peter@example.com',
        '--password' => 'secret-password',
    ])->assertSuccessful();

    expect(User::sole()->hasRole(RoleSeeder::MANAGER))->toBeTrue()
        ->and(Role::findByName(RoleSeeder::CASHIER)->hasPermissionTo('loads.create'))->toBeTrue();
});

it('refuses an email that is already taken', function () {
    User::factory()->create(['email' => 'peter@example.com']);

    $this->artisan('staff:create-manager', [
        '--name' => 'Peter Mwangi',
        '--email' => 'peter@example.com',
        '--password' => 'secret-password',
    ])
        ->expectsOutputToContain('The email has already been taken.')
        ->assertFailed();

    expect(User::count())->toBe(1);
});
