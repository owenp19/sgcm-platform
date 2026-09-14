<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CORS — política idéntica al backend Node/Express
    |--------------------------------------------------------------------------
    |
    | FRONTEND_URL puede ser '*' (cualquier origen, solo pruebas) o una lista
    | separada por comas: https://app.occre.gov.co,http://localhost:8100
    |
    */

    'paths' => ['api/*', 'up'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', env('FRONTEND_URL', '*'))))) ?: ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => ! in_array('*', array_map('trim', explode(',', (string) env('FRONTEND_URL', '*')))),

];
