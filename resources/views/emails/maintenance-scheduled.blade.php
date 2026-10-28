@extends('emails.layouts.master', [
    'subject' => $subject,
    'badgeText' => 'MAINTENANCE TERJADWAL',
])

@section('content')
<h1 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 700; color: #000000; line-height: 1.3; letter-spacing: -0.3px;">
    Pemberitahuan Maintenance Terjadwal
</h1>

<p style="margin: 0 0 20px 0; font-size: 14px; line-height: 1.6; color: #52525b;">
    Halo {{ $user->name ?? 'Pelanggan' }},<br>
    Kami akan melakukan pemeliharaan terjadwal pada infrastruktur CravaCloud. Mohon perhatikan jadwal berikut agar Anda dapat menyesuaikan aktivitas.
</p>

<!-- Jadwal Maintenance -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #fafafa; border: 1px solid #e4e4e7; border-radius: 6px; margin-bottom: 24px; font-size: 13px;">
    <tr>
        <td style="padding: 12px 18px; border-bottom: 1px solid #e4e4e7; background-color: #f4f4f5; border-top-left-radius: 6px; border-top-right-radius: 6px;">
            <strong style="color: #000000; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Detail Jadwal</strong>
        </td>
    </tr>
    <tr>
        <td style="padding: 16px 18px;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="padding: 6px 0; color: #71717a; width: 38%;">Kegiatan</td>
                    <td style="padding: 6px 0; color: #18181b; font-weight: 600;">{{ $window->title }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Mulai</td>
                    <td style="padding: 6px 0; color: #18181b; font-weight: 600;">{{ $startText }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Selesai</td>
                    <td style="padding: 6px 0; color: #18181b; font-weight: 600;">{{ $endText }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Estimasi Durasi</td>
                    <td style="padding: 6px 0; color: #18181b; font-weight: 600;">{{ $durationText }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@if(!empty($window->description))
<p style="margin: 0 0 20px 0; font-size: 14px; line-height: 1.6; color: #52525b;">
    {{ $window->description }}
</p>
@endif

<p style="margin: 0 0 24px 0; font-size: 13px; line-height: 1.6; color: #71717a;">
    Selama periode tersebut, sebagian layanan mungkin tidak dapat diakses sementara. Data dan konfigurasi Anda tetap aman.
</p>

<!-- Tombol Status -->
<table border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 8px;">
    <tr>
        <td align="center" style="border-radius: 6px; background-color: #000000;">
            <a href="{{ rtrim(config('app.url', 'https://cravacloud.id'), '/') }}/status"
               target="_blank" rel="noopener noreferrer" class="action-button"
               style="display: inline-block; padding: 12px 28px; font-size: 13px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 6px;">
                Pantau Halaman Status
            </a>
        </td>
    </tr>
</table>
@endsection
