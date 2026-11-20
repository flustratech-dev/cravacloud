<?php

namespace App\Providers;

use App\Services\Provisioning\ProviderManager;
use App\Services\Provisioning\Tencent\BalanceGuard;
use App\Services\Provisioning\Tencent\BillingClient;
use App\Services\Provisioning\Tencent\LighthouseClient;
use App\Services\Provisioning\Tencent\TatClient;
use App\Services\Provisioning\Tencent\Tc3Signer;
use App\Services\Provisioning\Tencent\TencentApiClient;
use Illuminate\Support\ServiceProvider;

class ProvisioningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProviderManager::class, function ($app) {
            return new ProviderManager($app);
        });

        $this->app->singleton(Tc3Signer::class, function () {
            return new Tc3Signer(
                (string) config('services.tencent.secret_id'),
                (string) config('services.tencent.secret_key'),
            );
        });

        $this->app->singleton(TencentApiClient::class, function ($app) {
            return new TencentApiClient(
                $app->make(Tc3Signer::class),
                (array) config('services.tencent'),
            );
        });

        $this->app->singleton(LighthouseClient::class, function ($app) {
            return new LighthouseClient($app->make(TencentApiClient::class));
        });

        $this->app->singleton(TatClient::class, function ($app) {
            return new TatClient($app->make(TencentApiClient::class));
        });

        $this->app->singleton(BillingClient::class, function ($app) {
            return new BillingClient($app->make(TencentApiClient::class));
        });

        $this->app->singleton(BalanceGuard::class, function ($app) {
            return new BalanceGuard($app->make(BillingClient::class));
        });
    }

    public function boot(): void
    {
    }
}
