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

it('lists the activity for a manager, newest first, filtered by action', function () {
    $this->actingAs(User::factory()->manager()->create());

    $older = ActivityLog::factory()->create(['action' => ActivityAction::LoginFailed, 'created_at' => now()->subHour()]);
    $newer = ActivityLog::factory()->create(['action' => ActivityAction::SettingsUpdated]);

    Livewire::test(ListActivityLogs::class)
        ->assertCanSeeTableRecords([$newer, $older], inOrder: true)
        ->filterTable('action', ActivityAction::LoginFailed->value)
        ->assertCanSeeTableRecords([$older])
        ->assertCanNotSeeTableRecords([$newer]);
});
