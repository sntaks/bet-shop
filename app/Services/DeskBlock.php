<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Whether KadiApi has shut a shop out: the shop is suspended or closed, or its key is wrong. Set
 * by any answer that says so and cleared by any successful one, so that shop's screens are
 * blocked from the first refusal until "Check again" gets through. Other shops are unaffected.
 */
class DeskBlock
{
    public const string SHOP_NOT_ACTIVE = 'shop_not_active';

    public const string INVALID_KEY = 'invalid_key';

    public const string NOT_SHOP_KEY = 'not_shop_key';

    public static function recordFrom(KadiResult $result, int $shopId): void
    {
        $reason = match (true) {
            $result->blocksShop() => self::SHOP_NOT_ACTIVE,
            $result->invalidKey() => self::INVALID_KEY,
            $result->notShopKey() => self::NOT_SHOP_KEY,
            default => null,
        };

        if ($reason !== null) {
            Cache::forever(self::cacheKey($shopId), $reason);
        } elseif ($result->ok()) {
            Cache::forget(self::cacheKey($shopId));
        }
    }

    /**
     * Lift the block, e.g. after the shop's key was replaced with one KadiApi accepted.
     */
    public static function clear(int $shopId): void
    {
        Cache::forget(self::cacheKey($shopId));
    }

    public static function reason(?int $shopId): ?string
    {
        if ($shopId === null) {
            return null;
        }

        $reason = Cache::get(self::cacheKey($shopId));

        return is_string($reason) ? $reason : null;
    }

    public static function isBlocked(?int $shopId): bool
    {
        return self::reason($shopId) !== null;
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

    private static function cacheKey(int $shopId): string
    {
        return "kadi:desk-block:{$shopId}";
    }
}
