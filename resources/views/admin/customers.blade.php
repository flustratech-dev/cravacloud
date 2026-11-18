@extends('layouts.admin', ['title' => 'Daftar Pelanggan', 'headerTitle' => 'Manajemen Pelanggan', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6">
    <!-- Filter and Search -->
    <div class="bg-white border border-hairline p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <form method="GET" action="{{ route('admin.customers') }}" class="flex flex-wrap items-center gap-2.5 text-xs w-full sm:w-auto">
            <div class="w-full sm:w-64">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama, email, username..."
                       class="w-full px-3 py-2 border border-hairline bg-white text-ink focus:outline-none focus:border-electric">
            </div>

            <div>
                <select name="channel" onchange="this.form.submit()" class="px-3 py-2 border border-hairline font-mono text-xs bg-white text-ink focus:outline-none focus:border-electric">
                    <option value="">Semua Jalur</option>
                    <option value="website" {{ request('channel') === 'website' ? 'selected' : '' }}>Website</option>
                    <option value="shopee" {{ request('channel') === 'shopee' ? 'selected' : '' }}>Shopee</option>
                </select>
            </div>

            <button type="submit" class="px-4 py-2 bg-ink hover:bg-electric text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                Cari
            </button>

            @if(request('search') || request('channel'))
                <a href="{{ route('admin.customers') }}" class="px-3 py-2 border border-hairline hover:border-ink text-ink font-mono text-xs">Reset</a>
            @endif
        </form>

        <span class="font-mono text-xs font-bold text-ink bg-creamDark border border-hairline px-3 py-1.5">
            Total: {{ $customers->total() }} Pelanggan
        </span>
    </div>

    <!-- Customers Table -->
    <div class="bg-white border border-hairline overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-creamDark/60 text-muted font-mono text-[10px] font-bold uppercase tracking-widest border-b border-hairline">
                    <tr>
                        <th class="px-5 py-3">Pelanggan</th>
                        <th class="px-5 py-3">Username</th>
                        <th class="px-5 py-3">Channel</th>
                        <th class="px-5 py-3">Kontak &amp; Lokasi</th>
                        <th class="px-5 py-3">VPS Aktif</th>
                        <th class="px-5 py-3">Total Order</th>
                        <th class="px-5 py-3">Terdaftar</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @forelse($customers as $customer)
                        <tr class="hover:bg-creamDark/30 transition-colors">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 bg-ink text-cream font-mono flex items-center justify-center font-bold text-xs shrink-0">
                                        {{ strtoupper(substr($customer->full_name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <span class="font-semibold text-ink block">{{ $customer->full_name }}</span>
                                        <span class="text-muted font-mono text-[11px]">{{ $customer->email }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 font-mono text-ink">
                                &#64;{{ $customer->username }}
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase border border-hairline bg-creamDark/60 text-ink">
                                    {{ ucfirst($customer->channel) }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-ink">
                                <span>{{ $customer->phone ?? '-' }}</span>
                                <span class="text-muted text-[11px] block">{{ $customer->address ?? ($customer->company ?? '-') }}</span>
                            </td>
                            <td class="px-5 py-3.5 font-mono font-bold text-ink">
                                {{ $customer->vps_instances_count }} VPS
                            </td>
                            <td class="px-5 py-3.5 font-mono font-semibold text-ink">
                                {{ $customer->orders_count }} Order
                            </td>
                            <td class="px-5 py-3.5 text-muted font-mono">
                                {{ $customer->created_at->timezone('Asia/Jakarta')->format('d/m/Y') }}
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                @unless($customer->is_admin)
                                    {{-- PHASE 7 - melihat panel persis seperti pelanggan, hanya baca. --}}
                                    <form action="{{ route('admin.impersonate.start', $customer->id) }}" method="POST" class="inline"
                                          onsubmit="return confirm('Masuk sebagai {{ addslashes($customer->full_name) }}? Mode ini hanya untuk melihat; semua aksi yang mengubah data akan diblokir.');">
                                        @csrf
                                        <button type="submit"
                                                class="px-2.5 py-1 font-mono text-[10px] font-bold uppercase bg-white hover:bg-electric hover:text-white hover:border-electric text-ink border border-ink transition-colors whitespace-nowrap cursor-pointer">
                                            Masuk sebagai
                                        </button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center font-mono text-xs text-muted">
                                Tidak ada data pelanggan yang cocok dengan pencarian.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($customers->hasPages())
            <div class="p-4 border-t border-hairline">
                {{ $customers->links() }}
            </div>
        @endif
    </div>
</div>
@endsection