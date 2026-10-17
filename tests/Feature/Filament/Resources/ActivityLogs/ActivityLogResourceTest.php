<?php

use App\Enums\ActivityAction;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Models\ActivityLog;
use App\Models\User;
use Livewire\Livewire;

it('forbids a cashier from the activity log', function () {
    $this->actingAs(User::factory()->cashier()->create())
        ->get(route('filament.shop.resources.activity-log.index'))
        ->assertForbidden();
});

it('lists the manager\'s own shop\'s activity, newest first, filtered by action', function () {
    $manager = User::factory()->manager()->create();
    $this->actingAs($manager);

    $older = ActivityLog::factory()->create(['shop_id' => $manager->shop_id, 'action' => ActivityAction::LoginFailed, 'created_at' => now()->subHour()]);
    $newer = ActivityLog::factory()->create(['shop_id' => $manager->shop_id, 'action' => ActivityAction::SettingsUpdated]);
    $otherShop = ActivityLog::factory()->create(['shop_id' => User::factory()->create()->shop_id, 'action' => ActivityAction::SettingsUpdated]);

    Livewire::test(ListActivityLogs::class)
        ->assertCanSeeTableRecords([$newer, $older], inOrder: true)
        ->assertCanNotSeeTableRecords([$otherShop])
        ->filterTable('action', ActivityAction::LoginFailed->value)
        ->assertCanSeeTableRecords([$older])
        ->assertCanNotSeeTableRecords([$newer]);
});

it('lists every shop\'s activity for an admin', function () {
    $this->actingAs(User::factory()->admin()->create());

    $logs = collect([User::factory()->create(), User::factory()->create()])
        ->map(fn (User $user): ActivityLog => ActivityLog::factory()->create(['shop_id' => $user->shop_id, 'user_id' => $user->id]));

    Livewire::test(ListActivityLogs::class)
        ->assertCanSeeTableRecords($logs)
        ->assertSee($logs[0]->shop->shop_no);
});
