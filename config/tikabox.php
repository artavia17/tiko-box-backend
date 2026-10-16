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

    /** Nada se cobra por debajo de esto, aunque pese menos. */
    'minimum_weight_lb' => (float) env('MINIMUM_WEIGHT_LB', 1),

    /**
     * Lo que nos cuesta la libra puesta en Costa Rica. No se guarda solo en
     * ningún paquete: solo sirve para proponerle el número a quien costea,
     * que lo confirma o lo corrige. El costo real siempre lo digita alguien.
     */
    'cost_per_pound' => (float) env('COST_PER_POUND', 3.5),

    /**
     * Consolidaciones: días que se esperan los paquetes desde que llega el
     * primero, y con cuántas horas de antelación se avisa que está por vencer.
     */
    'consolidation' => [
        'window_days' => (int) env('CONSOLIDATION_WINDOW_DAYS', 10),
        'expiring_soon_hours' => (int) env('CONSOLIDATION_EXPIRING_SOON_HOURS', 48),
        'min_prealerts' => 2,
        'max_prealerts' => 10,
    ],

    /** Colones por dólar. Cada paquete guarda el que tenía al registrarse. */
    'exchange_rate' => (float) env('EXCHANGE_RATE', 466),

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
