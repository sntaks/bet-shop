<?php

use App\Enums\ActivityAction;
use App\Filament\Pages\LoadPlayer;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\ResponseSequence;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

const LOADS_URL = 'https://kadi.test/api/v1/shop/loads';

beforeEach(function () {
    $this->cashier = User::factory()->cashier()->create();
    $this->actingAs($this->cashier);
});

/**
 * Fake KadiApi: the shop, an active player, and the given answer(s) to POST /shop/loads.
 */
function fakeLoadApi(mixed $loadResponse = null, ?array $player = null): void
{
    Http::fake([
        'kadi.test/api/v1/shop/me' => Http::response(['success' => true, 'data' => shopMe()]),
        'kadi.test/api/v1/shop/players/*' => Http::response(['success' => true, 'message' => 'Player found', 'data' => $player ?? ['player_no' => '12345671', 'name' => 'Grace K****', 'active' => true]]),
        'kadi.test/api/v1/shop/loads' => $loadResponse ?? Http::response(['success' => false], 500),
    ]);
}

function loadedResponse(int $amount = 1000, string $loadId = '5b1c7f2e-0f1d-4a2b-9a51-6f3e2c1d0b9a', string $message = 'Player loaded'): array
{
    return ['success' => true, 'message' => $message, 'data' => [
        'id' => 41,
        'load_id' => $loadId,
        'shop_no' => 'S101',
        'player' => ['player_no' => '12345671', 'name' => 'Grace K****', 'active' => true],
        'gross_amount' => $amount,
        'excise_amount' => $amount * 0.05,
        'net_amount' => $amount * 0.95,
        'status' => 'completed',
        'loaded_at' => '2026-10-17T10:15:00+03:00',
        'reversed_at' => null,
        'float_balance' => 11500.00,
    ]];
}

/**
 * The page at the confirm step for the given amount.
 */
function atConfirmStep(int $amount = 1000): Testable
{
    return Livewire::test(LoadPlayer::class)
        ->set('playerNoInput', '1234 5671')
        ->call('lookUp')
        ->set('amountInput', (string) $amount)
        ->call('review')
        ->assertSet('step', LoadPlayer::STEP_CONFIRM);
}

/**
 * @return list<string>
 */
function sentLoadKeys(): array
{
    return Http::recorded(fn (Request $request): bool => $request->url() === LOADS_URL)
        ->map(fn (array $pair): string => $pair[0]->header('Idempotency-Key')[0])
        ->values()
        ->all();
}

it('looks a player up by the number as typed and asks the cashier to confirm the masked name', function () {
    fakeLoadApi();

    Livewire::test(LoadPlayer::class)
        ->set('playerNoInput', '1234-5671')
        ->call('lookUp')
        ->assertHasNoErrors()
        ->assertSet('step', LoadPlayer::STEP_AMOUNT)
        ->assertSee('Confirm with the player: is this you?')
        ->assertSee('Grace K****');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://kadi.test/api/v1/shop/players/12345671');
});

it('tells the cashier what is wrong with a player number KadiApi refuses', function (int $status, string $code, string $shown) {
    Http::fake([
        'kadi.test/api/v1/shop/me' => Http::response(['success' => true, 'data' => shopMe()]),
        'kadi.test/api/v1/shop/players/*' => Http::response(['success' => false, 'code' => $code, 'message' => 'from KadiApi'], $status),
    ]);

    Livewire::test(LoadPlayer::class)
        ->set('playerNoInput', '1234 5678')
        ->call('lookUp')
        ->assertHasErrors(['playerNoInput'])
        ->assertSee($shown)
        ->assertSet('step', LoadPlayer::STEP_PLAYER);
})->with([
    'invalid number' => [422, 'invalid_player_no', 'That number is not valid. Check it with the player.'],
    'no such player' => [404, 'player_not_found', 'No player has that number. Check it with the player.'],
]);

it('refuses a number that is not 8 digits without asking KadiApi', function () {
    fakeLoadApi();

    Livewire::test(LoadPlayer::class)
        ->set('playerNoInput', '1234 567')
        ->call('lookUp')
        ->assertHasErrors(['playerNoInput']);

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/shop/players/'));
});

