@extends('layouts.admin', ['title' => 'Provisioning & Health', 'headerTitle' => 'Provisioning & Control Plane Kesehatan', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6" x-data="{ taskDetail: null }">
    <!-- Header Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 bg-white border border-hairline p-5">
        <div>
            <h2 class="font-display text-lg font-bold text-ink">Provisioning, Task Queue &amp; Upstream Control Center</h2>
            <p class="text-xs text-muted mt-1">Pantau eksekusi job Tencent/TAT, saldo deposit, kuota pool region, rekonsiliasi orphan/drift, dan antrean failed jobs.</p>
        </div>
        <span class="font-mono text-xs text-muted bg-creamDark px-3 py-1 border border-hairline">Diperbarui {{ now()->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</span>
    </div>

    <!-- Navigation Tabs -->
    <div class="bg-white p-1.5 border border-hairline flex flex-wrap items-center gap-1 font-mono text-xs">
        <a href="{{ route('admin.monitoring', ['tab' => 'overview']) }}"
           class="px-4 py-2 font-bold uppercase tracking-wider transition-colors {{ ($tab ?? 'overview') === 'overview' ? 'bg-electric text-white' : 'text-ink hover:bg-creamDark/60' }}">
            Overview &amp; SLA
        </a>
        <a href="{{ route('admin.monitoring', ['tab' => 'tasks']) }}"
           class="px-4 py-2 font-bold uppercase tracking-wider transition-colors inline-flex items-center gap-2 {{ ($tab ?? '') === 'tasks' ? 'bg-electric text-white' : 'text-ink hover:bg-creamDark/60' }}">
            <span>Task Queue &amp; TAT</span>
            @if(($taskStats['failed'] ?? 0) > 0)
                <span class="px-1.5 py-0.5 text-[10px] {{ ($tab ?? '') === 'tasks' ? 'bg-white text-electric' : 'bg-ink text-cream' }}">{{ $taskStats['failed'] }}</span>
            @endif
        </a>
        <a href="{{ route('admin.monitoring', ['tab' => 'upstream']) }}"
           class="px-4 py-2 font-bold uppercase tracking-wider transition-colors inline-flex items-center gap-2 {{ ($tab ?? '') === 'upstream' ? 'bg-electric text-white' : 'text-ink hover:bg-creamDark/60' }}">
            <span>Saldo, Kuota &amp; Reconcile</span>
            @if(isset($renewPendingInstances) && $renewPendingInstances->count() > 0)
                <span class="px-1.5 py-0.5 text-[10px] bg-red-600 text-white">{{ $renewPendingInstances->count() }}</span>
            @endif
        </a>
        <a href="{{ route('admin.monitoring', ['tab' => 'failed-jobs']) }}"
           class="px-4 py-2 font-bold uppercase tracking-wider transition-colors inline-flex items-center gap-2 {{ ($tab ?? '') === 'failed-jobs' ? 'bg-electric text-white' : 'text-ink hover:bg-creamDark/60' }}">
            <span>Failed Queue Jobs</span>
            @if(isset($failedJobs) && $failedJobs->count() > 0)
                <span class="px-1.5 py-0.5 text-[10px] {{ ($tab ?? '') === 'failed-jobs' ? 'bg-white text-electric' : 'bg-ink text-cream' }}">{{ $failedJobs->count() }}</span>
            @endif
        </a>
    </div>

    @if(($tab ?? 'overview') === 'overview')
        <div class="grid grid-cols-2 md:grid-cols-5 gap-px bg-hairline border border-hairline">
            @foreach(['running' => 'Running', 'provisioning' => 'Provisioning', 'stopped' => 'Stopped', 'error' => 'Error', 'suspended' => 'Suspended'] as $key => $label)
                <div class="bg-white p-5">
                    <span class="font-mono text-[10px] font-bold uppercase tracking-widest text-muted">{{ $label }}</span>
                    <p class="font-display text-2xl font-bold mt-1 {{ $key === 'error' ? 'text-electric' : 'text-ink' }}">{{ $statusCounts[$key] ?? 0 }}</p>
                </div>
            @endforeach
        </div>

        {{-- Upstream Quota Pool + Deposit Balance summary on Overview --}}
        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white border border-hairline overflow-hidden">
                <div class="p-5 border-b border-hairline flex items-center justify-between gap-2">
                    <h3 class="font-display font-bold text-ink text-sm uppercase tracking-wider">Kuota Upstream per Region</h3>
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-[10px] font-bold uppercase px-2 py-0.5 border border-hairline bg-creamDark text-muted">Guard: {{ $quotaGuardMode }}</span>
                        <a href="{{ route('admin.monitoring', ['tab' => 'upstream']) }}" class="font-mono text-[10px] font-bold text-electric hover:underline">Detail &amp; Reconcile &rarr;</a>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-creamDark/60 text-muted font-mono text-[10px] font-bold uppercase tracking-widest border-b border-hairline">
                            <tr>
                                <th class="px-5 py-3">Region</th>
                                <th class="px-5 py-3">Bundle</th>
                                <th class="px-5 py-3">Kapasitas</th>
                                <th class="px-5 py-3">Reserved</th>
                                <th class="px-5 py-3">Used</th>
                                <th class="px-5 py-3">Sisa</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hairline">
                            @forelse($quotaPools as $pool)
                                <tr>
                                    <td class="px-5 py-3 font-mono font-semibold text-ink">{{ $pool->region_code }}</td>
                                    <td class="px-5 py-3 font-mono text-muted">{{ $pool->bundle_type }}</td>
                                    <td class="px-5 py-3 font-mono text-ink">{{ $pool->capacity }}</td>
                                    <td class="px-5 py-3 font-mono text-muted">{{ $pool->reserved }}</td>
                                    <td class="px-5 py-3 font-mono text-muted">{{ $pool->used }}</td>
                                    <td class="px-5 py-3 font-mono font-bold {{ $pool->availableSlots() === 0 ? 'text-electric' : 'text-ink' }}">{{ $pool->availableSlots() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-6 text-center font-mono text-xs text-muted">Belum ada data kuota.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white border border-hairline overflow-hidden">
                <div class="p-5 border-b border-hairline flex items-center justify-between gap-2">
                    <h3 class="font-display font-bold text-ink text-sm uppercase tracking-wider">Deposit Upstream</h3>
                    <form method="POST" action="{{ route('admin.monitoring.refresh-balance') }}">
                        @csrf
                        <button type="submit" class="px-2.5 py-1 bg-electric hover:bg-electricHover text-white font-mono text-[10px] font-bold uppercase cursor-pointer">Segarkan</button>
                    </form>
                </div>
                <div class="p-5 space-y-2">
                    @if($balanceUsd === null)
                        <p class="font-display text-2xl font-bold text-muted">&mdash;</p>
                        <p class="text-xs text-muted">Saldo belum disinkronkan.</p>
                    @else
                        <p class="font-display text-3xl font-bold {{ $balanceUsd < $balanceThreshold ? 'text-electric' : 'text-ink' }}">${{ number_format($balanceUsd, 2) }}</p>
                        <p class="font-mono text-[11px] text-muted">USD &middot; Diperbarui {{ $balanceCheckedAt ? \Carbon\Carbon::parse($balanceCheckedAt)->diffForHumans() : 'cache' }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-6">
            <div class="bg-white border border-hairline overflow-hidden">
                <div class="p-5 border-b border-hairline flex items-center justify-between">
                    <h3 class="font-display font-bold text-ink text-sm uppercase tracking-wider">SLA Terlewat</h3>
                    <span class="font-mono text-xs font-bold px-2 py-0.5 {{ $slaBreaches->count() > 0 ? 'bg-ink text-cream' : 'bg-creamDark text-muted border border-hairline' }}">{{ $slaBreaches->count() }} tiket</span>
                </div>
                <div class="divide-y divide-hairline">
                    @forelse($slaBreaches as $ticket)
                        <a href="{{ route('admin.tickets.show', $ticket->id) }}" class="block p-4 hover:bg-creamDark/40 transition-colors">
                            <div class="flex justify-between gap-3">
                                <span class="font-semibold text-sm text-ink">#TK-{{ str_pad($ticket->id, 4, '0', STR_PAD_LEFT) }} &middot; {{ $ticket->subject }}</span>
                                <span class="font-mono text-xs text-ink font-bold whitespace-nowrap">{{ $ticket->sla_due_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-xs text-muted mt-1">{{ $ticket->customer->full_name ?? '-' }} &middot; {{ $ticket->assignee?->full_name ?? 'Belum ditugaskan' }}</p>
                        </a>
                    @empty
                        <div class="p-8 text-center font-mono text-xs text-muted">Tidak ada SLA yang terlewat.</div>
                    @endforelse
                </div>
            </div>

            <div class="bg-white border border-hairline overflow-hidden">
                <div class="p-5 border-b border-hairline flex items-center justify-between">
                    <h3 class="font-display font-bold text-ink text-sm uppercase tracking-wider">Provisioning Macet (&gt;30 menit)</h3>
                    <span class="font-mono text-xs font-bold px-2 py-0.5 {{ $staleProvisioning->count() > 0 ? 'bg-ink text-cream' : 'bg-creamDark text-muted border border-hairline' }}">{{ $staleProvisioning->count() }} VPS</span>
                </div>
                <div class="divide-y divide-hairline">
                    @forelse($staleProvisioning as $vps)
                        <a href="{{ route('admin.instances') }}?search={{ urlencode($vps->hostname) }}" class="block p-4 hover:bg-creamDark/40 transition-colors">
                            <div class="flex justify-between">
                                <span class="font-mono font-bold text-sm text-ink">{{ $vps->hostname ?? 'VPS-'.$vps->id }}</span>
                                <span class="font-mono text-xs text-ink font-bold">{{ $vps->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-xs text-muted mt-1">{{ $vps->customer->full_name ?? '-' }} &middot; {{ $vps->public_ip ?? 'IP belum tersedia' }}</p>
                        </a>
                    @empty
                        <div class="p-8 text-center font-mono text-xs text-muted">Tidak ada provisioning yang macet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="bg-white border border-hairline overflow-hidden">
            <div class="p-5 border-b border-hairline">
                <h3 class="font-display font-bold text-ink text-sm uppercase tracking-wider">Transisi Status Terbaru</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-creamDark/60 text-muted font-mono text-[10px] font-bold uppercase tracking-widest border-b border-hairline">
                        <tr>
                            <th class="px-5 py-3">Waktu</th>
                            <th class="px-5 py-3">VPS</th>
                            <th class="px-5 py-3">Transisi</th>
                            <th class="px-5 py-3">Alasan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline">
                        @forelse($recentTransitions as $row)
                            <tr class="hover:bg-creamDark/30 transition-colors">
                                <td class="px-5 py-3 font-mono text-muted">{{ $row->created_at->timezone('Asia/Jakarta')->format('d M H:i') }}</td>
                                <td class="px-5 py-3 font-mono font-semibold text-ink">{{ $row->vpsInstance->hostname ?? 'VPS-'.$row->vps_instance_id }}</td>
                                <td class="px-5 py-3 font-mono"><span class="text-muted">{{ $row->from_status ?? '-' }}</span> &rarr; <span class="font-bold text-ink">{{ $row->to_status }}</span></td>
                                <td class="px-5 py-3 text-muted">{{ $row->reason }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-8 text-center font-mono text-xs text-muted">Belum ada transisi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif(($tab ?? '') === 'tasks')
        <!-- Tab Task Queue & TAT -->
        <div class="bg-white border border-hairline p-4 flex flex-wrap items-center justify-between gap-3">
            <form method="GET" action="{{ route('admin.monitoring') }}" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="tab" value="tasks">
                <select name="status" class="px-3 py-2 border border-hairline font-mono text-xs bg-white">
                    <option value="all" {{ ($taskStatus ?? 'all') === 'all' ? 'selected' : '' }}>Semua Status</option>
                    @foreach(['pending', 'running', 'failed', 'succeeded', 'cancelled'] as $st)
                        <option value="{{ $st }}" {{ ($taskStatus ?? '') === $st ? 'selected' : '' }}>{{ strtoupper($st) }}</option>
                    @endforeach
                </select>
                <select name="kind" class="px-3 py-2 border border-hairline font-mono text-xs bg-white">
                    <option value="all" {{ ($taskKind ?? 'all') === 'all' ? 'selected' : '' }}>Semua Jenis</option>
                    @foreach(['provision', 'install_stack', 'reinstall', 'renew', 'upgrade', 'destroy', 'stop', 'start', 'reboot'] as $knd)
                        <option value="{{ $knd }}" {{ ($taskKind ?? '') === $knd ? 'selected' : '' }}>{{ $knd }}</option>
                    @endforeach
                </select>
                <button type="submit" class="px-4 py-2 bg-ink text-white font-mono text-xs font-bold uppercase cursor-pointer">Filter</button>
            </form>
            <div class="font-mono text-xs text-muted">
                Active: <strong class="text-ink">{{ $taskStats['pending_running'] ?? 0 }}</strong> &middot;
                Failed: <strong class="text-red-600">{{ $taskStats['failed'] ?? 0 }}</strong> &middot;
                Succeeded: <strong class="text-emerald-700">{{ $taskStats['succeeded'] ?? 0 }}</strong>
            </div>
        </div>

        <div class="bg-white border border-hairline overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-creamDark/60 text-muted font-mono text-[10px] font-bold uppercase tracking-widest border-b border-hairline">
                        <tr>
                            <th class="px-4 py-3">Task &amp; Kind</th>
                            <th class="px-4 py-3">Order / Instance</th>
                            <th class="px-4 py-3">Status &amp; Progress</th>
                            <th class="px-4 py-3">Log / Error</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline">
                        @forelse($tasks as $task)
                            <tr class="hover:bg-creamDark/20 align-top">
                                <td class="px-4 py-3 font-mono">
                                    <div class="font-bold text-ink">#{{ $task->id }} &middot; {{ $task->kind }}</div>
                                    <div class="text-[10px] text-muted">{{ $task->provider }} &middot; try {{ $task->attempts }}</div>
                                    <div class="text-[10px] text-muted">{{ $task->created_at->timezone('Asia/Jakarta')->format('d M H:i') }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    @if($task->order_id)
                                        <div class="font-mono font-bold text-ink">Order #{{ $task->order_id }}</div>
                                    @endif
                                    @if($task->vpsInstance)
                                        <div class="font-mono text-[11px] text-muted">{{ $task->vpsInstance->hostname }} (#{{ $task->vps_instance_id }})</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase border {{ $task->status === 'succeeded' ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : ($task->status === 'failed' ? 'border-red-300 bg-red-50 text-red-700' : 'border-hairline bg-creamDark text-ink') }}">
                                        {{ $task->status }} ({{ $task->progress }}%)
                                    </span>
                                    <div class="text-[11px] text-muted mt-1">{{ $task->current_step ?? '-' }}</div>
                                </td>
                                <td class="px-4 py-3 max-w-xs">
                                    @if($task->error)
                                        <p class="font-mono text-[11px] text-red-700 break-words">{{ \Illuminate\Support\Str::limit($task->error, 140) }}</p>
                                    @else
                                        <span class="text-muted text-[11px]">-</span>
                                    @endif
                                    @if(!empty($task->output['install_output']))
                                        <button type="button"
                                                @click="taskDetail = {{ Js::from($task->output['install_output']) }}"
                                                class="mt-1 font-mono text-[10px] font-bold text-electric hover:underline cursor-pointer">
                                            Lihat Tail Log TAT &rarr;
                                        </button>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right space-y-1.5">
                                    @if($task->status === 'failed')
                                        <form method="POST" action="{{ route('admin.tasks.retry', $task->id) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 bg-electric hover:bg-electricHover text-white font-mono text-[10px] font-bold uppercase cursor-pointer">
                                                Retry Task
                                            </button>
                                        </form>
                                    @endif
                                    @if(in_array($task->status, ['pending', 'running'], true))
                                        <form method="POST" action="{{ route('admin.tasks.cancel', $task->id) }}" class="flex items-center justify-end gap-1">
                                            @csrf
                                            <input type="text" name="reason" required placeholder="Alasan cancel..."
                                                   class="w-32 px-2 py-1 border border-hairline text-[11px] bg-white">
                                            <button type="submit" class="px-2.5 py-1 bg-ink text-white font-mono text-[10px] font-bold uppercase cursor-pointer">
                                                Cancel
                                            </button>
                                        </form>
                                    @endif
                                    @if($task->vpsInstance && $task->vpsInstance->provider === 'tencent' && $task->vpsInstance->provider_resource_id && $task->status === 'failed')
                                        <form method="POST" action="{{ route('admin.instances.retry-stack', $task->vpsInstance->id) }}" class="block">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1 border border-ink bg-white hover:bg-creamDark text-ink font-mono text-[10px] font-bold uppercase cursor-pointer">
                                                Retry Stack Saja
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-8 text-center text-muted text-xs font-mono">Belum ada provisioning task.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($tasks->hasPages())
                <div class="p-4 border-t border-hairline">{{ $tasks->links() }}</div>
            @endif
        </div>
    @elseif(($tab ?? '') === 'upstream')
        <!-- Tab Upstream: Balance, Quota Pools, Reconcile Report, Renew Pending -->
        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white border border-hairline overflow-hidden">
                <div class="p-5 border-b border-hairline flex items-center justify-between gap-2">
                    <h3 class="font-display font-bold text-ink text-sm uppercase tracking-wider">Kuota Upstream per Region</h3>
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-[10px] font-bold uppercase px-2 py-0.5 border border-hairline bg-creamDark text-muted">Guard: {{ $quotaGuardMode }}</span>
                        <a href="{{ route('admin.settings.index', ['tab' => 'provisioning']) }}" class="font-mono text-[10px] font-bold text-electric hover:underline">Edit Pool &rarr;</a>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-creamDark/60 text-muted font-mono text-[10px] font-bold uppercase tracking-widest border-b border-hairline">
                            <tr>
                                <th class="px-5 py-3">Region</th>
                                <th class="px-5 py-3">Bundle</th>
                                <th class="px-5 py-3">Kapasitas</th>
                                <th class="px-5 py-3">Reserved</th>
                                <th class="px-5 py-3">Used</th>
                                <th class="px-5 py-3">Sisa</th>
                                <th class="px-5 py-3 w-36">Pemakaian</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hairline">
                            @forelse($quotaPools as $pool)
                                @php $pct = $pool->usagePercent(); @endphp
                                <tr class="hover:bg-creamDark/30">
                                    <td class="px-5 py-3 font-mono font-semibold text-ink">{{ $pool->region_code }}</td>
                                    <td class="px-5 py-3 font-mono text-muted">{{ $pool->bundle_type }}</td>
                                    <td class="px-5 py-3 font-mono text-ink">{{ $pool->capacity }}</td>
                                    <td class="px-5 py-3 font-mono text-muted">{{ $pool->reserved }}</td>
                                    <td class="px-5 py-3 font-mono text-muted">{{ $pool->used }}</td>
                                    <td class="px-5 py-3 font-mono font-bold {{ $pool->availableSlots() === 0 ? 'text-electric' : 'text-ink' }}">{{ $pool->availableSlots() }}</td>
                                    <td class="px-5 py-3">
                                        <div class="h-2 bg-creamDark border border-hairline">
                                            <div class="h-full {{ $pct >= 90 ? 'bg-electric' : ($pct >= 70 ? 'bg-amber-400' : 'bg-emerald-500') }}" style="width: {{ min(100, $pct) }}%"></div>
                                        </div>
                                        <span class="font-mono text-[10px] text-muted">{{ $pct }}%</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-5 py-8 text-center font-mono text-xs text-muted">Belum ada data kuota.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white border border-hairline overflow-hidden flex flex-col justify-between">
                <div>
                    <div class="p-5 border-b border-hairline flex items-center justify-between gap-2">
                        <h3 class="font-display font-bold text-ink text-sm uppercase tracking-wider">Deposit Upstream</h3>
                        <form method="POST" action="{{ route('admin.monitoring.refresh-balance') }}">
                            @csrf
                            <button type="submit" class="px-3 py-1 bg-electric hover:bg-electricHover text-white font-mono text-[10px] font-bold uppercase cursor-pointer">
                                Segarkan Saldo
                            </button>
                        </form>
                    </div>
                    <div class="p-5 space-y-3">
                        @if($balanceUsd === null)
                            <p class="font-display text-2xl font-bold text-muted">&mdash;</p>
                            <p class="text-xs text-muted">Saldo belum pernah disinkronkan. Klik <strong>Segarkan Saldo</strong> di atas.</p>
                        @else
                            <p class="font-display text-3xl font-bold {{ $balanceUsd < $balanceThreshold ? 'text-electric' : 'text-ink' }}">${{ number_format($balanceUsd, 2) }}</p>
                            <p class="font-mono text-[11px] text-muted">
                                USD &middot; Diperbarui {{ $balanceCheckedAt ? \Carbon\Carbon::parse($balanceCheckedAt)->diffForHumans() : 'baru saja' }}
                            </p>
                            @if($balanceUsd < $balanceThreshold)
                                <div class="border border-electric bg-electric/5 p-3">
                                    <p class="font-mono text-[11px] font-bold text-electric uppercase">Saldo rendah</p>
                                    <p class="text-xs text-muted mt-1">Di bawah ambang ${{ number_format($balanceThreshold, 2) }}. Top-up sebelum provisioning/renewal.</p>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
                <div class="p-4 border-t border-hairline bg-creamDark/20 flex items-center justify-between gap-2">
                    <span class="font-mono text-[10px] text-muted uppercase">Rekonsiliasi State &amp; Orphan</span>
                    <form method="POST" action="{{ route('admin.monitoring.reconcile') }}">
                        @csrf
                        <button type="submit" class="px-3 py-1.5 border border-ink bg-white hover:bg-ink hover:text-white font-mono text-[10px] font-bold uppercase cursor-pointer">
                            Jalankan Reconcile
                        </button>
                    </form>
                </div>
            </div>
        </div>

        @if(isset($renewPendingInstances) && $renewPendingInstances->count() > 0)
            <div class="bg-white border-2 border-red-600 overflow-hidden">
                <div class="p-4 border-b border-red-200 bg-red-50 flex items-center justify-between">
                    <h3 class="font-display font-bold text-red-900 text-sm uppercase">Instance dengan Status renew_pending ({{ $renewPendingInstances->count() }})</h3>
                    <span class="font-mono text-[10px] text-red-800">Top up saldo lalu klik Retry Renew</span>
                </div>
                <div class="divide-y divide-hairline">
                    @foreach($renewPendingInstances as $rp)
                        <div class="p-4 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <div class="font-mono font-bold text-ink text-xs">{{ $rp->hostname }} (#{{ $rp->id }}) &middot; {{ $rp->provider_resource_id }}</div>
                                <div class="text-[11px] text-muted">Masa aktif pelanggan aman s/d {{ $rp->expires_at?->timezone('Asia/Jakarta')->format('d M Y') }}</div>
                            </div>
                            <form method="POST" action="{{ route('admin.instances.retry-renew', $rp->id) }}">
                                @csrf
                                <button type="submit" class="px-3.5 py-2 bg-red-600 hover:bg-red-700 text-white font-mono text-[10px] font-bold uppercase cursor-pointer">
                                    Retry Renew Upstream
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="bg-white border border-hairline overflow-hidden">
            <div class="p-5 border-b border-hairline flex items-center justify-between">
                <h3 class="font-display font-bold text-ink text-sm uppercase tracking-wider">Snapshot Rekonsiliasi Terakhir</h3>
                @if(!empty($reconcileReport['ran_at']))
                    <span class="font-mono text-[11px] text-muted">{{ \Carbon\Carbon::parse($reconcileReport['ran_at'])->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</span>
                @endif
            </div>
            <div class="p-5 space-y-4 text-xs">
                @if(empty($reconcileReport))
                    <p class="text-muted font-mono">Belum ada snapshot rekonsiliasi. Klik <strong>Jalankan Reconcile</strong> untuk memindai orphan &amp; drift.</p>
                @else
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="p-3 border border-hairline bg-creamDark/20"><span class="font-mono text-[10px] text-muted block">Dicek</span><strong class="font-display text-lg">{{ $reconcileReport['checked_count'] ?? 0 }}</strong></div>
                        <div class="p-3 border border-hairline bg-creamDark/20"><span class="font-mono text-[10px] text-muted block">Divergen / Auto-Fix</span><strong class="font-display text-lg">{{ $reconcileReport['divergent_count'] ?? 0 }} / {{ $reconcileReport['reconciled_count'] ?? 0 }}</strong></div>
                        <div class="p-3 border border-hairline bg-creamDark/20"><span class="font-mono text-[10px] text-muted block">Orphan Upstream</span><strong class="font-display text-lg text-electric">{{ count($reconcileReport['orphans'] ?? []) }}</strong></div>
                        <div class="p-3 border border-hairline bg-creamDark/20"><span class="font-mono text-[10px] text-muted block">Drift Masa Aktif</span><strong class="font-display text-lg">{{ count($reconcileReport['expiry_drifts'] ?? []) }}</strong></div>
                    </div>

                    @if(!empty($reconcileReport['orphans']))
                        <div>
                            <h4 class="font-mono text-[10px] font-bold uppercase text-red-700 mb-2">Orphan Upstream (Tidak di DB, Tidak Dihapus Otomatis)</h4>
                            <div class="border border-hairline divide-y divide-hairline">
                                @foreach($reconcileReport['orphans'] as $orp)
                                    <div class="p-3 font-mono text-[11px] flex justify-between">
                                        <span><strong>{{ $orp['upstream_id'] }}</strong> &middot; {{ $orp['name'] }}</span>
                                        <span class="text-muted">{{ $orp['region'] }} &middot; {{ $orp['state'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if(!empty($reconcileReport['expiry_drifts']))
                        <div>
                            <h4 class="font-mono text-[10px] font-bold uppercase text-amber-800 mb-2">Drift Masa Aktif (&gt; 2 Hari)</h4>
                            <div class="border border-hairline divide-y divide-hairline">
                                @foreach($reconcileReport['expiry_drifts'] as $drift)
                                    <div class="p-3 font-mono text-[11px] flex justify-between">
                                        <span>VPS #{{ $drift['vps_id'] }} ({{ $drift['hostname'] }})</span>
                                        <span>Selisih {{ $drift['diff_days'] }} hari</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    @else
        <!-- Tab Failed Queue Jobs -->
        <div class="bg-white border border-hairline overflow-hidden">
            <div class="p-5 border-b border-hairline flex items-center justify-between">
                <h3 class="font-display font-bold text-ink text-sm uppercase tracking-wider">Laravel Failed Queue Jobs</h3>
                <span class="font-mono text-xs text-muted">{{ $failedJobs->count() }} item</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-creamDark/60 text-muted font-mono text-[10px] font-bold uppercase tracking-widest border-b border-hairline">
                        <tr>
                            <th class="px-4 py-3">UUID / Queue</th>
                            <th class="px-4 py-3">Failed At</th>
                            <th class="px-4 py-3">Exception</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline">
                        @forelse($failedJobs as $job)
                            <tr class="hover:bg-creamDark/20 align-top">
                                <td class="px-4 py-3 font-mono text-[11px]">
                                    <div class="font-bold text-ink">{{ $job->uuid }}</div>
                                    <div class="text-muted">{{ $job->queue }}</div>
                                </td>
                                <td class="px-4 py-3 font-mono text-[11px] text-muted whitespace-nowrap">{{ $job->failed_at }}</td>
                                <td class="px-4 py-3 font-mono text-[11px] text-red-700 max-w-md break-words">{{ \Illuminate\Support\Str::limit($job->exception, 220) }}</td>
                                <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                                    <form method="POST" action="{{ route('admin.failed-jobs.retry', $job->uuid) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="px-3 py-1.5 bg-electric text-white font-mono text-[10px] font-bold uppercase cursor-pointer">Retry</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.failed-jobs.destroy', $job->uuid) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-3 py-1.5 bg-ink text-white font-mono text-[10px] font-bold uppercase cursor-pointer">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-8 text-center font-mono text-xs text-muted">Antrean failed_jobs bersih.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Modal Tail Log TAT Output -->
    <div x-show="taskDetail !== null" x-cloak class="fixed inset-0 z-50 bg-ink/60 flex items-center justify-center p-4" style="display: none;">
        <div @click.away="taskDetail = null" class="bg-white border border-ink max-w-3xl w-full max-h-[85vh] flex flex-col">
            <div class="px-5 py-3 border-b border-hairline flex items-center justify-between bg-creamDark/40">
                <h4 class="font-mono text-xs font-bold uppercase">Tail Log Eksekusi TAT (Maks 4.000 Karakter Terakhir)</h4>
                <button type="button" @click="taskDetail = null" class="font-mono text-base font-bold px-2 cursor-pointer">&times;</button>
            </div>
            <pre class="p-4 text-[11px] font-mono bg-ink text-cream overflow-auto flex-1 whitespace-pre-wrap" x-text="taskDetail"></pre>
        </div>
    </div>
</div>
@endsection
