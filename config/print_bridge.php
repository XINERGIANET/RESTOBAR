<?php

return [

    /*
     * Cola en caché para comandas hacia ticketeras sin IP (USB en otra PC).
     * Los nombres de impresora que usan el puente son los mismos que QZ_SECONDARY_FIRST_PRINTER_NAMES (qz.php).
     */

    'cache_ttl_seconds' => 600,

    'max_queue_length' => 200,

];