it('blocks loading an account that is not active', function () {
    fakeLoadApi(player: ['player_no' => '12345671', 'name' => 'Grace K****', 'active' => false]);

    Livewire::test(LoadPlayer::class)
        ->set('playerNoInput', '12345671')
        ->call('lookUp')
        ->assertSet('step', LoadPlayer::STEP_PLAYER)
        ->assertSee('This account is not active.');
});

it('tells the cashier to wait when the lookups are rate limited', function () {
    Http::fake([
        'kadi.test/api/v1/shop/me' => Http::response(['success' => true, 'data' => shopMe()]),
        'kadi.test/api/v1/shop/players/*' => Http::response(['message' => 'Too Many Attempts.'], 429, ['Retry-After' => '20']),
    ]);

    Livewire::test(LoadPlayer::class)
        ->set('playerNoInput', '12345671')
        ->call('lookUp')
        ->assertNotified('Too many requests, wait a moment')
        ->assertSet('step', LoadPlayer::STEP_PLAYER);
});

it('refuses an amount outside the load limits', function (string $amount) {
    fakeLoadApi();

    Livewire::test(LoadPlayer::class)
        ->set('playerNoInput', '12345671')
        ->call('lookUp')
        ->set('amountInput', $amount)
        ->call('review')
        ->assertHasErrors(['amountInput'])
        ->assertSet('step', LoadPlayer::STEP_AMOUNT);
})->with(['49', '20001', '100.50', '']);

it('loads the player, shows the receipt and records the load', function () {
    fakeLoadApi(Http::response(loadedResponse(), 201));

    atConfirmStep()
        ->assertSee('Take')
        ->assertSee("Loads can't be undone here. For a mistake, call Kadi Kings.", escape: false)
        ->call('loadPlayer')
        ->assertSet('step', LoadPlayer::STEP_DONE)
        ->assertSee('5b1c7f2e-0f1d-4a2b-9a51-6f3e2c1d0b9a')
        ->assertSee('KES 950.00')
        ->assertSee('Float left: KES 11,500.00');

    Http::assertSent(fn (Request $request): bool => $request->url() === LOADS_URL
        && $request->data() === ['player_no' => '12345671', 'amount' => 1000]
        && preg_match('/^[0-9a-f-]{36}$/', $request->header('Idempotency-Key')[0] ?? '') === 1);

    $log = ActivityLog::sole();
    expect($log->action)->toBe(ActivityAction::PlayerLoaded)
        ->and($log->user_id)->toBe($this->cashier->id)
        ->and($log->subject_id)->toBe('5b1c7f2e-0f1d-4a2b-9a51-6f3e2c1d0b9a')
        ->and($log->player_no)->toBe('12345671')
        ->and($log->amount)->toBe('1000.00')
        ->and($log->http_status)->toBe(201)
        ->and($log->idempotency_key)->toBe(sentLoadKeys()[0]);
});

it('shows each refusal and records it', function (int $status, array $body, string $shown) {
    fakeLoadApi(Http::response(['success' => false, ...$body], $status));

    atConfirmStep()
        ->call('loadPlayer')
        ->assertSet('step', LoadPlayer::STEP_CONFIRM)
        ->assertSet('idempotencyKey', null)
        ->assertSee($shown);

    $log = ActivityLog::sole();
    expect($log->action)->toBe(ActivityAction::LoadRefused)
        ->and($log->http_status)->toBe($status)
        ->and($log->api_code)->toBe($body['code'] ?? null);
})->with([
    'invalid player number' => [422, ['code' => 'invalid_player_no', 'message' => 'That is not a valid player number. Check it and try again.'], 'That is not a valid player number. Check it and try again.'],
    'no such player' => [404, ['code' => 'player_not_found', 'message' => 'No player has that number'], 'No player has that number'],
    'player inactive' => [409, ['code' => 'player_inactive', 'message' => "The player's account is not active"], "The player's account is not active"],
    'float too low' => [409, ['code' => 'insufficient_float', 'message' => 'The shop float is too low for this load', 'float_balance' => 300.00], 'The shop float is too low for this load. Float left: KES 300.00.'],
    'daily limit' => [422, ['code' => 'daily_limit_reached', 'message' => 'The player can be loaded at most KES 50000 a day at shops; KES 10000 is left today'], 'KES 10000 is left today'],
    'invalid amount' => [422, ['message' => 'The amount field must be at least 50.', 'errors' => ['amount' => ['The amount field must be at least 50.']]], 'The amount field must be at least 50.'],
    'key missing' => [400, ['code' => 'idempotency_key_required', 'message' => 'An Idempotency-Key header is required'], 'An Idempotency-Key header is required'],
]);

