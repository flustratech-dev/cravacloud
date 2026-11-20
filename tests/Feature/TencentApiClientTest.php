<?php

namespace Tests\Feature;

use App\Services\Provisioning\Exceptions\ProviderException;
use App\Services\Provisioning\Tencent\BillingClient;
use App\Services\Provisioning\Tencent\LighthouseClient;
use App\Services\Provisioning\Tencent\Tc3Signer;
use App\Services\Provisioning\Tencent\TencentApiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Transport-layer tests with Http::fake — zero real API calls.
 */
class TencentApiClientTest extends TestCase
{
    private function api(): TencentApiClient
    {
        return new TencentApiClient(
            new Tc3Signer('test-id', 'test-key'),
            (array) config('services.tencent'),
        );
    }

    public function test_lighthouse_call_sends_signed_headers_and_region(): void
    {
        Http::fake(['*' => Http::response(['Response' => ['TotalCount' => 0, 'RequestId' => 'r1']])]);

        $resp = $this->api()->call('lighthouse', 'DescribeInstances', ['Limit' => 1], 'ap-jakarta');

        $this->assertSame(0, $resp['TotalCount']);
        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://lighthouse.intl.tencentcloudapi.com/'
                && $request->hasHeader('X-TC-Action', 'DescribeInstances')
                && $request->hasHeader('X-TC-Version', '2020-03-24')
                && $request->hasHeader('X-TC-Region', 'ap-jakarta')
                && str_starts_with($request->header('Authorization')[0], 'TC3-HMAC-SHA256 Credential=test-id/')
                && $request->header('Content-Type')[0] === 'application/json; charset=utf-8'
                && $request->body() === '{"Limit":1}';
        });
    }

    public function test_tat_call_uses_tat_host_and_version(): void
    {
        Http::fake(['*' => Http::response(['Response' => ['RequestId' => 'r2']])]);

        $this->api()->call('tat', 'DescribeCommands', ['Limit' => 1]);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://tat.intl.tencentcloudapi.com/'
            && $r->hasHeader('X-TC-Version', '2020-10-28')
            && $r->hasHeader('X-TC-Region', 'ap-jakarta'));
    }

    public function test_billing_call_omits_region_header(): void
    {
        Http::fake(['*' => Http::response(['Response' => ['Balance' => 12345, 'RequestId' => 'r3']])]);

        (new BillingClient($this->api()))->describeAccountBalance();

        Http::assertSent(fn (Request $r) => $r->url() === 'https://billing.intl.tencentcloudapi.com/'
            && $r->hasHeader('X-TC-Version', '2018-07-09')
            && ! $r->hasHeader('X-TC-Region')
            && $r->body() === '{}');
    }

    public function test_empty_payload_is_sent_as_empty_json_object(): void
    {
        Http::fake(['*' => Http::response(['Response' => []])]);

        $this->api()->call('lighthouse', 'DescribeRegions');

        Http::assertSent(fn (Request $r) => $r->body() === '{}');
    }

    public function test_api_error_maps_to_non_retryable_provider_exception(): void
    {
        Http::fake(['*' => Http::response(['Response' => [
            'Error' => ['Code' => 'ResourcesSoldOut', 'Message' => 'sold out'],
            'RequestId' => 'req-err',
        ]])]);

        try {
            $this->api()->call('lighthouse', 'CreateInstances', ['BundleId' => 'x']);
            $this->fail('ProviderException expected');
        } catch (ProviderException $e) {
            $this->assertFalse($e->retryable);
            $this->assertSame('ResourcesSoldOut', $e->providerCode);
            $this->assertStringContainsString('req-err', $e->getMessage());
            $this->assertStringContainsString('[lighthouse/CreateInstances]', $e->getMessage());
        }
    }

    public function test_retryable_api_error_maps_to_retryable_exception(): void
    {
        Http::fake(['*' => Http::response(['Response' => [
            'Error' => ['Code' => 'RequestLimitExceeded', 'Message' => 'slow down'],
            'RequestId' => 'req-throttle',
        ]])]);

        try {
            $this->api()->call('lighthouse', 'DescribeInstances');
            $this->fail('ProviderException expected');
        } catch (ProviderException $e) {
            $this->assertTrue($e->retryable);
        }
    }

    public function test_connection_failure_maps_to_retryable_network_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        try {
            $this->api()->call('lighthouse', 'DescribeInstances');
            $this->fail('ProviderException expected');
        } catch (ProviderException $e) {
            $this->assertTrue($e->retryable);
            $this->assertSame('NetworkError', $e->providerCode);
        }
    }

    public function test_malformed_response_maps_to_retryable_exception(): void
    {
        Http::fake(['*' => Http::response('not json at all', 200)]);

        try {
            $this->api()->call('lighthouse', 'DescribeInstances');
            $this->fail('ProviderException expected');
        } catch (ProviderException $e) {
            $this->assertTrue($e->retryable);
            $this->assertSame('MalformedResponse', $e->providerCode);
        }
    }

    public function test_unknown_service_throws_invalid_argument(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->api()->call('cvm', 'DescribeInstances');
    }

    public function test_no_transport_level_retry_on_failure(): void
    {
        // Billing-bearing calls must never blind-retry at transport level.
        Http::fake(['*' => Http::response(['Response' => [
            'Error' => ['Code' => 'InternalError', 'Message' => 'boom'],
        ]])]);

        try {
            $this->api()->call('lighthouse', 'CreateInstances', ['BundleId' => 'x']);
        } catch (ProviderException) {
            // expected
        }

        Http::assertSentCount(1);
    }

    public function test_find_instance_by_name_uses_official_filter_key(): void
    {
        Http::fake(['*' => Http::response(['Response' => [
            'TotalCount' => 1,
            'InstanceSet' => [['InstanceId' => 'lhins-abc', 'InstanceName' => 'crava-1-2']],
        ]])]);

        $lh = new LighthouseClient($this->api());
        $instance = $lh->findInstanceByName('crava-1-2', 'ap-jakarta');

        $this->assertSame('lhins-abc', $instance['InstanceId']);
        Http::assertSent(function (Request $r) {
            $body = json_decode($r->body(), true);

            return ($body['Filters'][0]['Name'] ?? null) === 'instance-name'
                && ($body['Filters'][0]['Values'][0] ?? null) === 'crava-1-2';
        });
    }

    public function test_find_instance_by_name_returns_null_when_absent(): void
    {
        Http::fake(['*' => Http::response(['Response' => ['TotalCount' => 0, 'InstanceSet' => []]])]);

        $this->assertNull((new LighthouseClient($this->api()))->findInstanceByName('crava-9-9'));
    }

    public function test_reset_instance_uses_singular_action(): void
    {
        Http::fake(['*' => Http::response(['Response' => ['RequestId' => 'r-reset']])]);

        (new LighthouseClient($this->api()))->resetInstance('lhins-abc', 'lhbp-1l4ptuvm');

        Http::assertSent(function (Request $r) {
            $body = json_decode($r->body(), true);

            return $r->hasHeader('X-TC-Action', 'ResetInstance')
                && ($body['InstanceId'] ?? null) === 'lhins-abc'
                && ($body['BlueprintId'] ?? null) === 'lhbp-1l4ptuvm';
        });
    }

    public function test_renew_instances_sends_prepaid_block(): void
    {
        Http::fake(['*' => Http::response(['Response' => ['RequestId' => 'r-renew']])]);

        (new LighthouseClient($this->api()))->renewInstances(['lhins-abc'], 1);

        Http::assertSent(function (Request $r) {
            $body = json_decode($r->body(), true);

            return $r->hasHeader('X-TC-Action', 'RenewInstances')
                && ($body['InstanceChargePrepaid']['Period'] ?? null) === 1
                && ($body['InstanceChargePrepaid']['RenewFlag'] ?? null) === 'NOTIFY_AND_MANUAL_RENEW';
        });
    }

    public function test_container_bindings_resolve(): void
    {
        $this->assertInstanceOf(LighthouseClient::class, app(LighthouseClient::class));
        $this->assertInstanceOf(\App\Services\Provisioning\Tencent\TatClient::class, app(\App\Services\Provisioning\Tencent\TatClient::class));
        $this->assertInstanceOf(BillingClient::class, app(BillingClient::class));
    }
}