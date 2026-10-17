<?php

namespace App\Services;

use SensitiveParameter;

/**
 * Checks a shop key before it is saved: KadiApi must accept it on GET /shop/me and say it acts
 * for the same shop number, so a key can never be put on the wrong shop.
 */
class ShopKeyVerifier
{
    /**
     * Why the key cannot be used for this shop, or null when it can.
     */
    public static function problem(#[SensitiveParameter] string $apiKey, string $shopNo): ?string
    {
        $result = KadiShopApi::forKey($apiKey)->me();

        if ($result->ok()) {
            $keyShopNo = is_string($result->data['shop_no'] ?? null) ? $result->data['shop_no'] : '';

            return strcasecmp($keyShopNo, $shopNo) === 0
                ? null
                : "This key belongs to shop {$keyShopNo}, not ".strtoupper($shopNo).'.';
        }

        return match (true) {
            $result->invalidKey() => 'KadiApi does not accept this key. Check it was copied in full and is not revoked.',
            $result->blocksShop() => 'KadiApi says this shop is suspended or closed, so the key cannot be checked.',
            $result->notShopKey() => 'This is not a shop key. Issue one in GMS under Bet Shops.',
            $result->rateLimited() => 'Too many requests, wait a moment and save again.',
            $result->outcomeUnknown() => 'KadiApi could not be reached to check this key. Try again.',
            default => $result->message !== '' ? $result->message : 'KadiApi refused this key.',
        };
    }
}
