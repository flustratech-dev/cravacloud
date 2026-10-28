@extends('emails.layouts.master', [
    'subject' => 'Layanan Dihapus Permanen — ' . ($subscription->vpsSpec?->name ?? 'VPS'),
    'badgeText' => 'LAYANAN DIHAPUS',
])

@section('content')
<h1 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 700; color: #000000; line-height: 1.3; letter-spacing: -0.3px;">
    Layanan Server Telah Dihapus
</h1>

<p style="margin: 0 0 24px 0; font-size: 14px; line-height: 1.6; color: #52525b;">
    Halo <strong>{{ $user->full_name ?? 'Pelanggan CravaCloud' }}</strong>,<br>
    Masa tenggang perpanjangan (grace period) untuk server VPS Anda telah berakhir. Sesuai ketentuan layanan, instance server dan disk storage terkait telah dihapus secara permanen dari infrastruktur kami.
</p>

<!-- Terminated Details Box -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #fafafa; border: 1px solid #e4e4e7; border-radius: 6px; margin-bottom: 24px; font-size: 13px;">
    <tr>
        <td style="padding: 12px 18px; border-bottom: 1px solid #e4e4e7; background-color: #f4f4f5; border-top-left-radius: 6px; border-top-right-radius: 6px;">
            <strong style="color: #000000; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Rincian Layanan</strong>
        </td>
    </tr>
    <tr>
        <td style="padding: 16px 18px;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="padding: 6px 0; color: #71717a; width: 140px;">Paket:</td>
                    <td style="padding: 6px 0; color: #000000; font-weight: 600;">{{ $subscription->vpsSpec?->name ?? 'Cloud VPS' }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Status:</td>
                    <td style="padding: 6px 0; color: #000000; font-weight: 600;">Terminated (Dihapus)</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<p style="margin: 0 0 8px 0; font-size: 13px; line-height: 1.5; color: #71717a;">
    Jika Anda ingin menggunakan kembali layanan CravaCloud, Anda dapat melakukan pemesanan server baru kapan saja melalui dashboard.
</p>

<!-- Solid Black Action Button -->
<table border="0" cellpadding="0" cellspacing="0" role="presentation" width="100%" style="margin: 28px 0 12px 0;">
    <tr>
        <td align="center">
            <table border="0" cellpadding="0" cellspacing="0" role="presentation">
                <tr>
                    <td align="center" bgcolor="#000000" style="border-radius: 6px; background-color: #000000;">
                        <a href="{{ rtrim(config('app.url', 'https://cravacloud.id'), '/') }}/checkout" 
                           target="_blank" 
                           rel="noopener noreferrer" 
                           style="display: inline-block; padding: 12px 28px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 6px; background-color: #000000; border: 1px solid #000000; text-align: center;">
                            Pesan Server Baru &rarr;
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
@endsection
