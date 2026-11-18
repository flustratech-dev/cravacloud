<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Sinkronisasi hanya dijalankan pada database yang sudah memiliki data katalog
        // (fresh DB diisi oleh VpsSpecSeeder, sedangkan test tanpa seeder berharap tabel kosong).
        if (DB::table('vps_specs')->count() === 0) {
            return;
        }

        $legacyNames = [
            'Student Basic',
            'Standard',
            'Premium',
            'Startup',
            'Business',
            'Mahasiswa Basic',
        ];

        DB::table('vps_specs')
            ->where('category', 'vps')
            ->whereIn('name', $legacyNames)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        $catalog = [
            [
                'name' => 'Starter XS',
                'category' => 'vps',
                'cpu' => 2,
                'ram' => 2,
                'disk' => 40,
                'bandwidth' => 20,
                'cost_price' => 85000,
                'sell_price' => 115000,
                'is_active' => true,
                'is_renewable' => true,
                'tagline' => 'Entry-level hemat untuk aplikasi mikro, bot, web ringan, & dev environment.',
                'target_audience' => 'Developer pemula, aplikasi mikro, bot, dev environment',
                'features' => json_encode(['2 vCPU', '2 GB RAM', '40 GB NVMe SSD', '20 Mbps', '0,5 TB / bulan', '1x Dedicated IPv4']),
                'solution' => 'Node entry-level hemat di datacenter DCI Jakarta dengan virtualisasi KVM NVMe murni.',
                'badge' => 'KVM NVMe',
                'default_stack' => 'none',
                'allowed_providers' => json_encode(['cravacloud']),
                'default_provider' => 'cravacloud',
            ],
            [
                'name' => 'Starter S',
                'category' => 'vps',
                'cpu' => 2,
                'ram' => 4,
                'disk' => 60,
                'bandwidth' => 30,
                'cost_price' => 120000,
                'sell_price' => 165000,
                'is_active' => true,
                'is_renewable' => true,
                'tagline' => 'Pilihan terpopuler untuk Coolify PaaS, website bisnis UKM, & workflow n8n.',
                'target_audience' => 'UKM, Coolify PaaS, workflow n8n',
                'features' => json_encode(['2 vCPU', '4 GB RAM', '60 GB NVMe SSD', '30 Mbps', '1,5 TB / bulan', '1x Dedicated IPv4']),
                'solution' => 'Sweet Spot terbaik untuk menjalankan Coolify PaaS, website bisnis, API, dan n8n.',
                'badge' => 'SWEET SPOT',
                'default_stack' => 'coolify',
                'allowed_providers' => json_encode(['cravacloud']),
                'default_provider' => 'cravacloud',
            ],
            [
                'name' => 'Starter M',
                'category' => 'vps',
                'cpu' => 2,
                'ram' => 8,
                'disk' => 80,
                'bandwidth' => 30,
                'cost_price' => 195000,
                'sell_price' => 269000,
                'is_active' => true,
                'is_renewable' => true,
                'tagline' => 'Kapasitas RAM lega untuk multi-container Docker, aplikasi web, & cache.',
                'target_audience' => 'Multi-container Docker, web app & cache',
                'features' => json_encode(['2 vCPU', '8 GB RAM', '80 GB NVMe SSD', '30 Mbps', '2,5 TB / bulan', '1x Dedicated IPv4']),
                'solution' => 'Memori 8 GB dedikasi untuk workload multi-container Docker dan aplikasi modern.',
                'badge' => '8GB MEMORY',
                'default_stack' => 'coolify',
                'allowed_providers' => json_encode(['cravacloud']),
                'default_provider' => 'cravacloud',
            ],
            [
                'name' => 'Starter L',
                'category' => 'vps',
                'cpu' => 2,
                'ram' => 8,
                'disk' => 100,
                'bandwidth' => 30,
                'cost_price' => 245000,
                'sell_price' => 339000,
                'is_active' => true,
                'is_renewable' => true,
                'tagline' => 'Penyimpanan dan kuota trafik ekstra untuk produksi skala menengah & AI Agent.',
                'target_audience' => 'Produksi skala menengah & AI Agent',
                'features' => json_encode(['2 vCPU', '8 GB RAM', '100 GB NVMe SSD', '30 Mbps', '3,0 TB / bulan', '1x Dedicated IPv4']),
                'solution' => 'Storage 100 GB NVMe lega dengan kuota transfer 3,0 TB/bulan untuk workload produksi.',
                'badge' => '100GB NVMe',
                'default_stack' => 'coolify',
                'allowed_providers' => json_encode(['cravacloud']),
                'default_provider' => 'cravacloud',
            ],
            [
                'name' => 'Enterprise Pro',
                'category' => 'vps',
                'cpu' => 4,
                'ram' => 8,
                'disk' => 180,
                'bandwidth' => 35,
                'cost_price' => 720000,
                'sell_price' => 975000,
                'is_active' => true,
                'is_renewable' => true,
                'tagline' => 'Performa 4 Core untuk SaaS produksi, ERP, & beban database intensif.',
                'target_audience' => 'SaaS produksi, ERP, database intensif',
                'features' => json_encode(['4 vCPU', '8 GB RAM', '180 GB NVMe SSD', '35 Mbps', '5,0 TB / bulan', '1x Dedicated IPv4']),
                'solution' => 'High-compute 4 vCPU untuk aplikasi SaaS produksi, ERP, dan query database berat.',
                'badge' => 'HIGH COMPUTE',
                'default_stack' => 'coolify',
                'allowed_providers' => json_encode(['cravacloud']),
                'default_provider' => 'cravacloud',
            ],
            [
                'name' => 'Enterprise Max',
                'category' => 'vps',
                'cpu' => 4,
                'ram' => 16,
                'disk' => 220,
                'bandwidth' => 40,
                'cost_price' => 1080000,
                'sell_price' => 1459000,
                'is_active' => true,
                'is_renewable' => true,
                'tagline' => 'Memori 16 GB untuk Dify AI Studio, database besar, & multi-aplikasi korporat.',
                'target_audience' => 'Dify AI Studio, database besar, korporat',
                'features' => json_encode(['4 vCPU', '16 GB RAM', '220 GB NVMe SSD', '40 Mbps', '6,0 TB / bulan', '1x Dedicated IPv4']),
                'solution' => 'Komputasi 16 GB RAM optimal untuk Dify AI, orchestration korporat, dan database besar.',
                'badge' => '16GB MEMORY',
                'default_stack' => 'coolify',
                'allowed_providers' => json_encode(['cravacloud']),
                'default_provider' => 'cravacloud',
            ],
            [
                'name' => 'Enterprise Ultra',
                'category' => 'vps',
                'cpu' => 8,
                'ram' => 16,
                'disk' => 300,
                'bandwidth' => 45,
                'cost_price' => 1550000,
                'sell_price' => 2100000,
                'is_active' => true,
                'is_renewable' => true,
                'tagline' => 'Tenaga komputasi 8 vCPU untuk trafik tinggi, microservices, & analisis data.',
                'target_audience' => 'Trafik tinggi, microservices, analisis data',
                'features' => json_encode(['8 vCPU', '16 GB RAM', '300 GB NVMe SSD', '45 Mbps', '7,0 TB / bulan', '1x Dedicated IPv4']),
                'solution' => '8 Dedicated vCPU dengan 300 GB NVMe SSD untuk kluster microservices dan data analytics.',
                'badge' => '8 vCPU CORES',
                'default_stack' => 'dokploy',
                'allowed_providers' => json_encode(['cravacloud']),
                'default_provider' => 'cravacloud',
            ],
            [
                'name' => 'Enterprise Extreme',
                'category' => 'vps',
                'cpu' => 8,
                'ram' => 32,
                'disk' => 380,
                'bandwidth' => 50,
                'cost_price' => 2100000,
                'sell_price' => 2850000,
                'is_active' => true,
                'is_renewable' => true,
                'tagline' => 'Kapasitas puncak 32 GB RAM & 380 GB SSD untuk beban enterprise skala besar.',
                'target_audience' => 'Enterprise skala besar, private LLM/RAG',
                'features' => json_encode(['8 vCPU', '32 GB RAM', '380 GB NVMe SSD', '50 Mbps', '8,0 TB / bulan', '1x Dedicated IPv4']),
                'solution' => 'Flagship enterprise node dengan 32 GB RAM murni dan 380 GB NVMe SSD.',
                'badge' => 'FLAGSHIP SPEC',
                'default_stack' => 'dokploy',
                'allowed_providers' => json_encode(['cravacloud']),
                'default_provider' => 'cravacloud',
            ],
        ];

        foreach ($catalog as $item) {
            $existing = DB::table('vps_specs')->where('name', $item['name'])->first();
            if ($existing) {
                DB::table('vps_specs')->where('id', $existing->id)->update(array_merge($item, [
                    'updated_at' => now(),
                ]));
            } else {
                DB::table('vps_specs')->insert(array_merge($item, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        // Pertahankan integritas histori order: tidak menghapus baris vps_specs.
    }
};
