<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
    ],

    // Provisioning provider config (Poin 4).
    // Default 'manual' = admin input IP/password sendiri (behavior existing).
    // Provider real (mis. supplier, biznetgio) tinggal ditambah adapter dan
    // register di ProviderManager::createXxxDriver().
    'provisioning' => [
        'default' => env('PROVISIONING_DRIVER', 'manual'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        'site_verification' => env('GOOGLE_SITE_VERIFICATION'),
    ],

    'lynk' => [
        'merchant_key' => env('LYNK_MERCHANT_KEY'),
    ],

    'turnstile' => [
        'key' => env('TURNSTILE_SITE_KEY'),
        'secret' => env('TURNSTILE_SECRET_KEY'),
    ],

    /*
    | Akun tertaut dengan CravaCloud WA Gateway — satu akun, dua aplikasi, HANYA
    | autentikasi (email, kata sandi, status verifikasi). Tidak ada data lain
    | yang dibagi. Protokolnya ditulis di docs/AKUN_TERTAUT.md milik repo
    | cravacloud-wa dan harus sama persis di kedua sisi.
    |
    | `url` alamat dasar WA Gateway di tahap yang sama; `secret` rahasia HMAC
    | yang identik di kedua aplikasi dan berbeda tiap tahap. Salah satunya
    | kosong = penautan mati di kedua arah: yang keluar tidak dikirim, yang
    | masuk ditolak 503.
    |
    | Batas waktunya sengaja pendek: pengiriman pertama berjalan di ujung
    | permintaan web (bukan di antrean), dan WA yang sedang lambat tidak boleh
    | membuat halaman daftar di sini ikut menunggu lama.
    */
    'linked_accounts' => [
        'url' => rtrim((string) env('LINKED_ACCOUNTS_URL', ''), '/'),
        'secret' => env('LINKED_ACCOUNTS_SECRET'),
        'timeout' => (int) env('LINKED_ACCOUNTS_TIMEOUT', 5),
    ],

    'xendit' => [
        'secret_key' => env('XENDIT_SECRET_KEY'),
        'webhook_token' => env('XENDIT_WEBHOOK_TOKEN'),
        'internal_secret' => env('INTERNAL_WEBHOOK_SECRET', 'cravacloud_internal_xnd_token_38c92a'),
        'wagateway_webhook_url' => env('WAGATEWAY_WEBHOOK_URL', 'https://wa.cravacloud.id/api/payment/xendit/callback'),
        'buildclient_webhook_url' => env('BUILDCLIENT_WEBHOOK_URL', 'https://client.cravacloud.id/api/payment/xendit/callback'),
    ],

];


