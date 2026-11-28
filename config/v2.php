<?php

return [
    'enabled' => (bool) env('V2_ENABLED', false),
    'store' => [
        'name' => env('V2_STORE_NAME', 'Toko Bahan Bangunan'),
        'address' => env('V2_STORE_ADDRESS', ''),
        'phone' => env('V2_STORE_PHONE', ''),
        'footer' => env('V2_STORE_FOOTER', 'Terima kasih telah berbelanja. Barang yang sudah dibeli harap diperiksa kembali.'),
    ],
    'pos' => [
        'cashier_max_discount_percent' => ($maxDiscount = env('V2_CASHIER_MAX_DISCOUNT_PERCENT')) === null || $maxDiscount === ''
            ? null
            : (string) $maxDiscount,
        'block_cashier_below_cost' => (bool) env('V2_BLOCK_CASHIER_BELOW_COST', false),
    ],
];
