<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Casillero
    |--------------------------------------------------------------------------
    |
    | Datos del almacén en Miami que se le entregan al cliente. El código de
    | casillero es uno solo para toda la operación: es la suite que Tikabox
    | tiene en el almacén, no un número por cliente.
    |
    */

    'locker' => [
        'code' => env('LOCKER_CODE', 'SJO0024955'),
    ],

    'warehouse' => [
        'address' => env('WAREHOUSE_ADDRESS', '8005 NW 64th St'),
        'city' => env('WAREHOUSE_CITY', 'Miami'),
        'state' => env('WAREHOUSE_STATE', 'FL'),
        'zip' => env('WAREHOUSE_ZIP', '33195-6561'),
        'country' => env('WAREHOUSE_COUNTRY', 'United States'),
        'phone' => env('WAREHOUSE_PHONE', '+1 (305) 699-2991'),
    ],

    'price_per_pound' => (float) env('PRICE_PER_POUND', 6.5),

    /*
    |--------------------------------------------------------------------------
    | Verificación de correo
    |--------------------------------------------------------------------------
    */

    'verification' => [
        'ttl_minutes' => (int) env('VERIFICATION_TTL_MINUTES', 15),
        'max_attempts' => (int) env('VERIFICATION_MAX_ATTEMPTS', 5),
    ],

];
