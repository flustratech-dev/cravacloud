@extends('layouts.dashboard', ['title' => 'Pusat Bantuan & Support', 'headerTitle' => 'Pusat Bantuan & Support', 'backUrl' => route('dashboard.index'), 'backLabel' => 'Kembali ke Dashboard'])

@section('content')
<div class="space-y-6" x-data="{ showCreateModal: false }">
    <!-- Header (No lines under headings) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-base font-bold text-slate-900">Tiket Dukungan Teknis</h2>
            <p class="text-xs text-slate-500 mt-0.5">Bantuan konfigurasi server, OS, reverse proxy, dan penagihan.</p>
        </div>
        <button @click="showCreateModal = true" class="px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-semibold transition-colors flex items-center gap-1.5 self-start sm:self-auto">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>Buat Tiket Baru</span>
        </button>
    </div>

    <!-- Tickets List Table -->
    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider">
                    <tr>
                        <th class="px-5 py-3">ID</th>
                        <th class="px-5 py-3">Subjek Kendala</th>
                        <th class="px-5 py-3">Terkait VPS</th>
                        <th class="px-5 py-3">Prioritas</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Pembaruan</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($tickets as $ticket)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-5 py-3.5 font-mono-code font-semibold text-slate-700">
                                #TK-{{ str_pad($ticket->id, 4, '0', STR_PAD_LEFT) }}
                            </td>
                            <td class="px-5 py-3.5">
                                <a href="{{ route('dashboard.support.show', $ticket->id) }}" class="font-semibold text-slate-900 hover:underline transition-colors">
                                    {{ $ticket->subject }}
                                </a>
                                @if($ticket->latestCustomerMessage)
                                    <p class="text-slate-400 truncate max-w-xs mt-0.5">
                                        {{ $ticket->latestCustomerMessage->is_admin_reply ? 'Admin: ' : 'Anda: ' }} {{ $ticket->latestCustomerMessage->message }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-slate-600 font-mono-code">
                                {{ $ticket->vpsInstance ? ($ticket->vpsInstance->hostname ?? $ticket->vpsInstance->public_ip) : 'Umum' }}
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="px-2 py-0.5 rounded text-[11px] font-semibold uppercase border border-slate-300 bg-slate-50 text-slate-900">
                                    {{ $ticket->priority }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="px-2.5 py-0.5 rounded text-[11px] font-semibold uppercase border border-slate-300 bg-slate-50 text-slate-900">
                                    {{ str_replace('_', ' ', $ticket->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-slate-500">
                                {{ $ticket->updated_at->diffForHumans() }}
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <a href="{{ route('dashboard.support.show', $ticket->id) }}" class="text-slate-900 font-bold hover:underline">
                                    Buka &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-slate-400">
                                Belum ada tiket bantuan. Klik tombol "Buat Tiket Baru" untuk mengajukan pertanyaan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($tickets->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $tickets->links() }}
            </div>
        @endif
    </div>

    <!-- Create Ticket Modal -->
    <div x-show="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" style="display: none;">
        <div class="bg-white rounded-lg border border-slate-200 p-6 max-w-md w-full" @click.away="showCreateModal = false">
            <h3 class="text-base font-bold text-slate-900 mb-1">Buat Tiket Bantuan Baru</h3>
            <p class="text-xs text-slate-500 mb-4">Jelaskan pertanyaan atau kendala Anda secara singkat dan jelas.</p>

            <form action="{{ route('dashboard.support.store') }}" method="POST" class="space-y-3.5 text-xs">
                @csrf

                <div>
                    <label class="block font-medium text-slate-700 mb-1">Subjek Kendala *</label>
                    <input type="text" name="subject" required placeholder="Contoh: Bantuan setting domain SSL di Coolify" 
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Terkait Server</label>
                        <select name="vps_instance_id" class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none bg-white">
                            <option value="">-- Umum --</option>
                            @foreach($vpsList as $instance)
                                <option value="{{ $instance->id }}">{{ $instance->hostname ?? 'VPS-'.$instance->id }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Prioritas *</label>
                        <select name="priority" required class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none bg-white">
                            <option value="low">Rendah</option>
                            <option value="medium" selected>Sedang</option>
                            <option value="high">Tinggi</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block font-medium text-slate-700 mb-1">Pesan / Deskripsi *</label>
                    <textarea name="message" rows="4" required placeholder="Tuliskan kendala Anda secara detail..."
                              class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none"></textarea>
                </div>

                <div class="pt-3 flex items-center justify-end gap-2">
                    <button type="button" @click="showCreateModal = false" class="px-4 py-2 rounded-lg text-slate-700 hover:bg-slate-100 font-medium">
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white font-bold">
                        Kirim Tiket
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
