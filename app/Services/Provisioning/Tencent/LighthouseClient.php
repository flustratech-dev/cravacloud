<?php

namespace App\Services\Provisioning\Tencent;

/**
 * Typed wrappers for Tencent Cloud Lighthouse API (version 2020-03-24,
 * international endpoint). Thin layer: payload construction only, all
 * signing/transport/error-mapping lives in TencentApiClient.
 *
 * Official API quirks honored here (blueprint revisions 2 & 4):
 *  - Reinstall action is `ResetInstance` (SINGULAR, one instance per call).
 *  - DescribeInstances name filter key is `instance-name` (lowercase, hyphen).
 *  - SHUTDOWN (recycle bin) instances cannot be started; recover via
 *    RenewInstances, then re-sync public IP (it may change).
 */
class LighthouseClient
{
    public function __construct(private readonly TencentApiClient $api)
    {
    }

    public function describeRegions(): array
    {
        return $this->api->call('lighthouse', 'DescribeRegions');
    }

    public function describeBundles(?string $region = null, array $filters = [], int $limit = 100, int $offset = 0): array
    {
        return $this->api->call('lighthouse', 'DescribeBundles', array_filter([
            'Filters' => $filters ?: null,
            'Offset' => $offset,
            'Limit' => $limit,
        ]), $region);
    }

    public function describeBlueprints(?string $region = null, array $filters = [], int $limit = 100, int $offset = 0): array
    {
        return $this->api->call('lighthouse', 'DescribeBlueprints', array_filter([
            'Filters' => $filters ?: null,
            'Offset' => $offset,
            'Limit' => $limit,
        ]), $region);
    }

    public function describeInstances(?string $region = null, array $instanceIds = [], array $filters = [], int $limit = 20, int $offset = 0): array
    {
        return $this->api->call('lighthouse', 'DescribeInstances', array_filter([
            'InstanceIds' => $instanceIds ?: null,
            'Filters' => $filters ?: null,
            'Offset' => $offset,
            'Limit' => $limit,
        ]), $region);
    }

    /**
     * Orphan-adoption lookup: deterministic instance name `crava-{order}-{instance}`.
     * Uses the official `instance-name` filter (NOT `InstanceName`).
     */
    public function findInstanceByName(string $name, ?string $region = null): ?array
    {
        $resp = $this->describeInstances($region, [], [
            ['Name' => 'instance-name', 'Values' => [$name]],
        ], 1);

        return $resp['InstanceSet'][0] ?? null;
    }

    /** On-demand only: URL is single-use and valid ~15 seconds. NEVER cache. */
    public function describeInstanceVncUrl(string $instanceId, ?string $region = null): array
    {
        return $this->api->call('lighthouse', 'DescribeInstanceVncUrl', [
            'InstanceId' => $instanceId,
        ], $region);
    }

    /**
     * Create prepaid instances. Caller MUST include:
     *   BundleId, BlueprintId, InstanceName, InstanceCount,
     *   InstanceChargePrepaid: {Period: 1, RenewFlag: NOTIFY_AND_MANUAL_RENEW}
     *
     * WARNING: non-idempotent (no ClientToken support). Provider layer must
     * persist the returned InstanceIdSet immediately and use checkpoint
     * resume + findInstanceByName adoption on ambiguous failures.
     */
    public function createInstances(array $params, ?string $region = null): array
    {
        return $this->api->call('lighthouse', 'CreateInstances', $params, $region);
    }

    public function startInstances(array $instanceIds, ?string $region = null): array
    {
        return $this->api->call('lighthouse', 'StartInstances', ['InstanceIds' => $instanceIds], $region);
    }

    public function stopInstances(array $instanceIds, ?string $region = null): array
    {
        return $this->api->call('lighthouse', 'StopInstances', ['InstanceIds' => $instanceIds], $region);
    }

    public function rebootInstances(array $instanceIds, ?string $region = null): array
    {
        return $this->api->call('lighthouse', 'RebootInstances', ['InstanceIds' => $instanceIds], $region);
    }

