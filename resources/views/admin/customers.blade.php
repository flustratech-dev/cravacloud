@extends('layouts.admin', ['title' => 'Daftar Pelanggan', 'headerTitle' => 'Manajemen Pelanggan', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6">
    <!-- Filter and Search -->
    <div class="bg-white rounded-lg border border-slate-200 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <form method="GET" action="{{ route('admin.customers') }}" class="flex flex-wrap items-center gap-3 text-xs w-full sm:w-auto">
            <div class="w-full sm:w-64">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama, email, username..."
                       class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none">
            </div>

            <div>
                <select name="channel" onchange="this.form.submit()" class="px-3 py-2 rounded-lg border border-slate-300 font-medium focus:outline-none bg-white">
                    <option value="">Semua Jalur</option>
                    <option value="website" {{ request('channel') === 'website' ? 'selected' : '' }}>Website</option>
                    <option value="shopee" {{ request('channel') === 'shopee' ? 'selected' : '' }}>Shopee</option>
                </select>
            </div>

            <button type="submit" class="px-4 py-2 rounded-lg bg-black text-white font-bold hover:bg-neutral-800 transition-colors">
                Cari
            </button>

            @if(request('search') || request('channel'))
                <a href="{{ route('admin.customers') }}" class="text-slate-600 font-semibold hover:underline">Reset</a>
            @endif
        </form>

        <span class="text-xs font-mono-code font-bold text-slate-900 bg-slate-100 px-3 py-1.5 rounded">
            Total: {{ $customers->total() }} Pelanggan
        </span>
    </div>

    <!-- Customers Table (No lines under headings) -->
    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider">
                    <tr>
                        <th class="px-5 py-3">Pelanggan</th>
                        <th class="px-5 py-3">Username</th>
                        <th class="px-5 py-3">Channel</th>
                        <th class="px-5 py-3">Kontak & Lokasi</th>
                        <th class="px-5 py-3">VPS Aktif</th>
                        <th class="px-5 py-3">Total Order</th>
                        <th class="px-5 py-3">Terdaftar</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($customers as $customer)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-full bg-slate-200 text-slate-800 flex items-center justify-center font-bold text-xs">
                                        {{ strtoupper(substr($customer->full_name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <span class="font-semibold text-slate-900 block">{{ $customer->full_name }}</span>
                                        <span class="text-slate-400 font-mono-code text-[11px]">{{ $customer->email }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 font-mono-code text-slate-700">
                                &#64;{{ $customer->username }}
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="px-2 py-0.5 rounded text-[11px] font-semibold border border-slate-300 bg-slate-50 text-slate-900">
                                    {{ ucfirst($customer->channel) }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600">
                                <span>{{ $customer->phone ?? '-' }}</span>
                                <span class="text-slate-400 text-[11px] block">{{ $customer->address ?? ($customer->company ?? '-') }}</span>
                            </td>
                            <td class="px-5 py-3.5 font-mono-code font-bold text-slate-900">
                                {{ $customer->vps_instances_count }} VPS
                            </td>
                            <td class="px-5 py-3.5 font-mono-code font-semibold text-slate-800">
                                {{ $customer->orders_count }} Order
                            </td>
                            <td class="px-5 py-3.5 text-slate-500 font-mono-code">
                                {{ $customer->created_at->timezone('Asia/Jakarta')->format('d/m/Y') }}
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                @unless($customer->is_admin)
                                    {{-- PHASE 7 - melihat panel persis seperti pelanggan, hanya baca. --}}
                                    <form action="{{ route('admin.impersonate.start', $customer->id) }}" method="POST" class="inline"
                                          onsubmit="return confirm('Masuk sebagai {{ addslashes($customer->full_name) }}? Mode ini hanya untuk melihat; semua aksi yang mengubah data akan diblokir.');">
                                        @csrf
                                        <button type="submit"
                                                class="px-2.5 py-1 rounded text-[11px] font-semibold bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 whitespace-nowrap">
                                            Masuk sebagai
                                        </button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center text-slate-400">
                                Tidak ada data pelanggan yang cocok dengan pencarian.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($customers->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $customers->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
