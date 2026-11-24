# Runbook Kepatuhan, Whitelist & Go-Live Midtrans Bersama (`M767390567`)

Dokumen ini menjadi panduan operasional penggunaan **1 akun Midtrans (`M767390567` — PT Flustra Finances Artha)** untuk dua produk: **Pesanvo** (WhatsApp CRM) dan **CravaCloud** (`https://cravacloud.com` — Cloud VPS Hosting).

---

## 1. Arsitektur Routing Webhook 3 Lapis

| Produk | Prefix `order_id` | Jalur Utama (Lapis 1) | Backup Relay (Lapis 2) | Polling Safety Net (Lapis 3) |
| :--- | :--- | :--- | :--- | :--- |
| **Pesanvo** | `PSV-*` | Payment Notification URL default di Dashboard Midtrans (`https://<domain-pesanvo>/api/payment/midtrans/notify`) + `X-Override-Notification` | — | Tombol "Cek status Midtrans" & halaman tagihan |
| **CravaCloud** | `CRV-*` | Header `X-Override-Notification: https://cravacloud.com/api/webhooks/payment` pada Snap API | Relay otomatis di `MidtransWebhookController` Pesanvo untuk setiap order berawalan `CRV-` → forward ke `MIDTRANS_CRAVACLOUD_FORWARD_URL` | Cron `php artisan midtrans:check-status` tiap **5 menit** (`--hours=48`) + tombol **Sinkron Midtrans** di `/admin/orders` |

### Keamanan Webhook CravaCloud
1. Cek `MIDTRANS_SERVER_KEY` (HTTP `500` bila kosong).
2. Verifikasi `signature_key` SHA-512 (`order_id + status_code + gross_amount + server_key`, HTTP `403` bila tidak cocok).
3. **Pull-verify ke Midtrans Core API (`GET /v2/{order_id}/status`)** sebelum memproses settlement/cancel/expire (`MIDTRANS_VERIFY_WEBHOOK_VIA_API=true`). Status & nominal transaksi diambil langsung dari Core API sebagai sumber kebenaran, sehingga webhook `pending` yang dipalsukan menjadi `settlement` tetap ditolak.
4. **Idempotency status-aware** di `webhook_events`: transaksi `pending -> settlement` atau `pending -> expire` dengan `transaction_id` yang sama diproses hingga tuntas, sementara duplikat `settlement` diabaikan.
5. **Amount & closed-order guard**: selisih nominal ditolak (`422` + alert admin), dan settlement pada order `cancelled`/`expired` tidak memaksa aktivasi otomatis melainkan mencatat transaksi + mengirim alert `payment.late_settled`.

---

## 2. Template Tiket / Email Whitelist ke Midtrans (`support@midtrans.com` / AM)

Kirim email ini menggunakan email terdaftar pemilik MID `M767390567` sebelum transaksi produksi dibuka ke publik agar payout tidak tertahan audit kepatuhan multi-domain:

> **Subjek:** Permohonan Penambahan Website (`cravacloud.com`) & Penyesuaian Display Name Merchant – MID `M767390567` (PT Flustra Finances Artha)
>
> Halo Tim Merchant Support / Account Manager Midtrans,
>
> Kami dari **PT Flustra Finances Artha** (Merchant ID: **`M767390567`**) ingin mengajukan dua pembaruan konfigurasi akun Midtrans berikut:
>
> 1. **Penambahan Domain / Lini Bisnis Resmi (Whitelist Multi-Domain):**
>    - Website tambahan: **`https://cravacloud.com`**
>    - Kategori layanan: Cloud VPS Hosting & Managed Infrastructure (lini produk resmi milik PT Flustra Finances Artha, melengkapi lini SaaS WhatsApp CRM Pesanvo yang sudah aktif di MID yang sama).
>    - Seluruh transaksi dari website ini akan membawa prefix `order_id` **`CRV-`** dan menggunakan header `X-Override-Notification` ke `https://cravacloud.com/api/webhooks/payment`.
>
> 2. **Penyesuaian Merchant Display Name pada Halaman Pembayaran Snap:**
>    - Mohon penyesuaian nama tampilan merchant di popup/halaman Snap menjadi **`PT Flustra Finances Artha`** agar konsisten bagi pelanggan Pesanvo maupun CravaCloud.
>
> Mohon informasikan bila ada dokumen tambahan (T&C, halaman kontak, atau URL preview `cravacloud.com`) yang perlu kami lampirkan untuk proses verifikasi.
>
> Terima kasih,  
> **Muhamad Ryan Rizki**  
> PT Flustra Finances Artha

---

## 3. Checklist Environment Coolify sebelum Go-Live