    /** Move to recycle bin (SHUTDOWN). Required before TerminateInstances for active instances. */
    public function isolateInstances(array $instanceIds, ?string $region = null): array
    {
        return $this->api->call('lighthouse', 'IsolateInstances', ['InstanceIds' => $instanceIds], $region);
    }

    /** Permanent destroy. Instance must already be SHUTDOWN (isolated). */
    public function terminateInstances(array $instanceIds, ?string $region = null): array
    {
        return $this->api->call('lighthouse', 'TerminateInstances', ['InstanceIds' => $instanceIds], $region);
    }

    /**
     * Renew prepaid instances. Also the ONLY way to recover a SHUTDOWN
     * (recycle bin) instance — StartInstances does not work on SHUTDOWN.
     */
    public function renewInstances(array $instanceIds, int $period, string $renewFlag = 'NOTIFY_AND_MANUAL_RENEW', ?string $region = null): array
    {
        return $this->api->call('lighthouse', 'RenewInstances', [
            'InstanceIds' => $instanceIds,
            'InstanceChargePrepaid' => [
                'Period' => $period,
                'RenewFlag' => $renewFlag,
            ],
        ], $region);
    }

    /**
     * Reset the DEFAULT user's password (Ubuntu images: `ubuntu`, not root).
     * On a RUNNING instance this triggers shutdown+reboot: caller must poll
     * DescribeInstances until LatestOperationState == SUCCESS && RUNNING,
     * then wait for the TAT agent to be Online before RunCommand.
     *
     * $userName selects the OS default user explicitly (revision 3); when
     * null the API resets the image's built-in default user.
     */
    public function resetInstancesPassword(array $instanceIds, string $password, ?string $region = null, ?string $userName = null): array
    {
        return $this->api->call('lighthouse', 'ResetInstancesPassword', array_filter([
            'InstanceIds' => $instanceIds,
            'Password' => $password,
            'UserName' => $userName,
        ]), $region);
    }

    /** Reinstall OS/application image — official action is ResetInstance (singular). */
    public function resetInstance(string $instanceId, string $blueprintId, ?string $region = null): array
    {
        return $this->api->call('lighthouse', 'ResetInstance', [
            'InstanceId' => $instanceId,
            'BlueprintId' => $blueprintId,
        ], $region);
    }

    /** Upgrade-only (all target resources must be >= source; downgrades rejected by API). */
    public function modifyInstancesBundle(array $instanceIds, string $bundleId, ?string $region = null): array
    {
        return $this->api->call('lighthouse', 'ModifyInstancesBundle', [
            'InstanceIds' => $instanceIds,
            'BundleId' => $bundleId,
        ], $region);
    }

    public function describeFirewallRules(string $instanceId, ?string $region = null, int $limit = 100, int $offset = 0): array
    {
        return $this->api->call('lighthouse', 'DescribeFirewallRules', [
            'InstanceId' => $instanceId,
            'Offset' => $offset,
            'Limit' => $limit,
        ], $region);
    }

    /**
     * $rules: [{Protocol, Port, CidrBlock, Action, FirewallRuleDescription}]
     * Caller MUST diff against describeFirewallRules first (default rules
     * already open 22/80/443; duplicates cause errors).
     */
    public function createFirewallRules(string $instanceId, array $rules, ?string $region = null): array
    {
        return $this->api->call('lighthouse', 'CreateFirewallRules', [
            'InstanceId' => $instanceId,
            'FirewallRules' => $rules,
        ], $region);
    }

    public function deleteFirewallRules(string $instanceId, array $rules, ?string $region = null): array
    {
        return $this->api->call('lighthouse', 'DeleteFirewallRules', [
            'InstanceId' => $instanceId,
            'FirewallRules' => $rules,
        ], $region);
    }

    public function describeInstancesTrafficPackages(array $instanceIds, ?string $region = null, int $limit = 20, int $offset = 0): array
    {
        return $this->api->call('lighthouse', 'DescribeInstancesTrafficPackages', [
            'InstanceIds' => $instanceIds,
            'Offset' => $offset,
            'Limit' => $limit,
        ], $region);
    }
}