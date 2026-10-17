<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Role;

it('creates an active admin with no shop and a lower-cased email', function () {
    $this->artisan('staff:create-admin', [
        '--name' => 'Vincent Kioko',
        '--email' => 'Vincent@Example.com',
        '--password' => 'secret-password',
    ])->assertSuccessful();

    $admin = User::sole();

    expect($admin->email)->toBe('vincent@example.com')
        ->and($admin->is_active)->toBeTrue()
        ->and($admin->shop_id)->toBeNull()
        ->and($admin->isAdmin())->toBeTrue()
        ->and($admin->hasPermissionTo('shops.manage'))->toBeTrue()
        ->and($admin->hasPermissionTo('loads.create'))->toBeFalse();
});

it('seeds the roles when they are missing', function () {
    Role::query()->delete();

    $this->artisan('staff:create-admin', [
        '--name' => 'Vincent Kioko',
        '--email' => 'vincent@example.com',
        '--password' => 'secret-password',
    ])->assertSuccessful();

    expect(User::sole()->isAdmin())->toBeTrue()
        ->and(Role::findByName(RoleSeeder::CASHIER)->hasPermissionTo('loads.create'))->toBeTrue();
});

it('refuses an email that is already taken', function () {
    User::factory()->create(['email' => 'vincent@example.com']);

    $this->artisan('staff:create-admin', [
        '--name' => 'Vincent Kioko',
        '--email' => 'vincent@example.com',
        '--password' => 'secret-password',
    ])
        ->expectsOutputToContain('The email has already been taken.')
        ->assertFailed();

    expect(User::count())->toBe(1);
});
