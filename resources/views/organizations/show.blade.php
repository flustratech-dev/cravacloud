@extends('layouts.dashboard', ['title' => $org->name, 'headerTitle' => $org->name])

@section('content')
<div class="max-w-6xl space-y-6">
    <div>
        <a href="{{ route('organizations.index') }}" class="font-mono text-xs uppercase tracking-wider text-muted hover:text-electric transition-colors inline-flex items-center gap-1.5">
            <span>←</span>
            <span>Kembali ke Organisasi</span>
        </a>
    </div>

    <div class="bg-white border border-ink p-5 sm:p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="font-display text-xl font-bold text-ink">{{ $org->name }}</h2>
                <span class="px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-creamDark border border-hairline text-ink">{{ ucfirst($org->type) }}</span>
            </div>
            <p class="font-mono text-xs text-muted mt-1">{{ $org->billing_email ?: 'Billing email belum diatur' }}</p>
        </div>
        @if(auth()->user()->current_organization_id !== $org->id)
            <form method="POST" action="{{ route('organizations.switch', $org->id) }}">
                @csrf
                <button type="submit" class="rounded-none border border-ink bg-ink hover:bg-electric hover:border-electric text-white px-5 py-2.5 font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                    Jadikan aktif
                </button>
            </form>
        @else
            <span class="px-3 py-1.5 font-mono text-xs font-bold uppercase bg-emerald-100 text-emerald-900 border border-emerald-400 self-start md:self-auto">
                Workspace Aktif
            </span>
        @endif
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <section class="lg:col-span-2 bg-white border border-hairline p-6">
            <div class="border-b border-hairline pb-4">
                <h3 class="font-display font-bold text-base text-ink">Anggota</h3>
                <p class="text-xs text-muted mt-0.5">Kelola role dan akses tim dalam organisasi ini.</p>
            </div>
            <div class="divide-y divide-hairline">
                @foreach($org->members as $member)
                    <div class="py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-bold text-ink">{{ $member->user->full_name }} <span class="font-mono text-xs text-muted font-normal">· {{ $member->user->email }}</span></p>
                            <p class="font-mono text-[11px] text-muted mt-0.5 uppercase">{{ $member->role?->name ?: 'Role belum tersedia' }}</p>
                        </div>
                        @if($member->user_id !== $org->owner_id)
                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('organizations.members.role', [$org->id, $member->id]) }}" class="flex items-center gap-1.5">
                                    @csrf
                                    <select name="role_slug" class="rounded-none border border-hairline bg-white px-2.5 py-1.5 font-mono text-xs text-ink focus:outline-none focus:border-electric">
                                        <option value="admin">Admin</option>
                                        <option value="billing">Billing</option>
                                        <option value="operator">Operator</option>
                                        <option value="viewer">Viewer</option>
                                    </select>
                                    <button type="submit" class="px-2.5 py-1.5 border border-ink hover:bg-creamDark font-mono text-[11px] font-bold uppercase text-ink cursor-pointer">Simpan</button>
                                </form>
                                <form method="POST" action="{{ route('organizations.members.remove', [$org->id, $member->id]) }}" data-confirm="Hapus anggota ini dari organisasi?">
                                    @csrf
                                    <button type="submit" class="px-2.5 py-1.5 border border-red-400 hover:bg-red-50 font-mono text-[11px] font-bold uppercase text-red-700 cursor-pointer">Hapus</button>
                                </form>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        <section class="bg-white border border-hairline p-6">
            <h3 class="font-display font-bold text-base text-ink border-b border-hairline pb-3">Undang anggota</h3>
            <form method="POST" action="{{ route('organizations.invite', $org->id) }}" class="mt-4 space-y-3 text-xs">
                @csrf
                <input name="email" type="email" required placeholder="Email anggota"
                       class="w-full rounded-none border border-hairline bg-white px-3.5 py-2.5 text-base sm:text-sm font-mono focus:outline-none focus:border-electric">
                <select name="role_slug"
                        class="w-full rounded-none border border-hairline bg-white px-3.5 py-2.5 text-base sm:text-sm font-mono focus:outline-none focus:border-electric">
                    <option value="viewer">Viewer</option>
                    <option value="operator">Operator</option>
                    <option value="billing">Billing</option>
                    <option value="admin">Admin</option>
                </select>
                <button type="submit" class="w-full rounded-none bg-electric hover:bg-electricHover px-4 py-3 font-mono text-xs font-bold uppercase tracking-wider text-white transition-colors cursor-pointer">
                    Kirim undangan
                </button>
            </form>

            <div class="mt-6 border-t border-hairline pt-5 font-mono text-xs space-y-1.5">
                <h4 class="font-bold uppercase text-ink">Quota Organisasi</h4>
                <p class="text-muted">Members: <strong class="text-ink">{{ $org->members->count() }} / {{ $org->quota?->max_members ?: 'Unlimited' }}</strong></p>
                <p class="text-muted">API keys: <strong class="text-ink">{{ $org->quota?->max_api_keys ?: 'Unlimited' }}</strong></p>
            </div>
        </section>
    </div>

    <section class="bg-white border border-hairline p-6">
        <h3 class="font-display font-bold text-base text-ink border-b border-hairline pb-3">Billing organization</h3>
        <form method="POST" action="{{ route('organizations.billing', $org->id) }}" class="mt-4 grid md:grid-cols-2 gap-4 text-xs">
            @csrf
            <input name="billing_name" value="{{ $org->billing_name }}" placeholder="Nama billing"
                   class="rounded-none border border-hairline bg-white px-3.5 py-2.5 text-base sm:text-sm focus:outline-none focus:border-electric">
            <input name="billing_email" type="email" value="{{ $org->billing_email }}" placeholder="Email billing"
                   class="rounded-none border border-hairline bg-white px-3.5 py-2.5 text-base sm:text-sm font-mono focus:outline-none focus:border-electric">
            <input name="tax_id" value="{{ $org->tax_id }}" placeholder="NPWP"
                   class="rounded-none border border-hairline bg-white px-3.5 py-2.5 text-base sm:text-sm font-mono focus:outline-none focus:border-electric">
            <textarea name="billing_address" rows="2" placeholder="Alamat billing"
                      class="rounded-none border border-hairline bg-white px-3.5 py-2.5 text-base sm:text-sm focus:outline-none focus:border-electric">{{ $org->billing_address }}</textarea>
            <div class="md:col-span-2">
                <button type="submit" class="rounded-none bg-ink hover:bg-electric px-6 py-3 font-mono text-xs font-bold uppercase tracking-wider text-white transition-colors cursor-pointer">
                    Simpan billing
                </button>
            </div>
        </form>
    </section>

    @if($org->invitations->count())
        <section class="bg-white border border-hairline p-6">
            <h3 class="font-display font-bold text-base text-ink border-b border-hairline pb-3">Undangan pending</h3>
            <div class="mt-3 divide-y divide-hairline">
                @foreach($org->invitations->whereNull('accepted_at')->whereNull('revoked_at') as $inv)
                    <div class="py-3 flex justify-between items-center text-xs">
                        <span class="font-mono text-ink">{{ $inv->email }} · <strong class="uppercase">{{ $inv->role?->name }}</strong></span>
                        <form method="POST" action="{{ route('organizations.invitations.revoke', $inv->id) }}" data-confirm="Cabut undangan ini?">
                            @csrf
                            <button type="submit" class="font-mono text-xs font-bold uppercase text-red-700 hover:underline cursor-pointer">Cabut</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
