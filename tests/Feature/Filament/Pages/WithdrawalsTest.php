<?php

use App\Enums\ActivityAction;
use App\Filament\Pages\Withdrawals;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\ResponseSequence;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

const WITHDRAWAL_ID = '9d2f6c1e-3b7a-4c55-8e0d-2a1f4b6c7d8e';
const PAY_URL = 'https://kadi.test/api/v1/shop/withdrawals/'.WITHDRAWAL_ID.'/pay';
const REJECT_URL = 'https://kadi.test/api/v1/shop/withdrawals/'.WITHDRAWAL_ID.'/reject';

beforeEach(function () {
    $this->cashier = User::factory()->cashier()->create();
    $this->actingAs($this->cashier);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function pendingWithdrawal(array $overrides = []): array
{
    return array_replace_recursive([
        'withdrawal_id' => WITHDRAWAL_ID,
        'shop_no' => 'S101',
        'shop_name' => 'Kariobangi Bet Shop',
        'amount' => 1000.00,
        'status' => 'pending',
        'expires_at' => '2026-10-18T10:00:00+03:00',
        'requested_at' => '2026-10-17T10:00:00+03:00',
        'closed_at' => null,
        'player' => ['player_no' => '12345671', 'name' => 'Grace W****'],
        'failed_attempts' => 0,
    ], $overrides);
}

/**
 * Fake KadiApi: the shop, the list, and the given answers to pay and reject.
 *
 * @param  list<array<string, mixed>>|null  $items
 */
function fakeWithdrawalsApi(?array $items = null, mixed $pay = null, mixed $reject = null): void
{
    $items ??= [pendingWithdrawal()];

    Http::fake([
        'kadi.test/api/v1/shop/me' => Http::response(['success' => true, 'data' => shopMe()]),
        'kadi.test/api/v1/shop/withdrawals?*' => Http::response(['success' => true, 'data' => [
            'items' => $items,
            'pagination' => ['page' => 1, 'per_page' => 50, 'total' => count($items), 'last_page' => 1],
        ]]),
        PAY_URL => $pay ?? Http::response([], 500),
        REJECT_URL => $reject ?? Http::response([], 500),
    ]);
}

function paidResponse(): array
{
    return ['success' => true, 'message' => 'Paid. Hand the player KES 1,000', 'data' => [
        ...pendingWithdrawal(['status' => 'paid', 'closed_at' => '2026-10-17T11:00:00+03:00']),
        'float_balance' => 13500.00,
    ]];
}

function payingWithCode(string $code = '482913'): Testable
{
    return Livewire::test(Withdrawals::class)
        ->callTableAction('pay', WITHDRAWAL_ID)
        ->assertSet('paying.withdrawal_id', WITHDRAWAL_ID)
        ->set('pickupCode', $code);
}

/**
 * @return list<array{key: string, code: string}>
 */
function sentPayments(): array
{
    return Http::recorded(fn (Request $request): bool => $request->url() === PAY_URL)
        ->map(fn (array $pair): array => ['key' => $pair[0]->header('Idempotency-Key')[0], 'code' => $pair[0]->data()['pickup_code']])
        ->values()
        ->all();
}

it('lists the pending withdrawals and counts them for the navigation badge', function () {
    fakeWithdrawalsApi([pendingWithdrawal(), pendingWithdrawal(['withdrawal_id' => 'second', 'player' => ['name' => 'Peter M****']])]);

    Livewire::test(Withdrawals::class)
        ->assertSee('Grace W****')
        ->assertSee('1234 5671')
        ->assertSee('KES 1,000.00')
        ->assertSee('Peter M****');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://kadi.test/api/v1/shop/withdrawals?status=pending&page=1&per_page=50');

    expect(Withdrawals::getNavigationBadge())->toBe('2');
});

it('shows the other statuses on their tabs', function () {
    fakeWithdrawalsApi([pendingWithdrawal(['status' => 'rejected', 'closed_at' => '2026-10-17T12:00:00+03:00', 'close_reason' => 'Not enough cash in the till'])]);

    Livewire::test(Withdrawals::class)
        ->call('showStatus', 'rejected')
        ->assertSee('Not enough cash in the till')
        ->assertTableActionHidden('pay', WITHDRAWAL_ID);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'status=rejected'));
});

