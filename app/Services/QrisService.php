<?php

namespace App\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class QrisService
{
    /**
     * Base static payload QRIS DANA Bisnis CravaCloud (MERCHANT).
     */
    public const BASE_PAYLOAD = 'QRIS_PAYLOAD_PLACEHOLDER Tangerang 61051522463045BF1';

    /**
     * Generate dynamic QRIS string with specific amount.
     */
    public function generatePayload(float|int $amount): string
    {
        $payload = self::BASE_PAYLOAD;

        // 1. Ubah Tag 010211 (Static) menjadi 010212 (Dynamic)
        if (str_contains($payload, '010211')) {
            $payload = preg_replace('/010211/', '010212', $payload, 1);
        }

        // 2. Format Tag 54 (Transaction Amount)
        $amountInt = (string) (int) round($amount);
        $amountLength = str_pad((string) strlen($amountInt), 2, '0', STR_PAD_LEFT);
        $tag54 = '54' . $amountLength . $amountInt;

        // 3. Potong checksum lama (Tag 63 dan 4 char hex berikutnya)
        if (preg_match('/6304[A-Fa-f0-9]{4}$/', $payload)) {
            $payload = substr($payload, 0, -8);
        }

        // 4. Sisipkan Tag 54 sebelum Tag 5802ID
        $pos58 = strpos($payload, '5802ID');
        if ($pos58 !== false) {
            $payload = substr($payload, 0, $pos58) . $tag54 . substr($payload, $pos58);
        } else {
            // Fallback: setelah 5303360
            $pos53 = strpos($payload, '5303360');
            if ($pos53 !== false) {
                $posAfter53 = $pos53 + strlen('5303360');
                $payload = substr($payload, 0, $posAfter53) . $tag54 . substr($payload, $posAfter53);
            }
        }

        // 5. Tambahkan Tag 6304 dan hitung CRC16-CCITT
        $payloadWithTag63 = $payload . '6304';
        $crc = $this->calculateCrc16($payloadWithTag63);

        return $payloadWithTag63 . $crc;
    }

    /**
     * Calculate CRC16-CCITT (polynomial 0x1021, init 0xFFFF).
     */
    public function calculateCrc16(string $str): string
    {
        $crc = 0xFFFF;
        $len = strlen($str);

        for ($c = 0; $c < $len; $c++) {
            $crc ^= ord($str[$c]) << 8;
            for ($i = 0; $i < 8; $i++) {
                if ($crc & 0x8000) {
                    $crc = ($crc << 1) ^ 0x1021;
                } else {
                    $crc = $crc << 1;
                }
                $crc &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    /**
     * Generate base64 SVG data URI for embedding directly in <img src="...">.
     */
    public function generateDataUri(float|int $amount): string
    {
        $payload = $this->generatePayload($amount);
        return (new QRCode())->render($payload);
    }
}
