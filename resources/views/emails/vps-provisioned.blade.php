@extends('emails.layouts.master', [
    'subject' => 'Server VPS Anda Telah Aktif — ' . $instance->hostname,
    'badgeText' => 'SERVER AKTIF',
])

@section('content')
<h1 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 700; color: #000000; line-height: 1.3; letter-spacing: -0.3px;">
    Server VPS Anda Telah Aktif
</h1>

<p style="margin: 0 0 24px 0; font-size: 14px; line-height: 1.6; color: #52525b;">
    Halo <strong>{{ $user->full_name ?? 'Pelanggan CravaCloud' }}</strong>,<br>
    Server Virtual Private Server (VPS) Anda telah selesai dikonfigurasi dan saat ini berstatus <strong>Online (Running)</strong>.
</p>

<!-- Server Specs & Credentials Card (Minimalist Grayscale) -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #fafafa; border: 1px solid #e4e4e7; border-radius: 6px; margin-bottom: 24px; font-size: 13px;">
    <tr>
        <td style="padding: 12px 18px; border-bottom: 1px solid #e4e4e7; background-color: #f4f4f5; border-top-left-radius: 6px; border-top-right-radius: 6px;">
            <strong style="color: #000000; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Detail Kredensial Server</strong>
        </td>
    </tr>
    <tr>
        <td style="padding: 16px 18px;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="padding: 6px 0; color: #71717a; width: 140px;">Paket:</td>
                    <td style="padding: 6px 0; color: #000000; font-weight: 600;">{{ $instance->order?->vpsSpec?->name ?? 'Cloud VPS' }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Hostname:</td>
                    <td style="padding: 6px 0; color: #000000; font-family: Consolas, Monaco, monospace;">{{ $instance->hostname }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">IP Public:</td>
                    <td style="padding: 6px 0; color: #000000; font-family: Consolas, Monaco, monospace; font-weight: 700; font-size: 14px;">
                        {{ $instance->public_ip ?? 'Pending' }}
                    </td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Port SSH:</td>
                    <td style="padding: 6px 0; color: #000000; font-family: Consolas, Monaco, monospace;">{{ $instance->ssh_port ?? 22 }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Username:</td>
                    <td style="padding: 6px 0; color: #000000; font-family: Consolas, Monaco, monospace; font-weight: 600;">{{ $instance->ssh_user }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Sistem Operasi:</td>
                    <td style="padding: 6px 0; color: #000000;">{{ $instance->os_label }}</td>
                </tr>
                @if(!empty($instance->control_panel) && $instance->control_panel !== 'none')
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Control Panel:</td>
                    <td style="padding: 6px 0; color: #000000; font-weight: 600;">{{ $instance->control_panel_label }}</td>
                </tr>
                @endif
                @if(!empty($instance->app_url))
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">URL Aplikasi / Panel:</td>
                    <td style="padding: 6px 0; font-family: Consolas, Monaco, monospace;">
                        <a href="{{ $instance->app_url }}" target="_blank" rel="noopener noreferrer" style="color: #000000; font-weight: 600; text-decoration: underline;">
                            {{ $instance->app_url }}
                        </a>
                    </td>
                </tr>
                @endif
            </table>
        </td>
    </tr>
</table>

<!-- Minimalist Command Box (Pure Black on Light Gray) -->
<div style="background-color: #f4f4f5; border: 1px solid #e4e4e7; border-radius: 6px; padding: 12px 16px; margin-bottom: 24px;">
    <span style="color: #71717a; font-size: 12px; display: block; margin-bottom: 4px;">Akses cepat terminal (SSH):</span>
    <code style="color: #000000; font-family: Consolas, Monaco, monospace; font-size: 13px; font-weight: 600;">
        ssh {{ $instance->ssh_user }}{{ '@' }}{{ $instance->public_ip ?? 'IP_SERVER' }} -p {{ $instance->ssh_port ?? 22 }}
    </code>
</div>

<div style="background-color: #fafafa; border: 1px solid #e4e4e7; border-left: 3px solid #000000; border-radius: 6px; padding: 12px 16px; margin-bottom: 12px;">
    <p style="margin: 0; font-size: 13px; line-height: 1.5; color: #3f3f46;">
        <strong>Keamanan Kredensial SSH:</strong> Demi keamanan, password login SSH tidak dikirim melalui email. Buka <strong>Dashboard &rarr; Detail VPS &rarr; tab Akses</strong> (klik ikon mata) untuk menyalin password awal Anda, lalu segera ganti password setelah login pertama kali.
    </p>
</div>

<!-- Solid Black Action Button -->
<table border="0" cellpadding="0" cellspacing="0" role="presentation" width="100%" style="margin: 28px 0 12px 0;">
    <tr>
        <td align="center">
            <table border="0" cellpadding="0" cellspacing="0" role="presentation">
                <tr>
                    <td align="center" bgcolor="#000000" style="border-radius: 6px; background-color: #000000;">
                        <a href="{{ rtrim(config('app.url', 'https://cravacloud.id'), '/') }}/dashboard/vps/{{ $instance->id }}" 
                           target="_blank" 
                           rel="noopener noreferrer" 
                           style="display: inline-block; padding: 12px 28px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 6px; background-color: #000000; border: 1px solid #000000; text-align: center;">
                            Buka Dashboard Server &rarr;
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
@endsection
