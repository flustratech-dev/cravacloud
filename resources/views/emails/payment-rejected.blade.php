@extends('emails.layouts.master', [
    'subject' => 'Pembayaran Tidak Terverifikasi — ' . ($invoice?->invoice_number ?? 'INV-' . $order->id),
    'badgeText' => 'PEMBAYARAN DITOLAK',
])

@section('content')
<h1 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 700; color: #000000; line-height: 1.3; letter-spacing: -0.3px;">
    Pembayaran Belum Terverifikasi
</h1>

<p style="margin: 0 0 24px 0; font-size: 14px; line-height: 1.6; color: #52525b;">
    Halo <strong>{{ $order->customer->full_name ?? 'Pelanggan CravaCloud' }}</strong>,<br>
    Mohon maaf, pembayaran untuk pesanan Anda belum dapat kami verifikasi pada mutasi sistem dan pesanan ini telah dibatalkan.
</p>

<!-- Invoice Details Box (Minimalist Grayscale) -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #fafafa; border: 1px solid #e4e4e7; border-radius: 6px; margin-bottom: 24px; font-size: 13px;">
    <tr>
        <td style="padding: 12px 18px; border-bottom: 1px solid #e4e4e7; background-color: #f4f4f5; border-top-left-radius: 6px; border-top-right-radius: 6px;">
            <strong style="color: #000000; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Rincian Transaksi</strong>
        </td>
    </tr>
    <tr>
        <td style="padding: 16px 18px;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="padding: 6px 0; color: #71717a; width: 140px;">Nomor Invoice:</td>
                    <td style="padding: 6px 0; color: #000000; font-weight: 700; font-family: Consolas, Monaco, monospace;">
                        {{ $invoice?->invoice_number ?? ('INV-' . $order->id) }}
                    </td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Paket Layanan:</td>
                    <td style="padding: 6px 0; color: #000000; font-weight: 600;">{{ $order->vpsSpec->name ?? 'VPS Hosting' }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Total Tagihan:</td>
                    <td style="padding: 6px 0; color: #000000; font-weight: 700; font-size: 15px;">
                        Rp {{ number_format((float) $order->amount, 0, ',', '.') }}
                    </td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Metode:</td>
                    <td style="padding: 6px 0; color: #000000;">{{ strtoupper(str_replace('_', ' ', $order->payment_method ?? 'QRIS')) }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Catatan / Alasan:</td>
                    <td style="padding: 6px 0; color: #dc2626; font-weight: 600;">{{ $reason ?? 'Mutasi transfer tidak ditemukan pada sistem DANA Bisnis' }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Tanggal:</td>
                    <td style="padding: 6px 0; color: #000000;">{{ now()->timezone('Asia/Jakarta')->translatedFormat('d F Y, H:i') }} WIB</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div style="background-color: #f4f4f5; border-left: 3px solid #000000; padding: 12px 16px; border-radius: 4px; margin-bottom: 24px;">
    <p style="margin: 0; font-size: 13px; line-height: 1.5; color: #3f3f46;">
        <strong>Informasi Tambahan:</strong> Jika Anda merasa sudah menyelesaikan pembayaran melalui gateway namun transaksi dibatalkan atau melewati batas waktu, silakan hubungi tim dukungan kami melalui tiket bantuan untuk pengecekan lebih lanjut.
    </p>
</div>

<!-- Solid Black Action Button -->
<table border="0" cellpadding="0" cellspacing="0" role="presentation" width="100%" style="margin: 28px 0 12px 0;">
    <tr>
        <td align="center">
            <table border="0" cellpadding="0" cellspacing="0" role="presentation">
                <tr>
                    <td align="center" bgcolor="#000000" style="border-radius: 6px; background-color: #000000;">
                        <a href="{{ rtrim(config('app.url', 'https://cravacloud.id'), '/') }}/dashboard/support" 
                           target="_blank" 
                           rel="noopener noreferrer" 
                           style="display: inline-block; padding: 12px 28px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 6px; background-color: #000000; border: 1px solid #000000; text-align: center;">
                            Hubungi Tim Support &rarr;
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
@endsection
