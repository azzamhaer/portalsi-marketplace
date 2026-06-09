<?php

return [
    'tripay' => [
        'mode'          => env('TRIPAY_MODE', 'sandbox'),
        'api_key'       => env('TRIPAY_API_KEY'),
        'private_key'   => env('TRIPAY_PRIVATE_KEY'),
        'merchant_code' => env('TRIPAY_MERCHANT_CODE', 'T0001'),
    ],

    'brevo' => [
        'api_key'       => env('BREVO_API_KEY'),
        'sender_email'  => env('BREVO_SENDER_EMAIL', 'noreply@mpsi.id'),
        'sender_name'   => env('BREVO_SENDER_NAME', 'MPSI'),
    ],

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
];
