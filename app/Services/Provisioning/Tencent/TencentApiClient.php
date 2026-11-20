<?php

namespace App\Services\Provisioning\Tencent;

use App\Services\Provisioning\Exceptions\ProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Generic Tencent Cloud API v3 caller (POST JSON, TC3-HMAC-SHA256).
 *
 * Design notes:
 *  - NO automatic HTTP-level retries here. Retrying non-idempotent actions
 *    (CreateInstances, RenewInstances) at transport level risks double
 *    purchase. Retry policy lives in the queue jobs + provider checkpoints.
 *  - Request payloads and credentials are NEVER logged (payloads may contain
 *    passwords). Only service/action/request-id/duration are logged.
 *  - Billing service is region-less (no X-TC-Region header).
 */
class TencentApiClient
{
    public function __construct(
        private readonly Tc3Signer $signer,
        private readonly array $config,
    ) {
    }

    /**
     * @return array The `Response` object on success.
     *
     * @throws ProviderException On API error (mapped) or network failure (retryable,
     *                           outcome ambiguous — caller must use checkpoints/adoption).
     */
    public function call(string $service, string $action, array $payload = [], ?string $region = null): array
    {
        $host = $this->config['endpoints'][$service] ?? null;
        $version = $this->config['versions'][$service] ?? null;

        if (! $host || ! $version) {
            throw new InvalidArgumentException("Unknown Tencent service [{$service}] — check config/services.php.");
        }

        $json = $payload === []
            ? '{}'
            : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $signed = $this->signer->sign($service, $host, $action, $json);

        $headers = $signed['headers'];
        $headers['X-TC-Version'] = $version;
        $headers['Host'] = $host;

        $regionless = $this->config['regionless_services'] ?? ['billing'];
        if (! in_array($service, $regionless, true)) {
            $headers['X-TC-Region'] = $region ?: ($this->config['region'] ?? 'ap-jakarta');
        }

        $started = microtime(true);

        try {
            $response = Http::timeout((int) ($this->config['timeout'] ?? 30))
                ->withHeaders($headers)
                ->withBody($json, Tc3Signer::CONTENT_TYPE)
                ->post('https://'.$host.'/');
        } catch (ConnectionException $e) {
            throw new ProviderException(
                "[{$service}/{$action}] network error: ".$e->getMessage(),
                true,
                'NetworkError',
                null,
                $e,
            );
        }

        $body = $response->json();
        $requestId = is_array($body) ? ($body['Response']['RequestId'] ?? null) : null;

        Log::debug('tencent.api', [
            'service' => $service,
            'action' => $action,
            'http_status' => $response->status(),
            'request_id' => $requestId,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);

        if (! is_array($body) || ! isset($body['Response']) || ! is_array($body['Response'])) {
            throw new ProviderException(
                "[{$service}/{$action}] malformed response (HTTP {$response->status()})",
                true,
                'MalformedResponse',
            );
        }

        $resp = $body['Response'];

        if (isset($resp['Error']) && is_array($resp['Error'])) {
            throw TencentErrorMapper::toException($service, $action, $resp['Error'], $requestId);
        }

        return $resp;
    }
}