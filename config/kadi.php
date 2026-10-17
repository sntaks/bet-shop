<?php

return [

    /*
    |--------------------------------------------------------------------------
    | KadiApi
    |--------------------------------------------------------------------------
    |
    | The base URL of KadiApi (ending in /api/v1) and this shop's API key, issued
    | by Kadi Kings under Bet Shops. The key only works on the /shop/* routes and
    | always acts for this shop. It stays on the server: only App\Services\KadiShopApi
    | reads it.
    |
    */

    'url' => env('KADI_API_URL', 'https://api.kadi-kings.co.ke/api/v1'),

    'key' => env('KADI_SHOP_API_KEY'),

];
