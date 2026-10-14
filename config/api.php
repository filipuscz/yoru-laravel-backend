<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Token
    |--------------------------------------------------------------------------
    |
    | Shared credential checked by the X-API-TOKEN header. Values live here so
    | they remain available after `php artisan config:cache`.
    |
    */

    'secret_key' => env('API_SECRET_KEY'),

    'token_expire_hours' => (int) env('API_TOKEN_EXPIRE_HOURS', 1),

];
