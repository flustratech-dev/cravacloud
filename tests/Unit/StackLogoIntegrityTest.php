<?php

namespace Tests\Unit;

use App\Services\Provisioning\Stacks\StackRegistry;
use Tests\TestCase;

/**
 * Batch 0 logo integrity guard: every active stack (except 'none') must ship
 * a real vendor SVG/PNG file on disk with valid dimensions/XML, zero <text>
 * monogram fallbacks, and an exact SHA-256 match against resources/data/stack-logos.json.
 */
class StackLogoIntegrityTest extends TestCase
{
    private const ALLOWED_TIERS = ['dashboard-icons', 'simple-icons', 'vendor-official'];

    public function test_every_stack_logo_exists_and_is_valid_real_asset(): void
    {
        $registry = app(StackRegistry::class);

        foreach ($registry->all() as $key => $definition) {
            if ($key === 'none') {
                $this->assertNull($definition->logoPath);

                continue;
            }

            $this->assertNotEmpty($definition->logoPath, "Stack [{$key}] wajib memiliki logoPath.");
            $this->assertAssetIsValidRealLogo(public_path($definition->logoPath), $key, 'primary');

            if ($definition->secondaryLogoPath !== null) {
                $this->assertAssetIsValidRealLogo(public_path($definition->secondaryLogoPath), $key, 'secondary');
            }
        }
    }

    public function test_logo_manifest_covers_every_stack_and_matches_sha256(): void
    {
        $manifestPath = resource_path('data/stack-logos.json');
        $this->assertFileExists($manifestPath);

        $entries = json_decode((string) file_get_contents($manifestPath), true);
        $this->assertIsArray($entries);
        $this->assertNotEmpty($entries);

        $byPath = [];
        foreach ($entries as $entry) {
            $key = (string) ($entry['key'] ?? '');
            $tier = (string) ($entry['source_tier'] ?? '');
            $url = (string) ($entry['source_url'] ?? '');
            $localPath = ltrim((string) ($entry['local_path'] ?? ''), '/');
            $sha256 = (string) ($entry['sha256'] ?? '');

            $this->assertNotEmpty($key);
            $this->assertContains($tier, self::ALLOWED_TIERS, "Logo [{$key}] menggunakan tier tidak sah: {$tier}");
            $this->assertStringStartsWith('https://', $url, "Logo [{$key}] source_url harus HTTPS.");
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $sha256, "Logo [{$key}] wajib memiliki hash SHA-256 valid.");

            $fullPath = public_path($localPath);
            $this->assertFileExists($fullPath, "Logo manifest [{$localPath}] tidak ditemukan di public/.");
            $this->assertSame($sha256, hash_file('sha256', $fullPath), "SHA-256 tidak cocok untuk [{$localPath}].");

            $byPath[$localPath] = $entry;
        }

        foreach (app(StackRegistry::class)->all() as $key => $definition) {
            if ($key === 'none') {
                continue;
            }
            $this->assertArrayHasKey(
                ltrim((string) $definition->logoPath, '/'),
                $byPath,
                "logoPath [{$definition->logoPath}] milik [{$key}] tidak terdaftar di stack-logos.json."
            );
            if ($definition->secondaryLogoPath !== null) {
                $this->assertArrayHasKey(
                    ltrim($definition->secondaryLogoPath, '/'),
                    $byPath,
                    "secondaryLogoPath [{$definition->secondaryLogoPath}] milik [{$key}] tidak terdaftar di stack-logos.json."
                );
            }
        }

