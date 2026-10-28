@extends('emails.layouts.master', [
    'subject' => '[SUPPORT] ' . ($eventType === 'created' ? 'Tiket Baru' : 'Balasan Pelanggan') . ' #' . $ticket->id,
    'badgeText' => 'SUPPORT TIKET',
])

@section('content')
<h1 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 700; color: #000000; line-height: 1.3; letter-spacing: -0.3px;">
    {{ $eventType === 'created' ? 'Tiket Bantuan Baru Masuk' : 'Balasan Baru dari Pelanggan' }}
</h1>

<p style="margin: 0 0 20px 0; font-size: 14px; line-height: 1.6; color: #52525b;">
    Halo Admin CravaCloud,<br>
    Pelanggan <strong>{{ $ticket->customer->full_name ?? 'Pelanggan' }}</strong> ({{ $ticket->customer->email ?? '-' }}) telah mengirimkan pesan:
</p>

<!-- Ticket Details Box -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #fafafa; border: 1px solid #e4e4e7; border-radius: 6px; margin-bottom: 20px; font-size: 13px;">
    <tr>
        <td style="padding: 12px 18px; border-bottom: 1px solid #e4e4e7; background-color: #f4f4f5; border-top-left-radius: 6px; border-top-right-radius: 6px;">
            <strong style="color: #000000; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Tiket #{{ $ticket->id }}</strong>
        </td>
    </tr>
    <tr>
        <td style="padding: 16px 18px;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="padding: 6px 0; color: #71717a; width: 120px;">Subjek:</td>
                    <td style="padding: 6px 0; color: #000000; font-weight: 600;">{{ $ticket->subject }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Prioritas:</td>
                    <td style="padding: 6px 0; color: #000000;">{{ strtoupper($ticket->priority) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- Message Content -->
<div style="background-color: #fafafa; border: 1px solid #e4e4e7; border-left: 3px solid #000000; border-radius: 4px; padding: 14px 16px; margin-bottom: 24px; font-size: 13px; line-height: 1.6; color: #18181b;">
    {!! nl2br(e($userMessage)) !!}
</div>

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
                            Buka Tiket di Admin Panel &rarr;
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
@endsection
