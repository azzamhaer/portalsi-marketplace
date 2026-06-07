<?php

return [
    'tripay' => [
        'mode'          => env('TRIPAY_MODE', 'sandbox'),
        'api_key'       => env('TRIPAY_API_KEY'),
        'private_key'   => env('TRIPAY_PRIVATE_KEY'),
        'merchant_code' => env('TRIPAY_MERCHANT_CODE', 'T0001'),
    ],
];
