<?php

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Livewire\Livewire;

it('records a login and a logout', function () {
    $cashier = User::factory()->cashier()->create();

    Livewire::test(Login::class)
        ->fillForm(['email' => $cashier->email, 'password' => 'password'])
        ->call('authenticate');

    $this->post(route('filament.shop.auth.logout'));

    expect(ActivityLog::orderBy('id')->get(['user_id', 'action'])->toArray())->toBe([
        ['user_id' => $cashier->id, 'action' => ActivityAction::LoggedIn->value],
        ['user_id' => $cashier->id, 'action' => ActivityAction::LoggedOut->value],
    ]);
});

it('records a failed login with the email typed and never the password', function () {
    $cashier = User::factory()->cashier()->create();

    Livewire::test(Login::class)
        ->fillForm(['email' => $cashier->email, 'password' => 'wrong-password-123'])
        ->call('authenticate');

    $log = ActivityLog::sole();
    expect($log->action)->toBe(ActivityAction::LoginFailed)
        ->and($log->user_id)->toBe($cashier->id)
        ->and($log->details)->toBe(['email' => $cashier->email])
        ->and(json_encode($log->toArray()))->not->toContain('wrong-password-123');
});

it('records a failed login for an email nobody has', function () {
    Livewire::test(Login::class)
        ->fillForm(['email' => 'nobody@example.com', 'password' => 'password'])
        ->call('authenticate');

    expect(ActivityLog::sole()->only(['user_id', 'details']))->toBe(['user_id' => null, 'details' => ['email' => 'nobody@example.com']]);
});
