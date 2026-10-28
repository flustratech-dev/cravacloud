@extends('emails.layouts.master', [
    'subject' => $subject,
    'badgeText' => 'WAKTU TANGGAP TERLEWATI',
])

@section('content')
<h1 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 700; color: #000000; line-height: 1.3; letter-spacing: -0.3px;">
    Order Menunggu Terlalu Lama
</h1>

<p style="margin: 0 0 20px 0; font-size: 14px; line-height: 1.6; color: #52525b;">
    Halo Admin CravaCloud,<br>
    {{ count($rows) }} order sudah dibayar lebih dari {{ $slaText }} tetapi layanannya belum diserahkan ke pelanggan.
</p>

<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border: 1px solid #e4e4e7; border-radius: 6px; margin-bottom: 24px; font-size: 12px; border-collapse: separate; border-spacing: 0;">
    <tr style="background-color: #f4f4f5;">
        <td style="padding: 8px 12px; font-weight: 700; color: #000000; border-bottom: 1px solid #e4e4e7;">Order</td>
        <td style="padding: 8px 12px; font-weight: 700; color: #000000; border-bottom: 1px solid #e4e4e7;">Pelanggan</td>
        <td style="padding: 8px 12px; font-weight: 700; color: #000000; border-bottom: 1px solid #e4e4e7;">Menunggu</td>
    </tr>
    @foreach($rows as $row)
    <tr>
        <td style="padding: 8px 12px; color: #18181b; border-bottom: 1px solid #f4f4f5;">
            <strong>#{{ $row['id'] }}</strong><br>
            <span style="color: #a1a1aa; font-size: 11px;">{{ $row['package'] }}</span>
        </td>
        <td style="padding: 8px 12px; color: #52525b; border-bottom: 1px solid #f4f4f5;">
            {{ $row['customer'] }}<br>
            <span style="color: #a1a1aa; font-size: 11px;">Dibayar {{ $row['paid_at'] }}</span>
        </td>
        <td style="padding: 8px 12px; color: #b91c1c; font-weight: 700; border-bottom: 1px solid #f4f4f5;">
            {{ $row['waited'] }}
        </td>
    </tr>
    @endforeach
</table>

<table border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td align="center" style="border-radius: 6px; background-color: #000000;">
            <a href="{{ rtrim(config('app.url', 'https://cravacloud.id'), '/') }}/admin/fulfillment"
               target="_blank" rel="noopener noreferrer" class="action-button"
               style="display: inline-block; padding: 12px 28px; font-size: 13px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 6px;">
                Buka Papan Fulfillment
            </a>
        </td>
    </tr>
</table>
@endsection
