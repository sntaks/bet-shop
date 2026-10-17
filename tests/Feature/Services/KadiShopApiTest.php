<?php

use App\Models\Shop;
use App\Models\User;
use App\Services\KadiResult;
use App\Services\KadiShopApi;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

const SHOP_API_KEY = 'shop-key-secret-x9Qa';

beforeEach(function () {
    config(['kadi.url' => 'https://kadi.test/api/v1/']);
    $this->shop = Shop::factory()->create(['api_key' => SHOP_API_KEY]);
});

function kadiApi(): KadiShopApi
{
    return KadiShopApi::forShop(test()->shop);
}

it('acts for the logged-in user\'s shop with that shop\'s key', function () {
    Http::fake(['kadi.test/*' => Http::response(['success' => true, 'data' => shopMe()])]);
    $otherShop = Shop::factory()->create(['api_key' => 'other-shop-key-0001']);

    $this->actingAs(User::factory()->cashier()->create(['shop_id' => $otherShop->id]));
    app(KadiShopApi::class)->me();

    Http::assertSent(fn (Request $request): bool => $request->header('X-API-KEY') === ['other-shop-key-0001']);
});

it('makes no call at all for a user with no shop', function () {
    $this->actingAs(User::factory()->admin()->create());

    expect(app(KadiShopApi::class)->me()->is(KadiResult::NO_SHOP))->toBeTrue();

    Http::assertNothingSent();
});

it('sends the shop key as JSON to the configured KadiApi', function () {
    Http::fake(['kadi.test/*' => Http::response(['success' => true, 'data' => ['shop_no' => 'S101', 'float_balance' => 12500.00]])]);

    $result = kadiApi()->me();

    expect($result->ok())->toBeTrue()
        ->and($result->data['shop_no'])->toBe('S101');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://kadi.test/api/v1/shop/me'
        && $request->header('X-API-KEY') === [SHOP_API_KEY]
        && $request->header('Accept') === ['application/json']
        && ! $request->hasHeader('Idempotency-Key'));
});

it('looks a player up by the bare digits when the number is typed with spaces or dashes', function (string $typed) {
    Http::fake(['kadi.test/*' => Http::response(['success' => true, 'data' => ['player_no' => '12345671', 'name' => 'Grace K****', 'active' => true]])]);

    expect(kadiApi()->lookupPlayer($typed)->data['name'])->toBe('Grace K****');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://kadi.test/api/v1/shop/players/12345671');
})->with(['1234 5671', '1234-5671', ' 12345671 ']);

it('returns the refusal code and message of a player lookup', function (int $status, string $code, string $message) {
    Http::fake(['kadi.test/*' => Http::response(['success' => false, 'code' => $code, 'message' => $message], $status)]);

    $result = kadiApi()->lookupPlayer('12345678');

    expect($result->ok())->toBeFalse()
        ->and($result->status)->toBe($status)
        ->and($result->is($code))->toBeTrue()
        ->and($result->message)->toBe($message)
        ->and($result->outcomeUnknown())->toBeFalse();
})->with([
    'invalid number' => [422, 'invalid_player_no', 'That is not a valid player number. Check it and try again.'],
    'no such player' => [404, 'player_not_found', 'No player has that number'],
]);

it('loads a player with the given Idempotency-Key and the normalised player number', function () {
    Http::fake(['kadi.test/*' => Http::response(['success' => true, 'message' => 'Player loaded', 'data' => ['load_id' => 'load-1', 'net_amount' => 950.00, 'float_balance' => 11500.00]], 201)]);

    $result = kadiApi()->load('1234 5671', 1000, 'key-1');

    expect($result->status)->toBe(201)
        ->and($result->data['load_id'])->toBe('load-1');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://kadi.test/api/v1/shop/loads'
        && $request->header('Idempotency-Key') === ['key-1']
        && $request->data() === ['player_no' => '12345671', 'amount' => 1000]);
});

it('exposes the extra fields a refusal carries', function () {
    Http::fake(['kadi.test/*' => Http::response(['success' => false, 'code' => 'insufficient_float', 'message' => 'The shop float is too low for this load', 'float_balance' => 300.00], 409)]);

    $result = kadiApi()->load('12345671', 1000, 'key-1');

    expect($result->is('insufficient_float'))->toBeTrue()
        ->and($result->field('float_balance'))->toEqual(300);
});

it('returns the field errors of an invalid amount', function () {
    Http::fake(['kadi.test/*' => Http::response(['message' => 'The amount field must be at least 50.', 'errors' => ['amount' => ['The amount field must be at least 50.']]], 422)]);

    expect(kadiApi()->load('12345671', 10, 'key-1')->errors)->toBe(['amount' => ['The amount field must be at least 50.']]);
});

