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

it('forbids a deactivated staff member who is still logged in', function () {
    $user = User::factory()->cashier()->inactive()->create();

    $this->actingAs($user)
        ->get(route('filament.shop.pages.dashboard'))
        ->assertForbidden();
});
