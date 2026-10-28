<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers untuk semua response.
 *
 * - X-Frame-Options: SAMEORIGIN (cegah clickjacking)
 * - X-Content-Type-Options: nosniff
 * - Referrer-Policy: strict-origin-when-cross-origin
 * - Permissions-Policy: minimal
 * - Strict-Transport-Security: hanya di production HTTPS
 * - Content-Security-Policy: default aman (frame-ancestors 'none')
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=(), payment=(self), usb=(), interest-cohort=()'
        );

        // HSTS hanya kalau HTTPS (jangan set di HTTP dev supaya browser tidak terjebak).
        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // Basic CSP. Tailwind/Alpine di-load via Vite bundle sendiri,
        // jadi self + inline hash friendly. Bisa di-tune per environment.
        //
        // Di local dev, Vite dev server serve asset dari 127.0.0.1:5173
        // (plus WebSocket HMR di ws://127.0.0.1:5173). Kita whitelist origin itu
        // hanya kalau APP_ENV=local. Production tetap ketat.
        if (!$response->headers->has('Content-Security-Policy')) {
            $devViteHttp = '';
            $devViteWs = '';
            if (app()->environment('local')) {
                $devViteHttp = ' http://127.0.0.1:5173 http://localhost:5173';
                $devViteWs = ' ws://127.0.0.1:5173 ws://localhost:5173';
            }

            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self'; " .
                "img-src 'self' data: blob:{$devViteHttp}; " .
                "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://challenges.cloudflare.com https://static.cloudflareinsights.com https://app.midtrans.com https://app.sandbox.midtrans.com{$devViteHttp}; " .
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com{$devViteHttp}; " .
                "font-src 'self' https://fonts.gstatic.com data:{$devViteHttp}; " .
                "frame-src 'self' https://challenges.cloudflare.com https://app.midtrans.com https://app.sandbox.midtrans.com https://checkout.xendit.co https://*.xendit.co; " .
                "connect-src 'self' https://challenges.cloudflare.com https://cloudflareinsights.com https://*.lynk.id https://lynk.id https://app.midtrans.com https://app.sandbox.midtrans.com https://checkout.xendit.co https://*.xendit.co{$devViteHttp}{$devViteWs}; " .
                "frame-ancestors 'self'; " .
                "base-uri 'self'; " .
                "form-action 'self' https://*.lynk.id https://lynk.id https://app.midtrans.com https://app.sandbox.midtrans.com https://checkout.xendit.co https://*.xendit.co;"
            );
        }

        return $response;
    }
}
