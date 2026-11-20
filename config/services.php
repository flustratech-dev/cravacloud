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
        'merchant_id' => env('MIDTRANS_MERCHANT_ID'),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
    ],

    // Provisioning provider config (Poin 4).
    // Default 'manual' = admin input IP/password sendiri (behavior existing).
    // Provider real (mis. supplier, biznetgio) tinggal ditambah adapter dan
    // register di ProviderManager::createXxxDriver().
    'provisioning' => [
        'default' => env('PROVISIONING_DRIVER', 'manual'),
    ],

    /*
    | Tencent Cloud — internal infrastructure provider (WHITE-LABEL: never
    | expose these names/values to customer-facing output; public label stays
    | 'cravacloud' via Order::customerProviderLabel()).
    | Credentials come from env only; never logged, never committed.
    */
    'tencent' => [
        'secret_id' => env('TENCENT_SECRET_ID'),
        'secret_key' => env('TENCENT_SECRET_KEY'),
        'region' => env('TENCENT_REGION', 'ap-jakarta'),
        'timeout' => env('TENCENT_API_TIMEOUT', 30),

        'endpoints' => [
            'lighthouse' => 'lighthouse.intl.tencentcloudapi.com',
            'tat' => 'tat.intl.tencentcloudapi.com',
            'billing' => 'billing.intl.tencentcloudapi.com',
        ],

        'versions' => [
            'lighthouse' => '2020-03-24',
            'tat' => '2020-10-28',
            'billing' => '2018-07-09',
        ],

        // Services that must NOT receive an X-TC-Region header.
        'regionless_services' => ['billing'],

        // BalanceGuard (Phase 2): warn/block thresholds.
        'low_balance_usd' => env('TENCENT_LOW_BALANCE_USD', 20),
        'quota_warn_pct' => env('TENCENT_QUOTA_WARN_PCT', 80),
        'balance_cache_ttl' => 60,
        // DescribeAccountBalance returns cents on international accounts.
        // UNIT NOT YET VERIFIED against a non-zero balance (account was $0 at
        // Phase 1 probe) — re-verify via tencent:probe --live after top-up
        // before trusting BalanceGuard thresholds (D9).
        'balance_divisor' => env('TENCENT_BALANCE_DIVISOR', 100),

        // Verified live bundle prices USD/month (tencent:probe, 2026-11-19).
        // Used for HPP guards (D9) and SupplierPurchase actual_cost (Phase 3).
        'bundle_prices_usd' => [
            'bundle_starter_nmc_lin_med2_01' => 4.20,
            'bundle_starter_nmc_lin_med4_01' => 6.00,
            'bundle_starter_nmc_lin_med8_01' => 10.00,
            'bundle_starter_nmc_lin_med8_02' => 12.50,
            'bundle_gen_nmc_lin_lg8_01' => 36.00,
            'bundle_gen_nmc_lin_lg16_01' => 54.00,
            'bundle_gen_nmc_lin_2xlg16_01' => 78.00,
            'bundle_gen_nmc_lin_2xlg32_01' => 105.00,
        ],

        // Verified Linux blueprints in ap-jakarta & ap-singapore (live DescribeBlueprints,
        // D2: non-EOL Linux only; bundles SupportWindowsPlatform=false).
        'blueprints' => [
            'ubuntu2404' => 'lhbp-1l4ptuvm',
            'ubuntu2404_docker' => 'lhbp-2cacsycc',
            'ubuntu2204' => 'lhbp-qnuz61zs',
            'ubuntu2604' => 'lhbp-qg4ymn4o',
            'ubuntu2004' => 'lhbp-flkras26',
            'debian13' => 'lhbp-36w1bvaq',
            'debian13_docker' => 'lhbp-4f0qbogu',
            'debian12' => 'lhbp-mxml4cnq',
            'debian11' => 'lhbp-kfo8v0qz',
            'rocky10' => 'lhbp-4abndg66',
            'rocky94' => 'lhbp-8g1z9f6a',
            'centos_stream9' => 'lhbp-ne7v7wse',
            'centos_stream9_docker' => 'lhbp-7abg2z5i',
        ],

        // Default SSH login user per OS family (Lighthouse Ubuntu uses 'ubuntu';
        // Debian, Rocky Linux, and CentOS Stream use 'root').
        'default_users' => [
            'ubuntu' => 'ubuntu',
            'debian' => 'root',
            'rocky' => 'root',
            'centos' => 'root',
        ],

        // Provisioning polling (Phase 3). Interval 0 disables sleeping —
        // used by the Http::fake() feature tests. poll_max_seconds bounds a
        // single job attempt; exceeding it throws a RETRYABLE exception so
        // the queue job resumes from its persistent checkpoint later.
        'poll_interval_seconds' => env('TENCENT_POLL_INTERVAL', 5),
        'poll_max_seconds' => env('TENCENT_POLL_MAX', 240),

        // Lifecycle retention (D5, Phase 4) — no hardcoded 7-day supplier
        // limit for the tencent flow. Isolated (recycle bin) instances stay
        // recoverable via RenewInstances for recycle_bin_retention_days;
        // permanent termination additionally waits terminate_safety_days
        // past the upstream expiry (BillingTerminateExpiredCommand uses
        // min(customer grace deadline, supplier_expires_at + safety)).
        'recycle_bin_retention_days' => env('TENCENT_RECYCLE_BIN_RETENTION_DAYS', 15),
        'terminate_safety_days' => env('TENCENT_TERMINATE_SAFETY_DAYS', 12),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        'site_verification' => env('GOOGLE_SITE_VERIFICATION'),
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

];


