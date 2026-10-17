<?php

use App\Models\Shop;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('adds a shop with a key KadiApi confirms is that shop\'s', function () {
    Http::fake(['kadi.test/api/v1/shop/me' => Http::response(['success' => true, 'data' => shopMe(['shop_no' => 'S001'])])]);

    $this->artisan('shop:create', ['shop_no' => 's001', 'name' => 'Test Shop'])
        ->expectsQuestion('Shop API key', 'cli-shop-key-0001')
        ->assertSuccessful();

    $shop = Shop::sole();
    expect($shop->shop_no)->toBe('S001')
        ->and($shop->api_key)->toBe('cli-shop-key-0001');

    Http::assertSent(fn (Request $request): bool => $request->header('X-API-KEY') === ['cli-shop-key-0001']);
});

it('refuses a key that belongs to another shop', function () {
    Http::fake(['kadi.test/api/v1/shop/me' => Http::response(['success' => true, 'data' => shopMe(['shop_no' => 'S202'])])]);

    $this->artisan('shop:create', ['shop_no' => 'S001', 'name' => 'Test Shop'])
        ->expectsQuestion('Shop API key', 'cli-shop-key-0001')
        ->expectsOutputToContain('This key belongs to shop S202, not S001.')
        ->assertFailed();

    expect(Shop::count())->toBe(0);
});

it('refuses a shop number that is taken', function () {
    Shop::factory()->create(['shop_no' => 'S001']);

    $this->artisan('shop:create', ['shop_no' => 'S001', 'name' => 'Test Shop'])
        ->expectsOutputToContain('The shop no has already been taken.')
        ->assertFailed();

    Http::assertNothingSent();
});
