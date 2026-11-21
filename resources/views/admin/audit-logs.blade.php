@extends('layouts.admin', ['title' => 'Audit Log', 'headerTitle' => 'Jejak Audit', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6" x-data="{ openId: null }">

    @php
        $filterClass = 'px-3 py-2 border border-hairline text-xs text-ink bg-white focus:outline-none focus:border-electric font-mono';
        $tabs = ['admin' => 'Aksi Admin', 'login' => 'Riwayat Login', 'impersonation' => 'Sesi Masuk sebagai Pelanggan'];
    @endphp

    @include('admin.partials.system-tabs')

    <div class="bg-white p-1.5 border border-hairline flex flex-wrap items-center gap-1">
        @foreach($tabs as $key => $label)
            <a href="{{ route('admin.audit.index', ['tab' => $key]) }}"
               class="px-4 py-2 font-mono text-xs font-bold uppercase tracking-wider transition-colors whitespace-nowrap {{ $tab === $key ? 'bg-electric text-white' : 'text-ink hover:bg-creamDark/60' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="bg-white border border-hairline overflow-hidden">
        <div class="px-5 py-4 border-b border-hairline space-y-3">
            <div>
                <h3 class="font-display font-bold text-ink text-base">{{ $tabs[$tab] }}</h3>
                <p class="text-xs text-muted mt-0.5">
                    @if($tab === 'admin')
                        Setiap perubahan dari panel admin. Nilai rahasia seperti kredensial tidak pernah dicatat.
                    @elseif($tab === 'login')
                        Upaya masuk ke akun, berhasil maupun gagal.
                    @else
                        Setiap sesi masuk sebagai pelanggan beserta durasi dan jumlah aksi yang diblokir.
                    @endif
                </p>
            </div>

            <form method="GET" action="{{ route('admin.audit.index') }}" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="tab" value="{{ $tab }}">
                @if($tab === 'admin')
                    <select name="action" class="{{ $filterClass }}">
                        <option value="">Semua Kelompok Aksi</option>
                        @foreach($actionGroups as $group)
                            <option value="{{ $group }}" {{ ($filters['action'] ?? '') === $group ? 'selected' : '' }}>{{ $group }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari keterangan" class="{{ $filterClass }}">
                @endif
                @if($tab === 'login')
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Surel atau IP" class="{{ $filterClass }}">
                    <select name="outcome" class="{{ $filterClass }}">
                        <option value="">Semua Hasil</option>
                        @foreach(['success' => 'Berhasil', 'failed' => 'Gagal', 'blocked' => 'Diblokir', 'two_factor_required' => 'Butuh 2FA', 'two_factor_failed' => '2FA Gagal'] as $value => $label)
                            <option value="{{ $value }}" {{ ($filters['outcome'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                @endif
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="{{ $filterClass }}">
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="{{ $filterClass }}">
                <button type="submit" class="px-4 py-2 bg-ink hover:bg-electric text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">Saring</button>
                @if(array_filter($filters))
                    <a href="{{ route('admin.audit.index', ['tab' => $tab]) }}" class="px-4 py-2 bg-white hover:border-ink text-ink font-mono text-xs font-bold uppercase border border-hairline">Reset</a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            {{-- ================= Aksi admin ================= --}}
            @if($tab === 'admin')
                <table class="w-full text-left text-xs">
                    <thead class="bg-creamDark/60 text-muted font-mono text-[10px] font-bold uppercase tracking-widest border-y border-hairline">
                        <tr>
                            <th class="px-5 py-3.5">Waktu</th>
                            <th class="px-5 py-3.5">Aksi</th>
                            <th class="px-5 py-3.5">Keterangan</th>
                            <th class="px-5 py-3.5">Pelaku &amp; IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline">
                        @forelse($logs as $log)
                            <tr class="hover:bg-creamDark/30 transition-colors">
                                <td class="px-5 py-3.5 font-mono text-muted whitespace-nowrap">{{ $log->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i:s') }}</td>
                                <td class="px-5 py-3.5">
                                    <span class="px-1.5 py-0.5 text-[10px] font-bold bg-creamDark text-ink border border-hairline font-mono">{{ $log->action }}</span>
                                </td>
                                <td class="px-5 py-3.5 text-ink">
                                    {{ $log->description }}
                                    @if($log->subject_type)
                                        <span class="text-[10px] text-muted block font-mono">{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-ink">
                                    <div class="font-semibold text-ink">{{ $log->admin?->full_name ?? $log->admin?->email ?? 'Sistem' }}</div>
                                    <div class="text-[11px] text-muted font-mono">{{ $log->ip_address ?? '-' }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-10 text-center font-mono text-xs text-muted">Belum ada catatan aksi admin.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if($logs->hasPages())
                    <div class="px-5 py-4 border-t border-hairline">{{ $logs->links() }}</div>
                @endif
            @endif

            {{-- ================= Riwayat login ================= --}}
            @if($tab === 'login')
                <table class="w-full text-left text-xs">
                    <thead class="bg-creamDark/60 text-muted font-mono text-[10px] font-bold uppercase tracking-widest border-y border-hairline">
                        <tr>
                            <th class="px-5 py-3.5">Waktu</th>
                            <th class="px-5 py-3.5">Akun</th>
                            <th class="px-5 py-3.5 text-center">Hasil</th>
                            <th class="px-5 py-3.5">IP &amp; Perangkat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline">
                        @forelse($activities as $activity)
                            <tr class="hover:bg-creamDark/30 transition-colors">
                                <td class="px-5 py-3.5 font-mono text-muted whitespace-nowrap">{{ $activity->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i:s') }}</td>
                                <td class="px-5 py-3.5 text-ink">
                                    <div class="font-semibold text-ink">{{ $activity->user?->full_name ?? '—' }}</div>
                                    <div class="text-[11px] font-mono text-muted">{{ $activity->email }}</div>
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    @if($activity->outcome === 'success')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-electric text-white">Berhasil</span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-ink text-cream">{{ str_replace('_', ' ', $activity->outcome) }}</span>
                                    @endif
                                    @if($activity->reason)
                                        <span class="text-[10px] text-muted block mt-1">{{ $activity->reason }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-ink">
                                    <div class="font-mono">{{ $activity->ip_address ?? '-' }}</div>
                                    <div class="text-[11px] text-muted">{{ $activity->device_label ?? \Illuminate\Support\Str::limit($activity->user_agent, 50) }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-10 text-center font-mono text-xs text-muted">Belum ada riwayat login.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if($activities->hasPages())
                    <div class="px-5 py-4 border-t border-hairline">{{ $activities->links() }}</div>
                @endif
            @endif

            {{-- ================= Sesi impersonation ================= --}}
            @if($tab === 'impersonation')
                <table class="w-full text-left text-xs">
                    <thead class="bg-creamDark/60 text-muted font-mono text-[10px] font-bold uppercase tracking-widest border-y border-hairline">
                        <tr>
                            <th class="px-5 py-3.5">Mulai</th>
                            <th class="px-5 py-3.5">Admin</th>
                            <th class="px-5 py-3.5">Sebagai Pelanggan</th>
                            <th class="px-5 py-3.5 text-center">Durasi</th>
                            <th class="px-5 py-3.5 text-center">Aksi Diblokir</th>
                            <th class="px-5 py-3.5">Berakhir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline">
                        @forelse($sessions as $session)
                            <tr class="hover:bg-creamDark/30 transition-colors">
                                <td class="px-5 py-3.5 font-mono text-muted whitespace-nowrap">{{ $session->started_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }}</td>
                                <td class="px-5 py-3.5 text-ink">
                                    <div class="font-semibold text-ink">{{ $session->admin?->full_name ?? '-' }}</div>
                                    <div class="text-[11px] text-muted font-mono">{{ $session->ip_address ?? '-' }}</div>
                                </td>
                                <td class="px-5 py-3.5 text-ink">
                                    <div class="font-semibold text-ink">{{ $session->impersonated?->full_name ?? '-' }}</div>
                                    <div class="text-[11px] font-mono text-muted">{{ $session->impersonated?->email ?? '-' }}</div>
                                </td>
                                <td class="px-5 py-3.5 text-center font-mono">{{ $session->duration_minutes !== null ? $session->duration_minutes . ' mnt' : '—' }}</td>
                                <td class="px-5 py-3.5 text-center font-mono font-bold {{ $session->blocked_actions > 0 ? 'text-electric' : 'text-muted' }}">{{ $session->blocked_actions }}</td>
                                <td class="px-5 py-3.5">
                                    @if($session->ended_at)
                                        <span class="text-ink font-medium">{{ $session->end_reason_label }}</span>
                                        <span class="text-[11px] text-muted block font-mono">{{ $session->ended_at->timezone('Asia/Jakarta')->format('d M H:i') }}</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-electric text-white">Berjalan</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-10 text-center font-mono text-xs text-muted">Belum pernah ada sesi masuk sebagai pelanggan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if($sessions->hasPages())
                    <div class="px-5 py-4 border-t border-hairline">{{ $sessions->links() }}</div>
                @endif
            @endif
        </div>
    </div>

</div>
@endsection