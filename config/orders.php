<?php

$splitBranchIds = array_values(array_filter(array_map(
    static fn ($v) => (int) trim((string) $v),
    explode(',', (string) env('ORDER_SPLIT_ACCOUNT_BRANCH_IDS', ''))
)));

return [
    /*
    | Habilita división de cuenta (por monto / por productos) en cobro de pedidos.
    */
    'split_account_enabled' => env('ORDER_SPLIT_ACCOUNT_ENABLED', true),
    /*
    | Lista de IDs de sucursal donde aplica la división (vacío = todas las sucursales).
    */
    'split_account_branch_ids' => $splitBranchIds,
];