### A. Aplikasi CravaCloud (`https://cravacloud.com`)
Pastikan variable di Coolify terisi sesuai `.env.production` (nilai kunci aktif sama persis dengan MID `M767390567` — **jangan** tempel key ke git):
- `APP_URL=https://cravacloud.com`
- `SESSION_DOMAIN=.cravacloud.com`
- `GOOGLE_REDIRECT_URI="https://cravacloud.com/auth/google/callback"`
- `MAIL_ALLOWED_SENDER_DOMAINS=cravacloud.com,cravacloud.id`
- `MIDTRANS_SERVER_KEY=<Server Key Produksi M767390567>`
- `MIDTRANS_CLIENT_KEY=<Client Key Produksi M767390567>`
- `MIDTRANS_MERCHANT_ID=M767390567`
- `MIDTRANS_IS_PRODUCTION=true`
- `MIDTRANS_VERIFY_WEBHOOK_VIA_API=true`
- Pastikan container scheduler/cron (`php artisan schedule:run`) berjalan tiap menit agar `midtrans:check-status` aktif setiap 5 menit.
- Setelah deploy, buka `/admin/payment-gateways` dan klik tombol **Tes Koneksi** pada baris Midtrans (harus muncul pesan hijau *"Koneksi Midtrans berhasil (Server Key valid untuk mode Produksi)"*).

### B. Aplikasi Pesanvo (Relay Cadangan)
Pastikan variable di Coolify Pesanvo terisi:
- `MIDTRANS_ACTIVE=true`
- `MIDTRANS_IS_PRODUCTION=true`
- `MIDTRANS_MERCHANT_ID=M767390567`
- `MIDTRANS_CRAVACLOUD_FORWARD_URL=https://cravacloud.com/api/webhooks/payment`

---

## 4. SOP Uji Transaksi Live (1x Rp10.000)

Lakukan sebelum mengaktifkan promo atau iklan ke publik:
1. Pastikan `APP_URL=https://cravacloud.com` sudah mengarah ke server produksi dan DNS aktif.
2. Buat 1 akun uji & checkout paket termurah atau 1 order uji nominal minimal (mis. Rp10.000), pilih **QRIS / Virtual Account** agar ada jeda status `pending` → `settlement`.
3. **Cek log saat QR/VA terbit:** di `/admin/webhooks`, pastikan webhook `pending` tercatat dengan status `Berhasil` dan order tetap berstatus `pending`.
4. **Bayar QR/VA Rp10.000 tersebut** lewat e-wallet / m-banking.
5. **Verifikasi otomatis dalam ≤ 10 detik:**
   - Buka `/admin/webhooks`: event `event_id = transaction_id` berubah menjadi `event_type = settlement` dan status `Berhasil` (bukan ditolak `duplicate`).
   - Cek header log atau catatan `X-Override-Notification`: apabila notifikasi mendarat di Pesanvo dulu, pastikan di Admin Pesanvo → *Keuangan > Log Pembayaran* muncul log `forward:cravacloud:settlement` berstatus `processed`.
   - Buka `/admin/orders`: status order otomatis berubah menjadi **`paid`** (`paid_at` terisi) dan notifikasi WA/Email pembayaran diterima terkirim tepat 1x.
6. Batalkan/refund uji transaksi atau tandai sebagai transaksi tes internal di catatan keuangan bulan berjalan.

---

## 5. SOP Rekonsiliasi Bulanan (`PSV-` Split 60/40 vs `CRV-` 100% CravaCloud)

Setiap akhir bulan / tanggal 1 bulan berikutnya (WIB):
1. **Unduh CSV CravaCloud (`CRV-`):**
   - Buka Admin CravaCloud → **Payment Gateway** (`/admin/payment-gateways`).
   - Pilih bulan pada panel **Rekonsiliasi Bulanan Midtrans**, klik **Unduh CSV** (`rekonsiliasi-midtrans-cravacloud-YYYY-MM.csv`).
   - Catat total nominal bruto pada baris `TOTAL`.
2. **Unduh CSV Pesanvo (`PSV-`):**
   - Buka Admin Pesanvo → **Pendapatan** (`/admin/pendapatan`) dan ekspor CSV untuk rentang bulan yang sama (filter saluran `midtrans`).
3. **Cocokkan dengan Dashboard Midtrans:**
   - Unduh laporan transaksi / Payout CSV dari Dashboard Midtrans `M767390567`.
   - Pisahkan baris berdasarkan awalan `Order ID`:
     - **`CRV-*`** → harus cocok 100% dengan CSV CravaCloud (seluruh nilai bersih setelah MDR masuk ke pembukuan CravaCloud).
     - **`PSV-*`** → harus cocok 100% dengan CSV Pesanvo (hitung split **60/40** sesuai perjanjian Pesanvo).
   - Catat biaya MDR per transaksi agar transfer saldo bank dari rekening penampungan ke pos masing-masing bersih dan bebas selisih.
