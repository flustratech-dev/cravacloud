<?php

namespace App\Services\Provisioning\Tencent;

use App\Models\VpsInstance;

/**
 * Resolves the upstream region for an instance: the linked datacenter
 * region's provider_region_id, falling back to the configured default.
 * Internal only — region codes never appear in customer-facing output.
 */
trait ResolvesRegion
{
    protected function regionFor(VpsInstance $instance): string
    {
        $instance->loadMissing('datacenterRegion');

        return (string) ($instance->datacenterRegion?->provider_region_id
            ?: config('services.tencent.region', 'ap-jakarta'));
    }
}