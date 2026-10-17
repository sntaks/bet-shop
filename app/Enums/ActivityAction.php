<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What a staff member did, as recorded in the activity log.
 */
enum ActivityAction: string implements HasColor, HasLabel
{
    case LoggedIn = 'logged_in';
    case LoggedOut = 'logged_out';
    case LoginFailed = 'login_failed';
    case StaffCreated = 'staff_created';
    case StaffUpdated = 'staff_updated';
    case SettingsUpdated = 'settings_updated';

    public function getLabel(): string
    {
        return match ($this) {
            self::LoggedIn => 'Logged in',
            self::LoggedOut => 'Logged out',
            self::LoginFailed => 'Login failed',
            self::StaffCreated => 'Staff added',
            self::StaffUpdated => 'Staff changed',
            self::SettingsUpdated => 'Settings changed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::LoginFailed => 'danger',
            self::StaffCreated, self::StaffUpdated, self::SettingsUpdated => 'warning',
            default => 'gray',
        };
    }
}
