<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Whether KadiApi has shut this desk out: the shop is suspended or closed, or the key is wrong.
 * Set by any answer that says so and cleared by any successful one, so the whole panel is
 * blocked from the first refusal until "Check again" gets through.
 */
class DeskBlock
{
    public const string SHOP_NOT_ACTIVE = 'shop_not_active';

    public const string INVALID_KEY = 'invalid_key';

    public const string NOT_SHOP_KEY = 'not_shop_key';

    private const string CACHE_KEY = 'kadi:desk-block';

    public static function recordFrom(KadiResult $result): void
    {
        $reason = match (true) {
            $result->blocksShop() => self::SHOP_NOT_ACTIVE,
            $result->invalidKey() => self::INVALID_KEY,
            $result->notShopKey() => self::NOT_SHOP_KEY,
            default => null,
        };

        if ($reason !== null) {
            Cache::forever(self::CACHE_KEY, $reason);
        } elseif ($result->ok()) {
            Cache::forget(self::CACHE_KEY);
        }
    }

    public static function reason(): ?string
    {
        $reason = Cache::get(self::CACHE_KEY);

        return is_string($reason) ? $reason : null;
    }

    public static function isBlocked(): bool
    {
        return self::reason() !== null;
    }

    /**
     * What the cashier is told, for a block reason.
     */
    public static function message(string $reason): string
    {
        return match ($reason) {
            self::SHOP_NOT_ACTIVE => 'This shop is suspended. Contact Kadi Kings.',
            self::INVALID_KEY => "This desk's key is not valid. Contact Kadi Kings.",
            default => "This desk's key is not a shop key. Contact Kadi Kings to issue one.",
        };
    }
}
