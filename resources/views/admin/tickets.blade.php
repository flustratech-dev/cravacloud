@extends('layouts.admin', ['title' => 'Tiket Bantuan Pelanggan', 'headerTitle' => 'Helpdesk & Tiket Dukungan', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6">
    @include('admin.partials.helpdesk-tabs')

    @if($openServiceRequestCount > 0)
        <div class="p-4 bg-white border-2 border-electric text-ink flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
            <div>
                <strong class="font-display font-bold text-sm block text-ink">{{ $openServiceRequestCount }} permintaan server menunggu dikerjakan</strong>
                <span class="text-muted">Reinstall OS atau server tidak bisa diakses. Dijanjikan ke pelanggan maksimal {{ \App\Models\SupportTicket::SERVICE_REQUEST_SLA_WORKING_HOURS }} jam kerja.</span>
            </div>
            <a href="{{ route('admin.tickets', ['status' => 'active', 'type' => 'service']) }}" class="px-3.5 py-2 bg-electric hover:bg-electricHover text-white font-mono text-[11px] font-bold uppercase tracking-wider whitespace-nowrap self-start sm:self-auto transition-colors">
                Lihat Permintaan
            </a>
        </div>
    @endif

    <!-- Filter Bar -->
    <div class="bg-white border border-hairline p-4 flex flex-col sm:flex-row sm:items-end justify-between gap-3">
        <form method="GET" action="{{ route('admin.tickets') }}" class="flex flex-wrap items-end gap-3 text-xs">
            <div>
                <label class="block font-mono text-[10px] font-bold uppercase tracking-widest text-muted mb-1">Status Tiket</label>
                <select name="status" onchange="this.form.submit()" class="px-3 py-2 border border-hairline font-mono text-xs bg-white text-ink focus:outline-none focus:border-electric">
                    <option value="">Semua Status</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif (Open + In Progress)</option>
                    <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Open (Belum Dijawab)</option>
                    <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                    <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                    <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
                </select>
            </div>
            <div>
                <label class="block font-mono text-[10px] font-bold uppercase tracking-widest text-muted mb-1">Jenis</label>
                <select name="type" onchange="this.form.submit()" class="px-3 py-2 border border-hairline font-mono text-xs bg-white text-ink focus:outline-none focus:border-electric">
                    <option value="">Semua Jenis</option>
                    <option value="service" {{ request('type') === 'service' ? 'selected' : '' }}>Semua Permintaan Server</option>
                    @foreach(\App\Models\SupportTicket::typeLabels() as $typeKey => $typeName)
                        <option value="{{ $typeKey }}" {{ request('type') === $typeKey ? 'selected' : '' }}>{{ $typeName }}</option>
                    @endforeach
                </select>
            </div>
            @if(request('status') || request('type'))
                <div>
                    <a href="{{ route('admin.tickets') }}" class="inline-block px-3 py-2 border border-hairline hover:border-ink text-ink font-mono text-xs">Reset</a>
                </div>
            @endif
        </form>

        <span class="font-mono text-xs font-bold text-ink bg-creamDark border border-hairline px-3 py-1.5 self-start sm:self-end">
            Total: {{ $tickets->total() }} Tiket
        </span>
    </div>

    <!-- Tickets Table -->
    <div class="bg-white border border-hairline overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-creamDark/60 text-muted font-mono text-[10px] font-bold uppercase tracking-widest border-b border-hairline">
                    <tr>
                        <th class="px-5 py-3">ID Tiket</th>
                        <th class="px-5 py-3">Pelanggan</th>
                        <th class="px-5 py-3">Subjek Kendala</th>
                        <th class="px-5 py-3">Terkait VPS</th>
                        <th class="px-5 py-3">Prioritas</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Pembaruan</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @forelse($tickets as $ticket)
                        <tr class="hover:bg-creamDark/30 transition-colors">
                            <td class="px-5 py-3.5 font-mono font-bold text-ink">
                                #TK-{{ str_pad($ticket->id, 4, '0', STR_PAD_LEFT) }}
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="font-semibold text-ink block">{{ $ticket->customer->full_name }}</span>
                                <span class="text-muted font-mono text-[11px]">&#64;{{ $ticket->customer->username }}</span>
                            </td>
                            <td class="px-5 py-3.5">
                                @if($ticket->isServiceRequest())
                                    <span class="inline-block mb-1 px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase {{ $ticket->type === 'reinstall' ? 'bg-electric text-white' : 'bg-ink text-cream' }}">
                                        {{ $ticket->type === 'reinstall' ? 'Reinstall' : 'Server Down' }}
                                    </span>
                                @endif
                                <a href="{{ route('admin.tickets.show', $ticket->id) }}" class="font-bold text-ink hover:text-electric transition-colors block">
                                    {{ $ticket->subject }}
                                </a>
                                @if($ticket->latestMessage)
                                    <p class="text-muted truncate max-w-xs mt-0.5">
                                        {{ $ticket->latestMessage->is_admin_reply ? 'Admin: ' : 'User: ' }} {{ $ticket->latestMessage->message }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 font-mono text-ink">
                                {{ $ticket->vpsInstance ? ($ticket->vpsInstance->hostname ?? $ticket->vpsInstance->public_ip) : 'Umum' }}
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase border border-hairline bg-creamDark/60 text-ink">
                                    {{ $ticket->priority }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase border border-hairline bg-white text-ink">
                                    {{ str_replace('_', ' ', $ticket->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-muted font-mono text-[11px]">
                                {{ $ticket->updated_at->diffForHumans() }}
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <a href="{{ route('admin.tickets.show', $ticket->id) }}" class="px-3 py-1.5 bg-ink hover:bg-electric text-white font-mono text-[11px] font-bold uppercase transition-colors inline-block">
                                    Jawab
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center font-mono text-xs text-muted">
                                Tidak ada tiket bantuan yang ditemukan.
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
</div>
@endsection