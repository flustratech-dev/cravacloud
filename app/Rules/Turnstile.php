<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Turnstile implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = config('services.turnstile.secret');

        // Jika secret belum disetel, lewati validasi (mencegah lockout)
        if (empty($secret)) {
            return;
        }

        // Bypassed saat running unit test kecuali testing Turnstile aktif secara eksplisit
        if (app()->runningUnitTests() && !config('services.turnstile.testing_active', false)) {
            return;
        }

        if (empty($value) || !is_string($value)) {
            $fail('Verifikasi keamanan Cloudflare Turnstile diperlukan.');
            return;
        }

        try {
            $response = Http::asForm()
                ->timeout(6)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secret,
                    'response' => $value,
                    'remoteip' => request()->ip(),
                ]);

            if (!$response->successful() || !$response->json('success')) {
                $errorCodes = $response->json('error-codes', []);
                Log::warning('Cloudflare Turnstile verification failed.', [
                    'ip' => request()->ip(),
                    'error_codes' => $errorCodes,
                ]);

                $fail('Verifikasi keamanan gagal atau kadaluarsa. Silakan centang ulang verifikasi.');
            }
        } catch (\Throwable $e) {
            Log::error('Cloudflare Turnstile connection error: ' . $e->getMessage());
            $fail('Gagal menghubungi server Cloudflare Turnstile. Silakan coba beberapa saat lagi.');
        }
    }
}
