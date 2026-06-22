<?php

return [
    'url' => env('APISUNAT_URL', 'https://back.apisunat.com'),
    'id' => env('APISUNAT_ID'),
    'token' => [
        'prod' => env('APISUNAT_TOKEN_PROD'),
    ],
    'series' => [
        'boleta' => env('APISUNAT_SERIES_BOLETA', 'B001'),
        'factura' => env('APISUNAT_SERIES_FACTURA', 'F001'),
    ],
];
