<?php

namespace App\Services\Provisioning\Contracts;

use App\Models\VpsInstance;

/**
 * Capability: provider exposes an on-demand graphical console (VNC for
 * Tencent). Checked via instanceof so ManualProvider never offers it.
 *
 * The URL is SINGLE-USE and valid only ~15 seconds. Implementations MUST
 * fetch it on demand (one upstream call per request), MUST require a RUNNING
 * instance, and MUST NEVER cache, log, or persist the URL anywhere.
 */
interface SupportsVncConsole
{
    /**
     * @return array{url: string, expires_in: int}
     *
     * @throws \App\Services\Provisioning\Exceptions\ProviderException
     *         non-retryable 'InstanceNotRunning' when the server is not running.
     */
    public function vncUrl(VpsInstance $instance): array;
}