{{--
    Pembungkus surel berbasis template yang disunting admin.
    Isi ditampilkan dengan {{ }} sehingga otomatis di-escape.
    nl2br dipakai agar pergantian baris tetap terlihat tanpa mengizinkan HTML.
--}}
@extends('emails.layouts.master', [
    'subject' => $subject,
    'badgeText' => $badgeText,
])

@section('content')
<div style="font-size: 14px; line-height: 1.7; color: #52525b; white-space: pre-line;">{{ $bodyText }}</div>

<table border="0" cellpadding="0" cellspacing="0" style="margin-top: 28px;">
    <tr>
        <td align="center" style="border-radius: 6px; background-color: #000000;">
            <a href="{{ rtrim(config('app.url', 'https://cravacloud.id'), '/') }}/dashboard"
               target="_blank" rel="noopener noreferrer" class="action-button"
               style="display: inline-block; padding: 12px 28px; font-size: 13px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 6px;">
                Buka Dasbor
            </a>
        </td>
    </tr>
</table>
@endsection
