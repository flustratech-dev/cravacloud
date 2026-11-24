<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ============================================================
// Billing lifecycle scheduler (Poin 3)
// ============================================================
// Semua command dijalankan harian. Waktu dipilih agar tidak tabrakan
// dan agar reminder terkirim di jam kerja Indonesia.

Schedule::command('billing:check-due')
    ->dailyAt('08:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Kirim reminder H-7/H-3/H-1/H+0 ke subscription yang mendekati jatuh tempo.');

Schedule::command('billing:renew')
    ->dailyAt('02:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Generate invoice renewal untuk subscription auto-renew.');

Schedule::command('billing:enter-grace')
    ->dailyAt('03:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Masukkan subscription past due ke grace period.');

Schedule::command('billing:suspend-overdue')
    ->dailyAt('04:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Suspend subscription yang grace period-nya habis.');

Schedule::command('billing:terminate-expired')
    ->dailyAt('05:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Terminate subscription yang suspended > 30 hari.');

// ============================================================
// PHASE 1 - Maintenance terjadwal (TIDAK dijadwalkan)
// ============================================================
// Fitur jadwal maintenance tidak dipakai, jadi maintenance:sync tidak lagi
// berjalan tiap 5 menit agar tidak membebani server. Jendela maintenance
// tetap bisa dimulai/diselesaikan manual dari Admin > Maintenance.
// Aktifkan kembali blok ini bila fitur jadwal otomatis mulai dipakai:
//
// Schedule::command('maintenance:sync')
//     ->everyFiveMinutes()
//     ->timezone('Asia/Jakarta')
//     ->withoutOverlapping()
//     ->onOneServer()
//     ->description('Mulai/akhiri jendela maintenance terjadwal dan kirim pemberitahuannya.');

// ============================================================
// PHASE 3 - Pengingat perpanjangan instance (DIGANTIKAN billing:check-due + billing:*)
// ============================================================
// Command legacy renewal:remind dimatikan dari jadwal agar tidak terjadi bypass
// lifecycle Tencent maupun pengingat ganda. Gunakan billing:check-due (08:00 WIB).

// ============================================================
// PHASE 5 - Peringatan waktu tanggap fulfillment
// ============================================================
// Diganti pengingat bertahap (WA + email): 30 menit → 1 jam → tiap 2 jam
// sampai order diserahkan. fulfillment:check-sla (sekali, email saja) tidak
// dijadwalkan lagi agar admin tidak menerima surel ganda; command-nya tetap
// ada untuk dijalankan manual.
// Auto-renew di Supplier dimatikan: admin memperpanjang manual setelah pelanggan bayar.
// Pagi: ringkasan lengkap. Siang/sore/malam: hanya yang mendesak (≤ 3 hari).
Schedule::command('supplier:remind-admin --daily')
    ->dailyAt('08:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Ringkasan pagi tugas perpanjangan di Supplier (WA + email).');

Schedule::command('supplier:remind-admin')
    ->cron('0 12,16,20 * * *')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Pengingat VPS yang harus segera diperpanjang di Supplier.');

Schedule::command('provisioning:alert-stuck')
    ->everyFifteenMinutes()
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Alert admin (WA + email) bila ada task provisioning macet >20m atau order otomatis tertahan >15m.');

// ============================================================
// Provisioning reconciliation (Phase 4D — aktif)
// ============================================================
// Sejak ada adapter tencent yang benar-benar memanggil API, reconcile
// dijalankan tiap 15 menit: sync status + IP, koreksi kuota (syncFromProvider),
// deteksi orphan crava-* (alert, TANPA auto-terminate) dan drift masa aktif.
// No-op penuh bila tidak ada instance non-manual, jadi deployment manual
// murni tidak memanggil API apa pun.
Schedule::command('provider:reconcile --auto-correct')
    ->everyFifteenMinutes()
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Rekonsiliasi status VPS, kuota, orphan, dan drift masa aktif (no-op tanpa instance non-manual).');

// ============================================================
// Akun tertaut: perubahan akun yang belum sampai ke CravaCloud WA Gateway
// dikirim ulang tiap menit. Percobaan pertamanya berjalan di ujung permintaan
// web; yang ini menangkap sisanya saat WA Gateway sedang deploy — kalau tidak,
// kata sandi yang diganti tepat saat itu tidak pernah sampai.
// ============================================================
Schedule::command('akun-tertaut:kirim')
    ->everyMinute()
    ->withoutOverlapping()
    ->description('Kirim ulang perubahan akun ke CravaCloud WA Gateway.');

// ============================================================
// PILAR 3 - Auto-Expire Order pending (TTL 24 Jam)
// ============================================================
Schedule::command('orders:expire-pending')
    ->hourly()
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Batalkan secara otomatis order pending yang melebihi batas waktu pembayaran 24 jam.');


// ============================================================
// Tencent integration Phase 2 — guard read-only (Rp0)
// ============================================================
// Sinkronisasi kuota pool (tencent:sync-quota) sejak Phase 4D sudah menjadi
// bagian dari provider:reconcile tiap 15 menit (syncFromProvider dipanggil di
// sana), jadi tidak dijadwalkan terpisah lagi agar tidak dobel beban API.
// Command-nya tetap tersedia untuk dijalankan manual kapan pun.

// Watchdog saldo upstream (D9): alert WA+email bila < $20 atau < HPP order
// pending terendah. Read-only; dedupe harian dengan eskalasi bila makin turun.
Schedule::command('tencent:check-balance')
    ->hourly()
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Cek saldo upstream dan alert bila di bawah threshold (read-only).');

// ============================================================
// Ticket SLA Watchdog (v2.1 — tiap 15 menit)
// ============================================================
Schedule::command('tickets:check-sla')
    ->everyFifteenMinutes()
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Cek tiket support yang melewati SLA respons admin & kirim alert WA+email.');

// ============================================================
// Midtrans Safety Net — Polling order pending (tiap 5 menit)
// ============================================================
// Jaring pengaman bila webhook X-Override-Notification terlambat atau
// gagal terkirim pada akun Midtrans bersama.
Schedule::command('midtrans:check-status')
    ->everyFiveMinutes()
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Sinkronisasi otomatis order pending Midtrans ke Core API sebagai fallback webhook.');
