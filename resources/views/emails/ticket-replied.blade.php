@extends('emails.layouts.master', [
    'subject' => '[Tiket #' . $ticket->id . '] Balasan dari Tim Support: ' . $ticket->subject,
    'badgeText' => 'SUPPORT TIKET',
])

@section('content')
<h1 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 700; color: #000000; line-height: 1.3; letter-spacing: -0.3px;">
    Tanggapan Tiket Bantuan #{{ $ticket->id }}
</h1>

<p style="margin: 0 0 20px 0; font-size: 14px; line-height: 1.6; color: #52525b;">
    Halo <strong>{{ $user->full_name ?? 'Pelanggan CravaCloud' }}</strong>,<br>
    Tim teknis CravaCloud telah memberikan balasan untuk tiket bantuan Anda mengenai <strong>"{{ $ticket->subject }}"</strong>:
</p>

<!-- Message Box -->
<div style="background-color: #fafafa; border: 1px solid #e4e4e7; border-left: 3px solid #000000; border-radius: 4px; padding: 16px 18px; margin-bottom: 24px; font-size: 14px; line-height: 1.6; color: #18181b;">
    {!! nl2br(e($replyMessage)) !!}
</div>

<p style="margin: 0 0 8px 0; font-size: 13px; line-height: 1.5; color: #71717a;">
    Anda dapat membalas pesan ini atau menutup tiket jika masalah sudah terselesaikan melalui dashboard.
</p>

<!-- Solid Black Action Button -->
<table border="0" cellpadding="0" cellspacing="0" role="presentation" width="100%" style="margin: 28px 0 12px 0;">
    <tr>
        <td align="center">
            <table border="0" cellpadding="0" cellspacing="0" role="presentation">
                <tr>
                    <td align="center" bgcolor="#000000" style="border-radius: 6px; background-color: #000000;">
                        <a href="{{ rtrim(config('app.url', 'https://cravacloud.id'), '/') }}/dashboard/support/{{ $ticket->id }}" 
                           target="_blank" 
                           rel="noopener noreferrer" 
                           style="display: inline-block; padding: 12px 28px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 6px; background-color: #000000; border: 1px solid #000000; text-align: center;">
                            Lihat &amp; Balas di Dashboard &rarr;
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
@endsection
