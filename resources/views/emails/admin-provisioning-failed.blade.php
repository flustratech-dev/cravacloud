@extends('emails.layouts.master', [
    'subject' => 'ALERT: Provisioning Gagal — Order #' . ($task->order_id ?? ($order->id ?? 'N/A')),
    'badgeText' => 'ALERT TEKNIS',
])

@section('content')
<h1 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 700; color: #000000; line-height: 1.3; letter-spacing: -0.3px;">
    Peringatan: Provisioning VPS Gagal
</h1>

<p style="margin: 0 0 20px 0; font-size: 14px; line-height: 1.6; color: #52525b;">
    Halo Tim Teknis / Admin CravaCloud,<br>
    Sistem background worker gagal melakukan provisioning untuk pesanan berikut setelah seluruh batas percobaan habis. Harap segera lakukan tindakan atau provisioning manual.
</p>

<!-- Error Details Box (Strict Grayscale) -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #fafafa; border: 1px solid #e4e4e7; border-radius: 6px; margin-bottom: 24px; font-size: 13px;">
    <tr>
        <td style="padding: 12px 18px; border-bottom: 1px solid #e4e4e7; background-color: #f4f4f5; border-top-left-radius: 6px; border-top-right-radius: 6px;">
            <strong style="color: #000000; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Detail Masalah #{{ $task->id ?? ($order->id ?? 'ERR') }}</strong>
        </td>
    </tr>
    <tr>
        <td style="padding: 16px 18px;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="padding: 6px 0; color: #71717a; width: 140px;">Order ID:</td>
                    <td style="padding: 6px 0; color: #000000; font-weight: 700;">#{{ $task->order_id ?? ($order->id ?? '-') }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Provider Target:</td>
                    <td style="padding: 6px 0; color: #000000; font-weight: 600;">{{ strtoupper($task->provider ?? ($order->provider ?? 'DEFAULT')) }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Pesan Error:</td>
                    <td style="padding: 6px 0; color: #000000; font-family: Consolas, Monaco, monospace; font-size: 12px;">
                        {{ $errorMessage ?? ($task->error ?? 'Unknown error occurred during provisioning.') }}
                    </td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Percobaan:</td>
                    <td style="padding: 6px 0; color: #000000;">{{ $task->attempts ?? 1 }} / {{ $task->max_attempts ?? 3 }} kali</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- Solid Black Action Button -->
<table border="0" cellpadding="0" cellspacing="0" role="presentation" width="100%" style="margin: 28px 0 12px 0;">
    <tr>
        <td align="center">
            <table border="0" cellpadding="0" cellspacing="0" role="presentation">
                <tr>
                    <td align="center" bgcolor="#000000" style="border-radius: 6px; background-color: #000000;">
                        <a href="{{ rtrim(config('app.url', 'https://cravacloud.id'), '/') }}/admin/orders" 
                           target="_blank" 
                           rel="noopener noreferrer" 
                           style="display: inline-block; padding: 12px 28px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 6px; background-color: #000000; border: 1px solid #000000; text-align: center;">
                            Periksa di Admin Panel &rarr;
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
@endsection
