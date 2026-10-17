<?php

use App\Models\User;
use Filament\Auth\Pages\Login;
use Livewire\Livewire;

it('redirects a guest to the panel login', function () {
    $this->get(route('filament.shop.pages.dashboard'))
        ->assertRedirect(route('filament.shop.auth.login'));
});

it('logs an active staff member in', function () {
    $cashier = User::factory()->cashier()->create();

    Livewire::test(Login::class)
        ->fillForm(['email' => $cashier->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($cashier);
});

it('refuses a deactivated staff member at login', function () {
    $user = User::factory()->cashier()->inactive()->create();

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();
});

it('refuses the staff of a switched-off shop at login', function () {
    $cashier = User::factory()->cashier()->create();
    $cashier->shop->update(['is_active' => false]);

    Livewire::test(Login::class)
        ->fillForm(['email' => $cashier->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();
});

it('refuses a shop user whose shop is missing', function () {
    $user = User::factory()->cashier()->create(['shop_id' => null]);

    $this->actingAs($user)
        ->get(route('filament.shop.resources.staff.index'))
        ->assertForbidden();
});

it('sends an admin to the shops instead of a shop dashboard, and keeps them off the counter', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('filament.shop.pages.dashboard'))
        ->assertRedirect(route('filament.shop.resources.shops.index'));

    $this->get(route('filament.shop.pages.load'))->assertForbidden();
    $this->get(route('filament.shop.pages.withdrawals'))->assertForbidden();
    $this->get(route('filament.shop.pages.settings'))->assertForbidden();
});

it('forbids a deactivated staff member who is still logged in', function () {
    $user = User::factory()->cashier()->inactive()->create();

    $this->actingAs($user)
        ->get(route('filament.shop.pages.dashboard'))
        ->assertForbidden();
});
