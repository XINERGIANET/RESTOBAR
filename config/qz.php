<?php

return [

    'enabled' => env('QZ_TRAY_ENABLED', true),

    'private_key_path' => env('QZ_PRIVATE_KEY_PATH') ?: storage_path('app/qz/private-key.pem'),

    'certificate_path' => env('QZ_CERTIFICATE_PATH') ?: storage_path('app/qz/digital-certificate.txt'),

    // Reserva: otro PC / otra ticketera (mismo par certificado+clave generado en esa máquina).
    'private_key_path_secondary' => env('QZ2_PRIVATE_KEY_PATH') ?: storage_path('app/qz2/private-key.pem'),

    'certificate_path_secondary' => env('QZ2_CERTIFICATE_PATH') ?: storage_path('app/qz2/digital-certificate.txt'),

    'printer_name' => env('QZ_PRINTER_NAME', 'BARRA'),

    // Algoritmo de firma que validará QZ (debe coincidir con qz.security.setSignatureAlgorithm()).
    // Valores: SHA1 | SHA256 | SHA384 | SHA512
    'signature_algorithm' => env('QZ_SIGNATURE_ALGORITHM', 'SHA512'),

    // raw | pixel | auto (auto = intenta raw y cae a pixel en el frontend)
    'print_mode' => env('QZ_PRINT_MODE', 'auto'),

    // Lista blanca opcional de orígenes. Ej: "https://xinergia.net,https://restaurante.test"
    // Si está vacío, no se valida Origin.
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('QZ_ALLOWED_ORIGINS', (string) env('APP_URL', '')))
    ))),

    // Si true, en producción exige Origin válido (si se configuró allowed_origins).
    'production_lock' => env('QZ_PRODUCTION_LOCK', false),

    // Orden de prueba al conectar QZ Tray (cliente): primary,secondary o secondary,primary.
    // Si el primer par provoca rechazo en websocket.connect(), se prueba el siguiente.
    'cert_pair_try_order' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('QZ_CERT_PAIR_TRY_ORDER', 'primary,secondary'))
    ))),

    // Nombres de ticketera (printers_branch.name) que deben usar primero el par secondary (app/qz2).
    // Útil si el nombre en BD no contiene "barra2". Separados por coma.
    'secondary_first_printer_names' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('QZ_SECONDARY_FIRST_PRINTER_NAMES', 'BARRA2'))
    ))),

    // Comanda: ticketera con IP → RAW por servidor (sin QZ en el navegador). El resto usa QZ como cobro.
    'kitchen_skip_client_qz_when_printer_has_ip' => filter_var(
        env('QZ_KITCHEN_SKIP_CLIENT_WHEN_PRINTER_IP', true),
        FILTER_VALIDATE_BOOL
    ),

    // Comanda desde celular: no usar QZ (no existe en el móvil); enviar al servidor para ticketera por IP o Windows del host Laravel.
    'kitchen_comanda_disable_client_qz_on_touch_devices' => filter_var(
        env('QZ_KITCHEN_COMANDA_DISABLE_CLIENT_ON_TOUCH', true),
        FILTER_VALIDATE_BOOL
    ),

];
