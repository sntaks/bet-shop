<?php

use App\Filament\Pages\LoadHistory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->cashier()->create());
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function historyLoad(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 41,
        'load_id' => '5b1c7f2e-0f1d-4a2b-9a51-6f3e2c1d0b9a',
        'shop_no' => 'S101',
        'player' => ['player_no' => '12345671', 'name' => 'Grace K****', 'active' => true],
        'gross_amount' => 1000.00,
        'excise_amount' => 50.00,
        'net_amount' => 950.00,
        'status' => 'completed',
        'loaded_at' => '2026-10-17T10:15:00+03:00',
        'reversed_at' => null,
    ], $overrides);
}

/**
 * @param  list<array<string, mixed>>  $items
 */
function fakeHistoryApi(array $items, int $total = 1): void
{
    Http::fake([
        'kadi.test/api/v1/shop/me' => Http::response(['success' => true, 'data' => shopMe()]),
        'kadi.test/api/v1/shop/loads*' => Http::response(['success' => true, 'data' => [
            'items' => $items,
            'pagination' => ['page' => 1, 'per_page' => 50, 'total' => $total, 'last_page' => (int) ceil($total / 50)],
        ]]),
    ]);
}

it('lists today\'s loads in Nairobi by default', function () {
    // 22:30 UTC on the 17th is already the 18th in Nairobi.
    $this->travelTo(CarbonImmutable::parse('2026-10-17 22:30:00', 'UTC'));
    fakeHistoryApi([historyLoad()]);

    Livewire::test(LoadHistory::class)
        ->assertSee('Grace K****')
        ->assertSee('1234 5671')
        ->assertSee('KES 1,000.00')
        ->assertSee('KES 950.00');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://kadi.test/api/v1/shop/loads?from=2026-10-18&to=2026-10-18&page=1&per_page=50');
});

it('asks KadiApi for the days and page chosen', function () {
    fakeHistoryApi([historyLoad()], total: 120);

    Livewire::test(LoadHistory::class)
        ->filterTable('loaded', ['from' => '2026-10-01', 'to' => '2026-10-15'])
        ->call('gotoPage', 2);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://kadi.test/api/v1/shop/loads?from=2026-10-01&to=2026-10-15&page=2&per_page=50');
});

it('shows a load Kadi Kings reversed', function () {
    fakeHistoryApi([historyLoad(['status' => 'reversed', 'reversed_at' => '2026-10-17T12:00:00+03:00'])]);

    Livewire::test(LoadHistory::class)
        ->assertSee('Reversed by Kadi Kings')
        ->assertSee('17 Oct 2026, 12:00');
});

it('reprints the receipt of a load', function () {
    fakeHistoryApi([historyLoad()]);

    Livewire::test(LoadHistory::class)
        ->mountTableAction('reprint', '5b1c7f2e-0f1d-4a2b-9a51-6f3e2c1d0b9a')
        ->assertMountedActionModalSee([
            'CASH LOAD',
            'Kariobangi Bet Shop',
            '5b1c7f2e-0f1d-4a2b-9a51-6f3e2c1d0b9a',
            'Excise duty (5%)',
            '17 Oct 2026, 10:15',
        ]);
});

it('says why nothing is listed when KadiApi cannot be reached', function () {
    Http::fake([
        'kadi.test/api/v1/shop/me' => Http::response(['success' => true, 'data' => shopMe()]),
        'kadi.test/api/v1/shop/loads*' => Http::failedConnection(),
    ]);

    Livewire::test(LoadHistory::class)
        ->assertSee('Load history could not be shown')
        ->assertSee('Kadi Kings could not be reached. Try again in a moment.');
});

it('sends the desk to the blocked page when the key is not valid', function () {
    Http::fake(['kadi.test/*' => Http::response(['message' => 'Invalid or missing API key'], 401)]);

    Livewire::test(LoadHistory::class)
        ->assertRedirect(route('filament.shop.blocked'));
});
