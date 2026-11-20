<?php

namespace Tests\Unit;

use App\Services\Provisioning\Providers\TencentLighthouseProvider;
use PHPUnit\Framework\TestCase;

class FirewallDiffTest extends TestCase
{
    public function test_opens_missing_tcp_and_udp_ports_including_ranges(): void
    {
        $existing = [
            ['Protocol' => 'TCP', 'Port' => '22', 'CidrBlock' => '0.0.0.0/0', 'Action' => 'ACCEPT'],
            ['Protocol' => 'UDP', 'Port' => '53', 'CidrBlock' => '0.0.0.0/0', 'Action' => 'ACCEPT'],
        ];

        $missing = TencentLighthouseProvider::buildMissingFirewallRules(
            $existing,
            [22, 80, 443, '21114-21119'],
            [53, 51820, '2456-2458'],
            'rustdesk'
        );

        $this->assertCount(5, $missing);
        $this->assertSame([
            ['Protocol' => 'TCP', 'Port' => '80', 'CidrBlock' => '0.0.0.0/0', 'Action' => 'ACCEPT', 'FirewallRuleDescription' => 'crava-rustdesk'],
            ['Protocol' => 'TCP', 'Port' => '443', 'CidrBlock' => '0.0.0.0/0', 'Action' => 'ACCEPT', 'FirewallRuleDescription' => 'crava-rustdesk'],
            ['Protocol' => 'TCP', 'Port' => '21114-21119', 'CidrBlock' => '0.0.0.0/0', 'Action' => 'ACCEPT', 'FirewallRuleDescription' => 'crava-rustdesk'],
            ['Protocol' => 'UDP', 'Port' => '51820', 'CidrBlock' => '0.0.0.0/0', 'Action' => 'ACCEPT', 'FirewallRuleDescription' => 'crava-rustdesk'],
            ['Protocol' => 'UDP', 'Port' => '2456-2458', 'CidrBlock' => '0.0.0.0/0', 'Action' => 'ACCEPT', 'FirewallRuleDescription' => 'crava-rustdesk'],
        ], $missing);
    }

    public function test_comma_lists_ranges_adjacent_ports_and_all_rules_stay_idempotent(): void
    {
        $existing = [
            ['Protocol' => 'TCP', 'Port' => '22,80,443,8000-8100', 'CidrBlock' => '0.0.0.0/0', 'Action' => 'ACCEPT'],
            ['Protocol' => 'UDP', 'Port' => '2456', 'CidrBlock' => '0.0.0.0/0', 'Action' => 'ACCEPT'],
            ['Protocol' => 'UDP', 'Port' => '2457', 'CidrBlock' => '0.0.0.0/0', 'Action' => 'ACCEPT'],
            ['Protocol' => 'UDP', 'Port' => '2458', 'CidrBlock' => '0.0.0.0/0', 'Action' => 'ACCEPT'],
            // DROP rule and restricted CIDR must NOT count as covering 0.0.0.0/0 ACCEPT.
            ['Protocol' => 'TCP', 'Port' => '9000', 'CidrBlock' => '0.0.0.0/0', 'Action' => 'DROP'],
            ['Protocol' => 'UDP', 'Port' => '51820', 'CidrBlock' => '10.0.0.0/8', 'Action' => 'ACCEPT'],
        ];

        $missing = TencentLighthouseProvider::buildMissingFirewallRules(
            $existing,
            [22, 80, 443, 8080, 9000, 9000],
            ['2456-2458', 51820, 51820],
            'valheim'
        );

        $this->assertCount(2, $missing);
        $this->assertSame('TCP', $missing[0]['Protocol']);
        $this->assertSame('9000', $missing[0]['Port']);
        $this->assertSame('UDP', $missing[1]['Protocol']);
        $this->assertSame('51820', $missing[1]['Port']);

        $allExisting = [
            ['Protocol' => 'ALL', 'Port' => 'ALL', 'CidrBlock' => '0.0.0.0/0', 'Action' => 'ACCEPT'],
        ];
        $this->assertSame([], TencentLighthouseProvider::buildMissingFirewallRules($allExisting, [22, 80], ['2456-2458'], 'valheim'));
    }
}
