<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VpsSpec;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PackageController extends Controller
{
    use Concerns\LogsAdminAudit;

    private const CORE_PLAN_NAMES = [
        'Starter XS',
        'Starter S',
        'Starter M',
        'Starter L',
        'Enterprise Pro',
        'Enterprise Max',
        'Enterprise Ultra',
        'Enterprise Extreme',
    ];

    public function packages()
    {
        $specs = VpsSpec::with(['replacementSpec'])
            ->withCount(['orders'])
            ->where(function ($q) {
                $q->where('category', 'vps')->orHas('orders');
            })
            ->orderBy('sell_price', 'asc')
            ->get();

        $corePlanNames = self::CORE_PLAN_NAMES;

        $settings = app(\App\Services\SettingsService::class);
        $usdIdrRate = max(1, $settings->int('tencent_usd_idr', 16500));
        $bundlePricesUsd = (array) config('services.tencent.bundle_prices_usd', []);
        $canaryWhitelist = array_values(array_unique(array_map('intval', (array) $settings->get('provisioning_auto_spec_whitelist', []))));

        $stackRegistry = app(\App\Services\Provisioning\Stacks\StackRegistry::class);
        $allStacks = $stackRegistry->all();
        $disabledStacks = $stackRegistry->disabledKeys();

        return view('admin.packages', compact(
            'specs',
            'corePlanNames',
            'usdIdrRate',
            'bundlePricesUsd',
            'canaryWhitelist',
            'allStacks',
            'disabledStacks'
        ));
    }

    public function storePackage(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:vps_specs,name',
            'category' => 'nullable|string|in:vps',
            'tagline' => 'nullable|string|max:255',
            'badge' => 'nullable|string|max:100',
            'target_audience' => 'nullable|string|max:255',
            'solution' => 'nullable|string|max:1000',
            'default_stack' => 'nullable|string|max:50',
            'allowed_providers' => 'nullable|array',
            'allowed_providers.*' => 'string|max:50',
            'default_provider' => 'nullable|string|max:50',
            'features' => 'nullable',
            'cpu' => 'required|integer|min:1|max:128',
            'ram' => 'required|integer|min:1|max:1024',
            'disk' => 'required|integer|min:5|max:10000',
            'bandwidth' => 'required|integer|min:1|max:10000',
            'cost_price' => 'required|numeric|min:0',
            'sell_price' => 'required|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'is_renewable' => 'nullable|boolean',
            'replacement_spec_id' => 'nullable|integer|exists:vps_specs,id',
            'upstream_bundle_id' => 'nullable|string|max:100',
            'upstream_bundle_type' => 'nullable|string|in:STARTER_BUNDLE,GENERAL_BUNDLE',
        ]);

        $validated['category'] = $validated['category'] ?? 'vps';
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_renewable'] = $request->boolean('is_renewable', true);
        $validated['replacement_spec_id'] = $request->filled('replacement_spec_id') ? (int) $request->input('replacement_spec_id') : null;
        $validated['upstream_bundle_id'] = $request->filled('upstream_bundle_id') ? trim((string) $validated['upstream_bundle_id']) : null;
        $validated['upstream_bundle_type'] = $request->filled('upstream_bundle_type') ? trim((string) $validated['upstream_bundle_type']) : null;

        if (isset($validated['features']) && is_string($validated['features'])) {
            $validated['features'] = array_values(array_filter(array_map('trim', explode("\n", $validated['features']))));
        }

        $validated['allowed_providers'] = ['cravacloud'];
        $validated['default_provider'] = 'cravacloud';

        $spec = VpsSpec::create($validated);
        if ($request->boolean('canary_whitelist_present') || $request->boolean('canary_whitelisted')) {
            $this->syncCanaryWhitelist($spec->id, $request->boolean('canary_whitelisted'));
        }
        Cache::forget('landing.specs');
        $this->audit('package.created', "Membuat paket VPS baru '{$spec->name}'", $spec);

        return back()->with('success', "Paket '{$validated['name']}' berhasil ditambahkan.");
    }

    public function updatePackage(Request $request, $id)
    {
        $spec = VpsSpec::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:vps_specs,name,' . $spec->id,
            'category' => 'nullable|string|in:vps',
            'tagline' => 'nullable|string|max:255',
            'badge' => 'nullable|string|max:100',
            'target_audience' => 'nullable|string|max:255',
            'solution' => 'nullable|string|max:1000',
            'default_stack' => 'nullable|string|max:50',
            'allowed_providers' => 'nullable|array',
            'allowed_providers.*' => 'string|max:50',
            'default_provider' => 'nullable|string|max:50',
            'features' => 'nullable',
            'cpu' => 'required|integer|min:1|max:128',
            'ram' => 'required|integer|min:1|max:1024',
            'disk' => 'required|integer|min:5|max:10000',
            'bandwidth' => 'required|integer|min:1|max:10000',
            'cost_price' => 'required|numeric|min:0',
            'sell_price' => 'required|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'is_renewable' => 'nullable|boolean',
            'replacement_spec_id' => 'nullable|integer|exists:vps_specs,id',
            'upstream_bundle_id' => 'nullable|string|max:100',
            'upstream_bundle_type' => 'nullable|string|in:STARTER_BUNDLE,GENERAL_BUNDLE',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_renewable'] = $request->boolean('is_renewable');
        $validated['upstream_bundle_id'] = $request->filled('upstream_bundle_id') ? trim((string) $validated['upstream_bundle_id']) : null;
        $validated['upstream_bundle_type'] = $request->filled('upstream_bundle_type') ? trim((string) $validated['upstream_bundle_type']) : null;
        $replacementId = $request->filled('replacement_spec_id') ? (int) $request->input('replacement_spec_id') : null;
        if ($replacementId === (int) $spec->id) {
            $replacementId = null;
        }
        $validated['replacement_spec_id'] = $replacementId;

        if (isset($validated['features']) && is_string($validated['features'])) {
            $validated['features'] = array_values(array_filter(array_map('trim', explode("\n", $validated['features']))));
        }

        if (!empty($validated['allowed_providers'])) {
            if (empty($validated['default_provider']) || !in_array($validated['default_provider'], $validated['allowed_providers'], true)) {
                $validated['default_provider'] = $validated['allowed_providers'][0];
            }
        }

        $spec->update($validated);
        if ($request->boolean('canary_whitelist_present') || $request->has('canary_whitelisted')) {
            $this->syncCanaryWhitelist($spec->id, $request->boolean('canary_whitelisted'));
        }
        Cache::forget('landing.specs');
        $this->audit('package.updated', "Memperbarui paket VPS '{$spec->name}'", $spec, [
            'upstream_bundle_id' => $validated['upstream_bundle_id'],
            'upstream_bundle_type' => $validated['upstream_bundle_type'],
        ]);

        return back()->with('success', "Paket '{$spec->name}' berhasil diperbarui.");
    }

    public function toggleStackVisibility(Request $request)
    {
        $validated = $request->validate([
            'stack_key' => ['required', 'string', 'max:100'],
        ]);

        $key = trim($validated['stack_key']);
        if ($key === 'none') {
            return back()->with('error', 'Opsi "Tanpa Control Panel (none)" tidak dapat dinonaktifkan.');
        }

        $registry = app(\App\Services\Provisioning\Stacks\StackRegistry::class);
        $definition = $registry->get($key);
        if (! $definition) {
            return back()->with('error', "Stack '{$key}' tidak ditemukan di registry.");
        }

        $settings = app(\App\Services\SettingsService::class);
        $disabled = array_values(array_unique(array_map('strval', (array) $settings->get('disabled_stacks', []))));

        if (in_array($key, $disabled, true)) {
            $disabled = array_values(array_diff($disabled, [$key]));
            $nowEnabled = true;
        } else {
            $disabled[] = $key;
            $nowEnabled = false;
        }

        $settings->set('disabled_stacks', $disabled, 'json', ['group' => 'provisioning']);

        $this->audit(
            'stack.visibility_toggled',
            ($nowEnabled ? 'Mengaktifkan' : 'Menyembunyikan') . " stack {$definition->label} ({$key}) di Checkout & Reinstall.",
            null,
            ['stack_key' => $key, 'enabled' => $nowEnabled]
        );

        return redirect()->route('admin.packages.index', ['tab' => 'stacks'])
            ->with('success', "Stack '{$definition->label}' sekarang " . ($nowEnabled ? 'AKTIF (tampil di checkout)' : 'DISEMBUNYIKAN dari checkout') . '.');
    }

    private function syncCanaryWhitelist(int $specId, bool $enabled): void
    {
        $settings = app(\App\Services\SettingsService::class);
        $list = array_values(array_unique(array_map('intval', (array) $settings->get('provisioning_auto_spec_whitelist', []))));

        if ($enabled && ! in_array($specId, $list, true)) {
            $list[] = $specId;
        } elseif (! $enabled) {
            $list = array_values(array_diff($list, [$specId]));
        }

        $settings->set('provisioning_auto_spec_whitelist', $list, 'json', ['group' => 'provisioning']);
    }

    public function destroyPackage($id)
    {
        $spec = VpsSpec::findOrFail($id);

        $corePlanNames = array_map('strtolower', self::CORE_PLAN_NAMES);

        if (in_array(strtolower($spec->name), $corePlanNames, true)) {
            return back()->with('error', "Paket inti '{$spec->name}' dilindungi oleh sistem dan tidak boleh dihapus.");
        }

        if ($spec->orders()->exists()) {
            return back()->with('error', "Paket '{$spec->name}' memiliki riwayat pesanan di database dan tidak dapat dihapus.");
        }

        $specName = $spec->name;
        $spec->delete();
        Cache::forget('landing.specs');

        return back()->with('success', "Paket '{$specName}' berhasil dihapus.");
    }
}