it('reports an unknown outcome without retrying when KadiApi cannot be reached', function () {
    Http::fake(['kadi.test/*' => Http::failedConnection('cURL error 28: Operation timed out')]);

    $result = kadiApi()->load('12345671', 1000, 'key-1');

    expect($result->status)->toBeNull()
        ->and($result->is(KadiResult::UNREACHABLE))->toBeTrue()
        ->and($result->outcomeUnknown())->toBeTrue();

    Http::assertSentCount(1);
});

it('reports an unknown outcome for a server error even without a JSON body', function () {
    Http::fake(['kadi.test/*' => Http::response('<html>Bad Gateway</html>', 502)]);

    $result = kadiApi()->payWithdrawal('wd-1', '482913', 'key-1');

    expect($result->outcomeUnknown())->toBeTrue()
        ->and($result->body)->toBe([]);

    Http::assertSentCount(1);
});

it('recognises the responses that block the desk', function (int $status, array $body, string $check) {
    Http::fake(['kadi.test/*' => Http::response($body, $status)]);

    $result = kadiApi()->me();

    expect(collect(['blocksShop', 'invalidKey', 'notShopKey'])->filter(fn (string $method): bool => $result->{$method}())->values()->all())
        ->toBe([$check]);
})->with([
    'shop suspended or closed' => [403, ['code' => 'shop_not_active', 'message' => 'This shop is suspended. Contact Kadi Kings.'], 'blocksShop'],
    'key wrong or revoked' => [401, ['message' => 'Invalid or missing API key'], 'invalidKey'],
    'not a shop key' => [403, ['message' => 'A shop API key can only be used on shop routes'], 'notShopKey'],
]);

it('returns how long to wait when rate limited', function () {
    Http::fake(['kadi.test/*' => Http::response(['message' => 'Too Many Attempts.'], 429, ['Retry-After' => '30'])]);

    $result = kadiApi()->lookupPlayer('12345671');

    expect($result->rateLimited())->toBeTrue()
        ->and($result->retryAfter)->toBe(30);
});

it('lists loads for a date range, leaving out filters that are not set', function () {
    Http::fake(['kadi.test/*' => Http::response(['success' => true, 'data' => ['items' => [], 'pagination' => ['page' => 2, 'per_page' => 25, 'total' => 30, 'last_page' => 2]]])]);

    kadiApi()->loads(from: '2026-10-17', page: 2, perPage: 25);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://kadi.test/api/v1/shop/loads?from=2026-10-17&page=2&per_page=25');
});

it('lists pending withdrawals by default', function () {
    Http::fake(['kadi.test/*' => Http::response(['success' => true, 'data' => ['items' => [], 'pagination' => []]])]);

    kadiApi()->withdrawals();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://kadi.test/api/v1/shop/withdrawals?status=pending&page=1&per_page=50');
});

it('pays a withdrawal with the pickup code and returns the attempts left on a wrong code', function () {
    Http::fake(['kadi.test/*' => Http::response(['success' => false, 'code' => 'wrong_code', 'message' => 'That pickup code is wrong', 'attempts_left' => 1], 422)]);

    $result = kadiApi()->payWithdrawal('9d2f6c1e-3b7a-4c55-8e0d-2a1f4b6c7d8e', '482913', 'key-1');

    expect($result->is('wrong_code'))->toBeTrue()
        ->and($result->field('attempts_left'))->toBe(1);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://kadi.test/api/v1/shop/withdrawals/9d2f6c1e-3b7a-4c55-8e0d-2a1f4b6c7d8e/pay'
        && $request->header('Idempotency-Key') === ['key-1']
        && $request->data() === ['pickup_code' => '482913']);
});

it('rejects a withdrawal with the reason and the given Idempotency-Key', function () {
    Http::fake(['kadi.test/*' => Http::response(['success' => true, 'message' => "Withdrawal rejected; the money is back in the player's wallet", 'data' => ['status' => 'rejected']])]);

    expect(kadiApi()->rejectWithdrawal('wd-1', 'Not enough cash in the till', 'key-2')->data['status'])->toBe('rejected');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://kadi.test/api/v1/shop/withdrawals/wd-1/reject'
        && $request->header('Idempotency-Key') === ['key-2']
        && $request->data() === ['reason' => 'Not enough cash in the till']);
});

it('never writes the API key or a pickup code to the log', function () {
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
        $logged[] = $event->message.json_encode($event->context);
    });

    Http::fakeSequence('kadi.test/*')
        ->push(['code' => 'wrong_code', 'message' => 'That pickup code is wrong', 'attempts_left' => 4], 422)
        ->pushFailedConnection('cURL error 28: Operation timed out')
        ->push(['message' => 'Invalid or missing API key'], 401);

    kadiApi()->payWithdrawal('wd-1', '482913', 'key-1');
    kadiApi()->payWithdrawal('wd-1', '482913', 'key-1');
    kadiApi()->me();

    expect($logged)->toHaveCount(3)
        ->and(implode("\n", $logged))->not->toContain(SHOP_API_KEY)
        ->and(implode("\n", $logged))->not->toContain('482913');
});
