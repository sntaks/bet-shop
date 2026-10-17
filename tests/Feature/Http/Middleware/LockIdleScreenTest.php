<?php

use App\Http\Middleware\LockIdleScreen;
use App\Models\ShopSetting;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->cashier = User::factory()->cashier()->create();
});

it('locks the screen after the idle minutes in settings and keeps the page to return to', function () {
    ShopSetting::forShop($this->cashier->shop)->update(['idle_lock_minutes' => 5]);

    $this->actingAs($this->cashier)
        ->withSession([LockIdleScreen::LAST_ACTIVITY => now()->subMinutes(5)->getTimestamp()])
        ->get(route('filament.shop.pages.settings'))
        ->assertRedirect(route('filament.shop.unlock'))
        ->assertSessionHas(LockIdleScreen::LOCKED, true)
        ->assertSessionHas('url.intended', route('filament.shop.pages.settings'));
});

it('keeps the screen open while the staff member is active', function () {
    $this->freezeTime();
    Http::fake(['kadi.test/api/v1/shop/me' => Http::response(['success' => true, 'data' => shopMe()])]);

    $this->actingAs($this->cashier)
        ->withSession([LockIdleScreen::LAST_ACTIVITY => now()->subMinutes(9)->getTimestamp()])
        ->get(route('filament.shop.pages.dashboard'))
        ->assertOk()
        ->assertSessionMissing(LockIdleScreen::LOCKED)
        ->assertSessionHas(LockIdleScreen::LAST_ACTIVITY, now()->getTimestamp());
});

it('sends a locked session to the unlock screen from any page', function () {
    $this->actingAs($this->cashier)
        ->withSession([LockIdleScreen::LOCKED => true])
        ->get(route('filament.shop.pages.dashboard'))
        ->assertRedirect(route('filament.shop.unlock'));
});

it('still lets a locked session log out', function () {
    $this->actingAs($this->cashier)
        ->withSession([LockIdleScreen::LOCKED => true])
        ->post(route('filament.shop.auth.logout'))
        ->assertRedirect();

    $this->assertGuest();
});
