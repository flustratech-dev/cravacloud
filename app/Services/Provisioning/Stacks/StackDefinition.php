<?php

namespace App\Services\Provisioning\Stacks;

/**
 * Value object describing one installable application stack.
 * Ports are TCP inbound ports opened via the firewall diff step.
 * $script is a filename under resources/provisioning/stacks/ (null = no
 * install needed, e.g. 'none'). Scripts MUST be idempotent (marker file).
 */
class StackDefinition
{
    /**
     * @param array<int, int> $ports
     * @param array<int, string>|null $supportedOs OS keys allowed for this
     *        stack (null = every mapped OS). Install scripts are apt-get
     *        based, so panel/docker stacks restrict to verified distros
     *        (review patch A3 — incompatible combos stay manual).
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $ports,
        public readonly ?string $script = null,
        public readonly bool $needsDocker = false,
        public readonly ?string $appUrlTemplate = null,
        public readonly ?array $supportedOs = null,
    ) {
    }

    public function needsInstall(): bool
    {
        return $this->script !== null;
    }

    public function supportsOs(string $os): bool
    {
        return $this->supportedOs === null || in_array($os, $this->supportedOs, true);
    }
}