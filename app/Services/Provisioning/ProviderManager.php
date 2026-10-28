<?php

namespace App\Services\Provisioning;

use App\Services\Provisioning\Contracts\VpsProviderContract;
use App\Services\Provisioning\Providers\ManualProvider;
use Illuminate\Support\Manager;

/**
 * Registry provider. Resolve provider berdasarkan nama.
 * Extend dengan provider baru:
 *
 *   $manager->extend('supplier', fn($app) => new SupplierProvider($app['config']['services.supplier']));
 */
class ProviderManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('services.provisioning.default', 'manual');
    }

    public function createManualDriver(): VpsProviderContract
    {
        return new ManualProvider();
    }

    /**
     * Resolve provider by name. Kalau tidak dikenal, fallback ke manual.
     */
    public function provider(?string $name = null): VpsProviderContract
    {
        try {
            return $this->driver($name);
        } catch (\Throwable $e) {
            // Fallback aman: jangan sampai crash job hanya karena config typo.
            return $this->createManualDriver();
        }
    }
}
