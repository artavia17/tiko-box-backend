<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Casillero
    |--------------------------------------------------------------------------
    |
    | Datos del almacén en Miami que se le entregan al cliente, y la
    | configuración del código de casillero que se genera de forma
    | incremental al registrarse (SJO0024956, SJO0024957, ...).
    |
    */

    'locker' => [
        'prefix' => env('LOCKER_PREFIX', 'SJO'),
        'padding' => (int) env('LOCKER_PADDING', 7),
        'start' => (int) env('LOCKER_START', 24955),
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

];
