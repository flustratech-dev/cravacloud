<?php

namespace App\Services\Provisioning\Stacks;

/**
 * Value object describing one installable application stack.
 * Ports are TCP/UDP inbound ports (single int or range string like '2456-2458')
 * opened via the firewall diff step.
 * $script is a filename under resources/provisioning/stacks/ (null = no
 * install needed, e.g. 'none'). Scripts MUST be idempotent (marker file).
 */
class StackDefinition
{
    /**
     * @param array<int, int|string> $ports
     * @param array<int, string>|null $supportedOs OS keys allowed for this
     *        stack (null = every mapped OS). Install scripts are apt/dnf
     *        based, so panel/docker stacks restrict to verified distros
     *        (review patch A3 — incompatible combos stay manual).
     * @param array<int, int|string> $udpPorts
     * @param array<int, string> $warningBullets
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $ports,
        public readonly ?string $script = null,
        public readonly bool $needsDocker = false,
        public readonly ?string $appUrlTemplate = null,
        public readonly ?array $supportedOs = null,
        public readonly string $uiGroup = 'none',
        public readonly string $category = 'Lainnya',
        public readonly string $description = '',
        public readonly ?string $logoPath = null,
        public readonly ?string $secondaryLogoPath = null,
        public readonly array $udpPorts = [],
        public readonly int $minRamGb = 2,
        public readonly bool $requiresLicense = false,
        public readonly ?string $warningType = null,
        public readonly ?string $warningTitle = null,
        public readonly ?string $warningMessage = null,
        public readonly array $warningBullets = [],
        public readonly bool $hardBlockOnRam = true,
        public readonly bool $visibleOnCheckout = true,
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

    public function needsWarningConfirmation(): bool
    {
        return $this->requiresLicense || in_array($this->warningType, ['license', 'activation', 'network'], true);
    }

    public function meetsRam(int $ramGb): bool
    {
        return $ramGb >= $this->minRamGb;
    }

    /**
     * Normalized UI metadata representation for Checkout and Reinstall views.
     *
     * @return array<string, mixed>
     */
    public function toUiArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'uiGroup' => $this->uiGroup,
            'category' => $this->category,
            'description' => $this->description,
            'logoPath' => $this->logoPath,
            'logoUrl' => $this->logoPath ? '/'.ltrim($this->logoPath, '/') : null,
            'secondaryLogoPath' => $this->secondaryLogoPath,
            'secondaryLogoUrl' => $this->secondaryLogoPath ? '/'.ltrim($this->secondaryLogoPath, '/') : null,
            'ports' => $this->ports,
            'udpPorts' => $this->udpPorts,
            'minRamGb' => $this->minRamGb,
            'requiresLicense' => $this->requiresLicense,
            'warningType' => $this->warningType,
            'warningTitle' => $this->warningTitle,
            'warningMessage' => $this->warningMessage,
            'warningBullets' => $this->warningBullets,
            'hardBlockOnRam' => $this->hardBlockOnRam,
            'visibleOnCheckout' => $this->visibleOnCheckout,
            'needsWarningConfirmation' => $this->needsWarningConfirmation(),
        ];
    }
}