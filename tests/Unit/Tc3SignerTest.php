<?php

namespace Tests\Unit;

use App\Services\Provisioning\Tencent\Tc3Signer;
use PHPUnit\Framework\TestCase;

/**
 * Known-answer tests against the OFFICIAL TC3-HMAC-SHA256 documentation
 * payload and canonical request vectors, paired with non-secret fixture keys
 * (avoids AKID... GitHub Secret Scanning false positives).
 */
class Tc3SignerTest extends TestCase
{
    private const EXAMPLE_ID = 'test-secret-id';
    private const EXAMPLE_KEY = 'test-secret-key';
    private const HOST = 'cvm.tencentcloudapi.com';
    private const ACTION = 'DescribeInstances';
    private const TIMESTAMP = 1551113065;

    // Exact payload from the official signature v3 documentation.
    private const PAYLOAD = '{"Limit": 1, "Filters": [{"Values": ["\u672a\u547d\u540d"], "Name": "instance-name"}]}';

    // Published, key-independent hashes.
    private const EXPECTED_PAYLOAD_HASH = '35e9c5b0e3ae67532d3c9f17ead6c90222632e5b1ff7f6e89887f1398934f064';
    private const EXPECTED_CANONICAL_HASH = '7019a55be8395899b900fb5564e4200d984910f34794a27cb3fb7d10ff6a1e84';

    // Signature computed from the official TC3-HMAC-SHA256 derivation using
    // EXAMPLE_ID / EXAMPLE_KEY above.
    private const EXPECTED_SIGNATURE = '99f8790e445f249f002afeffee9d8517ca11199c00cc10167fa4a443ba2c1d5b';

    private function signer(): Tc3Signer
    {
        return new Tc3Signer(self::EXAMPLE_ID, self::EXAMPLE_KEY);
    }

    public function test_payload_hash_matches_official_vector(): void
    {
        $this->assertSame(self::EXPECTED_PAYLOAD_HASH, hash('sha256', self::PAYLOAD));
    }

    public function test_canonical_request_hash_matches_official_vector(): void
    {
        $this->assertSame(
            self::EXPECTED_CANONICAL_HASH,
            $this->signer()->canonicalRequestHash(self::HOST, self::ACTION, self::PAYLOAD),
        );
    }

    public function test_signature_matches_official_demo_vector(): void
    {
        $result = $this->signer()->sign('cvm', self::HOST, self::ACTION, self::PAYLOAD, self::TIMESTAMP);

        $this->assertSame(self::EXPECTED_SIGNATURE, $result['signature']);
        $this->assertSame(self::TIMESTAMP, $result['timestamp']);
    }

    public function test_authorization_header_format(): void
    {
        $result = $this->signer()->sign('cvm', self::HOST, self::ACTION, self::PAYLOAD, self::TIMESTAMP);

        $expected = sprintf(
            'TC3-HMAC-SHA256 Credential=%s/2019-02-25/cvm/tc3_request, SignedHeaders=content-type;host;x-tc-action, Signature=%s',
            self::EXAMPLE_ID,
            self::EXPECTED_SIGNATURE,
        );
        $this->assertSame($expected, $result['authorization']);
        $this->assertSame($expected, $result['headers']['Authorization']);
    }

    public function test_headers_include_exact_content_type_and_lowercase_action(): void
    {
        $result = $this->signer()->sign('cvm', self::HOST, self::ACTION, self::PAYLOAD, self::TIMESTAMP);

        $this->assertSame('application/json; charset=utf-8', $result['headers']['Content-Type']);
        $this->assertSame(self::ACTION, $result['headers']['X-TC-Action']);
        $this->assertSame((string) self::TIMESTAMP, $result['headers']['X-TC-Timestamp']);
    }

    public function test_action_case_does_not_change_signature(): void
    {
        // Canonical headers lowercase the action; signed header value must
        // therefore be identical for any casing.
        $a = $this->signer()->sign('cvm', self::HOST, 'DescribeInstances', self::PAYLOAD, self::TIMESTAMP);
        $b = $this->signer()->sign('cvm', self::HOST, 'describeinstances', self::PAYLOAD, self::TIMESTAMP);

        $this->assertSame($a['signature'], $b['signature']);
    }

    public function test_signing_is_deterministic_for_same_inputs(): void
    {
        $a = $this->signer()->sign('cvm', self::HOST, self::ACTION, self::PAYLOAD, self::TIMESTAMP);
        $b = $this->signer()->sign('cvm', self::HOST, self::ACTION, self::PAYLOAD, self::TIMESTAMP);

        $this->assertSame($a, $b);
    }

    public function test_different_payload_changes_signature(): void
    {
        $a = $this->signer()->sign('cvm', self::HOST, self::ACTION, self::PAYLOAD, self::TIMESTAMP);
        $b = $this->signer()->sign('cvm', self::HOST, self::ACTION, '{"Limit": 2}', self::TIMESTAMP);

        $this->assertNotSame($a['signature'], $b['signature']);
    }

    public function test_different_timestamp_changes_signature(): void
    {
        $a = $this->signer()->sign('cvm', self::HOST, self::ACTION, self::PAYLOAD, self::TIMESTAMP);
        $b = $this->signer()->sign('cvm', self::HOST, self::ACTION, self::PAYLOAD, self::TIMESTAMP + 1);

        $this->assertNotSame($a['signature'], $b['signature']);
    }

    public function test_different_service_changes_signature(): void
    {
        $a = $this->signer()->sign('cvm', self::HOST, self::ACTION, self::PAYLOAD, self::TIMESTAMP);
        $b = $this->signer()->sign('lighthouse', self::HOST, self::ACTION, self::PAYLOAD, self::TIMESTAMP);

        $this->assertNotSame($a['signature'], $b['signature']);
    }

    public function test_different_key_changes_signature(): void
    {
        $a = $this->signer()->sign('cvm', self::HOST, self::ACTION, self::PAYLOAD, self::TIMESTAMP);
        $b = (new Tc3Signer(self::EXAMPLE_ID, 'another-key'))->sign('cvm', self::HOST, self::ACTION, self::PAYLOAD, self::TIMESTAMP);

        $this->assertNotSame($a['signature'], $b['signature']);
    }
}