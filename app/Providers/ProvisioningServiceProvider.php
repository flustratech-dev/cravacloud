<?php

namespace App\Providers;

use App\Services\Provisioning\ProviderManager;
use Illuminate\Support\ServiceProvider;

class ProvisioningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProviderManager::class, function ($app) {
            return new ProviderManager($app);
        });
    }

    public function boot(): void
    {
    }
}
