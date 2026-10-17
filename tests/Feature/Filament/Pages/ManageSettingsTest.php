<?php

use App\Enums\ActivityAction;
use App\Filament\Pages\ManageSettings;
use App\Models\ActivityLog;
use App\Models\ShopSetting;
use App\Models\User;
use Livewire\Livewire;

it('forbids a cashier from the settings', function () {
    $this->actingAs(User::factory()->cashier()->create())
        ->get(route('filament.shop.pages.settings'))
        ->assertForbidden();
});

it('shows the defaults to a manager', function () {
    $this->actingAs(User::factory()->manager()->create());

    Livewire::test(ManageSettings::class)
        ->assertSchemaStateSet([
            'low_float_threshold' => ShopSetting::DEFAULT_LOW_FLOAT_THRESHOLD,
            'idle_lock_minutes' => ShopSetting::DEFAULT_IDLE_LOCK_MINUTES,
        ]);
});

it('saves the settings and records what changed', function () {
    $manager = User::factory()->manager()->create();
    $this->actingAs($manager);

    Livewire::test(ManageSettings::class)
        ->fillForm(['low_float_threshold' => 5000, 'idle_lock_minutes' => 10])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Settings saved');

    expect(ShopSetting::current()->low_float_threshold)->toBe(5000);

    $log = ActivityLog::sole();
    expect($log->action)->toBe(ActivityAction::SettingsUpdated)
        ->and($log->user_id)->toBe($manager->id)
        ->and($log->details)->toBe(['low_float_threshold' => ['from' => 2000, 'to' => 5000]]);
});

it('refuses an idle lock outside 1 to 120 minutes', function (int $minutes) {
    $this->actingAs(User::factory()->manager()->create());

    Livewire::test(ManageSettings::class)
        ->fillForm(['idle_lock_minutes' => $minutes])
        ->call('save')
        ->assertHasFormErrors(['idle_lock_minutes']);

    expect(ShopSetting::current()->idle_lock_minutes)->toBe(ShopSetting::DEFAULT_IDLE_LOCK_MINUTES);
})->with([0, 121]);
