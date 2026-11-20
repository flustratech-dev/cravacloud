<?php

namespace App\Services\Provisioning\Tencent;

/**
 * TC3-HMAC-SHA256 signer for Tencent Cloud API v3 (native, no SDK).
 *
 * Reference: official "Signature Method v3" documentation. The canonical
 * request layout and derivation steps below mirror the official PHP demo
 * verbatim; unit tests assert against the published worked-example vectors.
 *
 * SECURITY: this class never logs or exposes the secret key. The secret id
 * only appears inside the Authorization header value (as required by the
 * protocol) and must never be written to logs by callers.
 */
class Tc3Signer
{
    public const ALGORITHM = 'TC3-HMAC-SHA256';

    public const SIGNED_HEADERS = 'content-type;host;x-tc-action';

    public const CONTENT_TYPE = 'application/json; charset=utf-8';

    public function __construct(
        private readonly string $secretId,
        private readonly string $secretKey,
    ) {
    }

    /**
     * Sign a POST JSON request.
     *
     * @return array{authorization: string, signature: string, timestamp: int, headers: array<string, string>}
     */
    public function sign(string $service, string $host, string $action, string $payloadJson, ?int $timestamp = null): array
    {
        $timestamp ??= time();
        // Date MUST be derived from the timestamp in UTC (gmdate), never local timezone.
        $date = gmdate('Y-m-d', $timestamp);
        $credentialScope = $date.'/'.$service.'/tc3_request';

        $stringToSign = implode("\n", [
            self::ALGORITHM,
            (string) $timestamp,
            $credentialScope,
            $this->canonicalRequestHash($host, $action, $payloadJson),
        ]);

        $secretDate = hash_hmac('sha256', $date, 'TC3'.$this->secretKey, true);
        $secretService = hash_hmac('sha256', $service, $secretDate, true);
        $secretSigning = hash_hmac('sha256', 'tc3_request', $secretService, true);
        $signature = hash_hmac('sha256', $stringToSign, $secretSigning);

        $authorization = self::ALGORITHM
            .' Credential='.$this->secretId.'/'.$credentialScope
            .', SignedHeaders='.self::SIGNED_HEADERS
            .', Signature='.$signature;

        return [
            'authorization' => $authorization,
            'signature' => $signature,
            'timestamp' => $timestamp,
            'headers' => [
                'Authorization' => $authorization,
                'Content-Type' => self::CONTENT_TYPE,
                'X-TC-Action' => $action,
                'X-TC-Timestamp' => (string) $timestamp,
            ],
        ];
    }

    /**
     * SHA-256 of the canonical request (key-independent; used by tests/probe
     * to verify canonicalization against the official published vector).
     */
    public function canonicalRequestHash(string $host, string $action, string $payloadJson): string
    {
        $canonicalHeaders = implode("\n", [
            'content-type:'.self::CONTENT_TYPE,
            'host:'.$host,
            'x-tc-action:'.strtolower($action),
            '',
        ]);

        $canonicalRequest = implode("\n", [
            'POST',
            '/',
            '',
            $canonicalHeaders,
            self::SIGNED_HEADERS,
            hash('sha256', $payloadJson),
        ]);

        return hash('sha256', $canonicalRequest);
    }
}