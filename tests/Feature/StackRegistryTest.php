<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\Provisioning\Stacks\StackRegistry;
use Tests\TestCase;

/**
 * Phase 5E/5G — catalog integrity for the 1-click stack registry.
 * Every stack offered at checkout (Order::stackLabels) except the HOLD
 * managed_database (D3) must have a registry definition, an idempotent
 * install script on disk, and valid inbound ports. Proxy stacks open no
 * extra inbound port. No upstream call is made (pure config + filesystem).
 */
class StackRegistryTest extends TestCase
{
    private function registry(): StackRegistry
    {
        return app(StackRegistry::class);
    }

    public function test_every_checkout_stack_except_hold_has_definition_and_script(): void
    {
        $registry = $this->registry();

        foreach (Order::stackLabels() as $key => $label) {
            if ($key === 'managed_database') {
                continue; // HOLD (D3) — never auto-provisioned
            }

            $definition = $registry->get($key);
            $this->assertNotNull($definition, "Stack [{$key}] missing from registry.");

            if ($key === 'none') {
                $this->assertFalse($definition->needsInstall(), 'none must not need a script.');

                continue;
            }

            $this->assertTrue($definition->needsInstall(), "Stack [{$key}] must have an install script.");
            $this->assertNotEmpty($definition->script);
            $this->assertFileExists(
                resource_path('provisioning/stacks/'.$definition->script),
                "Install script missing for [{$key}]."
            );
        }
    }

    public function test_managed_database_is_absent_from_registry(): void
    {
        $this->assertFalse($this->registry()->has('managed_database'));
        $this->assertNull($this->registry()->get('managed_database'));
    }

    public function test_all_ports_are_valid_and_include_ssh(): void
    {
        foreach ($this->registry()->all() as $key => $definition) {
            $this->assertNotEmpty($definition->ports, "Stack [{$key}] has no ports.");
            $this->assertContains(22, $definition->ports, "Stack [{$key}] must keep SSH (22) open.");

            foreach ($definition->ports as $port) {
                $this->assertIsInt($port);
                $this->assertGreaterThanOrEqual(1, $port);
                $this->assertLessThanOrEqual(65535, $port);
            }

            $this->assertSame(array_unique($definition->ports), $definition->ports, "Stack [{$key}] has duplicate ports.");
        }
    }

    public function test_proxy_stacks_open_no_extra_inbound_port(): void
    {
        foreach (['omniroute', '9router'] as $key) {
            $this->assertSame([22], $this->registry()->get($key)->ports, "Proxy stack [{$key}] must only open SSH.");
        }
    }

    public function test_every_stack_supports_primary_checkout_os(): void
    {
        $registry = $this->registry();

        foreach (array_keys(Order::stackLabels()) as $key) {
            if ($key === 'managed_database') {
                continue;
            }
            $this->assertTrue(
                $registry->supportsOs('ubuntu2404', $key),
                "Stack [{$key}] must support ubuntu2404 (primary checkout OS)."
            );
        }
    }

    public function test_os_restrictions_are_enforced(): void
    {
        $registry = $this->registry();

        // Coolify/CloudPanel are apt-based panels — Rocky is not supported.
        $this->assertFalse($registry->supportsOs('rocky94', 'coolify'));
        $this->assertFalse($registry->supportsOs('rocky94', 'cloudpanel'));
        // aaPanel & the proxy connectors support Rocky 9/10 & CentOS Stream 9.
        $this->assertTrue($registry->supportsOs('rocky94', 'aapanel'));
        $this->assertTrue($registry->supportsOs('rocky10', 'aapanel'));
        $this->assertTrue($registry->supportsOs('centos_stream9', 'aapanel'));
        $this->assertTrue($registry->supportsOs('rocky94', 'omniroute'));
        $this->assertTrue($registry->supportsOs('rocky10', 'none'));
        // Docker-capable OSes use pre-baked Docker blueprints when configured.
        $this->assertSame(config('services.tencent.blueprints.debian13_docker'), $registry->blueprintIdFor('debian13', 'coolify'));
        $this->assertCount(count(Order::checkoutOperatingSystems()), $registry->supportedOsMap()['none']);
        // Unknown OS never resolves a blueprint.
        $this->assertFalse($registry->supportsOs('windows2022', 'docker'));
    }

    public function test_install_scripts_are_idempotent_with_marker(): void
    {
        foreach ($this->registry()->all() as $key => $definition) {
            if (!$definition->script) {
                continue;
            }
            $content = (string) file_get_contents(resource_path('provisioning/stacks/'.$definition->script));
            $this->assertStringContainsString('/var/lib/cravacloud/.installed-{{stack}}', $content, "Script [{$key}] must use the idempotency marker.");
            $this->assertStringContainsString('CRAVA_INSTALL_OK {{stack}}', $content, "Script [{$key}] must emit the success sentinel.");
            $this->assertStringContainsString('set -euo pipefail', $content, "Script [{$key}] must fail fast.");
        }
    }
}