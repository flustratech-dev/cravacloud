@extends('emails.layouts.master', [
    'subject' => '[PERINGATAN SLA] Tiket #' . $ticket->id . ' Melewati Batas Respons — ' . $ticket->subject,
    'badgeText' => 'PERINGATAN SLA',
])

@section('content')
<h1 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 700; color: #000000; line-height: 1.3; letter-spacing: -0.3px;">
    Peringatan Batas Waktu SLA Tiket Terlampaui
</h1>

<p style="margin: 0 0 20px 0; font-size: 14px; line-height: 1.6; color: #52525b;">
    Halo Admin CravaCloud,<br>
    Tiket bantuan pelanggan di bawah ini telah melewati batas respons SLA (Service Level Agreement) yang ditentukan dan membutuhkan respons segera dari tim teknis:
</p>

<!-- SLA Breach Details Box -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #fafafa; border: 1px solid #e4e4e7; border-radius: 6px; margin-bottom: 24px; font-size: 13px;">
    <tr>
        <td style="padding: 12px 18px; border-bottom: 1px solid #e4e4e7; background-color: #f4f4f5; border-top-left-radius: 6px; border-top-right-radius: 6px;">
            <strong style="color: #000000; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Informasi Tiket &amp; SLA</strong>
        </td>
    </tr>
    <tr>
        <td style="padding: 16px 18px;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="padding: 6px 0; color: #71717a; width: 140px;">Nomor Tiket:</td>
                    <td style="padding: 6px 0; color: #000000; font-weight: 700; font-family: Consolas, Monaco, monospace;">
                        #{{ $ticket->id }}
                    </td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Subjek:</td>
                    <td style="padding: 6px 0; color: #000000; font-weight: 600;">{{ $ticket->subject }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Pelanggan:</td>
                    <td style="padding: 6px 0; color: #000000;">
                        <strong>{{ $ticket->customer?->full_name ?? 'Pelanggan' }}</strong> ({{ $ticket->customer?->email ?? '-' }})
                    </td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Prioritas:</td>
                    <td style="padding: 6px 0; color: #000000; font-weight: 700;">{{ strtoupper($ticket->priority) }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Status:</td>
                    <td style="padding: 6px 0; color: #000000;">{{ strtoupper($ticket->status) }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Batas SLA:</td>
                    <td style="padding: 6px 0; color: #000000; font-weight: 600;">
                        {{ $ticket->sla_due_at ? \Carbon\Carbon::parse($ticket->sla_due_at)->timezone('Asia/Jakarta')->format('d M Y, H:i') . ' WIB' : '4 Jam setelah tiket dibuat' }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<p style="margin: 0 0 8px 0; font-size: 13px; line-height: 1.5; color: #71717a;">
    Segera tindak lanjuti tiket ini melalui panel admin untuk menjaga kepuasan pelanggan dan standar waktu pelayanan CravaCloud.
</p>

<!-- Solid Black Action Button -->
<table border="0" cellpadding="0" cellspacing="0" role="presentation" width="100%" style="margin: 28px 0 12px 0;">
    <tr>
        <td align="center">
            <table border="0" cellpadding="0" cellspacing="0" role="presentation">
                <tr>
                    <td align="center" bgcolor="#000000" style="border-radius: 6px; background-color: #000000;">
                        <a href="{{ rtrim(config('app.url', 'https://cravacloud.id'), '/') }}/admin/tickets/{{ $ticket->id }}" 
                           target="_blank" 
                           rel="noopener noreferrer" 
                           style="display: inline-block; padding: 12px 28px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 6px; background-color: #000000; border: 1px solid #000000; text-align: center;">
                            Buka &amp; Respons Tiket Sekarang &rarr;
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
@endsection