it('finds a request by player number among the newest 200', function () {
    fakeWithdrawalsApi([pendingWithdrawal(), pendingWithdrawal(['withdrawal_id' => 'other', 'player' => ['player_no' => '87654321', 'name' => 'Peter M****']])]);

    Livewire::test(Withdrawals::class)
        ->searchTable('1234 5671')
        ->assertSee('Grace W****')
        ->assertDontSee('Peter M****');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://kadi.test/api/v1/shop/withdrawals?status=pending&page=1&per_page='.Withdrawals::SEARCH_WINDOW);
});

it('pays with the pickup code and only then says to hand over the cash', function () {
    fakeWithdrawalsApi(pay: Http::response(paidResponse()));

    payingWithCode()
        ->assertDontSee('Hand the player')
        ->call('pay')
        ->assertSee('Hand the player KES 1,000.00')
        ->assertSee('CASH WITHDRAWAL')
        ->assertSee('Float now: KES 13,500.00');

    $payments = sentPayments();
    expect($payments)->toHaveCount(1)
        ->and($payments[0]['code'])->toBe('482913')
        ->and($payments[0]['key'])->toMatch('/^[0-9a-f-]{36}$/');

    $log = ActivityLog::sole();
    expect($log->action)->toBe(ActivityAction::WithdrawalPaid)
        ->and($log->subject_id)->toBe(WITHDRAWAL_ID)
        ->and($log->amount)->toBe('1000.00')
        ->and($log->idempotency_key)->toBe($payments[0]['key']);
});

it('refuses a code that is not 6 digits without asking KadiApi', function () {
    fakeWithdrawalsApi();

    payingWithCode('48291')
        ->call('pay')
        ->assertHasErrors(['pickupCode']);

    expect(sentPayments())->toBe([]);
});

it('says how many attempts are left after a wrong code and uses a new key for the next one', function () {
    fakeWithdrawalsApi(pay: Http::sequence()
        ->push(['success' => false, 'code' => 'wrong_code', 'message' => 'That pickup code is wrong', 'attempts_left' => 4], 422)
        ->push(['success' => false, 'code' => 'wrong_code', 'message' => 'That pickup code is wrong', 'attempts_left' => 1], 422));

    payingWithCode('111111')
        ->call('pay')
        ->assertSee('That pickup code is wrong. 4 attempts left.')
        ->assertDontSee('One more wrong code')
        ->set('pickupCode', '222222')
        ->call('pay')
        ->assertSee('That pickup code is wrong. 1 attempt left.')
        ->assertSee('One more wrong code cancels the request and gives the money back to the player.')
        ->assertDontSee('Hand the player');

    $payments = sentPayments();
    expect($payments[1]['key'])->not->toBe($payments[0]['key']);

    $log = ActivityLog::query()->latest('id')->first();
    expect($log->action)->toBe(ActivityAction::WithdrawalPayRefused)
        ->and($log->api_code)->toBe('wrong_code')
        ->and($log->details)->toMatchArray(['attempts_left' => 1]);
});

it('stops the payment for good when KadiApi closes the request', function (int $status, string $code, string $shown) {
    fakeWithdrawalsApi(pay: Http::response(['success' => false, 'code' => $code, 'message' => 'from KadiApi'], $status));

    payingWithCode()
        ->call('pay')
        ->assertSet('payClosed', true)
        ->assertSee($shown)
        ->assertSee('Do not hand over cash.')
        ->assertDontSee('Retry (safe)');
})->with([
    'too many wrong codes' => [409, 'too_many_attempts', 'Too many wrong codes. Kadi Kings cancelled the request and gave the money back to the player.'],
    'expired' => [409, 'expired', 'This request expired after 24 hours. The money went back to the player.'],
    'not pending' => [409, 'not_pending', 'This request is no longer pending'],
    'not this shop\'s' => [404, 'withdrawal_not_found', "This request is not one of this shop's."],
]);

