<?php

namespace App\Services\Provisioning\Stacks;

use App\Models\VpsInstance;
use RuntimeException;

/**
 * Assembles production install scripts by injecting shared _common.sh helpers
 * (OS guard, package lock wait, 2GB swap preflight, readiness gates) and
 * substituting ONLY non-secret template placeholders (D6).
 */
class StackScriptBuilder
{
    public function build(StackDefinition $definition, VpsInstance $instance): string
    {
        if (! $definition->script) {
            throw new RuntimeException("Stack [{$definition->key}] has no install script.");
        }

        $scriptPath = resource_path('provisioning/stacks/'.$definition->script);
        if (! is_file($scriptPath)) {
            throw new RuntimeException("Install script missing: {$scriptPath}");
        }

        $commonPath = resource_path('provisioning/stacks/_common.sh');
        if (! is_file($commonPath)) {
            throw new RuntimeException("Shared helper missing: {$commonPath}");
        }

        $common = $this->normalizeLf((string) file_get_contents($commonPath));
        $common = (string) preg_replace('/\A#!\/usr\/bin\/env bash\n/', '', $common);

        $template = $this->normalizeLf((string) file_get_contents($scriptPath));

        if (str_contains($template, '# {{common}}')) {
            $assembled = str_replace('# {{common}}', trim($common), $template);
        } elseif (str_contains($template, 'set -euo pipefail')) {
            $assembled = (string) preg_replace(
                '/set -euo pipefail/',
                "set -euo pipefail\n".trim($common),
                $template,
                1
            );
        } else {
            $assembled = trim($common)."\n".$template;
        }

        return str_replace(
            ['{{stack}}', '{{order_id}}', '{{hostname}}'],
            [$definition->key, (string) $instance->order_id, (string) $instance->hostname],
            $assembled,
        );
    }

    private function normalizeLf(string $text): string
    {
        return str_replace(["\r\n", "\r"], "\n", $text);
    }
}
