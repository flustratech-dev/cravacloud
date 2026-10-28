<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CravaCloud Business Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi bisnis CravaCloud. Ubah nilai-nilai ini sesuai dengan
    | data bisnis real Anda sebelum go-live ke production.
    |
    */

    // Nomor WhatsApp customer service (format internasional tanpa +)
    // Ganti dengan nomor WhatsApp Business real Anda
    'whatsapp' => env('CRAVACLOUD_WHATSAPP', '6285808749131'),

    // Email support
    'support_email' => env('CRAVACLOUD_SUPPORT_EMAIL', env('MAIL_SUPPORT_ADDRESS', 'support@cravacloud.id')),

    // Email admin/billing
    'admin_email' => env('CRAVACLOUD_ADMIN_EMAIL', 'support@cravacloud.id'),

    // Secret API Key untuk mengamankan endpoint /api/admin/*
    'admin_api_key' => env('ADMIN_API_KEY'),

    // Feature flag: simulasi pembayaran (hanya di local environment)
    'dev_simulate_payment' => env('APP_DEV_SIMULATE_PAYMENT', false),
];
