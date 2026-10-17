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
    case PlayerLoaded = 'player_loaded';
    case LoadRefused = 'load_refused';
    case LoadOutcomeUnknown = 'load_outcome_unknown';

    public function getLabel(): string
    {
        return match ($this) {
            self::PlayerLoaded => 'Player loaded',
            self::LoadRefused => 'Load refused',
            self::LoadOutcomeUnknown => 'Load not confirmed',
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
            self::PlayerLoaded => 'success',
            self::LoginFailed, self::LoadRefused, self::LoadOutcomeUnknown => 'danger',
            self::StaffCreated, self::StaffUpdated, self::SettingsUpdated => 'warning',
            default => 'gray',
        };
    }
}
