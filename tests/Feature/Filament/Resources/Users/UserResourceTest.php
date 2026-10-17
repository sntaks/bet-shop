<?php

use App\Enums\ActivityAction;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\DeleteAction;
use Livewire\Livewire;

it('forbids a cashier from the staff list', function () {
    $this->actingAs(User::factory()->cashier()->create())
        ->get(route('filament.shop.resources.staff.index'))
        ->assertForbidden();
});

it('adds a staff member with a role and records it', function () {
    $manager = User::factory()->manager()->create();
    $this->actingAs($manager);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Grace Wanjiku',
            'email' => 'Grace@Example.com',
            'password' => 'secret-password',
            'role' => RoleSeeder::CASHIER,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $staff = User::where('email', 'grace@example.com')->sole();
    expect($staff->hasRole(RoleSeeder::CASHIER))->toBeTrue();

    $log = ActivityLog::where('action', ActivityAction::StaffCreated)->sole();
    expect($log->user_id)->toBe($manager->id)
        ->and($log->subject_id)->toBe((string) $staff->id)
        ->and($log->details)->toMatchArray(['email' => 'grace@example.com', 'role' => RoleSeeder::CASHIER]);
});

it('refuses a role that does not exist', function () {
    $this->actingAs(User::factory()->manager()->create());

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'Grace', 'email' => 'grace@example.com', 'password' => 'secret-password', 'role' => 'owner'])
        ->call('create')
        ->assertHasFormErrors(['role']);

    expect(User::where('email', 'grace@example.com')->exists())->toBeFalse();
});

it('changes another staff member\'s role and active flag and records it without the password', function () {
    $this->actingAs(User::factory()->manager()->create());
    $cashier = User::factory()->cashier()->create();

    Livewire::test(EditUser::class, ['record' => $cashier->getRouteKey()])
        ->assertSchemaStateSet(['role' => RoleSeeder::CASHIER])
        ->fillForm(['role' => RoleSeeder::MANAGER, 'is_active' => false, 'password' => 'new-secret-password'])
        ->call('save')
        ->assertHasNoFormErrors();

    $cashier->refresh();
    expect($cashier->hasRole(RoleSeeder::MANAGER))->toBeTrue()
        ->and($cashier->hasRole(RoleSeeder::CASHIER))->toBeFalse()
        ->and($cashier->is_active)->toBeFalse();

    $details = ActivityLog::where('action', ActivityAction::StaffUpdated)->sole()->details;
    expect($details)->toMatchArray(['role' => RoleSeeder::MANAGER, 'is_active' => false, 'password_changed' => true])
        ->and(json_encode($details))->not->toContain('new-secret-password');
});

it('keeps a manager\'s own role and active flag even when the form is tampered with', function () {
    $manager = User::factory()->manager()->create();
    $this->actingAs($manager);

    Livewire::test(EditUser::class, ['record' => $manager->getRouteKey()])
        ->set('data.role', RoleSeeder::CASHIER)
        ->set('data.is_active', false)
        ->call('save');

    $manager->refresh();
    expect($manager->hasRole(RoleSeeder::MANAGER))->toBeTrue()
        ->and($manager->is_active)->toBeTrue();
});

it('offers no way to delete staff', function () {
    $this->actingAs(User::factory()->manager()->create());

    Livewire::test(EditUser::class, ['record' => User::factory()->cashier()->create()->getRouteKey()])
        ->assertActionDoesNotExist(DeleteAction::class);
});
