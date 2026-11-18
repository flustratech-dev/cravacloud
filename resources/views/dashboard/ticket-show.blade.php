@extends('layouts.dashboard', ['title' => 'Tiket #' . $ticket->id, 'headerTitle' => 'Diskusi Tiket Support'])

@section('content')
<div class="space-y-6 max-w-3xl">
    <div>
        <a href="{{ route('dashboard.support') }}" class="font-mono text-xs uppercase tracking-wider text-muted hover:text-electric transition-colors inline-flex items-center gap-1.5">
            <span>←</span>
            <span>Kembali ke Daftar Tiket</span>
        </a>
    </div>

    <!-- Ticket Header Card -->
    <div class="bg-white border border-ink p-5 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-4 border-b border-hairline">
            <div>
                <span class="font-mono text-xs text-electric font-bold">#TK-{{ str_pad($ticket->id, 4, '0', STR_PAD_LEFT) }}</span>
                <h1 class="font-display text-lg font-bold text-ink mt-0.5">{{ $ticket->subject }}</h1>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <span class="px-2.5 py-1 font-mono text-[11px] font-bold uppercase border border-ink bg-creamDark text-ink">
                    {{ str_replace('_', ' ', $ticket->status) }}
                </span>
                <span class="font-mono text-xs text-muted">{{ $ticket->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB</span>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4 pt-4 text-xs">
            <div>
                <span class="font-mono text-[10px] uppercase text-muted block">Pelapor</span>
                <span class="font-bold text-ink">{{ $ticket->customer->full_name }}</span>
            </div>
            <div>
                <span class="font-mono text-[10px] uppercase text-muted block">Terkait VPS</span>
                <span class="font-mono font-bold text-ink">{{ $ticket->vpsInstance ? ($ticket->vpsInstance->hostname ?? $ticket->vpsInstance->public_ip) : 'Umum' }}</span>
            </div>
            <div>
                <span class="font-mono text-[10px] uppercase text-muted block">Prioritas</span>
                <span class="font-mono font-bold uppercase text-ink">{{ $ticket->priority }}</span>
            </div>
        </div>
    </div>

    <!-- Messages Timeline -->
    <div class="space-y-3">
        @foreach($ticket->customerMessages as $msg)
            <div class="p-4 sm:p-5 border {{ $msg->is_admin_reply ? 'bg-creamDark/60 border-ink' : 'bg-white border-hairline' }}">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-6 h-6 flex items-center justify-center font-mono font-bold text-[11px] {{ $msg->is_admin_reply ? 'bg-electric text-white' : 'bg-ink text-cream' }}">
                            {{ strtoupper(substr($msg->user->full_name, 0, 1)) }}
                        </div>
                        <span class="font-display font-bold text-xs text-ink">{{ $msg->user->full_name }}</span>
                        @if($msg->is_admin_reply)
                            <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase bg-electric text-white">Tim Support</span>
                        @endif
                    </div>
                    <span class="font-mono text-[11px] text-muted">
                        {{ $msg->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB
                    </span>
                </div>
                <div class="text-xs text-ink leading-relaxed whitespace-pre-line pl-8">
                    {{ $msg->message }}
                </div>
            </div>
        @endforeach
    </div>

    <!-- Reply Form -->
    <div class="bg-white border border-hairline p-5 sm:p-6">
        <h3 class="font-mono text-xs font-bold uppercase tracking-wider text-ink mb-3">Kirim Balasan</h3>
        <form action="{{ route('dashboard.support.reply', $ticket->id) }}" method="POST" class="space-y-3">
            @csrf
            <textarea name="message" rows="3" required placeholder="Tuliskan pesan balasan Anda..."
                      class="w-full rounded-none px-3.5 py-2.5 text-base sm:text-sm border border-hairline bg-white text-ink focus:outline-none focus:border-electric"></textarea>

            <div class="flex justify-end">
                <button type="submit" class="rounded-none px-6 py-2.5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                    Kirim Balasan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
