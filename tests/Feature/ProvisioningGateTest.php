<?php

namespace Tests\Feature;

use App\Models\VpsSpec;
use App\Services\Provisioning\ProvisioningGate;
use App\Services\SettingsService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProvisioningGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function gate(): ProvisioningGate
    {
        return app(ProvisioningGate::class);
    }

    private function starterXs(): VpsSpec
    {
        return VpsSpec::where('category', 'vps')->where('name', 'Starter XS')->firstOrFail();
    }

    public function test_default_off_and_whitelist_seeded_with_starter_xs(): void
    {
        $this->assertFalse($this->gate()->autoEnabled());
        $this->assertSame([(int) $this->starterXs()->id], $this->gate()->whitelist());
        $this->assertFalse($this->gate()->allows($this->starterXs()));
    }

    public function test_enabled_and_whitelisted_mapped_spec_is_allowed(): void
    {
        app(SettingsService::class)->set('provisioning_auto_enabled', true, 'boolean');

        $this->assertTrue($this->gate()->allows($this->starterXs()));
    }

    public function test_enabled_but_not_whitelisted_is_rejected(): void
    {
        app(SettingsService::class)->set('provisioning_auto_enabled', true, 'boolean');
        $enterprise = VpsSpec::where('category', 'vps')->where('name', 'Enterprise Pro')->firstOrFail();

        $this->assertFalse($this->gate()->allows($enterprise));
    }

    public function test_unmapped_spec_never_allowed_even_if_whitelisted(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('provisioning_auto_enabled', true, 'boolean');

        $dbSpec = VpsSpec::where('category', 'managed_db')->firstOrFail();
        $settings->set('provisioning_auto_spec_whitelist', [(int) $dbSpec->id], 'json');

        // D3 HOLD: no upstream mapping → manual flow regardless of settings.
        $this->assertFalse($this->gate()->allows($dbSpec));
    }

    public function test_rollback_by_disabling_master_switch(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('provisioning_auto_enabled', true, 'boolean');
        $this->assertTrue($this->gate()->allows($this->starterXs()));

        $settings->set('provisioning_auto_enabled', false, 'boolean');
        $this->assertFalse($this->gate()->allows($this->starterXs()));
    }
}