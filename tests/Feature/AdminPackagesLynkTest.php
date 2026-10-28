<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VpsSpec;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPackagesLynkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_update_package_payment_url_with_full_https(): void
    {
        $admin = User::where('is_admin', true)->first();
        $spec = VpsSpec::find(1);

        $response = $this->actingAs($admin)->put('/admin/packages/' . $spec->id, [
            'name' => $spec->name,
            'category' => $spec->category ?: 'vps',
            'cpu' => $spec->cpu,
            'ram' => $spec->ram,
            'disk' => $spec->disk,
            'bandwidth' => $spec->bandwidth,
            'cost_price' => $spec->cost_price,
            'sell_price' => $spec->sell_price,
            'payment_url' => 'https://lynk.id/cravacloud/student-basic',
            'is_active' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $spec->refresh();
        $this->assertEquals('https://lynk.id/cravacloud/student-basic', $spec->payment_url);
    }

    public function test_admin_can_update_package_payment_url_without_protocol(): void
    {
        $admin = User::where('is_admin', true)->first();
        $spec = VpsSpec::find(1);

        $response = $this->actingAs($admin)->put('/admin/packages/' . $spec->id, [
            'name' => $spec->name,
            'category' => $spec->category ?: 'vps',
            'cpu' => $spec->cpu,
            'ram' => $spec->ram,
            'disk' => $spec->disk,
            'bandwidth' => $spec->bandwidth,
            'cost_price' => $spec->cost_price,
            'sell_price' => $spec->sell_price,
            'payment_url' => 'lynk.id/cravacloud/student-basic-no-protocol',
            'is_active' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $spec->refresh();
        $this->assertEquals('https://lynk.id/cravacloud/student-basic-no-protocol', $spec->payment_url);
    }
}