it('retries a load whose outcome is unknown with the same Idempotency-Key', function (Closure $firstAnswer) {
    fakeLoadApi($firstAnswer(Http::sequence())
        ->push(loadedResponse(message: 'Already loaded with this Idempotency-Key'), 200));

    $page = atConfirmStep()
        ->call('loadPlayer')
        ->assertSet('outcomeUnknown', true)
        ->assertSee('Not sure the load went through.')
        ->assertSee('Retry (safe)')
        ->assertDontSee('Change amount');

    expect(ActivityLog::sole()->action)->toBe(ActivityAction::LoadOutcomeUnknown);

    $page->call('changeAmount')
        ->assertSet('step', LoadPlayer::STEP_CONFIRM)
        ->call('loadPlayer')
        ->assertSet('step', LoadPlayer::STEP_DONE);

    $keys = sentLoadKeys();
    expect($keys)->toHaveCount(2)
        ->and($keys[1])->toBe($keys[0]);
})->with([
    'timeout' => [fn (ResponseSequence $sequence): ResponseSequence => $sequence->pushFailedConnection('cURL error 28: Operation timed out')],
    'gateway timeout' => [fn (ResponseSequence $sequence): ResponseSequence => $sequence->push('Gateway Timeout', 504)],
]);

it('uses a new Idempotency-Key for the next load', function () {
    fakeLoadApi(Http::sequence()
        ->push(loadedResponse(), 201)
        ->push(loadedResponse(loadId: 'second-load'), 201));

    atConfirmStep()
        ->call('loadPlayer')
        ->call('startOver')
        ->set('playerNoInput', '12345671')
        ->call('lookUp')
        ->set('amountInput', '1000')
        ->call('review')
        ->call('loadPlayer')
        ->assertSee('second-load');

    $keys = sentLoadKeys();
    expect($keys)->toHaveCount(2)
        ->and($keys[1])->not->toBe($keys[0]);
});

it('uses a new Idempotency-Key when the amount changes after a refusal', function () {
    fakeLoadApi(Http::sequence()
        ->push(['success' => false, 'code' => 'daily_limit_reached', 'message' => 'KES 500 is left today'], 422)
        ->push(loadedResponse(500), 201));

    atConfirmStep()
        ->call('loadPlayer')
        ->call('changeAmount')
        ->set('amountInput', '500')
        ->call('review')
        ->call('loadPlayer')
        ->assertSet('step', LoadPlayer::STEP_DONE);

    $keys = sentLoadKeys();
    expect($keys[1])->not->toBe($keys[0]);

    Http::assertSent(fn (Request $request): bool => $request->url() === LOADS_URL && $request->data()['amount'] === 500);
});

it('sends the desk to the blocked page when the shop is suspended mid-load', function () {
    fakeLoadApi(Http::response(['code' => 'shop_not_active', 'message' => 'This shop is suspended. Contact Kadi Kings.'], 403));

    atConfirmStep()
        ->call('loadPlayer')
        ->assertRedirect(route('filament.shop.blocked'));
});

it('never puts the API key in the page', function () {
    fakeLoadApi();

    $this->get(route('filament.shop.pages.load'))
        ->assertOk()
        ->assertDontSee(config('kadi.key'));
});
