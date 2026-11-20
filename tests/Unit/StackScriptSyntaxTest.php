<?php

namespace Tests\Unit;

use App\Models\VpsInstance;
use App\Services\Provisioning\Stacks\StackRegistry;
use App\Services\Provisioning\Stacks\StackScriptBuilder;
use Tests\TestCase;

/**
 * Batch 0 installer script audit & syntax guard:
 *  - Verifies bash -n syntax on _common.sh, every raw stack script, and the
 *    full assembled script produced by StackScriptBuilder.
 *  - Enforces strict shell flags, OS guards, 2GB swap, readiness gates before
 *    $MARKER, D6 zero-secret echo, anti-stub bans, and port parity with StackDefinition.
 */
class StackScriptSyntaxTest extends TestCase
{
    public function test_common_helper_passes_bash_syntax_check(): void
    {
        $bash = $this->findBashBinary();
        $commonPath = resource_path('provisioning/stacks/_common.sh');
        $this->assertFileExists($commonPath);

        $raw = (string) file_get_contents($commonPath);
        [$code, $output] = $this->runBashSyntaxCheck($bash, $raw);

        $this->assertSame(0, $code, "Syntax error pada _common.sh:\n{$output}");
    }

    public function test_all_stack_scripts_exist_pass_syntax_and_meet_production_standards(): void
    {
        $bash = $this->findBashBinary();
        $registry = app(StackRegistry::class);
        $builder = app(StackScriptBuilder::class);

        $dummyInstance = new VpsInstance([
            'order_id' => 99999,
            'hostname' => 'crava-syntax-check-01',
        ]);

        foreach ($registry->all() as $key => $definition) {
            if ($key === 'none') {
                $this->assertNull($definition->script);

                continue;
            }

            $this->assertNotEmpty($definition->script, "Stack [{$key}] wajib memiliki file script.");
            $path = resource_path('provisioning/stacks/'.$definition->script);
            $this->assertFileExists($path, "Script missing untuk [{$key}]: {$path}");

            $raw = (string) file_get_contents($path);
            $this->assertStringStartsWith("#!/usr/bin/env bash\n", str_replace("\r\n", "\n", $raw), "Script [{$key}] wajib diawali #!/usr/bin/env bash dengan LF.");
            $this->assertStringContainsString('set -euo pipefail', $raw, "Script [{$key}] wajib mengandung set -euo pipefail.");
            $this->assertStringContainsString('# {{common}}', $raw, "Script [{$key}] wajib menginjeksi # {{common}}.");
            $this->assertStringContainsString('/var/lib/cravacloud/.installed-{{stack}}', $raw, "Script [{$key}] wajib memakai marker idempoten.");
            $this->assertStringContainsString('CRAVA_INSTALL_OK {{stack}}', $raw, "Script [{$key}] wajib mencetak sentinel sukses.");
            $this->assertStringContainsString('crava_require_os', $raw, "Script [{$key}] wajib memeriksa kompatibilitas OS.");
            $this->assertStringContainsString('crava_ensure_swap', $raw, "Script [{$key}] wajib memanggil crava_ensure_swap.");

            // Early-exit idempotency block must also emit CRAVA_INSTALL_OK.
            $this->assertSame(
                1,
                preg_match('/if\s+.*?\$MARKER.*?then\s*(.*?)\s*fi/s', $raw, $markerIfMatch),
                "Script [{$key}] wajib memiliki blok early-exit \$MARKER."
            );
            $this->assertStringContainsString(
                'CRAVA_INSTALL_OK {{stack}}',
                $markerIfMatch[1],
                "Blok early-exit \$MARKER di [{$key}] wajib mencetak CRAVA_INSTALL_OK {{stack}}."
            );

            // Keep StackDefinition::$needsDocker synchronized with crava_ensure_docker usage.
            if (str_contains($raw, 'crava_ensure_docker')) {
                $this->assertTrue(
                    $definition->needsDocker,
                    "Stack [{$key}] memanggil crava_ensure_docker tetapi StackDefinition::needsDocker masih false."
                );
            }

            // Readiness gate must execute BEFORE touching $MARKER.
            $markerTouchPos = strpos($raw, 'touch "$MARKER"');
            $this->assertNotFalse($markerTouchPos, "Script [{$key}] wajib membuat \$MARKER.");

            $hasGateBeforeMarker = false;
            foreach (['crava_wait_port', 'crava_wait_http', 'crava_wait_container', 'crava_wait_service', 'docker info', 'command -v claude'] as $gateNeedle) {
                $gatePos = strrpos($raw, $gateNeedle);
                if ($gatePos !== false && $gatePos < $markerTouchPos) {
                    $hasGateBeforeMarker = true;
                    break;
                }
            }
            $this->assertTrue($hasGateBeforeMarker, "Script [{$key}] wajib memanggil readiness gate sebelum touch \"\$MARKER\".");

            // Forbidden internal registry / placeholder tokens.
            foreach (['registry.cravacloud.id', 'example.com', 'changeme'] as $banned) {
                $this->assertStringNotContainsStringIgnoringCase($banned, $raw, "Script [{$key}] masih mengandung string terlarang [{$banned}].");
            }
            $this->assertDoesNotMatchRegularExpression('/\b(TODO|FIXME)\b/i', $raw, "Script [{$key}] mengandung TODO/FIXME.");

            // D6: never echo or printf secret variables to stdout without file redirection.
            foreach (preg_split('/\R/', $raw) as $lineNo => $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                    continue;
                }
                if (preg_match('/^(echo|printf)\b/', $trimmed) && ! str_contains($trimmed, '>')) {
                    $this->assertDoesNotMatchRegularExpression(
                        '/\$\{?[A-Z0-9_]*(PASS|SECRET|TOKEN|API_KEY)[A-Z0-9_]*\}?/',
                        $trimmed,
                        "Pelanggaran D6 di [{$key}] baris ".($lineNo + 1).": secret tidak boleh di-echo ke stdout."
                    );
                }
            }

            // Port synchronization: UFW & public Docker bindings must match StackDefinition.
            $this->assertPortsMatchDefinition($key, $raw, $definition->ports, $definition->udpPorts);

            // bash -n on raw template and fully assembled script.
            [$rawCode, $rawOut] = $this->runBashSyntaxCheck($bash, $raw);
            $this->assertSame(0, $rawCode, "Syntax error bash -n pada template [{$definition->script}]:\n{$rawOut}");

            $assembled = $builder->build($definition, $dummyInstance);
            $this->assertStringContainsString('crava_wait_pkg_lock()', $assembled, "Builder harus menyuntikkan _common.sh untuk [{$key}].");
            [$fullCode, $fullOut] = $this->runBashSyntaxCheck($bash, $assembled);
            $this->assertSame(0, $fullCode, "Syntax error bash -n pada hasil build [{$definition->script}]:\n{$fullOut}");
        }
    }

    /**
     * @param array<int, int|string> $expectedTcp
     * @param array<int, int|string> $expectedUdp
     */
    private function assertPortsMatchDefinition(string $stackKey, string $script, array $expectedTcp, array $expectedUdp): void
    {
        $declaredTcp = [];
        $declaredUdp = [];

        if (preg_match_all('/(?:crava_ufw_allow|ufw allow)\s+([^\r\n;#]+)/', $script, $ufwMatches)) {
            foreach ($ufwMatches[1] as $argLine) {
                foreach (preg_split('/\s+/', trim($argLine)) as $token) {
                    $token = trim($token, "\"' ");
                    if ($token === '' || str_starts_with($token, '$')) {
                        continue;
                    }
                    if (preg_match('/^(\d+(?:-\d+)?)(?:\/(tcp|udp))?$/i', $token, $m)) {
                        $portVal = str_contains($m[1], '-') ? $m[1] : (int) $m[1];
                        $proto = strtolower($m[2] ?? 'tcp');
                        if ($proto === 'udp') {
                            $declaredUdp[] = $portVal;
                        } else {
                            $declaredTcp[] = $portVal;
                        }
                    }
                }
            }
        }

        // Verify all UFW ports exist in StackDefinition and vice versa.
        foreach ($declaredTcp as $p) {
            $this->assertContains($p, $expectedTcp, "UFW TCP port [{$p}] di script [{$stackKey}] belum terdaftar di StackDefinition::\$ports.");
        }
        foreach ($declaredUdp as $p) {
            $this->assertContains($p, $expectedUdp, "UFW UDP port [{$p}] di script [{$stackKey}] belum terdaftar di StackDefinition::\$udpPorts.");
        }
        foreach ($expectedTcp as $p) {
            $this->assertContains($p, $declaredTcp, "StackDefinition::\$ports [{$p}] milik [{$stackKey}] belum diizinkan via crava_ufw_allow.");
        }
        foreach ($expectedUdp as $p) {
            $this->assertContains($p, $declaredUdp, "StackDefinition::\$udpPorts [{$p}] milik [{$stackKey}] belum diizinkan via crava_ufw_allow.");
        }

        // Verify public Docker `-p <host>:<container>` or compose `"<host>:<container>"` ports (ignore loopback 127.0.0.1).
        if (preg_match_all('/(?:-p\s+|["\'])(\d{1,5}(?:-\d{1,5})?):\d{1,5}(?:-\d{1,5})?(?:\/(tcp|udp))?/i', $script, $dockerMatches, PREG_OFFSET_CAPTURE)) {
            foreach ($dockerMatches[1] as $idx => $match) {
                $offset = $match[1];
                $before = substr($script, max(0, $offset - 12), 12);
                if (str_contains($before, '127.0.0.1:') || str_contains($before, 'localhost:')) {
                    continue;
                }
                $portVal = str_contains($match[0], '-') ? $match[0] : (int) $match[0];
                $proto = strtolower($dockerMatches[2][$idx][0] ?? 'tcp');
                if ($proto === 'udp') {
                    $this->assertContains($portVal, $expectedUdp, "Docker publish UDP [{$portVal}] di [{$stackKey}] tidak terdaftar di StackDefinition::\$udpPorts.");
                } else {
                    $this->assertContains($portVal, $expectedTcp, "Docker publish TCP [{$portVal}] di [{$stackKey}] tidak terdaftar di StackDefinition::\$ports.");
                }
            }
        }
    }

    private function findBashBinary(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            foreach ([
                'C:\\Program Files\\Git\\bin\\bash.exe',
                'C:\\Program Files\\Git\\usr\\bin\\bash.exe',
            ] as $candidate) {
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        return 'bash';
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runBashSyntaxCheck(string $bashBinary, string $scriptContent): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open([$bashBinary, '-n'], $descriptors, $pipes);
        $this->assertIsResource($process, "Gagal menjalankan [{$bashBinary} -n].");

        $normalized = str_replace(["\r\n", "\r"], "\n", $scriptContent);
        fwrite($pipes[0], $normalized);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [$exitCode, trim($stdout."\n".$stderr)];
    }
}
