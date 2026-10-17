<?php

return [

    /*
    |--------------------------------------------------------------------------
    | KadiApi
    |--------------------------------------------------------------------------
    |
    | The base URL of KadiApi (ending in /api/v1). Each shop's own API key, issued
    | by Kadi Kings under Bet Shops, is stored encrypted on its row in the shops
    | table (added by an admin) and only ever read by App\Services\KadiShopApi.
    |
    */

    'url' => env('KADI_API_URL', 'https://api.kadi-kings.co.ke/api/v1'),

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
