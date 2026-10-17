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
    case WithdrawalPaid = 'withdrawal_paid';
    case WithdrawalPayRefused = 'withdrawal_pay_refused';
    case WithdrawalPayOutcomeUnknown = 'withdrawal_pay_outcome_unknown';
    case WithdrawalRejected = 'withdrawal_rejected';
    case WithdrawalRejectFailed = 'withdrawal_reject_failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::WithdrawalPaid => 'Withdrawal paid',
            self::WithdrawalPayRefused => 'Withdrawal not paid',
            self::WithdrawalPayOutcomeUnknown => 'Withdrawal payment not confirmed',
            self::WithdrawalRejected => 'Withdrawal rejected',
            self::WithdrawalRejectFailed => 'Withdrawal rejection failed',
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
            self::PlayerLoaded, self::WithdrawalPaid => 'success',
            self::WithdrawalRejected => 'warning',
            self::LoginFailed, self::LoadRefused, self::LoadOutcomeUnknown, self::WithdrawalPayRefused,
            self::WithdrawalPayOutcomeUnknown, self::WithdrawalRejectFailed => 'danger',
            self::StaffCreated, self::StaffUpdated, self::SettingsUpdated => 'warning',
            default => 'gray',
        };
    }
}
