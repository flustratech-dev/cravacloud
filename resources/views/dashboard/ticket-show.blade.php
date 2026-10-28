@extends('layouts.dashboard', ['title' => 'Tiket #' . $ticket->id, 'headerTitle' => 'Diskusi Tiket Support', 'backUrl' => route('dashboard.support'), 'backLabel' => 'Kembali ke Daftar Tiket'])

@section('content')
<div class="space-y-6 max-w-3xl">
    <div>
        <a href="{{ route('dashboard.support') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 transition-colors py-1 group">
            <svg class="w-4 h-4 text-slate-400 group-hover:text-slate-700 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>Kembali ke Daftar Tiket</span>
        </a>
    </div>

    <!-- Ticket Summary Card (No line under title) -->
    <div class="bg-white rounded-lg border border-slate-200 p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2">
            <div>
                <span class="font-mono-code text-xs text-slate-400 font-semibold">#TK-{{ str_pad($ticket->id, 4, '0', STR_PAD_LEFT) }}</span>
                <h1 class="text-base font-bold text-slate-900 mt-0.5">{{ $ticket->subject }}</h1>
            </div>
            <div class="flex items-center gap-3">
                <span class="px-2.5 py-1 rounded text-xs font-semibold uppercase border border-slate-300 bg-slate-50 text-slate-900">
                    {{ str_replace('_', ' ', $ticket->status) }}
                </span>
                <span class="text-xs text-slate-400">{{ $ticket->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB</span>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4 pt-3 text-xs text-slate-600 border-t border-slate-100">
            <div>
                <span class="text-slate-400 block text-[11px]">Pelapor:</span>
                <span class="font-medium text-slate-900">{{ $ticket->customer->full_name }}</span>
            </div>
            <div>
                <span class="text-slate-400 block text-[11px]">Terkait VPS:</span>
                <span class="font-medium text-slate-900">{{ $ticket->vpsInstance ? ($ticket->vpsInstance->hostname ?? $ticket->vpsInstance->public_ip) : 'Umum' }}</span>
            </div>
            <div>
                <span class="text-slate-400 block text-[11px]">Prioritas:</span>
                <span class="font-semibold uppercase text-slate-900">{{ $ticket->priority }}</span>
            </div>
        </div>
    </div>

    <!-- Messages Timeline -->
    <div class="space-y-3">
        @foreach($ticket->customerMessages as $msg)
            <div class="rounded-lg p-4 border {{ $msg->is_admin_reply ? 'bg-slate-100 border-slate-300' : 'bg-white border-slate-200' }}">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-full flex items-center justify-center font-bold text-xs {{ $msg->is_admin_reply ? 'bg-black text-white' : 'bg-slate-300 text-slate-900' }}">
                            {{ strtoupper(substr($msg->user->full_name, 0, 1)) }}
                        </div>
                        <span class="font-semibold text-xs text-slate-900">{{ $msg->user->full_name }}</span>
                        @if($msg->is_admin_reply)
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-black text-white">
                                Tim Support
                            </span>
                        @endif
                    </div>
                    <span class="text-xs text-slate-500">
                        {{ $msg->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB
                    </span>
                </div>
                <div class="text-xs text-slate-800 leading-relaxed whitespace-pre-line pl-8">
                    {{ $msg->message }}
                </div>
            </div>
        @endforeach
    </div>

    <!-- Reply Box -->
    <div class="bg-white rounded-lg border border-slate-200 p-5">
        <h3 class="font-semibold text-slate-900 text-sm mb-3">Kirim Balasan</h3>
        <form action="{{ route('dashboard.support.reply', $ticket->id) }}" method="POST" class="space-y-3">
            @csrf
            <textarea name="message" rows="3" required placeholder="Tuliskan pesan balasan Anda..."
                      class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs focus:outline-none"></textarea>
            
            <div class="flex justify-end">
                <button type="submit" class="px-5 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white font-bold text-xs transition-colors">
                    Kirim Balasan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
