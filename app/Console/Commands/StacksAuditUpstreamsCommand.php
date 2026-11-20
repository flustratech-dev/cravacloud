<?php

namespace App\Console\Commands;

use App\Services\Provisioning\Stacks\StackRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Audit HTTP installer URLs, container registries (Docker Hub / GHCR / OCI v2),
 * and npm packages referenced by provisioning scripts before rollout.
 */
class StacksAuditUpstreamsCommand extends Command
{
    protected $signature = 'stacks:audit-upstreams
        {--stack= : Comma-separated stack keys}
        {--dry-run : List detected upstreams without making network requests}';

    protected $description = 'Audit ketersediaan seluruh upstream URL, container image, dan paket npm pada script installer';

    public function handle(StackRegistry $registry): int
    {
        $onlyRaw = trim((string) $this->option('stack'));
        $onlyKeys = $onlyRaw !== '' ? array_values(array_filter(array_map('trim', explode(',', $onlyRaw)))) : null;
        $dryRun = (bool) $this->option('dry-run');

        $targets = [];
        foreach ($registry->all() as $key => $definition) {
            if ($onlyKeys !== null && ! in_array($key, $onlyKeys, true)) {
                continue;
            }
            if (! $definition->script) {
                continue;
            }
            $path = resource_path('provisioning/stacks/'.$definition->script);
            if (! is_file($path)) {
                $this->error("Script tidak ditemukan: {$path}");

                return self::FAILURE;
            }

            $content = (string) file_get_contents($path);
            foreach ($this->extractUpstreams($key, $content) as $upstream) {
                $targets[] = $upstream;
            }
        }

        // Also inspect shared _common.sh for upstream installer URLs (e.g. get.docker.com).
        $commonPath = resource_path('provisioning/stacks/_common.sh');
        if ($onlyKeys === null && is_file($commonPath)) {
            foreach ($this->extractUpstreams('_common', (string) file_get_contents($commonPath)) as $upstream) {
                $targets[] = $upstream;
            }
        }

        if ($dryRun) {
            $this->table(
                ['Stack', 'Type', 'Target'],
                array_map(fn ($t) => [$t['stack'], $t['type'], $t['target']], $targets),
            );
            $this->info('Dry-run selesai: '.count($targets).' upstream terdeteksi.');

            return self::SUCCESS;
        }

        $cache = [];
        $rows = [];
        $failures = [];

        foreach ($targets as $item) {
            $cacheKey = $item['type'].'|'.$item['target'];
            if (! isset($cache[$cacheKey])) {
                $cache[$cacheKey] = $this->probe($item['type'], $item['target']);
            }
            $res = $cache[$cacheKey];

            $rows[] = [
                $item['stack'],
                $item['type'],
                $item['target'],
                $res['ok'] ? 'OK ('.$res['detail'].')' : 'FAIL: '.$res['detail'],
            ];

            if (! $res['ok']) {
                $failures[] = "[{$item['stack']}] {$item['type']} {$item['target']} => {$res['detail']}";
            }
        }

        $this->table(['Stack', 'Type', 'Target', 'Status'], $rows);

        if ($failures !== []) {
            foreach ($failures as $fail) {
                $this->error($fail);
            }

            return self::FAILURE;
        }

        $this->info('Audit upstream selesai: seluruh '.count($targets).' target terverifikasi hidup.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{stack: string, type: string, target: string}>
     */
    private function extractUpstreams(string $stackKey, string $content): array
    {
        $found = [];
        $seen = [];
        $add = function (string $type, string $target) use ($stackKey, &$found, &$seen): void {
            $k = $type.'|'.$target;
            if (! isset($seen[$k])) {
                $seen[$k] = true;
                $found[] = ['stack' => $stackKey, 'type' => $type, 'target' => $target];
            }
        };

        // 1. HTTP/HTTPS URLs (ignoring template placeholders, printf specs, regexes & container DNS names).
        if (preg_match_all('/https?:\/\/[^\s"\'`<>\\)]+/i', $content, $urlMatches)) {
            foreach ($urlMatches[0] as $rawUrl) {
                $url = rtrim($rawUrl, ".,;|'");
                $host = (string) (parse_url($url, PHP_URL_HOST) ?? '');
                $isInternalHost = $host === ''
                    || (! str_contains($host, '.') && ! filter_var($host, FILTER_VALIDATE_IP))
                    || str_starts_with($host, '127.')
                    || $host === '0.0.0.0';

                if (str_contains($url, '{{')
                    || str_contains($url, '$')
                    || str_contains($url, '%')
                    || preg_match('/:\/\/[^\/]*[\[\]*]/', $url)
                    || $isInternalHost) {
                    continue;
                }
                $add('http', $url);
            }
        }

        // 2. Compose `image:` directives.
        if (preg_match_all('/^\s*image:\s*([^\s#]+)/m', $content, $imgMatches)) {
            foreach ($imgMatches[1] as $img) {
                if (! str_contains($img, '$')) {
                    $add('docker', trim($img, "\"'"));
                }
            }
        }

        // 3. `docker run` image references (first token matching image:tag that is not flag/port/volume).
        $unwrapped = str_replace("\\\n", ' ', str_replace(["\r\n", "\r"], "\n", $content));
        if (preg_match_all('/docker\s+run\b[^\n;]+/', $unwrapped, $runMatches)) {
            foreach ($runMatches[0] as $cmd) {
                $tokens = preg_split('/\s+/', trim($cmd)) ?: [];
                foreach ($tokens as $idx => $tok) {
                    if ($idx < 2 || $tok === '' || str_starts_with($tok, '-') || str_starts_with($tok, '$')) {
                        continue;
                    }
                    if (str_starts_with($tok, '/') || str_starts_with($tok, '.') || str_contains($tok, ':/') || preg_match('/^\d+(?:-\d+)?:\d+/', $tok)) {
                        continue;
                    }
                    if (preg_match('/^[a-z0-9][a-z0-9._\/-]*:[a-z0-9._-]+$/i', $tok) && ! str_contains($tok, '=')) {
                        $add('docker', $tok);
                        break;
                    }
                }
            }
        }

        // 4. `npm install -g ...` packages.
        if (preg_match_all('/npm\s+install\s+-g\s+([^\r\n;|&]+)/', $content, $npmMatches)) {
            foreach ($npmMatches[1] as $pkgLine) {
                foreach (preg_split('/\s+/', trim($pkgLine)) as $pkg) {
                    $pkg = trim($pkg, "\"'");
                    if ($pkg === '' || str_starts_with($pkg, '-')) {
                        continue;
                    }
                    $add('npm', $pkg);
                }
            }
        }

        return $found;
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function probe(string $type, string $target): array
    {
        return match ($type) {
            'http' => $this->probeHttp($target),
            'npm' => $this->probeNpm($target),
            'docker' => $this->probeDocker($target),
            default => ['ok' => false, 'detail' => 'unknown type'],
        };
    }

    /** @return array{ok: bool, detail: string} */
    private function probeHttp(string $url): array
    {
        try {
            $headers = ['User-Agent' => 'CravaCloud-UpstreamAudit/1.0'];
            $resp = $this->withNetworkRetry(fn () => Http::timeout(15)->withHeaders($headers)->head($url));
            if ($resp->status() === 405 || $resp->status() === 403 || $resp->status() === 400) {
                $resp = $this->withNetworkRetry(fn () => Http::timeout(15)->withHeaders($headers + ['Range' => 'bytes=0-1024'])->get($url));
            }

            if ($resp->successful() || in_array($resp->status(), [206, 301, 302], true)) {
                return ['ok' => true, 'detail' => 'HTTP '.$resp->status()];
            }

            return ['ok' => false, 'detail' => 'HTTP '.$resp->status()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'detail' => mb_substr($e->getMessage(), 0, 120)];
        }
    }

    /** @return array{ok: bool, detail: string} */
    private function probeNpm(string $package): array
    {
        try {
            $encoded = str_starts_with($package, '@') ? rawurlencode($package) : $package;
            $resp = $this->withNetworkRetry(fn () => Http::timeout(15)->get("https://registry.npmjs.org/{$encoded}/latest"));
            if ($resp->successful()) {
                $version = $resp->json('version');

                return ['ok' => true, 'detail' => 'npm '.($version ?: '200')];
            }

            return ['ok' => false, 'detail' => 'npm HTTP '.$resp->status()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'detail' => mb_substr($e->getMessage(), 0, 120)];
        }
    }

    /** @return array{ok: bool, detail: string} */
    private function probeDocker(string $image): array
    {
        try {
            [$repoPart, $tag] = str_contains($image, ':') ? explode(':', $image, 2) : [$image, 'latest'];
            $segments = explode('/', $repoPart);
            $first = $segments[0] ?? '';

            $isExplicitRegistry = count($segments) > 1 && (str_contains($first, '.') || str_contains($first, ':') || $first === 'localhost');
            if (! $isExplicitRegistry) {
                $repo = count($segments) === 1 ? "library/{$repoPart}" : $repoPart;
                $resp = $this->withNetworkRetry(fn () => Http::timeout(15)->get("https://hub.docker.com/v2/repositories/{$repo}/tags/{$tag}"));
                if ($resp->successful()) {
                    return ['ok' => true, 'detail' => 'DockerHub 200'];
                }

                return ['ok' => false, 'detail' => 'DockerHub HTTP '.$resp->status()];
            }

            $registry = array_shift($segments);
            $repo = implode('/', $segments);
            $manifestUrl = "https://{$registry}/v2/{$repo}/manifests/{$tag}";
            $accept = 'application/vnd.oci.image.index.v1+json, application/vnd.docker.distribution.manifest.list.v2+json, application/vnd.docker.distribution.manifest.v2+json';

            $resp = $this->withNetworkRetry(fn () => Http::timeout(15)->withHeaders(['Accept' => $accept])->head($manifestUrl));
            $wwwAuth = '';
            if ($resp->status() === 401) {
                $wwwAuth = (string) ($resp->header('WWW-Authenticate') ?: $resp->header('Www-Authenticate'));
                $token = $this->fetchBearerToken($wwwAuth);
                if (! $token) {
                    return ['ok' => false, 'detail' => "OCI {$registry} auth failed"];
                }
                $resp = $this->withNetworkRetry(fn () => Http::timeout(15)->withToken($token)->withHeaders(['Accept' => $accept])->head($manifestUrl));
                if ($resp->status() === 405) {
                    $resp = $this->withNetworkRetry(fn () => Http::timeout(15)->withToken($token)->withHeaders(['Accept' => $accept])->get($manifestUrl));
                }
            }

            if ($resp->status() === 429 && ($registry === 'docker.n8n.io' || str_contains($wwwAuth, 'auth.docker.io'))) {
                $hubResp = $this->withNetworkRetry(fn () => Http::timeout(15)->get("https://hub.docker.com/v2/repositories/{$repo}/tags/{$tag}"));
                if ($hubResp->successful()) {
                    return ['ok' => true, 'detail' => 'DockerHub fallback 200'];
                }
            }

            if ($resp->successful()) {
                return ['ok' => true, 'detail' => "OCI {$registry} ".$resp->status()];
            }

            return ['ok' => false, 'detail' => "OCI {$registry} HTTP ".$resp->status()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'detail' => mb_substr($e->getMessage(), 0, 120)];
        }
    }

    private function fetchBearerToken(string $wwwAuth): ?string
    {
        if (! preg_match('/realm="([^"]+)"/i', $wwwAuth, $realmMatch)) {
            return null;
        }
        $query = [];
        if (preg_match('/service="([^"]+)"/i', $wwwAuth, $svcMatch)) {
            $query['service'] = $svcMatch[1];
        }
        if (preg_match('/scope="([^"]+)"/i', $wwwAuth, $scopeMatch)) {
            $query['scope'] = $scopeMatch[1];
        }

        $resp = $this->withNetworkRetry(fn () => Http::timeout(15)->get($realmMatch[1], $query));
        if (! $resp->successful()) {
            return null;
        }

        return $resp->json('token') ?: $resp->json('access_token');
    }

    /**
     * Retry transient network errors (e.g. cURL 52 empty reply / 502-504) before failing a probe.
     */
    private function withNetworkRetry(callable $callback, int $attempts = 3): \Illuminate\Http\Client\Response
    {
        $lastException = null;
        for ($i = 0; $i < $attempts; $i++) {
            try {
                /** @var \Illuminate\Http\Client\Response $resp */
                $resp = $callback();
                if ($resp->status() < 500 || $i === $attempts - 1) {
                    return $resp;
                }
            } catch (\Throwable $e) {
                $lastException = $e;
                if ($i === $attempts - 1) {
                    throw $e;
                }
            }
            usleep(350_000 * ($i + 1));
        }

        throw $lastException ?? new \RuntimeException('Upstream probe failed after retries.');
    }
}
