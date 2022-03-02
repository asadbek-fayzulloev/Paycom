<?php
return [
    'login' => 'Paycom',
    'merchant' => env('PAYCOM_MERCHANT', ''),
    'key' => env('PAYCOM_KEY', ''),
    'key_test' => env('PAYCOM_KEY_TEST', ''),
    'is_test' => env('PAYCOM_TEST', 'true'),
    'table' => [
        'orders' => env('PAYCOM_ORDERS', 'orders'),
        'transactions' => env('PAYCOM_TRANSACTIONS', 'paycom_transactions'),
        'users' => env('PAYCOM_USERS', 'users'),

    ]
];
