<?php

use App\Filament\Pages\Auth\Unlock;
use App\Http\Middleware\LockIdleScreen;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->cashier = User::factory()->cashier()->create();
    $this->actingAs($this->cashier);
});

it('locks the session when the lock screen is opened', function () {
    $this->get(route('filament.shop.unlock'))
        ->assertOk()
        ->assertSee("Enter {$this->cashier->name}'s password to carry on.")
        ->assertSessionHas(LockIdleScreen::LOCKED, true);
});

it('unlocks with the staff member\'s password and returns to the page they were on', function () {
    $this->freezeTime();
    session(['url.intended' => route('filament.shop.pages.settings')]);

    Livewire::test(Unlock::class)
        ->fillForm(['password' => 'password'])
        ->call('unlock')
        ->assertHasNoFormErrors()
        ->assertRedirect(route('filament.shop.pages.settings'));

    expect(session(LockIdleScreen::LOCKED))->toBeNull()
        ->and(session(LockIdleScreen::LAST_ACTIVITY))->toBe(now()->getTimestamp());
});

it('stays locked on a wrong password', function () {
    Livewire::test(Unlock::class)
        ->fillForm(['password' => 'not-the-password'])
        ->call('unlock')
        ->assertHasFormErrors(['password'])
        ->assertNoRedirect();

    expect(session(LockIdleScreen::LOCKED))->toBeTrue();
});

it('logs out to switch user', function () {
    Livewire::test(Unlock::class)
        ->callAction('switchUser')
        ->assertRedirect(route('filament.shop.auth.login'));

    $this->assertGuest();
});
