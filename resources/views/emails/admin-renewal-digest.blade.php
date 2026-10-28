@extends('emails.layouts.master', [
    'subject' => $subject,
    'badgeText' => 'DIGEST PERPANJANGAN',
])

@section('content')
<h1 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 700; color: #000000; line-height: 1.3; letter-spacing: -0.3px;">
    Ringkasan Perpanjangan Harian
</h1>

<p style="margin: 0 0 20px 0; font-size: 14px; line-height: 1.6; color: #52525b;">
    Halo Admin CravaCloud,<br>
    Berikut ringkasan {{ $total }} layanan yang memerlukan perhatian hari ini,
    {{ now()->timezone('Asia/Jakarta')->format('d M Y') }}.
</p>

<!-- Ringkasan Angka -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #fafafa; border: 1px solid #e4e4e7; border-radius: 6px; margin-bottom: 24px; font-size: 13px;">
    <tr>
        <td style="padding: 16px 18px;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                @foreach($sections as $key => $label)
                <tr>
                    <td style="padding: 6px 0; color: #71717a; width: 70%;">{{ $label }}</td>
                    <td style="padding: 6px 0; color: #18181b; font-weight: 700; text-align: right;">
                        {{ $counts[$key] ?? 0 }}
                    </td>
                </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>

@foreach($sections as $key => $label)
    @if(!empty($digest[$key]))
    <h2 style="margin: 24px 0 10px 0; font-size: 14px; font-weight: 700; color: #000000;">
        {{ $label }} ({{ count($digest[$key]) }})
    </h2>

    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border: 1px solid #e4e4e7; border-radius: 6px; margin-bottom: 8px; font-size: 12px; border-collapse: separate; border-spacing: 0;">
        <tr style="background-color: #f4f4f5;">
            <td style="padding: 8px 12px; font-weight: 700; color: #000000; border-bottom: 1px solid #e4e4e7;">Layanan</td>
            <td style="padding: 8px 12px; font-weight: 700; color: #000000; border-bottom: 1px solid #e4e4e7;">Pelanggan</td>
            <td style="padding: 8px 12px; font-weight: 700; color: #000000; border-bottom: 1px solid #e4e4e7;">Jatuh Tempo</td>
        </tr>
        @foreach($digest[$key] as $row)
        <tr>
            <td style="padding: 8px 12px; color: #18181b; border-bottom: 1px solid #f4f4f5;">
                <strong>{{ $row['hostname'] }}</strong><br>
                <span style="color: #a1a1aa; font-size: 11px;">{{ $row['type'] }} &middot; #{{ $row['id'] }}</span>
            </td>
            <td style="padding: 8px 12px; color: #52525b; border-bottom: 1px solid #f4f4f5;">
                {{ $row['customer'] }}<br>
                <span style="color: #a1a1aa; font-size: 11px;">{{ $row['email'] }}</span>
            </td>
            <td style="padding: 8px 12px; color: #52525b; border-bottom: 1px solid #f4f4f5;">
                {{ $row['expires_at'] }}<br>
                <span style="color: #a1a1aa; font-size: 11px;">Tenggang: {{ $row['grace_ends_at'] }}</span>
            </td>
        </tr>
        @endforeach
    </table>
    @endif
@endforeach

<table border="0" cellpadding="0" cellspacing="0" style="margin-top: 24px;">
    <tr>
        <td align="center" style="border-radius: 6px; background-color: #000000;">
            <a href="{{ rtrim(config('app.url', 'https://cravacloud.id'), '/') }}/admin/instances"
               target="_blank" rel="noopener noreferrer" class="action-button"
               style="display: inline-block; padding: 12px 28px; font-size: 13px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 6px;">
                Buka Panel Admin
            </a>
        </td>
    </tr>
</table>
@endsection
