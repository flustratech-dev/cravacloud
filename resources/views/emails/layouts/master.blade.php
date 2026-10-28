<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="id">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="color-scheme" content="light" />
    <meta name="supported-color-schemes" content="light" />
    <title>{{ $subject ?? config('app.name', 'CravaCloud') }}</title>
    <style type="text/css">
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
        body { margin: 0; padding: 0; width: 100% !important; min-width: 100%; background-color: #fafafa; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        @media only screen and (max-width: 600px) {
            .email-card { width: 100% !important; border-radius: 0 !important; border-left: 0 !important; border-right: 0 !important; }
            .card-body { padding: 24px 20px !important; }
            .action-button { width: 100% !important; box-sizing: border-box !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #fafafa; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; color: #18181b; -webkit-font-smoothing: antialiased;">
    <!-- Outer Container -->
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #fafafa; table-layout: fixed;">
        <tr>
            <td align="center" style="padding: 40px 16px 48px 16px;">
                
                <!-- Logo Header (Embedded CID for 100% Gmail reliability) -->
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 560px; margin-bottom: 24px;">
                    <tr>
                        <td align="center">
                            <a href="{{ rtrim(config('app.url', 'https://cravacloud.id'), '/') }}" target="_blank" rel="noopener noreferrer" style="text-decoration: none; display: inline-block;">
                                @if(isset($message) && file_exists(public_path('images/logo.png')))
                                    <img src="{{ $message->embed(public_path('images/logo.png')) }}" 
                                         alt="{{ config('app.name', 'CravaCloud') }}" 
                                         height="32" 
                                         style="height: 32px; max-height: 32px; width: auto; border: 0; display: block; margin: 0 auto; outline: none; text-decoration: none;" />
                                @else
                                    <span style="font-size: 20px; font-weight: 800; letter-spacing: -0.5px; color: #000000; text-transform: uppercase;">
                                        CRAVACLOUD
                                    </span>
                                @endif
                            </a>
                        </td>
                    </tr>
                </table>

                <!-- Main Card (Minimalist Monochrome Enterprise Style) -->
                <table border="0" cellpadding="0" cellspacing="0" width="100%" class="email-card" style="max-width: 560px; background-color: #ffffff; border: 1px solid #e4e4e7; border-radius: 8px; overflow: hidden;">
                    @if(isset($badgeText))
                    <tr>
                        <td style="padding: 24px 32px 0 32px;">
                            <span style="display: inline-block; background-color: #000000; color: #ffffff; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; padding: 4px 10px; border-radius: 4px;">
                                {{ $badgeText }}
                            </span>
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <td class="card-body" style="padding: {{ isset($badgeText) ? '16px 32px 32px 32px' : '32px' }};">
                            @yield('content')
                        </td>
                    </tr>
                </table>

                <!-- Minimalist Enterprise Footer -->
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 560px; margin-top: 24px;">
                    <tr>
                        <td align="center" style="padding: 0 16px; font-size: 12px; line-height: 1.6; color: #71717a; text-align: center;">
                            <p style="margin: 0 0 8px 0; color: #52525b;">
                                Bantuan teknis & pertanyaan: 
                                <a href="{{ rtrim(config('app.url', 'https://cravacloud.id'), '/') }}/dashboard/support" target="_blank" rel="noopener noreferrer" style="color: #000000; font-weight: 600; text-decoration: underline;">Pusat Bantuan</a> 
                                atau balas email ini.
                            </p>
                            <p style="margin: 0 0 6px 0; color: #71717a;">
                                &copy; {{ date('Y') }} <strong>{{ config('app.name', 'CravaCloud') }}</strong>. Hak Cipta Dilindungi.
                            </p>
                            <p style="margin: 0; font-size: 11px; color: #a1a1aa;">
                                Email sistem otomatis. Harap jaga kerahasiaan kata sandi dan kredensial akses Anda.
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</body>
</html>
