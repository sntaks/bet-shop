<?php

namespace App\Filament\Support;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * How money, player numbers and times read at the counter: KES with 2 decimals, `1234 5678`,
 * and Nairobi time.
 */
class Format
{
    public static function money(int|float|string|null $amount): string
    {
        return 'KES '.number_format((float) ($amount ?? 0), 2);
    }

    /**
     * KadiApi sends the 8 bare digits; players read them as `1234 5678`.
     */
    public static function playerNo(?string $playerNo): string
    {
        if ($playerNo === null || preg_match('/^\d{8}$/', $playerNo) !== 1) {
            return $playerNo ?? '—';
        }

        return substr($playerNo, 0, 4).' '.substr($playerNo, 4);
    }

    /**
     * An ISO 8601 time from KadiApi as `17 Oct 2026, 10:15` in Nairobi time, or a dash.
     */
    public static function dateTime(?string $value): string
    {
        if (blank($value)) {
            return '—';
        }

        try {
            return CarbonImmutable::parse($value)->setTimezone(config('kadi.timezone'))->format('d M Y, H:i');
        } catch (Throwable) {
            return $value;
        }
    }
}
