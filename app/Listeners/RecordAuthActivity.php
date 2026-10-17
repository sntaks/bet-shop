<?php

namespace App\Listeners;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Logins, logouts and failed logins go to the activity log, against the user's shop. A failed
 * login keeps the email that was typed, never the password.
 */
class RecordAuthActivity
{
    public function handleLogin(Login $event): void
    {
        ActivityLog::record(ActivityAction::LoggedIn, self::who($event->user));
    }

    public function handleLogout(Logout $event): void
    {
        ActivityLog::record(ActivityAction::LoggedOut, self::who($event->user));
    }

    public function handleFailed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        ActivityLog::record(ActivityAction::LoginFailed, [
            ...self::who($event->user),
            'details' => ['email' => is_string($email) ? $email : null],
        ]);
    }

    /**
     * @return array{user_id: int|null, shop_id: int|null}
     */
    private static function who(?Authenticatable $user): array
    {
        return [
            'user_id' => $user instanceof User ? $user->id : null,
            'shop_id' => $user instanceof User ? $user->shop_id : null,
        ];
    }
}
