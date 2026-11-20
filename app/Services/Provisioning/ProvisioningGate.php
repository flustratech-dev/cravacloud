<?php

namespace App\Services\Provisioning;

use App\Models\VpsSpec;
use App\Services\SettingsService;

/**
 * Canary rollout gate (D13). Auto-provisioning is OFF by default and flips
 * per-spec via settings — rollback = turn the setting off, no deploy.
 *
 * The Phase 3 orchestrator MUST consult allows($spec) before dispatching
 * any upstream job. Until then this gate only reports; nothing dispatches.
 *
 * Settings (group 'provisioning'):
 *   provisioning_auto_enabled        boolean master switch (default false)
 *   provisioning_auto_spec_whitelist json array of spec IDs (canary: Starter XS)
 */
class ProvisioningGate
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function autoEnabled(): bool
    {
        return $this->settings->bool('provisioning_auto_enabled', false);
    }

    /** @return array<int, int> */
    public function whitelist(): array
    {
        return array_map('intval', $this->settings->array('provisioning_auto_spec_whitelist', []));
    }

    public function allows(VpsSpec $spec): bool
    {
        return $this->autoEnabled()
            && $spec->hasUpstreamMapping() // D3: unmapped ai_combo/managed_db never auto
            && in_array((int) $spec->id, $this->whitelist(), true);
    }
}