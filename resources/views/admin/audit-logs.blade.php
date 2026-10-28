@extends('layouts.admin', ['title' => 'Audit Log', 'headerTitle' => 'Jejak Audit', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6" x-data="{ openId: null }">

    @php
        $filterClass = 'px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-black';
        $tabs = ['admin' => 'Aksi Admin', 'login' => 'Riwayat Login', 'impersonation' => 'Sesi Masuk sebagai Pelanggan'];
    @endphp

    <div class="bg-white p-2 rounded-lg border border-slate-200 flex items-center gap-1 overflow-x-auto">
        @foreach($tabs as $key => $label)
            <a href="{{ route('admin.audit.index', ['tab' => $key]) }}"
               class="px-4 py-2 rounded-lg text-xs font-bold transition-colors whitespace-nowrap {{ $tab === $key ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:bg-slate-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 space-y-3">
            <div>
                <h3 class="font-bold text-slate-900 text-base">{{ $tabs[$tab] }}</h3>
                <p class="text-xs text-slate-500">
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
                <button type="submit" class="px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold">Saring</button>
                @if(array_filter($filters))
                    <a href="{{ route('admin.audit.index', ['tab' => $tab]) }}" class="px-4 py-2 rounded-lg bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold border border-slate-200">Reset</a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            {{-- ================= Aksi admin ================= --}}
            @if($tab === 'admin')
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider border-y border-slate-200">
                        <tr>
                            <th class="px-5 py-3.5">Waktu</th>
                            <th class="px-5 py-3.5">Aksi</th>
                            <th class="px-5 py-3.5">Keterangan</th>
                            <th class="px-5 py-3.5">Pelaku & IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($logs as $log)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-5 py-3.5 font-mono-code text-slate-600 whitespace-nowrap">{{ $log->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i:s') }}</td>
                                <td class="px-5 py-3.5">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200 font-mono-code">{{ $log->action }}</span>
                                </td>
                                <td class="px-5 py-3.5 text-slate-700">
                                    {{ $log->description }}
                                    @if($log->subject_type)
                                        <span class="text-[10px] text-slate-400 block font-mono-code">{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-slate-700">
                                    <div class="font-semibold text-slate-900">{{ $log->admin?->full_name ?? $log->admin?->email ?? 'Sistem' }}</div>
                                    <div class="text-[11px] text-slate-500 font-mono-code">{{ $log->ip_address ?? '-' }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-10 text-center text-slate-500 text-sm">Belum ada catatan aksi admin.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if($logs->hasPages())
                    <div class="px-5 py-4 border-t border-slate-100">{{ $logs->links() }}</div>
                @endif
            @endif

            {{-- ================= Riwayat login ================= --}}
            @if($tab === 'login')
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider border-y border-slate-200">
                        <tr>
                            <th class="px-5 py-3.5">Waktu</th>
                            <th class="px-5 py-3.5">Akun</th>
                            <th class="px-5 py-3.5 text-center">Hasil</th>
                            <th class="px-5 py-3.5">IP & Perangkat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($activities as $activity)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-5 py-3.5 font-mono-code text-slate-600 whitespace-nowrap">{{ $activity->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i:s') }}</td>
                                <td class="px-5 py-3.5 text-slate-700">
                                    <div class="font-semibold text-slate-900">{{ $activity->user?->full_name ?? '—' }}</div>
                                    <div class="text-[11px] text-slate-500">{{ $activity->email }}</div>
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    @if($activity->outcome === 'success')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200"><span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>Berhasil</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-red-50 text-red-800 border border-red-200"><span class="w-1.5 h-1.5 rounded-full bg-red-600"></span>{{ str_replace('_', ' ', $activity->outcome) }}</span>
                                    @endif
                                    @if($activity->reason)
                                        <span class="text-[10px] text-slate-400 block mt-1">{{ $activity->reason }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-slate-700">
                                    <div class="font-mono-code">{{ $activity->ip_address ?? '-' }}</div>
                                    <div class="text-[11px] text-slate-500">{{ $activity->device_label ?? \Illuminate\Support\Str::limit($activity->user_agent, 50) }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-10 text-center text-slate-500 text-sm">Belum ada riwayat login.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if($activities->hasPages())
                    <div class="px-5 py-4 border-t border-slate-100">{{ $activities->links() }}</div>
                @endif
            @endif

            {{-- ================= Sesi impersonation ================= --}}
            @if($tab === 'impersonation')
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider border-y border-slate-200">
                        <tr>
                            <th class="px-5 py-3.5">Mulai</th>
                            <th class="px-5 py-3.5">Admin</th>
                            <th class="px-5 py-3.5">Sebagai Pelanggan</th>
                            <th class="px-5 py-3.5 text-center">Durasi</th>
                            <th class="px-5 py-3.5 text-center">Aksi Diblokir</th>
                            <th class="px-5 py-3.5">Berakhir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($sessions as $session)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-5 py-3.5 font-mono-code text-slate-600 whitespace-nowrap">{{ $session->started_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }}</td>
                                <td class="px-5 py-3.5 text-slate-700">
                                    <div class="font-semibold text-slate-900">{{ $session->admin?->full_name ?? '-' }}</div>
                                    <div class="text-[11px] text-slate-500 font-mono-code">{{ $session->ip_address ?? '-' }}</div>
                                </td>
                                <td class="px-5 py-3.5 text-slate-700">
                                    <div class="font-semibold text-slate-900">{{ $session->impersonated?->full_name ?? '-' }}</div>
                                    <div class="text-[11px] text-slate-500">{{ $session->impersonated?->email ?? '-' }}</div>
                                </td>
                                <td class="px-5 py-3.5 text-center font-mono-code">{{ $session->duration_minutes !== null ? $session->duration_minutes . ' mnt' : '—' }}</td>
                                <td class="px-5 py-3.5 text-center font-mono-code font-semibold {{ $session->blocked_actions > 0 ? 'text-amber-700' : 'text-slate-400' }}">{{ $session->blocked_actions }}</td>
                                <td class="px-5 py-3.5">
                                    @if($session->ended_at)
                                        <span class="text-slate-700">{{ $session->end_reason_label }}</span>
                                        <span class="text-[11px] text-slate-400 block font-mono-code">{{ $session->ended_at->timezone('Asia/Jakarta')->format('d M H:i') }}</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200"><span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>Berjalan</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500 text-sm">Belum pernah ada sesi masuk sebagai pelanggan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if($sessions->hasPages())
                    <div class="px-5 py-4 border-t border-slate-100">{{ $sessions->links() }}</div>
                @endif
            @endif
        </div>
    </div>

</div>
@endsection
