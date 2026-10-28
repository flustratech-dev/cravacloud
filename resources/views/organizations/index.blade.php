@extends('layouts.dashboard', ['title' => 'Organisasi', 'headerTitle' => 'Organisasi & Workspace', 'backUrl' => route('dashboard.index'), 'backLabel' => 'Kembali ke Dashboard'])

@section('content')
<div class="max-w-5xl space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-slate-900">Organisasi Anda</h2>
            <p class="text-sm text-slate-500 mt-1">Pisahkan resource, anggota, dan billing antar workspace.</p>
        </div>
        <a href="{{ route('organizations.create') }}" class="rounded-lg bg-black hover:bg-neutral-800 transition-colors px-4 py-2.5 text-sm font-bold text-white">+ Buat organisasi</a>
    </div>

    <div class="grid md:grid-cols-2 gap-4">
        @forelse($orgs as $org)
            <a href="{{ route('organizations.show', $org->id) }}" class="bg-white border {{ $currentOrgId === $org->id ? 'border-black ring-1 ring-black' : 'border-slate-200' }} rounded-lg p-5 hover:border-slate-400 transition-all block">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-bold text-slate-900">{{ $org->name }}</p>
                        <p class="text-xs text-slate-500 mt-1">{{ ucfirst($org->type) }} · {{ $org->members->count() }} member</p>
                    </div>
                    @if($currentOrgId === $org->id)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 shrink-0">
                            Aktif
                        </span>
                    @endif
                </div>
                <p class="text-xs text-slate-500 mt-4">{{ $org->billing_email ?: 'Billing email belum diatur' }}</p>
            </a>
        @empty
            <div class="bg-white border border-slate-200 rounded-lg p-8 text-center text-sm text-slate-500 md:col-span-2">
                Belum ada organisasi.
            </div>
        @endforelse
    </div>
</div>
@endsection
