<?php

return [
    'alipay' => [
        'app_id' => env('ALIPAY_APP_ID'),
        'ali_public_key' => env('ALIPAY_ALI_PUBLIC_KEY'),
        'private_key' => env('ALIPAY_PRIVATE_KEY'),
        'log' => [
            'file' => storage_path('logs/alipay.log'),
        ],
    ],
    'wechat' => [
        'app_id' => env('WECHAT_PAY_APP_ID'),
        'mch_id' => env('WECHAT_PAY_MCH_ID'),
        'key' => env('WECHAT_PAY_KEY'),
        'cert_client' => '',
        'cert_key' => '',
        'log' => [
            'file' => storage_path('logs/wechat.log')
        ],
    ],
];
