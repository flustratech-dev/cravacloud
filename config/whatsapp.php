<?php

/*
|--------------------------------------------------------------------------
| Klien VexaHost / CravaCloud WhatsApp Gateway
|--------------------------------------------------------------------------
|
| Konfigurasi integrasi WhatsApp Gateway resmi (VexaHost WA Gateway API v1).
| Seluruh kredensial dan pengaturan dikelola penuh lewat file .env
| (WA_GATEWAY_*) tanpa override dari basis data agar konsisten di semua
| lingkungan (dev, staging, production).
|
| Pengiriman bersifat satu arah murni: dari Admin/Sistem ke Pengguna/Operator.
|
*/

return [

    // Status aktif pengiriman WhatsApp.
    'enabled' => filter_var(env('WA_GATEWAY_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    // Base URL VexaHost WA Gateway.
    'url' => rtrim((string) env('WA_GATEWAY_URL', 'https://wa.vexahostcloud.my.id'), '/'),

    // API key milik workspace aplikasi ini di dashboard gateway (X-Api-Key).
    'key' => env('WA_GATEWAY_KEY'),

    // Kosongkan untuk memakai sesi gateway pertama yang sedang terhubung.
    // Atau isi session ID spesifik jika ingin mengirim dari nomor tertentu.
    'session' => env('WA_GATEWAY_SESSION'),

    // Batas waktu koneksi dan respon HTTP (dalam detik).
    // Sengaja pendek agar notifikasi pelengkap tidak menahan proses bisnis.
    'timeout' => (int) env('WA_GATEWAY_TIMEOUT', 5),
    'connect_timeout' => (int) env('WA_GATEWAY_CONNECT_TIMEOUT', 3),

    // TTL cache hasil /api/v1/health (detik) agar polling admin tidak membanjiri gateway.
    'health_cache_seconds' => (int) env('WA_GATEWAY_HEALTH_CACHE_SECONDS', 30),

    // Signing secret untuk memverifikasi HMAC-SHA256 webhook masuk (X-VexaHost-Signature).
    'webhook_secret' => env('WA_GATEWAY_WEBHOOK_SECRET'),

];
