<?php

namespace App\Console\Commands;

use App\Services\Provisioning\Stacks\StackRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Download, validate, and hash-verify official vendor stack logos from
 * resources/data/stack-logos.json (dashboard-icons, simple-icons, vendor-official).
 */
class StacksSyncLogosCommand extends Command
{
    protected $signature = 'stacks:sync-logos
        {--only= : Comma-separated stack keys}
        {--check : Offline verify files & SHA-256 without downloading}
        {--force : Re-download all logos even if valid}';

    protected $description = 'Sinkronkan & verifikasi integritas logo resmi stack dari manifest resources/data/stack-logos.json';

    private const ALLOWED_TIERS = ['dashboard-icons', 'simple-icons', 'vendor-official'];

    public function handle(StackRegistry $registry): int
    {
        $manifestPath = resource_path('data/stack-logos.json');
        if (! is_file($manifestPath)) {
            $this->error("Manifest tidak ditemukan: {$manifestPath}");

            return self::FAILURE;
        }

        $entries = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($entries)) {
            $this->error('Format JSON pada stack-logos.json tidak valid.');

            return self::FAILURE;
        }

        $onlyRaw = trim((string) $this->option('only'));
        $onlyKeys = $onlyRaw !== '' ? array_values(array_filter(array_map('trim', explode(',', $onlyRaw)))) : null;
        $checkOnly = (bool) $this->option('check');
        $force = (bool) $this->option('force');

        $rows = [];
        $errors = [];

        foreach ($entries as $idx => &$entry) {
            $key = (string) ($entry['key'] ?? '');
            $role = (string) ($entry['role'] ?? 'primary');
            $tier = (string) ($entry['source_tier'] ?? '');
            $url = (string) ($entry['source_url'] ?? '');
            $localRel = ltrim((string) ($entry['local_path'] ?? ''), '/');
            $format = strtolower((string) ($entry['format'] ?? ''));
            $brandColor = $entry['brand_color'] ?? null;
            $expectedHash = (string) ($entry['sha256'] ?? '');

            if ($onlyKeys !== null && ! in_array($key, $onlyKeys, true)) {
                continue;
            }

            if (! in_array($tier, self::ALLOWED_TIERS, true) || ! str_starts_with($url, 'https://')) {
                $errors[] = "{$key} ({$role}): tier atau source_url tidak sah.";
                $rows[] = [$key, $role, $tier, $localRel, 'INVALID_META'];

                continue;
            }

            $absPath = public_path($localRel);

            if ($checkOnly) {
                $issue = $this->validateLocalAsset($absPath, $format, $expectedHash);
                if ($issue !== null) {
                    $errors[] = "{$key} ({$role}) [{$localRel}]: {$issue}";
                    $rows[] = [$key, $role, $tier, $localRel, 'FAIL: '.$issue];
                } else {
                    $rows[] = [$key, $role, $tier, $localRel, 'OK (sha256 match)'];
                }

                continue;
            }

            $alreadyValid = ! $force
                && $expectedHash !== ''
                && $this->validateLocalAsset($absPath, $format, $expectedHash) === null;

            if ($alreadyValid) {
                $rows[] = [$key, $role, $tier, $localRel, 'CACHED'];

                continue;
            }

            $response = Http::timeout(20)
                ->retry(2, 300)
                ->withHeaders(['User-Agent' => 'CravaCloud-LogoSync/1.0'])
                ->get($url);
            if (! $response->successful()) {
                $errors[] = "{$key} ({$role}): HTTP {$response->status()} dari {$url}";
                $rows[] = [$key, $role, $tier, $localRel, 'HTTP_'.$response->status()];

                continue;
            }

            $payload = $response->body();
            if ($format === 'svg' && $tier === 'simple-icons' && is_string($brandColor) && $brandColor !== '') {
                $payload = $this->applySvgBrandColor($payload, $brandColor);
            }

            $issue = $this->validatePayload($payload, $format);
            if ($issue !== null) {
                $errors[] = "{$key} ({$role}): {$issue}";
                $rows[] = [$key, $role, $tier, $localRel, 'INVALID: '.$issue];

                continue;
            }

            $dir = dirname($absPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($absPath, $payload);
            $newHash = hash('sha256', $payload);
            $entry['sha256'] = $newHash;

            $rows[] = [$key, $role, $tier, $localRel, 'SYNCED ('.strlen($payload).' B)'];
        }
        unset($entry);

        if (! $checkOnly) {
            file_put_contents(
                $manifestPath,
                json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n"
            );
        }

        // Cross-check all StackRegistry definitions against the manifest.
        $manifestPaths = array_column($entries, 'local_path');
        foreach ($registry->all() as $key => $definition) {
            if ($key === 'none') {
                continue;
            }
            if (! $definition->logoPath || ! in_array($definition->logoPath, $manifestPaths, true)) {
                $errors[] = "Stack [{$key}] logoPath [{$definition->logoPath}] belum terdaftar di stack-logos.json.";
            }
            if ($definition->secondaryLogoPath && ! in_array($definition->secondaryLogoPath, $manifestPaths, true)) {
                $errors[] = "Stack [{$key}] secondaryLogoPath [{$definition->secondaryLogoPath}] belum terdaftar di stack-logos.json.";
            }
        }

        $this->table(['Key', 'Role', 'Tier', 'Local Path', 'Status'], $rows);

        if ($errors !== []) {
            foreach ($errors as $err) {
                $this->error($err);
            }

            return self::FAILURE;
        }

        $this->info(($checkOnly ? 'Verifikasi' : 'Sinkronisasi').' selesai: '.count($rows).' aset logo 100% valid.');

        return self::SUCCESS;
    }

