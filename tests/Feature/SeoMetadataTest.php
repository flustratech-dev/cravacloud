<?php

namespace Tests\Feature;

use App\Models\VpsSpec;
use App\Support\KatalogDokumentasi;
use App\Support\KatalogProduk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_full_seo_metadata(): void
    {
        $response = $this->get(route('home'));

        $response->assertStatus(200);

        // Standard Meta
        $response->assertSee('<title>CravaCloud — Cloud VPS NVMe KVM Indonesia Mulai Rp 80rb/bln</title>', false);
        $response->assertSee('name="description"', false);
        // <meta name="keywords"> sengaja tidak ada lagi — tidak dipakai mesin pencari mana pun.
        $response->assertDontSee('name="keywords"', false);
        $response->assertSee('name="robots" content="index, follow', false);
        $response->assertSee('rel="canonical"', false);

        // Geo targeting
        $response->assertSee('name="geo.region" content="ID-JK"', false);
        $response->assertSee('name="geo.placename" content="Jakarta, Indonesia"', false);

        // Open Graph
        $response->assertSee('property="og:type" content="website"', false);
        $response->assertSee('property="og:site_name" content="CravaCloud Indonesia"', false);
        $response->assertSee('property="og:title"', false);
        $response->assertSee('property="og:description"', false);
        $response->assertSee('property="og:image"', false);
        $response->assertSee('property="og:locale" content="id_ID"', false);

        // Twitter Card
        $response->assertSee('name="twitter:card" content="summary_large_image"', false);
        $response->assertSee('name="twitter:title"', false);
        $response->assertSee('name="twitter:description"', false);
        $response->assertSee('name="twitter:image"', false);
    }

    public function test_homepage_renders_google_site_verification_when_configured(): void
    {
        config(['services.google.site_verification' => 'test-google-verification-token-12345']);

        $response = $this->get(route('home'));

        $response->assertStatus(200);
        $response->assertSee('name="google-site-verification" content="test-google-verification-token-12345"', false);
    }

    public function test_homepage_renders_valid_json_ld_schema(): void
    {
        $response = $this->get(route('home'));

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('application/ld+json', $content);

        // Extract JSON inside ld+json tag
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $content, $matches);
        $this->assertNotEmpty($matches, 'JSON-LD schema tag found');

        $schema = json_decode(trim($matches[1]), true);
        $this->assertIsArray($schema, 'JSON-LD is valid JSON');
        $this->assertEquals('https://schema.org', $schema['@context']);
        $this->assertArrayHasKey('@graph', $schema);

        $types = array_column($schema['@graph'], '@type');
        $this->assertContains('Organization', $types);
        $this->assertContains('WebSite', $types);
        $this->assertContains('Product', $types);
    }

    public function test_sitemap_xml_endpoint_returns_valid_xml(): void
    {
        $response = $this->get(route('sitemap'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');

        $content = $response->getContent();
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', trim($content));
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $content);
        $this->assertStringContainsString('<loc>', $content);
        $this->assertStringContainsString('<lastmod>', $content);
        $this->assertStringContainsString('<changefreq>', $content);
        $this->assertStringContainsString('<priority>', $content);

        // SimpleXML validation
        $xml = simplexml_load_string($content);
        $this->assertNotFalse($xml, 'Sitemap is valid XML structure');
        // Enam halaman publik + satu halaman per kelompok dokumentasi. Checkout
        // sengaja tidak didaftarkan: bagi perayap isinya formulir kosong, dan
        // halamannya sendiri sudah bertanda noindex.
        $this->assertCount(
            6 + count(KatalogProduk::semua()) + count(KatalogDokumentasi::kelompok()),
            $xml->url,
        );
        $this->assertStringNotContainsString('/checkout', $content);

        // Halaman dokumentasi yang tidak terdaftar di sini hanya bisa ditemukan
        // lewat sidebar, dan itu yang membuatnya tidak pernah terindeks.
        foreach (array_keys(KatalogDokumentasi::kelompok()) as $slug) {
            $this->assertStringContainsString("/docs/{$slug}</loc>", $content);
        }
    }

    public function test_robots_txt_contains_sitemap_and_disallows(): void
    {
        $robotsPath = public_path('robots.txt');
        $this->assertFileExists($robotsPath);

        $robotsContent = file_get_contents($robotsPath);
        $this->assertStringContainsString('User-agent: *', $robotsContent);
        $this->assertStringContainsString('Allow: /', $robotsContent);
        $this->assertStringContainsString('Disallow: /admin/', $robotsContent);
        $this->assertStringContainsString('Disallow: /dashboard/', $robotsContent);
        $this->assertStringContainsString('Sitemap: https://cravacloud.id/sitemap.xml', $robotsContent);
    }

    public function test_organization_schema_names_the_brand_and_its_subdomain(): void
    {
        $schema = $this->schemaGraph();
        $organization = $this->findByType($schema, 'Organization');

        // Kebingungan dengan penyedia asing bernama mirip diselesaikan dengan
        // menyebut sendiri nama-nama yang dipakai orang saat mencari kita.
        $this->assertContains('CravaCloud', $organization['alternateName']);
        $this->assertContains('CravaCloud Indonesia', $organization['alternateName']);

        // WA Gateway di subdomain harus terbaca sebagai bagian dari merek yang sama.
        $this->assertSame(
            rtrim(config('whatsapp.url'), '/').'#organization',
            $organization['subOrganization']['@id'],
        );

        // Vendor pengembang bukan bagian dari identitas entitas. Kreditnya tetap
        // tampil sebagai teks biasa di footer — yang dilarang di sini cuma relasinya.
        $this->assertArrayNotHasKey('parentOrganization', $organization);
    }

    public function test_product_prices_follow_the_active_catalogue(): void
    {
        $paket = fn (string $name, int $price) => VpsSpec::create([
            'name' => $name,
            'category' => 'vps',
            'cpu' => 2,
            'ram' => 4,
            'disk' => 50,
            'bandwidth' => 1000,
            'cost_price' => (int) ($price * 0.6),
            'sell_price' => $price,
            'is_active' => true,
        ]);

        $paket('Uji Termurah', 55000);
        $paket('Uji Termahal', 720000);

        $product = $this->findByType($this->schemaGraph(), 'Product');

        // Angka mati akan diam-diam berbeda dari halaman harga begitu katalog berubah.
        $this->assertSame('55000', $product['offers']['lowPrice']);
        $this->assertSame('720000', $product['offers']['highPrice']);
    }

    public function test_docs_page_carries_its_own_description_and_breadcrumb(): void
    {
        $response = $this->get(route('docs'));

        $response->assertStatus(200);
        $response->assertSee('provisioning VPS, akses SSH', false);

        $breadcrumb = $this->findByType($this->schemaGraph(route('docs')), 'BreadcrumbList');
        $this->assertSame('Beranda', $breadcrumb['itemListElement'][0]['name']);
        $this->assertSame('Dokumentasi', $breadcrumb['itemListElement'][1]['name']);

        // Butir terakhir adalah halaman yang sedang dibuka, jadi tanpa 'item'.
        $this->assertArrayNotHasKey('item', $breadcrumb['itemListElement'][1]);
    }

    public function test_every_public_page_has_a_distinct_description(): void
    {
        $descriptions = [];

        foreach (['home', 'docs', 'status', 'terms', 'privacy', 'refund'] as $name) {
            preg_match(
                '/<meta name="description" content="([^"]*)"/',
                $this->get(route($name))->getContent(),
                $m,
            );
            $descriptions[$name] = $m[1] ?? '';
        }

        // Deskripsi yang sama di semua halaman membuat cuplikan hasil pencarian
        // tidak menjelaskan halaman yang sedang ditampilkan.
        $this->assertSame(
            count($descriptions),
            count(array_unique($descriptions)),
            'Setiap halaman publik harus punya meta description sendiri.',
        );
    }

    public function test_checkout_is_not_indexed(): void
    {
        $response = $this->get(route('checkout'));

        // Formulir kosong tidak boleh bersaing dengan beranda untuk pencarian merek.
        $response->assertSee('name="robots" content="noindex, follow"', false);
    }

    public function test_favicon_files_are_square_multiples_of_48(): void
    {
        // Google Search mengabaikan favicon yang tidak persegi atau lebih kecil
        // dari 48px, lalu menggantinya dengan ikon bawaan di hasil pencarian.
        $this->assertFileExists(public_path('favicon.ico'));

        foreach ([48, 96, 192] as $size) {
            $path = public_path("images/favicon-{$size}x{$size}.png");
            $this->assertFileExists($path);

            [$width, $height] = getimagesize($path);
            $this->assertSame($size, $width);
            $this->assertSame($size, $height);
            $this->assertSame(0, $size % 48, "Ukuran {$size}px harus kelipatan 48.");
        }

        // 512px tidak ikut aturan kelipatan 48: ia bukan favicon melainkan logo
        // untuk webmanifest dan Organization.logo, yang butuh sisi panjang.
        [$width, $height] = getimagesize(public_path('images/favicon-512x512.png'));
        $this->assertSame(512, $width);
        $this->assertSame(512, $height);
    }

    public function test_open_graph_image_dimensions_match_the_actual_file(): void
    {
        $content = $this->get(route('home'))->getContent();

        preg_match('/property="og:image" content="([^"]*)"/', $content, $image);
        preg_match('/property="og:image:width" content="(\d+)"/', $content, $width);
        preg_match('/property="og:image:height" content="(\d+)"/', $content, $height);

        $path = public_path(ltrim(parse_url($image[1], PHP_URL_PATH), '/'));
        [$realWidth, $realHeight] = getimagesize($path);

        // Ukuran yang dideklarasikan salah membuat pratinjau tautan dirender
        // terpotong atau ditolak sama sekali oleh WhatsApp dan X.
        $this->assertSame($realWidth, (int) $width[1]);
        $this->assertSame($realHeight, (int) $height[1]);
    }

    /** Mengambil @graph JSON-LD dari sebuah halaman. */
    private function schemaGraph(?string $url = null): array
    {
        preg_match(
            '/<script type="application\/ld\+json">(.*?)<\/script>/s',
            $this->get($url ?? route('home'))->getContent(),
            $m,
        );

        return json_decode(trim($m[1]), true)['@graph'];
    }

    /** Mencari satu simpul bertipe tertentu di dalam @graph. */
    private function findByType(array $graph, string $type): array
    {
        foreach ($graph as $node) {
            if (($node['@type'] ?? null) === $type) {
                return $node;
            }
        }

        $this->fail("Tidak ada simpul bertipe {$type} di dalam @graph.");
    }

    /**
     * `/vps`, `/ai`, dan `/database` dulu menjawab 301 ke anchor beranda. URL
     * yang menjawab redirect tidak pernah diindeks sebagai halaman tersendiri,
     * jadi seluruh kata kunci produk tidak punya halaman untuk ditunjuk.
     */
    public function test_product_urls_are_pages_not_redirects(): void
    {
        foreach (['packages.vps', 'packages.ai', 'packages.db'] as $name) {
            $response = $this->get(route($name));

            $response->assertStatus(200);
            $response->assertSee('<h1', false);
        }
    }

    public function test_each_product_page_has_its_own_identity(): void
    {
        $judul = [];
        $deskripsi = [];

        foreach (array_keys(KatalogProduk::semua()) as $slug) {
            $content = $this->get(url($slug === 'database' ? 'database' : $slug))->getContent();

            preg_match('/<title>([^<]*)<\/title>/', $content, $t);
            preg_match('/<meta name="description" content="([^"]*)"/', $content, $d);

            $judul[] = $t[1] ?? '';
            $deskripsi[] = $d[1] ?? '';

            // Remah jejak menggantikan URL mentah di hasil pencarian.
            $this->assertStringContainsString('BreadcrumbList', $content);
        }

        $this->assertSame(count($judul), count(array_unique($judul)));
        $this->assertSame(count($deskripsi), count(array_unique($deskripsi)));
    }

    public function test_product_page_prices_come_from_the_catalogue(): void
    {
        VpsSpec::create([
            'name' => 'Uji Paket Produk',
            'category' => 'vps',
            'cpu' => 2, 'ram' => 4, 'disk' => 50, 'bandwidth' => 1000,
            'cost_price' => 50000, 'sell_price' => 137500, 'is_active' => true,
        ]);

        // Halaman yang menjanjikan angka berbeda dari yang ditagihkan adalah
        // janji yang kita langgar di hadapan orang yang baru saja membayar.
        $this->get(route('packages.vps'))
            ->assertSee('Uji Paket Produk')
            ->assertSee('Rp 137.500');
    }

    /**
     * Google membatasi rich result FAQ ke situs pemerintah dan kesehatan sejak
     * Agustus 2023, jadi ini tidak dipasang untuk mendapat kotak lipat di hasil
     * pencarian. Yang membacanya AI Overview dan mesin jawab lain — dan di
     * sanalah merek kita paling sering tertukar dengan penyedia bernama mirip.
     */
    public function test_product_pages_publish_their_faq_as_structured_data(): void
    {
        $faq = $this->findByType($this->schemaGraph(route('packages.vps')), 'FAQPage');

        $this->assertSame(
            count(KatalogProduk::ambil('vps')['tanya']),
            count($faq['mainEntity']),
        );
        $this->assertSame('Question', $faq['mainEntity'][0]['@type']);
        $this->assertNotEmpty($faq['mainEntity'][0]['acceptedAnswer']['text']);
    }

    public function test_product_pages_link_into_the_documentation(): void
    {
        // Halaman yang tidak ditaut dari mana pun jarang ditelusuri perayap.
        $this->get(route('packages.vps'))
            ->assertSee(route('docs.kelompok', 'keamanan'), false);
    }
}
