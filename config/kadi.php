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

    /*
    |--------------------------------------------------------------------------
    | Shop Timezone
    |--------------------------------------------------------------------------
    |
    | Dates are stored in the app timezone (UTC) and shown, filtered and sent
    | to KadiApi (whose date filters are Nairobi days) in this one.
    |
    */

    'timezone' => 'Africa/Nairobi',

];
