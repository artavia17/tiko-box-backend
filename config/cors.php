<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS)
|--------------------------------------------------------------------------
|
| Orígenes que pueden llamar a la API desde el navegador: el frontend local
| y los dominios de Tikabox (con y sin www). Se puede agregar más desde el
| .env con CORS_ALLOWED_ORIGINS separando por comas.
|
*/

$origins = array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))));

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique(array_merge([
        // Desarrollo local
        'http://localhost:3000',
        'http://127.0.0.1:3000',

        // Ambiente de pruebas
        'https://dev.tikaboxcr.com',
        'https://www.dev.tikaboxcr.com',

        // Producción
        'https://tikaboxcr.com',
        'https://www.tikaboxcr.com',
    ], $origins))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 60 * 60 * 24,

    'supports_credentials' => false,

];
