@extends('layouts.admin', ['title' => 'Tiket Bantuan Pelanggan', 'headerTitle' => 'Helpdesk & Tiket Dukungan', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6">
    @if($openServiceRequestCount > 0)
        <div class="p-4 rounded-lg bg-blue-50 border border-blue-200 text-blue-900 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
            <div>
                <strong class="font-bold text-sm block">{{ $openServiceRequestCount }} permintaan server menunggu dikerjakan</strong>
                Reinstall OS atau server tidak bisa diakses. Dijanjikan ke pelanggan maksimal {{ \App\Models\SupportTicket::SERVICE_REQUEST_SLA_WORKING_HOURS }} jam kerja.
            </div>
            <a href="{{ route('admin.tickets', ['status' => 'active', 'type' => 'service']) }}" class="px-3 py-1.5 rounded-lg bg-white border border-blue-300 hover:bg-blue-100 font-semibold whitespace-nowrap self-start sm:self-auto">
                Lihat Permintaan
            </a>
        </div>
    @endif

    <!-- Filter Bar -->
    <div class="bg-white rounded-lg border border-slate-200 p-4 flex items-center justify-between">
        <form method="GET" action="{{ route('admin.tickets') }}" class="flex items-center gap-3 text-xs">
            <div>
                <label class="block font-medium text-slate-500 mb-1">Status Tiket</label>
                <select name="status" onchange="this.form.submit()" class="px-3 py-1.5 rounded-lg border border-slate-300 font-medium focus:outline-none bg-white">
                    <option value="">Semua Status</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif (Open + In Progress)</option>
                    <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Open (Belum Dijawab)</option>
                    <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                    <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                    <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
                </select>
            </div>
            <div>
                <label class="block font-medium text-slate-500 mb-1">Jenis</label>
                <select name="type" onchange="this.form.submit()" class="px-3 py-1.5 rounded-lg border border-slate-300 font-medium focus:outline-none bg-white">
                    <option value="">Semua Jenis</option>
                    <option value="service" {{ request('type') === 'service' ? 'selected' : '' }}>Semua Permintaan Server</option>
                    @foreach(\App\Models\SupportTicket::typeLabels() as $typeKey => $typeName)
                        <option value="{{ $typeKey }}" {{ request('type') === $typeKey ? 'selected' : '' }}>{{ $typeName }}</option>
                    @endforeach
                </select>
            </div>
            @if(request('status') || request('type'))
                <div class="pt-5">
                    <a href="{{ route('admin.tickets') }}" class="text-slate-600 font-semibold hover:underline">Reset</a>
                </div>
            @endif
        </form>

        <span class="text-xs font-mono-code font-bold text-slate-900 bg-slate-100 px-3 py-1.5 rounded">
            Total: {{ $tickets->total() }} Tiket
        </span>
    </div>

    <!-- Tickets Table (No lines under headings) -->
    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider">
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
                <tbody class="divide-y divide-slate-100">
                    @forelse($tickets as $ticket)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-5 py-3.5 font-mono-code font-bold text-slate-700">
                                #TK-{{ str_pad($ticket->id, 4, '0', STR_PAD_LEFT) }}
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="font-semibold text-slate-900 block">{{ $ticket->customer->full_name }}</span>
                                <span class="text-slate-400 font-mono-code text-[11px]">&#64;{{ $ticket->customer->username }}</span>
                            </td>
                            <td class="px-5 py-3.5">
                                @if($ticket->isServiceRequest())
                                    <span class="inline-block mb-1 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase {{ $ticket->type === 'reinstall' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $ticket->type === 'reinstall' ? 'Reinstall' : 'Server Down' }}
                                    </span>
                                @endif
                                <a href="{{ route('admin.tickets.show', $ticket->id) }}" class="font-bold text-slate-900 hover:underline transition-colors block">
                                    {{ $ticket->subject }}
                                </a>
                                @if($ticket->latestMessage)
                                    <p class="text-slate-400 truncate max-w-xs mt-0.5">
                                        {{ $ticket->latestMessage->is_admin_reply ? 'Admin: ' : 'User: ' }} {{ $ticket->latestMessage->message }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 font-mono-code text-slate-600">
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
                                <a href="{{ route('admin.tickets.show', $ticket->id) }}" class="px-3 py-1.5 rounded bg-black hover:bg-neutral-800 text-white font-bold text-xs transition-colors">
                                    Jawab
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center text-slate-400">
                                Tidak ada tiket bantuan yang ditemukan.
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
</div>
@endsection