    private function applySvgBrandColor(string $svg, string $hexColor): string
    {
        if (preg_match('/<svg\b[^>]*\bfill=/i', $svg)) {
            return (string) preg_replace(
                '/(<svg\b[^>]*\bfill=)(["\'])[^"\']*\2/i',
                '$1"'.$hexColor.'"',
                $svg,
                1
            );
        }

        return (string) preg_replace(
            '/<svg\b/i',
            '<svg fill="'.$hexColor.'"',
            $svg,
            1
        );
    }

    private function validateLocalAsset(string $absPath, string $format, string $expectedHash): ?string
    {
        if (! is_file($absPath)) {
            return 'file tidak ditemukan';
        }

        $payload = (string) file_get_contents($absPath);
        $issue = $this->validatePayload($payload, $format);
        if ($issue !== null) {
            return $issue;
        }

        if ($expectedHash === '' || ! hash_equals($expectedHash, hash('sha256', $payload))) {
            return 'checksum SHA-256 tidak cocok';
        }

        return null;
    }

    private function validatePayload(string $payload, string $format): ?string
    {
        $size = strlen($payload);
        if ($size < 200 || $size > 256000) {
            return "ukuran {$size} B di luar rentang 200B-250KB";
        }

        if ($format === 'svg') {
            $lower = strtolower($payload);
            if (str_contains($lower, '<html') || str_contains($lower, '<!doctype html') || str_contains($lower, '<script')) {
                return 'mengandung HTML/script terlarang';
            }
            if (preg_match('/<text\b/i', $payload)) {
                return 'mengandung elemen <text> (pola monogram terlarang)';
            }
            if (str_contains($lower, 'prefers-color-scheme')) {
                return 'mengandung media query prefers-color-scheme (berisiko hilang di dark mode)';
            }

            $prev = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($payload);
            libxml_clear_errors();
            libxml_use_internal_errors($prev);

            if ($xml === false || strtolower($xml->getName()) !== 'svg') {
                return 'bukan XML SVG valid';
            }

            $attrs = $xml->attributes();
            $viewBox = trim((string) ($attrs['viewBox'] ?? $attrs['viewbox'] ?? ''));
            if ($viewBox === '') {
                return 'atribut viewBox SVG kosong/hilang';
            }

            $vbParts = preg_split('/[\s,]+/', $viewBox);
            if (is_array($vbParts) && count($vbParts) === 4) {
                $vw = (float) $vbParts[2];
                $vh = (float) $vbParts[3];
                if ($vw <= 0.0 || $vh <= 0.0 || ($vw / $vh) > 5.0 || ($vw / $vh) < 0.2) {
                    return "rasio viewBox SVG ekstrem ({$vw}x{$vh})";
                }
            }

            return null;
        }

        if ($format === 'png') {
            if (! str_starts_with($payload, "\x89PNG\r\n\x1a\n")) {
                return 'magic bytes PNG tidak valid';
            }
            $info = @getimagesizefromstring($payload);
            if (! $info || ($info['mime'] ?? '') !== 'image/png' || $info[0] < 64 || $info[1] < 64) {
                return 'dimensi PNG < 64x64 atau korup';
            }
            if (function_exists('imagecreatefromstring')) {
                $gd = @imagecreatefromstring($payload);
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
                    if ($sampled > 0 && $nonWhite < max(3, (int) floor($sampled * 0.01))) {
                        return "PNG tampak kosong/putih polos ({$nonWhite}/{$sampled} piksel terlihat)";
                    }
                }
            }

            return null;
        }

        return "format tidak didukung: {$format}";
    }
}
