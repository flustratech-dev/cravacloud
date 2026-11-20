<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Services\Provisioning\Providers\TencentLighthouseProvider;
use App\Services\Provisioning\Stacks\StackRegistry;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StackWarningAndValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Notification::fake();
    }

    private function createCustomer(string $username = 'stackuser'): array
    {
        $user = User::create([
            'username' => $username,
            'email' => $username.'@example.com',
            'full_name' => 'Customer '.$username,
            'password' => Hash::make('password123'),
            'channel' => 'website',
            'is_admin' => false,
        ]);
        $org = $user->createPersonalOrganization();
        $user->switchToOrganization($org);

        return [$user, $org];
    }

    public function test_checkout_renders_search_filter_warning_modal_and_real_logo_tiles(): void
    {
        $response = $this->get('/checkout');

        $response->assertOk()
            ->assertSee('stackSearch', false)
            ->assertSee('stackCategory', false)
            ->assertSee('handleStackClick', false)
            ->assertSee('warningModalOpen', false)
            ->assertSee('bg-white/95', false)
            ->assertSee('1-Click Applications');
    }

    public function test_checkout_blocks_stack_when_spec_ram_is_below_min_ram_gb(): void
    {
        [$user] = $this->createCustomer('ramguard');
        $starterXs = VpsSpec::where('name', 'Starter XS')->firstOrFail();
        $this->assertSame(2, (int) $starterXs->ram);

        $response = $this->actingAs($user)->postJson('/checkout', [
            'vps_spec_id' => $starterXs->id,
            'control_panel' => 'dify_ollama', // minRamGb = 4
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'hostname' => 'ram-guard-01',
            'payment_method' => 'qris',
            'terms_accepted' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['control_panel']);
    }

    public function test_checkout_blocks_warning_stack_when_unacknowledged_and_records_metadata_when_confirmed(): void
    {
        [$user] = $this->createCustomer('warncheckout');
        $starterXs = VpsSpec::where('name', 'Starter XS')->firstOrFail();

        $blocked = $this->actingAs($user)->postJson('/checkout', [
            'vps_spec_id' => $starterXs->id,
            'control_panel' => 'omniroute',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'hostname' => 'omni-bypass-01',
            'payment_method' => 'qris',
            'terms_accepted' => 1,
        ]);

        $blocked->assertStatus(422)
            ->assertJsonValidationErrors(['warning_acknowledged']);

        $ok = $this->actingAs($user)->postJson('/checkout', [
            'vps_spec_id' => $starterXs->id,
            'control_panel' => 'omniroute',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'hostname' => 'omni-ok-01',
            'payment_method' => 'qris',
            'terms_accepted' => 1,
            'warning_acknowledged' => 1,
        ]);

        $ok->assertOk()->assertJson(['success' => true]);

        $order = Order::where('hostname', 'omni-ok-01')->firstOrFail();
        $this->assertIsArray($order->metadata);
        $this->assertTrue($order->metadata['warning_acknowledged']);
        $this->assertFalse($order->metadata['license_acknowledged']);
        $this->assertSame('omniroute', $order->metadata['acknowledged_stack']);
        $this->assertNotEmpty($order->metadata['acknowledged_at']);
    }

    public function test_enforces_byol_license_acknowledgement_on_real_paid_panels_and_persists_audit_metadata(): void
    {
        [$user] = $this->createCustomer('byoluser');
        $starterS = VpsSpec::where('name', 'Starter S')->firstOrFail(); // 4 GB RAM

        foreach (['cpanel_whm', 'directadmin', 'plesk'] as $idx => $panelKey) {
            $missingLicense = $this->actingAs($user)->postJson('/checkout', [
                'vps_spec_id' => $starterS->id,
                'control_panel' => $panelKey,
                'datacenter_location' => 'indonesia',
                'os' => 'ubuntu2404',
                'hostname' => "byol-bypass-{$idx}",
                'payment_method' => 'qris',
                'terms_accepted' => 1,
                'warning_acknowledged' => 1,
                'license_acknowledged' => 0,
            ]);

            $missingLicense->assertStatus(422)
                ->assertJsonValidationErrors(['license_acknowledged']);

            $ok = $this->actingAs($user)->postJson('/checkout', [
                'vps_spec_id' => $starterS->id,
                'control_panel' => $panelKey,
                'datacenter_location' => 'indonesia',
                'os' => 'ubuntu2404',
                'hostname' => "byol-ok-{$idx}",
                'payment_method' => 'qris',
                'terms_accepted' => 1,
                'warning_acknowledged' => 1,
                'license_acknowledged' => 1,
            ]);

            $ok->assertOk()->assertJson(['success' => true]);

            $order = Order::where('hostname', "byol-ok-{$idx}")->firstOrFail();
            $this->assertTrue($order->metadata['license_acknowledged']);
            $this->assertTrue($order->metadata['warning_acknowledged']);
            $this->assertSame($panelKey, $order->metadata['acknowledged_stack']);
        }
    }

    public function test_batch1_catalog_completeness_os_guard_ram_block_activation_and_udp_firewall(): void
    {
        $registry = app(StackRegistry::class);

        $batch1FreePanels = ['portainer', 'easypanel', '1panel', 'caprover', 'casaos', 'serverkit', 'cosmos_cloud', 'fastpanel', 'webmin'];
        $batch1PaidPanels = ['cpanel_whm', 'directadmin', 'plesk'];
        $batch1Apps = [
            'lemp_laravel', 'nginx_proxy_manager', 'grafana_prometheus', 'umami',
            'supabase', 'appwrite', 'pocketbase', 'nocodb', 'postgresql_pgadmin', 'mysql_phpmyadmin',
            'minio', 'open_webui', 'flowise', 'wireguard_easy', 'rustdesk_server',
            'nextcloud', 'immich', 'moodle', 'odoo', 'pterodactyl', 'fivem_txadmin', 'minecraft_paper',
        ];
        $batch1All = array_merge($batch1FreePanels, $batch1PaidPanels, $batch1Apps);

        $this->assertCount(34, $batch1All);
        $this->assertGreaterThanOrEqual(61, count($registry->all()), 'Katalog pasca Batch 1 harus berjumlah minimal 61 stack.');

        $validOs = array_keys(Order::checkoutOperatingSystems());
        $osMap = $registry->supportedOsMap();
        $labels = Order::stackLabels();

        foreach ($batch1All as $key) {
            $def = $registry->get($key);
            $this->assertNotNull($def, "Stack Batch 1 [{$key}] belum terdaftar di StackRegistry.");
            $this->assertArrayHasKey($key, $labels, "Stack Batch 1 [{$key}] belum diturunkan ke Order::stackLabels().");
            $this->assertNotEmpty($osMap[$key], "Stack Batch 1 [{$key}] harus mendukung minimal 1 OS checkout.");
            foreach ($osMap[$key] as $osKey) {
                $this->assertContains($osKey, $validOs, "OS [{$osKey}] pada [{$key}] bukan OS checkout valid.");
            }
        }

        // Paid panels must carry requiresLicense + warningType='license'.
        foreach ($batch1PaidPanels as $key) {
            $def = $registry->get($key);
            $this->assertTrue($def->requiresLicense);
            $this->assertSame('license', $def->warningType);
            $this->assertNotEmpty($def->warningBullets);
        }

        // 4 GB minRamGb stacks blocked on 2 GB Starter XS.
        [$user] = $this->createCustomer('batch1ram');
        $starterXs = VpsSpec::where('name', 'Starter XS')->firstOrFail();
        $this->assertSame(2, (int) $starterXs->ram);
        foreach (['cpanel_whm', 'supabase', 'appwrite', 'open_webui', 'immich', 'odoo', 'pterodactyl', 'fivem_txadmin', 'minecraft_paper'] as $i => $heavyKey) {
            $res = $this->actingAs($user)->postJson('/checkout', [
                'vps_spec_id' => $starterXs->id,
                'control_panel' => $heavyKey,
                'datacenter_location' => 'indonesia',
                'os' => 'ubuntu2404',
                'hostname' => "heavy-xs-{$i}",
                'payment_method' => 'qris',
                'terms_accepted' => 1,
                'warning_acknowledged' => 1,
                'license_acknowledged' => 1,
            ]);
            $res->assertStatus(422)->assertJsonValidationErrors(['control_panel']);
        }

        // Activation/network warnings on FiveM & WireGuard.
        $starterS = VpsSpec::where('name', 'Starter S')->firstOrFail();
        $this->actingAs($user)->postJson('/checkout', [
            'vps_spec_id' => $starterS->id,
            'control_panel' => 'fivem_txadmin',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'hostname' => 'fivem-no-ack',
            'payment_method' => 'qris',
            'terms_accepted' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors(['warning_acknowledged']);

        // UDP + TCP firewall rules for WireGuard, RustDesk, FiveM, Minecraft.
        $wg = $registry->get('wireguard_easy');
        $wgRules = TencentLighthouseProvider::buildMissingFirewallRules([], $wg->ports, $wg->udpPorts, 'wireguard_easy');
        $this->assertNotEmpty(array_filter($wgRules, fn ($r) => $r['Protocol'] === 'UDP' && $r['Port'] === '51820'));
        $this->assertNotEmpty(array_filter($wgRules, fn ($r) => $r['Protocol'] === 'TCP' && $r['Port'] === '51821'));

        $rd = $registry->get('rustdesk_server');
        $rdRules = TencentLighthouseProvider::buildMissingFirewallRules([], $rd->ports, $rd->udpPorts, 'rustdesk_server');
        $this->assertNotEmpty(array_filter($rdRules, fn ($r) => $r['Protocol'] === 'UDP' && $r['Port'] === '21116'));
        $this->assertNotEmpty(array_filter($rdRules, fn ($r) => $r['Protocol'] === 'TCP' && $r['Port'] === '21115'));
    }

    public function test_reinstall_enforces_ram_and_warning_gates_and_renders_shared_modal(): void
    {
        [$user, $org] = $this->createCustomer('reinstallguard');
        $spec = VpsSpec::where('name', 'Starter XS')->firstOrFail();

        $order = Order::create([
            'customer_id' => $user->id,
            'organization_id' => $org->id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'coolify',
            'provider' => 'cravacloud',
            'hostname' => 'srv-reinstall-guard',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'status' => 'active',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => $spec->sell_price,
            'paid_at' => now(),
        ]);

        $vps = VpsInstance::create([
            'customer_id' => $user->id,
            'organization_id' => $org->id,
            'order_id' => $order->id,
            'hostname' => 'srv-reinstall-guard',
            'public_ip' => '103.150.10.22',
            'os' => 'ubuntu2404',
            'status' => 'running',
            'cpu' => 2,
            'ram' => 2,
            'disk' => 40,
            'control_panel' => 'coolify',
            'provider' => 'cravacloud',
            'initial_root_password' => 'SecretPass#123',
            'expires_at' => now()->addDays(20),
        ]);

        $this->actingAs($user)->get("/dashboard/vps/{$vps->id}")
            ->assertOk()
            ->assertSee('stackSearch', false)
            ->assertSee('stackCategory', false)
            ->assertSee('handleStackClick', false)
            ->assertSee('warningModalOpen', false)
            ->assertSee('bg-white/95', false);

        // 2 GB VPS trying to reinstall to dify_ollama (min 4 GB) -> 422 JSON / validation error
        $this->actingAs($user)->postJson("/dashboard/vps/{$vps->id}/requests/reinstall", [
            'os' => 'ubuntu2404',
            'control_panel' => 'dify_ollama',
            'confirm_hostname' => $vps->hostname,
        ])->assertStatus(422)->assertJsonValidationErrors(['control_panel']);

        // Warning stack without warning_acknowledged -> 422
        $this->actingAs($user)->postJson("/dashboard/vps/{$vps->id}/requests/reinstall", [
            'os' => 'ubuntu2404',
            'control_panel' => 'omniroute',
            'confirm_hostname' => $vps->hostname,
        ])->assertStatus(422)->assertJsonValidationErrors(['warning_acknowledged']);

        // With acknowledgement -> succeeds and updates ticket request_data + order metadata
        $this->actingAs($user)->post("/dashboard/vps/{$vps->id}/requests/reinstall", [
            'os' => 'ubuntu2404',
            'control_panel' => 'omniroute',
            'confirm_hostname' => $vps->hostname,
            'warning_acknowledged' => '1',
        ])->assertRedirect(route('dashboard.vps.show', $vps->id));

        $ticket = SupportTicket::where('vps_instance_id', $vps->id)->firstOrFail();
        $this->assertTrue($ticket->request_data['warning_acknowledged']);
        $this->assertSame('omniroute', $ticket->request_data['acknowledged_stack']);

        $freshOrder = $order->fresh();
        $this->assertTrue($freshOrder->metadata['warning_acknowledged']);
        $this->assertSame('omniroute', $freshOrder->metadata['acknowledged_stack']);
    }

    public function test_batch2_stacks_are_registered_with_warnings_ram_gates_and_udp_ports(): void
    {
        $this->seed();
        /** @var StackRegistry $registry */
        $registry = app(StackRegistry::class);

        $batch2Keys = [
            'erpnext', 'dolibarr', 'twenty_crm', 'invoice_ninja', 'akaunting',
            'paperless_ngx', 'stirling_pdf', 'bookstack', 'outline', 'wikijs',
            'plane', 'calcom', 'chatwoot', 'typebot', 'mattermost', 'listmonk',
            'discourse', 'flarum', 'vaultwarden', 'adguard_home', 'pihole',
            'authentik', 'keycloak', 'guacamole', 'openvpn', 'headscale',
            'metabase', 'plausible', 'matomo', 'meilisearch', 'qdrant',
            'redis_insight', 'mongodb',
        ];

        $this->assertGreaterThanOrEqual(94, count($registry->all()));
        $labels = Order::stackLabels();

        foreach ($batch2Keys as $key) {
            $stack = $registry->get($key);
            $this->assertNotNull($stack, "Batch 2 stack [{$key}] missing from StackRegistry.");
            $this->assertArrayHasKey($key, $labels, "Batch 2 stack [{$key}] missing from Order::stackLabels().");
        }

        // Heavy 4 GB Batch 2 stacks rejected on Starter XS (2 GB).
        [$user] = $this->createCustomer('batch2ram');
        $starterXs = VpsSpec::where('name', 'Starter XS')->firstOrFail();
        foreach (['erpnext', 'twenty_crm', 'plane', 'calcom', 'chatwoot', 'typebot', 'discourse', 'authentik', 'metabase', 'plausible'] as $i => $heavyKey) {
            $this->actingAs($user)->postJson('/checkout', [
                'vps_spec_id' => $starterXs->id,
                'control_panel' => $heavyKey,
                'datacenter_location' => 'indonesia',
                'os' => 'ubuntu2404',
                'hostname' => "b2-xs-{$i}",
                'payment_method' => 'qris',
                'terms_accepted' => 1,
                'warning_acknowledged' => 1,
            ])->assertStatus(422)->assertJsonValidationErrors(['control_panel']);
        }

        // Network/activation warning enforcement on Starter S (4 GB).
        $starterS = VpsSpec::where('name', 'Starter S')->firstOrFail();
        foreach (['outline', 'vaultwarden', 'adguard_home', 'pihole', 'openvpn', 'headscale'] as $i => $warnKey) {
            $this->actingAs($user)->postJson('/checkout', [
                'vps_spec_id' => $starterS->id,
                'control_panel' => $warnKey,
                'datacenter_location' => 'indonesia',
                'os' => 'ubuntu2404',
                'hostname' => "b2-warn-{$i}",
                'payment_method' => 'qris',
                'terms_accepted' => 1,
            ])->assertStatus(422)->assertJsonValidationErrors(['warning_acknowledged']);
        }

        // TCP + UDP firewall parity for DNS & VPN Batch 2 stacks.
        $adguard = $registry->get('adguard_home');
        $adguardRules = TencentLighthouseProvider::buildMissingFirewallRules([], $adguard->ports, $adguard->udpPorts, 'adguard_home');
        $this->assertNotEmpty(array_filter($adguardRules, fn ($r) => $r['Protocol'] === 'UDP' && $r['Port'] === '53'));
        $this->assertNotEmpty(array_filter($adguardRules, fn ($r) => $r['Protocol'] === 'TCP' && $r['Port'] === '53'));
        $this->assertNotEmpty(array_filter($adguardRules, fn ($r) => $r['Protocol'] === 'TCP' && $r['Port'] === '3000'));

        $pihole = $registry->get('pihole');
        $piholeRules = TencentLighthouseProvider::buildMissingFirewallRules([], $pihole->ports, $pihole->udpPorts, 'pihole');
        $this->assertNotEmpty(array_filter($piholeRules, fn ($r) => $r['Protocol'] === 'UDP' && $r['Port'] === '53'));
        $this->assertNotEmpty(array_filter($piholeRules, fn ($r) => $r['Protocol'] === 'TCP' && $r['Port'] === '80'));

        $openvpn = $registry->get('openvpn');
        $ovpnRules = TencentLighthouseProvider::buildMissingFirewallRules([], $openvpn->ports, $openvpn->udpPorts, 'openvpn');
        $this->assertNotEmpty(array_filter($ovpnRules, fn ($r) => $r['Protocol'] === 'UDP' && $r['Port'] === '1194'));
        $this->assertNotEmpty(array_filter($ovpnRules, fn ($r) => $r['Protocol'] === 'TCP' && $r['Port'] === '943'));

        $headscale = $registry->get('headscale');
        $hsRules = TencentLighthouseProvider::buildMissingFirewallRules([], $headscale->ports, $headscale->udpPorts, 'headscale');
        $this->assertNotEmpty(array_filter($hsRules, fn ($r) => $r['Protocol'] === 'UDP' && $r['Port'] === '3478'));
        $this->assertNotEmpty(array_filter($hsRules, fn ($r) => $r['Protocol'] === 'TCP' && $r['Port'] === '8080'));
        $this->assertNotEmpty(array_filter($hsRules, fn ($r) => $r['Protocol'] === 'TCP' && $r['Port'] === '3000'));
    }

    public function test_batch3_final_catalog_completeness_warnings_ram_and_udp(): void
    {
        $this->seed();
        /** @var StackRegistry $registry */
        $registry = app(StackRegistry::class);

        $all = $registry->all();
        $this->assertCount(177, $all, 'Katalog final CravaCloud pasca Batch 3 wajib tepat 177 stack.');

        $panels = array_filter($all, fn ($s) => $s->uiGroup === 'panel');
        $apps = array_filter($all, fn ($s) => $s->uiGroup === 'app');
        $none = array_filter($all, fn ($s) => $s->uiGroup === 'none');
        $this->assertCount(27, $panels, 'Control Panel wajib berjumlah 27 (22 gratis + 5 BYOL).');
        $this->assertCount(149, $apps, '1-Click Applications wajib berjumlah 149 (148 tabel + claude_opencode).');
        $this->assertCount(1, $none);

        $batch3Keys = [
            'ispconfig', 'froxlor', 'keyhelp', 'cockpit', 'yunohost', 'dockge', 'webuzo', 'enhance',
            'nodejs_pm2', 'python_fastapi', 'golang_caddy', 'traefik', 'caddy', 'haproxy', 'coder', 'it_tools', 'hoppscotch',
            'posthog', 'gitlab_ce', 'jenkins', 'woodpecker_ci', 'dozzle', 'watchtower', 'glitchtip', 'gatus',
            'baserow', 'grist', 'clickhouse', 'rabbitmq', 'influxdb',
            'langfuse', 'litellm', 'activepieces', 'localai', 'whisper_asr', 'libretranslate', 'changedetection', 'searxng',
            'woocommerce', 'drupal', 'joomla', 'payload_cms', 'medusajs', 'magento', 'owncast',
            'espocrm', 'firefly_iii', 'kimai', 'mautic', 'snipe_it', 'glpi',
            'vikunja', 'redmine', 'memos', 'affine', 'cryptpad',
            'rocketchat', 'zulip', 'matrix_synapse', 'mastodon', 'ntfy',
            'owncloud', 'seafile', 'filebrowser', 'syncthing', 'photoprism', 'jellyfin', 'plex', 'navidrome', 'home_assistant', 'linkwarden', 'freshrss',
            'netbird', 'kasm_workspaces', 'homarr', 'openspeedtest',
            'crafty_controller', 'minecraft_bedrock', 'palworld_server', 'valheim_server', 'terraria_tshock', 'cs2_server', 'rust_server',
        ];
        $this->assertCount(83, $batch3Keys);

        $labels = Order::stackLabels();
        foreach ($batch3Keys as $key) {
            $stack = $registry->get($key);
            $this->assertNotNull($stack, "Batch 3 stack [{$key}] missing from StackRegistry.");
            $this->assertArrayHasKey($key, $labels, "Batch 3 stack [{$key}] missing from Order::stackLabels().");
        }

        // BYOL panels: webuzo + enhance require license_acknowledged & warning_acknowledged
        [$user] = $this->createCustomer('batch3guard');
        $starterS = VpsSpec::where('name', 'Starter S')->firstOrFail();
        $starterXs = VpsSpec::where('name', 'Starter XS')->firstOrFail();

        $this->actingAs($user)->postJson('/checkout', [
            'vps_spec_id' => $starterS->id,
            'control_panel' => 'webuzo',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'hostname' => 'b3-webuzo',
            'payment_method' => 'qris',
            'terms_accepted' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors(['license_acknowledged']);

        $this->actingAs($user)->postJson('/checkout', [
            'vps_spec_id' => $starterXs->id,
            'control_panel' => 'enhance',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'hostname' => 'b3-enhance-ram',
            'payment_method' => 'qris',
            'terms_accepted' => 1,
            'warning_acknowledged' => 1,
            'license_acknowledged' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors(['control_panel']);

        // 8 GB RAM gate: posthog, gitlab_ce, palworld_server, rust_server rejected on Starter S (4 GB)
        foreach (['posthog', 'gitlab_ce', 'palworld_server', 'rust_server'] as $i => $ram8Key) {
            $this->actingAs($user)->postJson('/checkout', [
                'vps_spec_id' => $starterS->id,
                'control_panel' => $ram8Key,
                'datacenter_location' => 'indonesia',
                'os' => 'ubuntu2404',
                'hostname' => "b3-8g-{$i}",
                'payment_method' => 'qris',
                'terms_accepted' => 1,
                'warning_acknowledged' => 1,
            ])->assertStatus(422)->assertJsonValidationErrors(['control_panel']);
        }

        // CS2 Steam GSLT activation warning on Starter S (4 GB)
        $this->actingAs($user)->postJson('/checkout', [
            'vps_spec_id' => $starterS->id,
            'control_panel' => 'cs2_server',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'hostname' => 'b3-cs2-noack',
            'payment_method' => 'qris',
            'terms_accepted' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors(['warning_acknowledged']);

        // UDP + TCP range firewall parity for Valheim, CS2, Syncthing, NetBird
        $valheim = $registry->get('valheim_server');
        $vhRules = TencentLighthouseProvider::buildMissingFirewallRules([], $valheim->ports, $valheim->udpPorts, 'valheim_server');
        $this->assertNotEmpty(array_filter($vhRules, fn ($r) => $r['Protocol'] === 'UDP' && $r['Port'] === '2456-2458'));

        $cs2 = $registry->get('cs2_server');
        $cs2Rules = TencentLighthouseProvider::buildMissingFirewallRules([], $cs2->ports, $cs2->udpPorts, 'cs2_server');
        $this->assertNotEmpty(array_filter($cs2Rules, fn ($r) => $r['Protocol'] === 'TCP' && $r['Port'] === '27015'));
        $this->assertNotEmpty(array_filter($cs2Rules, fn ($r) => $r['Protocol'] === 'UDP' && $r['Port'] === '27015'));
        $this->assertNotEmpty(array_filter($cs2Rules, fn ($r) => $r['Protocol'] === 'UDP' && $r['Port'] === '27020'));
    }
}
