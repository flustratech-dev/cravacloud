<?php

namespace App\Services\Provisioning\Contracts;

use App\Models\VpsInstance;

/**
 * Capability: provider exposes instance-level firewall management.
 * addFirewallRules MUST diff against existing rules first (revision 5)
 * so default-open ports (22/80/443) never cause duplicate-rule errors.
 */
interface SupportsFirewall
{
    /** @return array<int, array{Protocol: string, Port: string, CidrBlock: string, Action: string, FirewallRuleDescription?: string}> */
    public function listFirewallRules(VpsInstance $instance): array;

    /** $rules: same shape as listFirewallRules return. Only missing rules are created. */
    public function addFirewallRules(VpsInstance $instance, array $rules): array;

    public function deleteFirewallRules(VpsInstance $instance, array $rules): array;
}