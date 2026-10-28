@extends('emails.layouts.master', [
    'subject' => 'Atur Ulang Kata Sandi Akun CravaCloud',
    'badgeText' => 'KEAMANAN AKUN',
])

@section('content')
<h1 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 700; color: #000000; line-height: 1.3; letter-spacing: -0.3px;">
    Atur Ulang Kata Sandi
</h1>

<p style="margin: 0 0 20px 0; font-size: 14px; line-height: 1.6; color: #52525b;">
    Halo <strong>{{ $user->full_name ?? 'Pelanggan CravaCloud' }}</strong>,<br>
    Kami menerima permintaan untuk mengatur ulang kata sandi akun CravaCloud Anda. Klik tombol di bawah ini untuk membuat kata sandi baru.
</p>

<!-- Solid Black Action Button -->
<table border="0" cellpadding="0" cellspacing="0" role="presentation" width="100%" style="margin: 28px 0 20px 0;">
    <tr>
        <td align="center">
            <table border="0" cellpadding="0" cellspacing="0" role="presentation">
                <tr>
                    <td align="center" bgcolor="#000000" style="border-radius: 6px; background-color: #000000;">
                        <a href="{{ $resetUrl }}" 
                           target="_blank" 
                           rel="noopener noreferrer" 
                           style="display: inline-block; padding: 12px 28px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 6px; background-color: #000000; border: 1px solid #000000; text-align: center;">
                            Atur Ulang Kata Sandi &rarr;
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<p style="margin: 0 0 8px 0; font-size: 13px; line-height: 1.5; color: #71717a;">
    Tautan ini hanya berlaku selama {{ config('auth.passwords.users.expire', 60) }} menit. Jika Anda tidak pernah meminta perubahan kata sandi, abaikan email ini dan akun Anda akan tetap aman.
</p>
@endsection
