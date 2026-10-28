<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pengaman regresi: setiap kombinasi jenis paket x status server x data opsional
 * yang bisa dialami pelanggan harus merender dashboard tanpa error, dan tidak
 * boleh menampilkan aksi daya palsu atau link panel tebakan.
 */
class CustomerDashboardMatrixTest extends TestCase
{
    use RefreshDatabase;

    private const STATUSES = ['running', 'provisioning', 'stopped', 'rebooting', 'reinstalling', 'suspended', 'error', 'terminated'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function customer(): User
    {
        $customer = User::create([
            'username' => 'matrixuser',
            'email' => 'matrixuser@example.com',
            'full_name' => 'Pelanggan Matriks',
            'password' => Hash::make('password123'),
            'channel' => 'website',
            'is_admin' => false,
        ]);
        $customer->switchToOrganization($customer->createPersonalOrganization());

        return $customer->fresh();
    }

    /**
     * @return array<string, array{spec: VpsSpec, control_panel: string, extra: array}>
     */
    private function packages(): array
    {
        return [
            'vps' => [
                'spec' => VpsSpec::where('category', 'vps')->firstOrFail(),
                'control_panel' => 'coolify',
                'extra' => [],
            ],
            'ai' => [
                'spec' => VpsSpec::where('category', 'ai_combo')->firstOrFail(),
                'control_panel' => 'vscode_server',
                'extra' => ['app_name' => 'Cloud AI Workstation'],
            ],
            'database' => [
                'spec' => VpsSpec::where('category', 'managed_db')->firstOrFail(),
                'control_panel' => 'managed_database',
                'extra' => [
                    'db_engine' => 'postgres',
                    'db_manager' => 'cloudbeaver',
                    'db_name' => 'cravadb_production',
                    'db_user' => 'admin_crava',
                    'db_password' => 'RahasiaDb#123',
                    'db_port' => 5432,
                ],
            ],
        ];
    }

    private function makeVps(User $customer, array $package, string $status, array $variant, int $n): VpsInstance
    {
        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $package['spec']->id,
            'control_panel' => $package['control_panel'],
            'provider' => 'tencent',
            'hostname' => "matrix-{$n}",
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'status' => 'active',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => $package['spec']->sell_price,
            'paid_at' => now(),
        ]);

        return VpsInstance::create(array_merge([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'order_id' => $order->id,
            'hostname' => "matrix-{$n}",
            'public_ip' => '139.180.210.' . ($n % 250 + 1),
            'os' => 'ubuntu2404',
            'status' => $status,
            'cpu' => $package['spec']->cpu,
            'ram' => $package['spec']->ram,
            'disk' => $package['spec']->disk,
            'control_panel' => $package['control_panel'],
            'provider' => 'tencent',
            'initial_root_password' => 'PasswordAwal#123',
            'starts_at' => now()->subDays(10),
            'expires_at' => now()->addDays(20),
        ], $package['extra'], $variant));
    }

    public function test_every_package_status_and_variant_renders_safely(): void
    {
        $customer = $this->customer();

        $variants = [
            'normal' => [],
            'tanpa_ip' => ['public_ip' => null],
            'dengan_link_panel' => ['app_url' => 'https://panel.matrix.id'],
            'masa_tenggang' => ['expires_at' => now()->subDays(2), 'grace_period_ends_at' => now()->addDays(3)],
            'kadaluarsa' => ['expires_at' => now()->subDays(10), 'grace_period_ends_at' => now()->subDays(5)],
            'tanpa_tanggal' => ['starts_at' => null, 'expires_at' => null, 'grace_period_ends_at' => null],
            'os_label_lama' => ['os' => 'Ubuntu 24.04 LTS'],
        ];

        $n = 0;
        foreach ($this->packages() as $packageName => $package) {
            foreach (self::STATUSES as $status) {
                foreach ($variants as $variantName => $variant) {
                    $n++;
                    $vps = $this->makeVps($customer, $package, $status, $variant, $n);
                    $label = "{$packageName}/{$status}/{$variantName}";

                    $response = $this->actingAs($customer)->get("/dashboard/vps/{$vps->id}");
                    $this->assertSame(200, $response->getStatusCode(), "Detail VPS gagal dirender: {$label}");

                    $html = $response->getContent();
                    foreach (['Nyalakan Server', 'Matikan (Stop)', 'Force Reset', '>Reboot<'] as $forbidden) {
                        $this->assertStringNotContainsString($forbidden, $html, "Aksi daya palsu muncul ({$forbidden}): {$label}");
                    }
                    if ($packageName === 'vps') {
                        $this->assertStringNotContainsString(':8000', $html, "Link panel tebakan IP:8000 muncul: {$label}");
                    }

                    // Tombol permintaan hanya muncul bila server memang boleh mengajukan.
                    $canRequest = $vps->fresh()->acceptsServiceRequests();
                    $this->assertSame(
                        $canRequest,
                        str_contains($html, 'Ajukan Reinstall OS'),
                        "Tombol reinstall tidak sesuai aturan: {$label}"
                    );
                }
            }
        }

        // Daftar VPS memuat seluruh kombinasi sekaligus.
        $index = $this->actingAs($customer)->get('/dashboard');
        $index->assertOk();
        $this->assertStringNotContainsString('Matikan Server', $index->getContent());
        $this->assertStringNotContainsString('Nyalakan Server', $index->getContent());
    }

    public function test_detail_page_with_open_and_closed_requests_renders_for_all_packages(): void
    {
        $customer = $this->customer();

        $n = 100;
        foreach ($this->packages() as $packageName => $package) {
            $n++;
            $vps = $this->makeVps($customer, $package, 'running', [], $n);

            foreach ([SupportTicket::TYPE_REINSTALL, SupportTicket::TYPE_UNREACHABLE] as $type) {
                foreach (['open', 'in_progress', 'resolved', 'closed'] as $ticketStatus) {
                    SupportTicket::create([
                        'customer_id' => $customer->id,
                        'organization_id' => $customer->current_organization_id,
                        'vps_instance_id' => $vps->id,
                        'subject' => "Tiket {$type} {$ticketStatus}",
                        'type' => $type,
                        'request_data' => $type === SupportTicket::TYPE_REINSTALL ? ['os' => 'ubuntu2204', 'control_panel' => $package['control_panel']] : null,
                        'priority' => 'high',
                        'status' => $ticketStatus,
                    ]);
                }
            }

            $response = $this->actingAs($customer)->get("/dashboard/vps/{$vps->id}");
            $this->assertSame(200, $response->getStatusCode(), "Detail VPS dengan tiket gagal: {$packageName}");
            $response->assertSee('Reinstall Sedang Diproses');
            $response->assertDontSee('Ajukan Reinstall OS');

            // Hanya satu banner per jenis permintaan meskipun ada beberapa tiket terbuka.
            $this->assertSame(1, substr_count($response->getContent(), 'Reinstall OS sedang'), "Banner reinstall ganda: {$packageName}");
        }
    }
}
