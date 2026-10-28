@extends('emails.layouts.master', [
    'subject' => 'Informasi Akun CravaCloud',
    'badgeText' => 'AKUN DIAKTIFKAN',
])

@section('content')
<h1 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 700; color: #000000; line-height: 1.3; letter-spacing: -0.3px;">
    Selamat Datang di CravaCloud
</h1>

<p style="margin: 0 0 24px 0; font-size: 14px; line-height: 1.6; color: #52525b;">
    Halo <strong>{{ $user->full_name ?? 'Pelanggan CravaCloud' }}</strong>,<br>
    Akun CravaCloud Anda telah siap digunakan. Anda dapat mengakses dashboard untuk mengelola server dan layanan Anda.
</p>

<!-- Account Credentials Box -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #fafafa; border: 1px solid #e4e4e7; border-radius: 6px; margin-bottom: 24px; font-size: 13px;">
    <tr>
        <td style="padding: 12px 18px; border-bottom: 1px solid #e4e4e7; background-color: #f4f4f5; border-top-left-radius: 6px; border-top-right-radius: 6px;">
            <strong style="color: #000000; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Informasi Login Dashboard</strong>
        </td>
    </tr>
    <tr>
        <td style="padding: 16px 18px;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="padding: 6px 0; color: #71717a; width: 140px;">URL Login:</td>
                    <td style="padding: 6px 0; color: #000000; font-weight: 600;">
                        <a href="{{ rtrim(config('app.url', 'https://cravacloud.id'), '/') }}/login" style="color: #000000; text-decoration: underline;">
                            {{ rtrim(config('app.url', 'https://cravacloud.id'), '/') }}/login
                        </a>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Username:</td>
                    <td style="padding: 6px 0; color: #000000; font-family: Consolas, Monaco, monospace; font-weight: 700;">{{ $user->username }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Email:</td>
                    <td style="padding: 6px 0; color: #000000;">{{ $user->email }}</td>
                </tr>
                @if(isset($plainPassword) && !empty($plainPassword))
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Password Awal:</td>
                    <td style="padding: 6px 0;">
                        <span style="display: inline-block; background-color: #f4f4f5; color: #000000; border: 1px solid #d4d4d8; padding: 2px 8px; border-radius: 4px; font-family: Consolas, Monaco, monospace; font-weight: 700; font-size: 13px;">
                            {{ $plainPassword }}
                        </span>
                    </td>
                </tr>
                @endif
            </table>
        </td>
    </tr>
</table>

<p style="margin: 0 0 8px 0; font-size: 13px; line-height: 1.5; color: #71717a;">
    Silakan segera login dan ganti password Anda melalui menu pengaturan profil untuk keamanan akun Anda.
</p>

<!-- Solid Black Action Button -->
<table border="0" cellpadding="0" cellspacing="0" role="presentation" width="100%" style="margin: 28px 0 12px 0;">
    <tr>
        <td align="center">
            <table border="0" cellpadding="0" cellspacing="0" role="presentation">
                <tr>
                    <td align="center" bgcolor="#000000" style="border-radius: 6px; background-color: #000000;">
                        <a href="{{ rtrim(config('app.url', 'https://cravacloud.id'), '/') }}/login" 
                           target="_blank" 
                           rel="noopener noreferrer" 
                           style="display: inline-block; padding: 12px 28px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 6px; background-color: #000000; border: 1px solid #000000; text-align: center;">
                            Login ke Dashboard &rarr;
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
@endsection
