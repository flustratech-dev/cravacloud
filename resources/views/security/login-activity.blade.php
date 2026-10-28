@extends('layouts.dashboard', ['title' => 'Riwayat Login', 'headerTitle' => 'Riwayat Login', 'backUrl' => route('security.settings'), 'backLabel' => 'Kembali ke Pengaturan Keamanan'])

@section('content')
<div class="max-w-5xl space-y-4">
    <div>
        <a href="{{ route('security.settings') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 transition-colors py-1 group">
            <svg class="w-4 h-4 text-slate-400 group-hover:text-slate-700 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>Kembali ke Pengaturan Keamanan</span>
        </a>
    </div>

    <div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
        <div class="p-6">
            <h2 class="text-xl font-bold">Riwayat login</h2>
            <p class="text-sm text-slate-500 mt-1">Aktivitas autentikasi terakhir pada akun Anda.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-6 py-3">Waktu</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">IP</th>
                        <th class="px-6 py-3">Perangkat</th>
                        <th class="px-6 py-3">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($activities as $activity)
                        <tr>
                            <td class="px-6 py-3 whitespace-nowrap">{{ $activity->created_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</td>
                            <td class="px-6 py-3"><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $activity->outcome === 'success' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">{{ $activity->outcome }}</span></td>
                            <td class="px-6 py-3 font-mono-code text-xs">{{ $activity->ip_address ?: '-' }}</td>
                            <td class="px-6 py-3">{{ $activity->device_label ?: Str::limit($activity->user_agent, 45) }}</td>
                            <td class="px-6 py-3 text-slate-500">{{ $activity->reason ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-slate-500">Belum ada aktivitas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-6">{{ $activities->links() }}</div>
    </div>
</div>
@endsection
