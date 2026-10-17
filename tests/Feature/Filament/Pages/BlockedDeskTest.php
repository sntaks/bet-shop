<?php

use App\Filament\Pages\BlockedDesk;
use App\Models\User;
use App\Services\DeskBlock;
use App\Services\KadiResult;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->cashier = User::factory()->cashier()->create();
    DeskBlock::recordFrom(new KadiResult(403, 'shop_not_active', 'This shop is suspended. Contact Kadi Kings.'), $this->cashier->shop_id);
    $this->actingAs($this->cashier);
});

it('unblocks the desk and goes to the dashboard when the check gets through', function () {
    Http::fake(['kadi.test/*' => Http::response(['success' => true, 'data' => ['status' => 'active']])]);

    Livewire::test(BlockedDesk::class)
        ->callAction('checkAgain')
        ->assertRedirect(route('filament.shop.pages.dashboard'));

    expect(DeskBlock::isBlocked($this->cashier->shop_id))->toBeFalse();
});

it('stays blocked when the shop is still suspended', function () {
    Http::fake(['kadi.test/*' => Http::response(['code' => 'shop_not_active', 'message' => 'This shop is suspended. Contact Kadi Kings.'], 403)]);

    Livewire::test(BlockedDesk::class)
        ->callAction('checkAgain')
        ->assertNotified('Still blocked.')
        ->assertNoRedirect();

    expect(DeskBlock::reason($this->cashier->shop_id))->toBe(DeskBlock::SHOP_NOT_ACTIVE);
});

it('stays blocked and says so when KadiApi cannot be reached', function () {
    Http::fake(['kadi.test/*' => Http::failedConnection()]);

    Livewire::test(BlockedDesk::class)
        ->callAction('checkAgain')
        ->assertNotified('Kadi Kings could not be reached. Try again in a moment.');

    expect(DeskBlock::isBlocked($this->cashier->shop_id))->toBeTrue();
});

it('sends an unblocked desk back to the dashboard', function () {
    DeskBlock::clear($this->cashier->shop_id);

    $this->get(route('filament.shop.blocked'))
        ->assertRedirect(route('filament.shop.pages.dashboard'));
});
