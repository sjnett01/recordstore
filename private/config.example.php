<?php
return [
    'app' => [
        'base_url' => 'https://example.com',
        'timezone' => 'Europe/London',

        'worker_secret' => 'GENERATE_A_LONG_RANDOM_SECRET',
        'download_limit_default' => 3,
        // Keep false on a live store. Set true only while testing the included demo checkout.
        'demo_checkout' => false,
        'ffmpeg_path' => '/usr/bin/ffmpeg',
        'ffprobe_path' => '/usr/bin/ffprobe',
        // Optional override. Relative paths resolve from the private application directory.
        // 'montage_fx_dir' => 'assets/montage',
        'master_upload_max_bytes' => 536870912, // 512 MB
    ],
    'payments' => [
        'currency' => 'GBP',
        'paypal' => [
            'sandbox' => [
                'client_id' => 'REPLACE_WITH_PAYPAL_SANDBOX_CLIENT_ID',
                'client_secret' => 'REPLACE_WITH_PAYPAL_SANDBOX_CLIENT_SECRET',
                'webhook_id' => 'REPLACE_WITH_PAYPAL_SANDBOX_WEBHOOK_ID',
            ],
            'live' => [
                'client_id' => 'REPLACE_WITH_PAYPAL_LIVE_CLIENT_ID',
                'client_secret' => 'REPLACE_WITH_PAYPAL_LIVE_CLIENT_SECRET',
                'webhook_id' => 'REPLACE_WITH_PAYPAL_LIVE_WEBHOOK_ID',
            ],
        ],
    ],
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'recordstore',
        'user' => 'REPLACE_WITH_DB_USER',
        'pass' => 'REPLACE_WITH_DB_PASSWORD',
        'charset' => 'utf8mb4',
    ],
    'paths' => [
        'masters' => __DIR__.'/masters',
        'previews' => dirname(__DIR__).'/public/previews',
        'artwork' => dirname(__DIR__).'/public/artwork',
    ],
];
