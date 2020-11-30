<?php

return [
    'services' => [
        'meta' => [
            'key' => env('CLIXRAY_META_KEY'),
            'url' => env('CLIXRAY_META_URL'),
            'secret' => env('CLIXRAY_META_SECRET'),
        ],
        'cis' => [
            'key' => env('CLIXRAY_CIS_KEY'),
            'url' => env('CLIXRAY_CIS_URL'),
            'secret' => env('CLIXRAY_CIS_SECRET'),
        ],
        'partners' => [
            'key' => env('CLIXRAY_PARTNERS_KEY'),
            'url' => env('CLIXRAY_PARTNERS_URL'),
            'secret' => env('CLIXRAY_PARTNERS_SECRET'),
        ],
        'deals' => [
            'key' => env('CLIXRAY_DEALS_KEY'),
            'url' => env('CLIXRAY_DEALS_URL'),
            'secret' => env('CLIXRAY_DEALS_SECRET'),
        ],
        'loyalty' => [
            'key' => env('CLIXRAY_LOYALTY_KEY'),
            'url' => env('CLIXRAY_LOYALTY_URL'),
            'secret' => env('CLIXRAY_LOYALTY_SECRET'),
        ],
    ]
];
