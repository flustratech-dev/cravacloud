@extends('layouts.admin', ['title' => 'Monitoring Operasional', 'headerTitle' => 'Monitoring Operasional', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 bg-white border border-hairline p-5">
        <div>
            <h2 class="font-display text-lg font-bold text-ink">Health &amp; Operasional</h2>
            <p class="text-xs text-muted mt-1">Pantau layanan bermasalah, provisioning yang macet, dan SLA helpdesk.</p>
        </div>
        <span class="font-mono text-xs text-muted bg-creamDark px-3 py-1 border border-hairline">Diperbarui {{ now()->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</span>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-5 gap-px bg-hairline border border-hairline">
        @foreach(['running' => 'Running', 'provisioning' => 'Provisioning', 'stopped' => 'Stopped', 'error' => 'Error', 'suspended' => 'Suspended'] as $key => $label)
            <div class="bg-white p-5">
                <span class="font-mono text-[10px] font-bold uppercase tracking-widest text-muted">{{ $label }}</span>
                <p class="font-display text-2xl font-bold mt-1 {{ $key === 'error' ? 'text-electric' : 'text-ink' }}">{{ $statusCounts[$key] ?? 0 }}</p>
            </div>
        @endforeach
    </div>

    {{-- Phase 5F — admin-only upstream widgets (quota pool + deposit balance) --}}
    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white border border-hairline overflow-hidden">
            <div class="p-5 border-b border-hairline flex items-center justify-between">
                <h3 class="font-display font-bold text-ink text-sm uppercase tracking-wider">Kuota Upstream per Region</h3>
                <span class="font-mono text-[10px] font-bold uppercase px-2 py-0.5 border border-hairline bg-creamDark text-muted">Guard: {{ $quotaGuardMode }}</span>
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
                            <th class="px-5 py-3 w-40">Pemakaian</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline">
                        @forelse($quotaPools as $pool)
                            @php $pct = $pool->usagePercent(); @endphp
                            <tr class="hover:bg-creamDark/30 transition-colors">
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
                            <tr><td colspan="7" class="px-5 py-8 text-center font-mono text-xs text-muted">Belum ada data kuota. Jalankan sinkronisasi kuota upstream.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white border border-hairline overflow-hidden">
            <div class="p-5 border-b border-hairline">
                <h3 class="font-display font-bold text-ink text-sm uppercase tracking-wider">Deposit Upstream</h3>
            </div>
            <div class="p-5 space-y-3">
                @if($balanceUsd === null)
                    <p class="font-display text-2xl font-bold text-muted">—</p>
                    <p class="text-xs text-muted">Saldo belum disinkronkan. Jalankan <span class="font-mono font-bold text-ink">tencent:check-balance</span> untuk memperbarui.</p>
                @else
                    <p class="font-display text-3xl font-bold {{ $balanceUsd < $balanceThreshold ? 'text-electric' : 'text-ink' }}">${{ number_format($balanceUsd, 2) }}</p>
                    <p class="font-mono text-[11px] text-muted">USD · cache 60 detik</p>
                    @if($balanceUsd < $balanceThreshold)
                        <div class="border border-electric bg-electric/5 p-3">
                            <p class="font-mono text-[11px] font-bold text-electric uppercase">Saldo rendah</p>
                            <p class="text-xs text-muted mt-1">Di bawah ambang ${{ number_format($balanceThreshold, 2) }}. Top-up akun upstream sebelum provisioning baru.</p>
                        </div>
                    @else
                        <p class="text-xs text-muted">Ambang rendah: ${{ number_format($balanceThreshold, 2) }}.</p>
                    @endif
                @endif
                <p class="text-[11px] text-muted border-t border-hairline pt-3">Nilai dibaca dari cache, bukan panggilan API langsung. Verifikasi unit saldo setelah top-up pertama.</p>
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
                        <p class="text-xs text-muted mt-1">{{ $ticket->customer->full_name }} &middot; {{ $ticket->assignee?->full_name ?? 'Belum ditugaskan' }}</p>
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
</div>
@endsection