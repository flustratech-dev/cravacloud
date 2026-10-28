<?php

/*
|--------------------------------------------------------------------------
| Klien CravaCloud WhatsApp Gateway
|--------------------------------------------------------------------------
|
| Konfigurasi integrasi WhatsApp Gateway resmi CravaCloud.
| Mendukung kontrol penuh lewat panel admin (/admin/whatsapp). Nilai yang
| diubah di panel admin disimpan di basis data dan menimpa nilai di file ini.
|
| Pengiriman bersifat satu arah murni: dari Admin/Sistem ke Pengguna.
|
*/

return [

    // Status aktif pengiriman WhatsApp. Default false sampai admin mengisi API key.
    'enabled' => env('WA_GATEWAY_ENABLED', false),

    // URL CravaCloud WA Gateway.
    'url' => env('WA_GATEWAY_URL', 'https://wa.cravacloud.id'),

    // API key milik tenant/workspace aplikasi ini di dashboard gateway.
    // Dibiarkan null/kosong secara bawaan untuk diisi via panel admin.
    'key' => env('WA_GATEWAY_KEY', null),

    // Kosongkan untuk memakai sesi gateway pertama yang sedang terhubung.
    // Atau isi session ID spesifik jika ingin mengirim dari nomor tertentu.
    'session' => env('WA_GATEWAY_SESSION', null),

    // Batas waktu koneksi dan respon HTTP (dalam detik).
    'timeout' => (int) env('WA_GATEWAY_TIMEOUT', 10),
    'connect_timeout' => (int) env('WA_GATEWAY_CONNECT_TIMEOUT', 3),

];
