@extends('emails.layouts.master', [
    'subject' => 'Pemberitahuan Penangguhan Layanan — ' . ($subscription->vpsSpec?->name ?? 'VPS'),
    'badgeText' => 'LAYANAN DITANGGUHKAN',
])

@section('content')
<h1 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 700; color: #000000; line-height: 1.3; letter-spacing: -0.3px;">
    Layanan Server Anda Ditangguhkan
</h1>

<p style="margin: 0 0 24px 0; font-size: 14px; line-height: 1.6; color: #52525b;">
    Halo <strong>{{ $user->full_name ?? 'Pelanggan CravaCloud' }}</strong>,<br>
    Layanan server VPS Anda telah ditangguhkan sementara karena tagihan perpanjangan telah melewati batas jatuh tempo.
</p>

<!-- Suspension Details Box -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #fafafa; border: 1px solid #e4e4e7; border-radius: 6px; margin-bottom: 24px; font-size: 13px;">
    <tr>
        <td style="padding: 12px 18px; border-bottom: 1px solid #e4e4e7; background-color: #f4f4f5; border-top-left-radius: 6px; border-top-right-radius: 6px;">
            <strong style="color: #000000; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Informasi Penangguhan</strong>
        </td>
    </tr>
    <tr>
        <td style="padding: 16px 18px;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="padding: 6px 0; color: #71717a; width: 140px;">Layanan:</td>
                    <td style="padding: 6px 0; color: #000000; font-weight: 600;">{{ $subscription->vpsSpec?->name ?? 'Cloud VPS' }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Tagihan Tertunggak:</td>
                    <td style="padding: 6px 0; color: #000000; font-weight: 700; font-size: 14px;">
                        Rp {{ number_format((float) $subscription->unit_amount, 0, ',', '.') }}
                    </td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Status Server:</td>
                    <td style="padding: 6px 0; color: #000000; font-weight: 600;">Suspended (Dihentikan)</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<p style="margin: 0 0 8px 0; font-size: 13px; line-height: 1.5; color: #71717a;">
    Data Anda masih tersimpan aman. Untuk mengaktifkan kembali server secara instan, silakan selesaikan pembayaran invoice perpanjangan Anda.
</p>

<!-- Solid Black Action Button -->
<table border="0" cellpadding="0" cellspacing="0" role="presentation" width="100%" style="margin: 28px 0 12px 0;">
    <tr>
        <td align="center">
            <table border="0" cellpadding="0" cellspacing="0" role="presentation">
                <tr>
                    <td align="center" bgcolor="#000000" style="border-radius: 6px; background-color: #000000;">
                        <a href="{{ rtrim(config('app.url', 'https://cravacloud.id'), '/') }}/dashboard/billing" 
                           target="_blank" 
                           rel="noopener noreferrer" 
                           style="display: inline-block; padding: 12px 28px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 6px; background-color: #000000; border: 1px solid #000000; text-align: center;">
                            Bayar &amp; Aktifkan Kembali Server &rarr;
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
@endsection
