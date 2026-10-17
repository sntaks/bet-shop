<?php

use App\Enums\ActivityAction;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\ActivityLog;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\DeleteAction;
use Livewire\Livewire;

it('forbids a cashier from the staff list', function () {
    $this->actingAs(User::factory()->cashier()->create())
        ->get(route('filament.shop.resources.staff.index'))
        ->assertForbidden();
});

it('adds a staff member to the manager\'s own shop, whatever the form says, and records it', function () {
    $manager = User::factory()->manager()->create();
    $otherShop = Shop::factory()->create();
    $this->actingAs($manager);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Grace Wanjiku',
            'email' => 'Grace@Example.com',
            'password' => 'secret-password',
            'role' => RoleSeeder::CASHIER,
            'is_active' => true,
        ])
        ->set('data.shop_id', $otherShop->id)
        ->call('create')
        ->assertHasNoFormErrors();

    $staff = User::where('email', 'grace@example.com')->sole();
    expect($staff->hasRole(RoleSeeder::CASHIER))->toBeTrue()
        ->and($staff->shop_id)->toBe($manager->shop_id);

    $log = ActivityLog::where('action', ActivityAction::StaffCreated)->sole();
    expect($log->user_id)->toBe($manager->id)
        ->and($log->shop_id)->toBe($manager->shop_id)
        ->and($log->subject_id)->toBe((string) $staff->id)
        ->and($log->details)->toMatchArray(['email' => 'grace@example.com', 'role' => RoleSeeder::CASHIER]);
});

it('refuses a role a manager cannot give', function (string $role) {
    $this->actingAs(User::factory()->manager()->create());

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'Grace', 'email' => 'grace@example.com', 'password' => 'secret-password', 'role' => $role])
        ->call('create')
        ->assertHasFormErrors(['role']);

    expect(User::where('email', 'grace@example.com')->exists())->toBeFalse();
})->with(['admin', 'owner']);

it('lists only the manager\'s own shop\'s staff', function () {
    $manager = User::factory()->manager()->create();
    $ownCashier = User::factory()->cashier()->create(['shop_id' => $manager->shop_id]);
    $otherCashier = User::factory()->cashier()->create();
    $admin = User::factory()->admin()->create();
    $this->actingAs($manager);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$manager, $ownCashier])
        ->assertCanNotSeeTableRecords([$otherCashier, $admin]);
});

it('does not let a manager open another shop\'s staff member', function () {
    $this->actingAs(User::factory()->manager()->create());

    $this->get(route('filament.shop.resources.staff.edit', User::factory()->cashier()->create()))
        ->assertNotFound();
});

it('changes a staff member\'s role and active flag and records it without the password', function () {
    $manager = User::factory()->manager()->create();
    $cashier = User::factory()->cashier()->create(['shop_id' => $manager->shop_id]);
    $this->actingAs($manager);

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

it('keeps a manager\'s own role, shop and active flag even when the form is tampered with', function () {
    $manager = User::factory()->manager()->create();
    $shopId = $manager->shop_id;
    $this->actingAs($manager);

    Livewire::test(EditUser::class, ['record' => $manager->getRouteKey()])
        ->set('data.role', RoleSeeder::CASHIER)
        ->set('data.is_active', false)
        ->set('data.shop_id', Shop::factory()->create()->id)
        ->call('save');

    $manager->refresh();
    expect($manager->hasRole(RoleSeeder::MANAGER))->toBeTrue()
        ->and($manager->is_active)->toBeTrue()
        ->and($manager->shop_id)->toBe($shopId);
});

it('lets an admin add a manager to any shop', function () {
    $shop = Shop::factory()->create();
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Peter Mwangi',
            'email' => 'peter@example.com',
            'password' => 'secret-password',
            'role' => RoleSeeder::MANAGER,
            'shop_id' => $shop->id,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $manager = User::where('email', 'peter@example.com')->sole();
    expect($manager->shop_id)->toBe($shop->id)
        ->and($manager->hasRole(RoleSeeder::MANAGER))->toBeTrue()
        ->and(ActivityLog::sole()->shop_id)->toBe($shop->id);
});

it('requires a shop for shop staff and none for an admin', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'Peter', 'email' => 'peter@example.com', 'password' => 'secret-password', 'role' => RoleSeeder::CASHIER])
        ->call('create')
        ->assertHasFormErrors(['shop_id' => 'required']);

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'Second Admin', 'email' => 'admin2@example.com', 'password' => 'secret-password', 'role' => RoleSeeder::ADMIN])
        ->call('create')
        ->assertHasNoFormErrors();

    $admin = User::where('email', 'admin2@example.com')->sole();
    expect($admin->isAdmin())->toBeTrue()
        ->and($admin->shop_id)->toBeNull();
});

it('lets an admin see every shop\'s staff', function () {
    $admin = User::factory()->admin()->create();
    $cashiers = User::factory()->cashier()->count(2)->create();
    $this->actingAs($admin);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$admin, ...$cashiers])
        ->assertSee($cashiers[0]->shop->shop_no);
});

it('offers no way to delete staff', function () {
    $manager = User::factory()->manager()->create();
    $this->actingAs($manager);

    Livewire::test(EditUser::class, ['record' => User::factory()->cashier()->create(['shop_id' => $manager->shop_id])->getRouteKey()])
        ->assertActionDoesNotExist(DeleteAction::class);
});
