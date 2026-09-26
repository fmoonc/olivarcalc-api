<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing
|--------------------------------------------------------------------------
|
| The web app (olivarcalc.es) and the API (api.olivarcalc.es) are different
| origins, and Sanctum cookie auth needs credentialed requests. Origins come
| from CORS_ALLOWED_ORIGINS so staging and production differ by env alone
| (spec §7.5).
|
*/

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'docs/api', 'docs/api.json'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:4321')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['ETag'],

    'max_age' => 0,

    'supports_credentials' => true,

];
