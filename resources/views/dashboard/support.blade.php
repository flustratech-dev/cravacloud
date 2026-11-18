@extends('layouts.dashboard', ['title' => 'Pusat Bantuan & Support', 'headerTitle' => 'Pusat Bantuan & Support'])

@section('content')
<div class="space-y-6" x-data="{ showCreateModal: false }">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-hairline pb-4">
        <div>
            <h2 class="font-display text-lg font-bold text-ink">Tiket Dukungan Teknis</h2>
            <p class="text-xs text-muted mt-0.5">Bantuan konfigurasi server, OS, reverse proxy, dan penagihan.</p>
        </div>
        <button type="button" @click="showCreateModal = true"
                class="rounded-none px-5 py-2.5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors inline-flex items-center gap-2 self-start sm:self-auto cursor-pointer">
            <span>+ Buat Tiket Baru</span>
        </button>
    </div>

    <!-- Tickets Table -->
    <div class="bg-white border border-hairline overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-creamDark border-b border-hairline font-mono text-ink font-bold uppercase tracking-wider">
                    <tr>
                        <th class="px-5 py-3.5">ID</th>
                        <th class="px-5 py-3.5">Subjek Kendala</th>
                        <th class="px-5 py-3.5">Terkait VPS</th>
                        <th class="px-5 py-3.5">Prioritas</th>
                        <th class="px-5 py-3.5">Status</th>
                        <th class="px-5 py-3.5">Pembaruan</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @forelse($tickets as $ticket)
                        <tr class="hover:bg-creamDark/30 transition-colors">
                            <td class="px-5 py-3.5 font-mono font-bold text-ink whitespace-nowrap">
                                #TK-{{ str_pad($ticket->id, 4, '0', STR_PAD_LEFT) }}
                            </td>
                            <td class="px-5 py-3.5">
                                <a href="{{ route('dashboard.support.show', $ticket->id) }}" class="font-display font-bold text-ink hover:text-electric hover:underline">
                                    {{ $ticket->subject }}
                                </a>
                                @if($ticket->latestCustomerMessage)
                                    <p class="text-muted truncate max-w-xs mt-0.5">
                                        {{ $ticket->latestCustomerMessage->is_admin_reply ? 'Admin: ' : 'Anda: ' }}{{ $ticket->latestCustomerMessage->message }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-ink font-mono whitespace-nowrap">
                                {{ $ticket->vpsInstance ? ($ticket->vpsInstance->hostname ?? $ticket->vpsInstance->public_ip) : 'Umum' }}
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase border border-hairline bg-creamDark text-ink">
                                    {{ $ticket->priority }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase border border-hairline bg-white text-ink">
                                    {{ str_replace('_', ' ', $ticket->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 font-mono text-muted whitespace-nowrap">
                                {{ $ticket->updated_at->diffForHumans() }}
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                <a href="{{ route('dashboard.support.show', $ticket->id) }}" class="font-mono text-xs font-bold uppercase text-electric hover:underline">
                                    Buka →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center font-mono text-xs text-muted">
                                Belum ada tiket bantuan. Klik tombol "+ Buat Tiket Baru" untuk mengajukan pertanyaan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($tickets->hasPages())
            <div class="p-4 border-t border-hairline">
                {{ $tickets->links() }}
            </div>
        @endif
    </div>

    <!-- Create Ticket Modal -->
    <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-ink/60" style="display: none;">
        <div class="bg-white border-2 border-ink p-6 max-w-md w-full" @click.away="showCreateModal = false">
            <h3 class="font-display text-lg font-bold text-ink mb-1">Buat Tiket Bantuan Baru</h3>
            <p class="text-xs text-muted mb-5">Jelaskan pertanyaan atau kendala teknis Anda secara ringkas dan jelas.</p>

            <form action="{{ route('dashboard.support.store') }}" method="POST" class="space-y-4 text-xs">
                @csrf

                <div>
                    <label class="font-mono text-[11px] font-bold uppercase text-ink block mb-1">Subjek Kendala *</label>
                    <input type="text" name="subject" required placeholder="Contoh: Bantuan setting domain SSL di Coolify"
                           class="w-full rounded-none px-3.5 py-2.5 text-base sm:text-sm border border-hairline bg-white text-ink focus:outline-none focus:border-electric">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-mono text-[11px] font-bold uppercase text-ink block mb-1">Terkait Server</label>
                        <select name="vps_instance_id" class="w-full rounded-none px-3 py-2.5 text-base sm:text-sm border border-hairline bg-white text-ink font-mono focus:outline-none focus:border-electric">
                            <option value="">-- Umum --</option>
                            @foreach($vpsList as $instance)
                                <option value="{{ $instance->id }}">{{ $instance->hostname ?? 'VPS-'.$instance->id }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="font-mono text-[11px] font-bold uppercase text-ink block mb-1">Prioritas *</label>
                        <select name="priority" required class="w-full rounded-none px-3 py-2.5 text-base sm:text-sm border border-hairline bg-white text-ink font-mono focus:outline-none focus:border-electric">
                            <option value="low">Rendah</option>
                            <option value="medium" selected>Sedang</option>
                            <option value="high">Tinggi</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="font-mono text-[11px] font-bold uppercase text-ink block mb-1">Pesan / Deskripsi *</label>
                    <textarea name="message" rows="4" required placeholder="Tuliskan kendala Anda secara detail..."
                              class="w-full rounded-none px-3.5 py-2.5 text-base sm:text-sm border border-hairline bg-white text-ink focus:outline-none focus:border-electric"></textarea>
                </div>

                <div class="pt-3 flex items-center justify-end gap-2">
                    <button type="button" @click="showCreateModal = false" class="rounded-none px-4 py-2.5 border border-ink text-ink hover:bg-creamDark font-mono text-xs font-bold uppercase cursor-pointer">
                        Batal
                    </button>
                    <button type="submit" class="rounded-none px-5 py-2.5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase cursor-pointer">
                        Kirim Tiket
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
