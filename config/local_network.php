<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Impresión térmica solo desde red local
    |--------------------------------------------------------------------------
    |
    | El endpoint que envía texto a la ticketera (IP:puerto) solo acepta
    | peticiones cuya IP cliente esté en estos rangos (WiFi/LAN típica).
    | Ajusta TRUSTED_PROXIES en bootstrap si usas nginx/cloudflare.
    |
    */
    'thermal_print_enabled' => env('LOCAL_NETWORK_THERMAL_PRINT', true),

    'thermal_port' => (int) env('LOCAL_NETWORK_THERMAL_PORT', 9100),

    'thermal_timeout_seconds' => (int) env('LOCAL_NETWORK_THERMAL_TIMEOUT', 4),

    /*
    |--------------------------------------------------------------------------
    | Fallback USB local (Windows)
    |--------------------------------------------------------------------------
    |
    | Si la ticketera no tiene IP en printers_branch, el servidor intentará
    | imprimir RAW en la impresora local de Windows usando el campo "name".
    |
    */
    'thermal_windows_local_enabled' => env('LOCAL_NETWORK_THERMAL_WINDOWS_LOCAL', true),

    /**
     * Rangos CIDR extra (coma separada), además de los privados por defecto.
     */
    'additional_cidrs' => array_values(array_filter(array_map('trim', explode(',', (string) env('LOCAL_NETWORK_EXTRA_CIDRS', ''))))),
];
