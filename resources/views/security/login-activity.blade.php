@extends('layouts.dashboard', ['title' => 'Riwayat Login', 'headerTitle' => 'Riwayat Login'])

@section('content')
<div class="max-w-5xl space-y-5">
    <div>
        <a href="{{ route('security.settings') }}" class="font-mono text-xs uppercase tracking-wider text-muted hover:text-electric transition-colors inline-flex items-center gap-1.5">
            <span>←</span>
            <span>Kembali ke Pengaturan Keamanan</span>
        </a>
    </div>

    <div class="bg-white border border-hairline overflow-hidden">
        <div class="p-6 border-b border-hairline">
            <h2 class="font-display text-xl font-bold text-ink">Riwayat login</h2>
            <p class="text-xs text-muted mt-1">Catatan autentikasi terakhir pada akun Anda.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-creamDark border-b border-hairline font-mono text-ink font-bold uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-3.5">Waktu</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5">IP Address</th>
                        <th class="px-6 py-3.5">Perangkat</th>
                        <th class="px-6 py-3.5">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @forelse($activities as $activity)
                        <tr class="hover:bg-creamDark/30 transition-colors">
                            <td class="px-6 py-3.5 whitespace-nowrap font-mono text-ink">{{ $activity->created_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</td>
                            <td class="px-6 py-3.5 whitespace-nowrap">
                                <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase border {{ $activity->outcome === 'success' ? 'bg-emerald-100 text-emerald-900 border-emerald-400' : 'bg-red-100 text-red-900 border-red-400' }}">
                                    {{ $activity->outcome }}
                                </span>
                            </td>
                            <td class="px-6 py-3.5 font-mono text-ink">{{ $activity->ip_address ?: '-' }}</td>
                            <td class="px-6 py-3.5 text-ink">{{ $activity->device_label ?: Str::limit($activity->user_agent, 45) }}</td>
                            <td class="px-6 py-3.5 text-muted">{{ $activity->reason ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center font-mono text-xs text-muted">Belum ada aktivitas login.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-hairline">{{ $activities->links() }}</div>
    </div>
</div>
@endsection
