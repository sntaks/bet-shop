<?php

namespace App\Listeners;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * Logins, logouts and failed logins go to the activity log. A failed login keeps the email
 * that was typed, never the password.
 */
class RecordAuthActivity
{
    public function handleLogin(Login $event): void
    {
        ActivityLog::record(ActivityAction::LoggedIn, ['user_id' => $event->user->getAuthIdentifier()]);
    }

    public function handleLogout(Logout $event): void
    {
        ActivityLog::record(ActivityAction::LoggedOut, ['user_id' => $event->user->getAuthIdentifier()]);
    }

    public function handleFailed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        ActivityLog::record(ActivityAction::LoginFailed, [
            'user_id' => $event->user?->getAuthIdentifier(),
            'details' => ['email' => is_string($email) ? $email : null],
        ]);
    }
}
