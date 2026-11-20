<?php

namespace App\Services\Provisioning\Tencent;

/**
 * Typed wrappers for Tencent Cloud TAT (Automation Tools) API,
 * version 2020-10-28, international endpoint.
 *
 * Rules (blueprint revision 2):
 *  - ALWAYS verify DescribeAutomationAgentStatus == Online before RunCommand
 *    (agent may be offline right after a password-reset reboot).
 *  - When using Parameters: set EnableParameter=true and pass Parameters as
 *    a JSON STRING; script placeholders use {{key}}.
 *  - Avoid plaintext secrets in command content/parameters where possible
 *    (TAT retains invocation history) — see decision D6.
 */
class TatClient
{
    public function __construct(private readonly TencentApiClient $api)
    {
    }

    /**
     * $params must include: InstanceIds, Content (base64 script), CommandType
     * (SHELL), Timeout, WorkingDirectory; optionally CommandName,
     * EnableParameter (bool) + Parameters (JSON string).
     */
    public function runCommand(array $params, ?string $region = null): array
    {
        return $this->api->call('tat', 'RunCommand', $params, $region);
    }

    public function describeInvocationTasks(string $invocationId, ?string $instanceId = null, ?string $region = null, int $limit = 20, int $offset = 0): array
    {
        return $this->api->call('tat', 'DescribeInvocationTasks', array_filter([
            'InvocationId' => $invocationId,
            'Filters' => $instanceId
                ? [['Name' => 'instance-id', 'Values' => [$instanceId]]]
                : null,
            'HideOutput' => false,
            'Offset' => $offset,
            'Limit' => $limit,
        ]), $region);
    }

    /**
     * Agent readiness gate. Returns AutomationAgentStatusSet with AgentStatus
     * values such as Online / Offline.
     */
    public function describeAutomationAgentStatus(array $instanceIds, ?string $region = null): array
    {
        return $this->api->call('tat', 'DescribeAutomationAgentStatus', [
            'InstanceIds' => $instanceIds,
        ], $region);
    }

    /** Read-only listing; used by tencent:probe to verify TAT auth. */
    public function describeCommands(?string $region = null, int $limit = 10): array
    {
        return $this->api->call('tat', 'DescribeCommands', [
            'Limit' => $limit,
        ], $region);
    }
}