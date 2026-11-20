<?php

use App\Http\Controllers\Api\AdminApiController;
use App\Http\Controllers\Api\LinkedAccountController;
use App\Http\Controllers\Api\LinkedAccountLookupController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Middleware\VerifyLinkedAccountSignature;
use Illuminate\Support\Facades\Route;

// Midtrans Payment Webhook (public - diverifikasi dengan SHA-512 signature_key)
Route::match(['get', 'post'], '/webhooks/payment', [PaymentWebhookController::class, 'handle'])->name('api.webhooks.payment');

// Admin API Endpoints - PROTECTED with admin.api middleware (ApiKey or Admin Session)
Route::prefix('admin')->middleware('admin.api')->group(function () {
    Route::post('/vps/provision', [AdminApiController::class, 'provision'])->name('api.admin.vps.provision');
    Route::post('/shopee/process-order', [AdminApiController::class, 'processShopeeOrder'])->name('api.admin.shopee.process');
    Route::get('/vps/{id}/status', [AdminApiController::class, 'vpsStatus'])->name('api.admin.vps.status');
});

// Akun tertaut dengan CravaCloud WA Gateway — satu akun, dua aplikasi, hanya
// autentikasi. Dijaga tanda tangan HMAC dengan rahasia bersama
// (LINKED_ACCOUNTS_SECRET), bukan sesi atau API key admin.
Route::post('/internal/akun-tertaut', LinkedAccountController::class)
    ->middleware([VerifyLinkedAccountSignature::class, 'throttle:600,1'])
    ->name('api.akun-tertaut');

// WA Gateway menjemput akun untuk satu email saat pemiliknya masuk di sana dan
// akunnya belum sampai. Hanya hash yang diberikan; kata sandi dicocokkan di WA.
Route::post('/internal/akun-tertaut/cari', LinkedAccountLookupController::class)
    ->middleware([VerifyLinkedAccountSignature::class, 'throttle:120,1'])
    ->name('api.akun-tertaut.cari');
