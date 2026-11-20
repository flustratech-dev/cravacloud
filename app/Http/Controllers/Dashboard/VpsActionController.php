<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Services\Provisioning\Exceptions\Day2Exception;
use App\Services\Provisioning\VpsDay2Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Phase 5D — customer Day-2 self-service (power, console, reset password,
 * firewall, traffic, upgrade). Every instance is resolved org-scoped so a
 * member of organization B can never act on organization A's server (404).
 *
 * All business rules live in VpsDay2Service; this controller only validates
 * input, enforces permissions (via route middleware) and shapes JSON. Errors
 * are white-label — no vendor name, bundle id or upstream detail is exposed.
 */
class VpsActionController extends Controller
{
    public function __construct(private readonly VpsDay2Service $day2)
    {
    }

    private function organizationId(): int
    {
        return (int) Auth::user()->current_organization_id;
    }

    private function instance(int $id): VpsInstance
    {
        return VpsInstance::where('organization_id', $this->organizationId())->findOrFail($id);
    }

    /** Capability map for the detail page (gates the Day-2 panel). */
    public function capabilities(int $id): JsonResponse
    {
        return response()->json(['ok' => true] + $this->day2->capabilities($this->instance($id)));
    }

    public function power(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['start', 'stop', 'reboot', 'force_reboot'])],
        ]);

        return $this->guard(fn () => $this->day2->power($this->instance($id), $data['action']));
    }

    public function resetPassword(int $id): JsonResponse
    {
        return $this->guard(fn () => $this->day2->resetPassword($this->instance($id)));
    }

    /** On-demand VNC console. The URL is single-use and NEVER logged here. */
    public function console(int $id): JsonResponse
    {
        return $this->guard(fn () => $this->day2->console($this->instance($id)));
    }

    public function firewallIndex(int $id): JsonResponse
    {
        return $this->guard(fn () => ['rules' => $this->day2->firewallRules($this->instance($id))]);
    }

    public function firewallStore(Request $request, int $id): JsonResponse
    {
        $data = $this->validateFirewall($request);

        return $this->guard(fn () => $this->day2->addFirewallRule($this->instance($id), $data));
    }

    public function firewallDestroy(Request $request, int $id): JsonResponse
    {
        $data = $this->validateFirewall($request, requireDescription: false);
        $confirmSsh = $request->boolean('confirm_ssh');

        return $this->guard(fn () => $this->day2->deleteFirewallRule($this->instance($id), $data, $confirmSsh));
    }

    public function traffic(int $id): JsonResponse
    {
        return $this->guard(fn () => $this->day2->traffic($this->instance($id)));
    }

    public function upgradePreview(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['target_spec_id' => ['required', 'integer']]);
        $target = VpsSpec::findOrFail($data['target_spec_id']);

        return $this->guard(fn () => $this->day2->upgradePreview($this->instance($id), $target));
    }

    public function upgrade(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['target_spec_id' => ['required', 'integer']]);
        $target = VpsSpec::findOrFail($data['target_spec_id']);

        return $this->guard(fn () => $this->day2->createUpgrade($this->instance($id), $target));
    }

    // =====================================================================

    private function validateFirewall(Request $request, bool $requireDescription = true): array
    {
        return $request->validate([
            'protocol' => ['required', Rule::in(['TCP', 'UDP', 'ICMP', 'ALL', 'tcp', 'udp', 'icmp', 'all'])],
            // Single port, comma list or range (e.g. "80", "80,443", "8000-8100").
            'port' => ['required', 'string', 'max:60', 'regex:/^\d{1,5}(-\d{1,5})?(,\d{1,5}(-\d{1,5})?)*$/'],
            'cidr' => ['nullable', 'string', 'max:45', 'regex:/^(\d{1,3}\.){3}\d{1,3}(\/\d{1,2})?$/'],
            'action' => ['nullable', Rule::in(['ACCEPT', 'DROP', 'accept', 'drop'])],
            'description' => [$requireDescription ? 'nullable' : 'nullable', 'string', 'max:100'],
        ]);
    }

    /** Run a Day-2 operation, translating Day2Exception into a 422 JSON body. */
    private function guard(callable $fn): JsonResponse
    {
        try {
            return response()->json(['ok' => true] + $fn());
        } catch (Day2Exception $e) {
            $body = ['ok' => false, 'code' => $e->errorCode, 'error' => $e->getMessage()];
            if ($e->replacementSpecId !== null) {
                $body['replacement_spec_id'] = $e->replacementSpecId;
            }

            return response()->json($body, 422);
        }
    }
}