it('retries a payment whose outcome is unknown with the same key and code, and holds the cash until then', function (Closure $firstAnswer) {
    fakeWithdrawalsApi(pay: $firstAnswer(Http::sequence())->push(paidResponse()));

    payingWithCode('482913')
        ->call('pay')
        ->assertSee('Not sure it was paid.')
        ->assertSee('Do not hand over cash until it is confirmed.')
        ->assertSee('Retry (safe)')
        ->assertDontSee('Hand the player')
        ->set('pickupCode', '999999')
        ->call('pay')
        ->assertSee('Hand the player KES 1,000.00');

    $payments = sentPayments();
    expect($payments)->toHaveCount(2)
        ->and($payments[1])->toBe($payments[0]);

    expect(ActivityLog::query()->orderBy('id')->pluck('action')->all())
        ->toBe([ActivityAction::WithdrawalPayOutcomeUnknown, ActivityAction::WithdrawalPaid]);
})->with([
    'timeout' => [fn (ResponseSequence $sequence): ResponseSequence => $sequence->pushFailedConnection('cURL error 28: Operation timed out')],
    'bad gateway' => [fn (ResponseSequence $sequence): ResponseSequence => $sequence->push('Bad Gateway', 502)],
]);

it('rejects a withdrawal with the reason the player will see', function () {
    fakeWithdrawalsApi(reject: Http::response(['success' => true, 'message' => "Withdrawal rejected; the money is back in the player's wallet", 'data' => pendingWithdrawal(['status' => 'rejected'])]));

    Livewire::test(Withdrawals::class)
        ->callTableAction('reject', WITHDRAWAL_ID, ['reason' => 'Not enough cash in the till'])
        ->assertHasNoTableActionErrors()
        ->assertNotified('Rejected');

    Http::assertSent(fn (Request $request): bool => $request->url() === REJECT_URL
        && $request->data() === ['reason' => 'Not enough cash in the till']
        && $request->hasHeader('Idempotency-Key'));

    $log = ActivityLog::sole();
    expect($log->action)->toBe(ActivityAction::WithdrawalRejected)
        ->and($log->details)->toMatchArray(['reason' => 'Not enough cash in the till']);
});

it('refuses a rejection reason shorter than 3 characters', function () {
    fakeWithdrawalsApi();

    Livewire::test(Withdrawals::class)
        ->callTableAction('reject', WITHDRAWAL_ID, ['reason' => 'no'])
        ->assertHasTableActionErrors(['reason' => 'min']);

    Http::assertNotSent(fn (Request $request): bool => $request->url() === REJECT_URL);
});

it('retries a rejection whose outcome is unknown with the same key', function () {
    fakeWithdrawalsApi(reject: Http::sequence()
        ->pushFailedConnection()
        ->push(['success' => true, 'message' => 'Withdrawal rejected', 'data' => pendingWithdrawal(['status' => 'rejected'])]));

    Livewire::test(Withdrawals::class)
        ->callTableAction('reject', WITHDRAWAL_ID, ['reason' => 'Not enough cash in the till'])
        ->assertNotified('Not sure it was rejected')
        ->callTableAction('reject', WITHDRAWAL_ID, ['reason' => 'Not enough cash in the till'])
        ->assertNotified('Rejected');

    $keys = Http::recorded(fn (Request $request): bool => $request->url() === REJECT_URL)
        ->map(fn (array $pair): string => $pair[0]->header('Idempotency-Key')[0])
        ->values();

    expect($keys)->toHaveCount(2)
        ->and($keys[1])->toBe($keys[0]);
});

it('never stores a pickup code', function () {
    fakeWithdrawalsApi(pay: Http::sequence()
        ->push(['success' => false, 'code' => 'wrong_code', 'message' => 'That pickup code is wrong', 'attempts_left' => 4], 422)
        ->pushFailedConnection()
        ->push(paidResponse()));

    payingWithCode('111111')
        ->call('pay')
        ->set('pickupCode', '482913')
        ->call('pay')
        ->call('pay');

    $stored = ActivityLog::all()->toJson();

    expect(ActivityLog::count())->toBe(3)
        ->and($stored)->not->toContain('111111')
        ->and($stored)->not->toContain('482913');
});

it('never puts the API key in the page', function () {
    fakeWithdrawalsApi();

    $this->get(route('filament.shop.pages.withdrawals'))
        ->assertOk()
        ->assertDontSee(auth()->user()->shop->api_key);
});
