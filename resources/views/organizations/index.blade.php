@extends('layouts.dashboard', ['title' => 'Organisasi', 'headerTitle' => 'Organisasi & Workspace'])

@section('content')
<div class="max-w-5xl space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-hairline pb-4">
        <div>
            <h2 class="font-display text-xl font-bold text-ink">Organisasi Anda</h2>
            <p class="text-xs text-muted mt-1">Pisahkan resource server, anggota tim, dan billing antar workspace.</p>
        </div>
        <a href="{{ route('organizations.create') }}"
           class="rounded-none bg-electric hover:bg-electricHover transition-colors px-5 py-2.5 font-mono text-xs font-bold uppercase tracking-wider text-white self-start sm:self-auto">
            + Buat organisasi
        </a>
    </div>

    <div class="grid md:grid-cols-2 gap-4">
        @forelse($orgs as $org)
            <a href="{{ route('organizations.show', $org->id) }}"
               class="bg-white border {{ $currentOrgId === $org->id ? 'border-2 border-electric' : 'border-hairline hover:border-ink' }} p-5 transition-colors block">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-display font-bold text-ink text-base">{{ $org->name }}</p>
                        <p class="font-mono text-[11px] text-muted mt-1 uppercase">{{ $org->type }} · {{ $org->members->count() }} member</p>
                    </div>
                    @if($currentOrgId === $org->id)
                        <span class="px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-emerald-100 text-emerald-900 border border-emerald-400 shrink-0">
                            Aktif
                        </span>
                    @endif
                </div>
                <p class="font-mono text-xs text-muted mt-4 pt-3 border-t border-hairline">{{ $org->billing_email ?: 'Billing email belum diatur' }}</p>
            </a>
        @empty
            <div class="bg-white border border-hairline p-8 text-center font-mono text-xs text-muted md:col-span-2">
                Belum ada organisasi.
            </div>
        @endforelse
    </div>
</div>
@endsection
