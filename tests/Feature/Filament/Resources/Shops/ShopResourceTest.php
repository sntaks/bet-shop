<?php

use App\Enums\ActivityAction;
use App\Filament\Resources\Shops\Pages\CreateShop;
use App\Filament\Resources\Shops\Pages\EditShop;
use App\Models\ActivityLog;
use App\Models\Shop;
use App\Models\User;
use App\Services\DeskBlock;
use App\Services\KadiResult;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

const NEW_SHOP_KEY = 'new-shop-key-abcd1234';

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('forbids a manager from the shops', function () {
    $this->actingAs(User::factory()->manager()->create())
        ->get(route('filament.shop.resources.shops.index'))
        ->assertForbidden();
});

it('adds a shop once KadiApi confirms the key is that shop\'s, and stores the key encrypted', function () {
    Http::fake(['kadi.test/api/v1/shop/me' => Http::response(['success' => true, 'data' => shopMe(['shop_no' => 'S101'])])]);

    Livewire::test(CreateShop::class)
        ->fillForm(['shop_no' => 's101', 'name' => 'Kariobangi Bet Shop', 'api_key' => NEW_SHOP_KEY, 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $shop = Shop::sole();
    expect($shop->shop_no)->toBe('S101')
        ->and($shop->api_key)->toBe(NEW_SHOP_KEY)
        ->and(DB::table('shops')->value('api_key'))->not->toContain(NEW_SHOP_KEY);

    Http::assertSent(fn (Request $request): bool => $request->header('X-API-KEY') === [NEW_SHOP_KEY]);

    $log = ActivityLog::sole();
    expect($log->action)->toBe(ActivityAction::ShopCreated)
        ->and($log->shop_id)->toBe($shop->id)
        ->and($log->toJson())->not->toContain(NEW_SHOP_KEY);
});

it('refuses a key KadiApi does not accept for that shop', function (Closure $answer, string $shown) {
    Http::fake(['kadi.test/api/v1/shop/me' => $answer()]);

    Livewire::test(CreateShop::class)
        ->fillForm(['shop_no' => 'S101', 'name' => 'Kariobangi Bet Shop', 'api_key' => NEW_SHOP_KEY])
        ->call('create')
        ->assertHasFormErrors(['api_key'])
        ->assertSee($shown);

    expect(Shop::count())->toBe(0);
})->with([
    'another shop\'s key' => [fn () => Http::response(['success' => true, 'data' => shopMe(['shop_no' => 'S202'])]), 'This key belongs to shop S202, not S101.'],
    'revoked key' => [fn () => Http::response(['message' => 'Invalid or missing API key'], 401), 'KadiApi does not accept this key.'],
    'not a shop key' => [fn () => Http::response(['message' => 'A shop API key can only be used on shop routes'], 403), 'This is not a shop key.'],
    'unreachable' => [fn () => Http::failedConnection(), 'KadiApi could not be reached to check this key.'],
]);

it('never shows a shop\'s key, only its last 4 characters', function () {
    $shop = Shop::factory()->create(['api_key' => 'secret-shop-key-wxyz']);

    $this->get(route('filament.shop.resources.shops.edit', $shop))
        ->assertOk()
        ->assertSee('…wxyz')
        ->assertDontSee('secret-shop-key-wxyz');
});

it('keeps the key when the field is left blank', function () {
    $shop = Shop::factory()->create(['api_key' => 'secret-shop-key-wxyz', 'name' => 'Old name']);

    Livewire::test(EditShop::class, ['record' => $shop->getRouteKey()])
        ->fillForm(['name' => 'New name'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($shop->refresh()->name)->toBe('New name')
        ->and($shop->api_key)->toBe('secret-shop-key-wxyz');

    Http::assertNothingSent();
});

it('replaces a key after checking it, and lifts the shop\'s block', function () {
    $shop = Shop::factory()->create(['shop_no' => 'S101']);
    DeskBlock::recordFrom(new KadiResult(401, null, 'Invalid or missing API key'), $shop->id);
    Http::fake(['kadi.test/api/v1/shop/me' => Http::response(['success' => true, 'data' => shopMe(['shop_no' => 'S101'])])]);

    Livewire::test(EditShop::class, ['record' => $shop->getRouteKey()])
        ->fillForm(['api_key' => NEW_SHOP_KEY])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($shop->refresh()->api_key)->toBe(NEW_SHOP_KEY)
        ->and(DeskBlock::isBlocked($shop->id))->toBeFalse()
        ->and(ActivityLog::sole()->details)->toBe(['key' => '…1234']);
});

it('stops a switched-off shop\'s staff from logging in', function () {
    $cashier = User::factory()->cashier()->create();
    $cashier->shop->update(['is_active' => false]);

    $this->actingAs($cashier)
        ->get(route('filament.shop.pages.loads'))
        ->assertForbidden();
});
