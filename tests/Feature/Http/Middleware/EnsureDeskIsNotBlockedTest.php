<?php

use App\Models\User;
use App\Services\DeskBlock;
use App\Services\KadiResult;
use App\Services\KadiShopApi;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->cashier = User::factory()->cashier()->create();
    $this->actingAs($this->cashier);
});

it('shows the blocked page instead of every screen once KadiApi refuses the shop', function (int $status, array $body, string $message) {
    Http::fake(['kadi.test/*' => Http::response($body, $status)]);
    app(KadiShopApi::class)->lookupPlayer('12345671');

    $this->get(route('filament.shop.pages.loads'))
        ->assertRedirect(route('filament.shop.blocked'));

    $this->get(route('filament.shop.blocked'))
        ->assertOk()
        ->assertSee($message);
})->with([
    'shop suspended or closed' => [403, ['code' => 'shop_not_active', 'message' => 'This shop is suspended. Contact Kadi Kings.'], 'This shop is suspended. Contact Kadi Kings.'],
    'key wrong or revoked' => [401, ['message' => 'Invalid or missing API key'], "This desk's key is not valid. Contact Kadi Kings."],
    'not a shop key' => [403, ['message' => 'A shop API key can only be used on shop routes'], "This desk's key is not a shop key. Contact Kadi Kings to issue one."],
]);

it('lets every screen through again after a successful answer', function () {
    Http::fakeSequence('kadi.test/*')
        ->push(['code' => 'shop_not_active', 'message' => 'This shop is suspended. Contact Kadi Kings.'], 403)
        ->push(['success' => true, 'data' => shopMe()])
        ->whenEmpty(Http::response(['success' => true, 'data' => shopMe()]));

    app(KadiShopApi::class)->me();
    app(KadiShopApi::class)->me();

    $this->get(route('filament.shop.pages.dashboard'))
        ->assertOk();
});

it('does not block the desk for an ordinary refusal or an unreachable KadiApi', function (mixed $response) {
    Http::fake(['kadi.test/*' => $response]);
    app(KadiShopApi::class)->lookupPlayer('12345678');

    expect(DeskBlock::isBlocked($this->cashier->shop_id))->toBeFalse();
})->with([
    'player not found' => fn () => Http::response(['code' => 'player_not_found', 'message' => 'No player has that number'], 404),
    'connection failed' => fn () => Http::failedConnection(),
]);

it('blocks only the shop KadiApi refused', function () {
    $otherShopCashier = User::factory()->cashier()->create();
    DeskBlock::recordFrom(new KadiResult(403, 'shop_not_active', 'This shop is suspended. Contact Kadi Kings.'), $otherShopCashier->shop_id);
    Http::fake(['kadi.test/api/v1/shop/me' => Http::response(['success' => true, 'data' => shopMe()])]);

    $this->get(route('filament.shop.pages.dashboard'))
        ->assertOk();

    $this->actingAs($otherShopCashier)
        ->get(route('filament.shop.pages.dashboard'))
        ->assertRedirect(route('filament.shop.blocked'));
});
