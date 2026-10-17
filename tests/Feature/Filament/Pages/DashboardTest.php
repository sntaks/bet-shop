<?php

use App\Filament\Pages\Dashboard;
use App\Models\ShopSetting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->cashier()->create());
});

it('shows the float, how to buy more and today\'s loads', function () {
    Http::fake(['kadi.test/api/v1/shop/me' => Http::response(['success' => true, 'data' => shopMe()])]);

    $this->get(route('filament.shop.pages.dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Float', 'KES 12,500.00'])
        ->assertSee('To buy float, pay the Kadi Kings paybill with account')
        ->assertSee('SHOP-S101')
        ->assertSeeInOrder(['Loads', '4', 'Cash taken', 'KES 3,800.00'])
        ->assertDontSee('The float is low');
});

it('warns when the float is below the threshold in settings', function () {
    ShopSetting::forShop(auth()->user()->shop)->update(['low_float_threshold' => 15000]);
    Http::fake(['kadi.test/api/v1/shop/me' => Http::response(['success' => true, 'data' => shopMe()])]);

    $this->get(route('filament.shop.pages.dashboard'))
        ->assertSee('The float is low (under KES 15,000.00).');
});

it('sends the desk to the blocked page when the shop is suspended', function () {
    Http::fake(['kadi.test/api/v1/shop/me' => Http::response(['code' => 'shop_not_active', 'message' => 'This shop is suspended. Contact Kadi Kings.'], 403)]);

    Livewire::test(Dashboard::class)
        ->assertRedirect(route('filament.shop.blocked'));
});

it('says so when KadiApi cannot be reached and shows the float after a refresh', function () {
    Http::fake(['kadi.test/api/v1/shop/me' => Http::sequence()
        ->pushFailedConnection()
        ->push(['success' => true, 'data' => shopMe()])]);

    Livewire::test(Dashboard::class)
        ->assertSee('Kadi Kings could not be reached')
        ->callAction('refresh')
        ->assertSee('KES 12,500.00');
});

it('never puts the API key in the page', function () {
    Http::fake(['kadi.test/api/v1/shop/me' => Http::response(['success' => true, 'data' => shopMe()])]);

    $this->get(route('filament.shop.pages.dashboard'))
        ->assertOk()
        ->assertDontSee(auth()->user()->shop->api_key);
});