        foreach (['panels', 'apps'] as $dir) {
            $files = glob(public_path("images/logos/{$dir}/*")) ?: [];
            foreach ($files as $absFile) {
                if (! is_file($absFile)) {
                    continue;
                }
                $relPath = 'images/logos/'.$dir.'/'.basename($absFile);
                $this->assertArrayHasKey($relPath, $byPath, "File logo yatim/usang tidak terdaftar di manifest: {$relPath}");
            }
        }
    }

    private function assertAssetIsValidRealLogo(string $absPath, string $stackKey, string $role): void
    {
        $this->assertFileExists($absPath, "Logo {$role} untuk [{$stackKey}] tidak ditemukan: {$absPath}");

        $size = filesize($absPath);
        $this->assertIsInt($size);
        $this->assertGreaterThanOrEqual(200, $size, "Logo {$role} [{$stackKey}] terlalu kecil ({$size} B).");
        $this->assertLessThanOrEqual(256000, $size, "Logo {$role} [{$stackKey}] terlalu besar ({$size} B > 250 KB).");

        $content = (string) file_get_contents($absPath);
        $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));

        if ($ext === 'svg') {
            $lower = strtolower($content);
            $this->assertStringNotContainsString('<html', $lower, "Logo [{$stackKey}] tampak seperti dokumen HTML.");
            $this->assertStringNotContainsString('<!doctype html', $lower, "Logo [{$stackKey}] tampak seperti dokumen HTML.");
            $this->assertStringNotContainsString('<script', $lower, "Logo [{$stackKey}] mengandung tag <script>.");
            $this->assertStringNotContainsString('prefers-color-scheme', $lower, "Logo [{$stackKey}] mengandung prefers-color-scheme yang berisiko hilang di dark mode.");
            $this->assertDoesNotMatchRegularExpression('/<text\b/i', $content, "Logo [{$stackKey}] mengandung <text> (pola monogram terlarang).");

            $prev = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($content);
            libxml_clear_errors();
            libxml_use_internal_errors($prev);

            $this->assertNotFalse($xml, "Logo SVG [{$stackKey}] bukan XML valid.");
            $this->assertSame('svg', strtolower($xml->getName()), "Root element [{$stackKey}] bukan <svg>.");

            $attrs = $xml->attributes();
            $viewBox = trim((string) ($attrs['viewBox'] ?? $attrs['viewbox'] ?? ''));
            $this->assertNotEmpty($viewBox, "Logo SVG [{$stackKey}] wajib memiliki atribut viewBox.");

            $vbParts = preg_split('/[\s,]+/', $viewBox);
            if (is_array($vbParts) && count($vbParts) === 4) {
                $vw = (float) $vbParts[2];
                $vh = (float) $vbParts[3];
                $this->assertGreaterThan(0.0, $vw, "Logo SVG [{$stackKey}] lebar viewBox <= 0.");
                $this->assertGreaterThan(0.0, $vh, "Logo SVG [{$stackKey}] tinggi viewBox <= 0.");
                $ratio = $vw / $vh;
                $this->assertGreaterThanOrEqual(0.2, $ratio, "Logo SVG [{$stackKey}] rasio viewBox terlalu sempit ({$ratio}).");
                $this->assertLessThanOrEqual(5.0, $ratio, "Logo SVG [{$stackKey}] rasio viewBox terlalu lebar/banner ({$ratio}).");
            }

            return;
        }

        if ($ext === 'png') {
            $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $content, "Logo PNG [{$stackKey}] gagal validasi magic bytes.");
            $info = @getimagesizefromstring($content);
            $this->assertIsArray($info, "Logo PNG [{$stackKey}] gagal dibaca getimagesizefromstring().");
            $this->assertSame('image/png', $info['mime'] ?? null);
            $this->assertGreaterThanOrEqual(64, $info[0], "Logo PNG [{$stackKey}] lebar < 64px.");
            $this->assertGreaterThanOrEqual(64, $info[1], "Logo PNG [{$stackKey}] tinggi < 64px.");

            if (function_exists('imagecreatefromstring')) {
                $gd = @imagecreatefromstring($content);
                if ($gd !== false) {
                    $w = imagesx($gd);
                    $h = imagesy($gd);
                    $stepX = max(1, (int) floor($w / 20));
                    $stepY = max(1, (int) floor($h / 20));
                    $sampled = 0;
                    $nonWhite = 0;
                    for ($x = 0; $x < $w; $x += $stepX) {
                        for ($y = 0; $y < $h; $y += $stepY) {
                            $sampled++;
                            $rgba = imagecolorat($gd, $x, $y);
                            $alpha = ($rgba >> 24) & 0x7F;
                            $r = ($rgba >> 16) & 0xFF;
                            $g = ($rgba >> 8) & 0xFF;
                            $b = $rgba & 0xFF;
                            if ($alpha < 110 && ($r < 235 || $g < 235 || $b < 235)) {
                                $nonWhite++;
                            }
                        }
                    }
                    imagedestroy($gd);
                    $this->assertGreaterThanOrEqual(
                        max(3, (int) floor($sampled * 0.01)),
                        $nonWhite,
                        "Logo PNG [{$stackKey}] tampak kosong/putih polos ({$nonWhite}/{$sampled} piksel terlihat)."
                    );
                }
            }

            return;
        }

        $this->fail("Format logo tidak didukung untuk [{$stackKey}]: {$ext}");
    }
}
