@extends('layouts.app', [
    'title' => $produk['judul'],
    'description' => $produk['deskripsi'],
    'breadcrumbs' => $remah,
    'faq' => $produk['tanya'],
])

@section('content')
<div class="min-h-screen bg-slate-50">

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16 space-y-14">

        <header class="space-y-4 max-w-3xl">
            <nav class="flex items-center gap-2 text-xs">
                <a href="{{ url('/') }}" class="font-semibold text-slate-500 hover:text-slate-900 transition-colors">Beranda</a>
                <span class="text-slate-300">/</span>
                <span class="font-semibold text-slate-900">{{ $produk['h1'] }}</span>
            </nav>

            <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
                {{ $produk['h1'] }}
            </h1>

            <p class="text-slate-600 text-sm sm:text-base leading-relaxed">
                {{ $produk['intro'] }}
            </p>
        </header>

        {{-- Tabel perbandingan. Sengaja berbentuk tabel, bukan kartu seperti di
             beranda: yang sampai ke halaman ini sudah tahu ia mau apa, dan yang
             dibutuhkannya adalah membandingkan angka berdampingan. --}}
        <section aria-labelledby="judul-paket" class="space-y-4">
            <h2 id="judul-paket" class="text-xl font-bold text-slate-900 tracking-tight">
                Paket &amp; Harga
            </h2>

            @if ($paket->isEmpty())
                <p class="text-sm text-slate-500">
                    Paket untuk kategori ini sedang tidak tersedia.
                    <a href="{{ route('contact') }}" class="text-[#4A6FA5] font-semibold hover:underline">Hubungi tim kami</a>
                    untuk penawaran.
                </p>
            @else
                <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                    <table class="w-full text-sm text-left">
                        <caption class="sr-only">Perbandingan paket {{ $produk['h1'] }} CravaCloud</caption>
                        <thead class="text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200 bg-slate-50">
                            <tr>
                                <th scope="col" class="px-5 py-3 font-bold">Paket</th>
                                <th scope="col" class="px-5 py-3 font-bold">vCPU</th>
                                <th scope="col" class="px-5 py-3 font-bold">RAM</th>
                                <th scope="col" class="px-5 py-3 font-bold">Penyimpanan</th>
                                <th scope="col" class="px-5 py-3 font-bold">Bandwidth</th>
                                <th scope="col" class="px-5 py-3 font-bold">Harga / bulan</th>
                                <th scope="col" class="px-5 py-3"><span class="sr-only">Pesan</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($paket as $spec)
                                <tr class="hover:bg-slate-50/70 transition-colors">
                                    <th scope="row" class="px-5 py-4 font-bold text-slate-900 align-top">
                                        {{ $spec->name }}
                                        @if ($spec->tagline)
                                            <span class="block font-normal text-[11px] text-slate-500 mt-0.5 max-w-[15rem]">{{ $spec->tagline }}</span>
                                        @endif
                                    </th>
                                    <td class="px-5 py-4 font-mono-code text-slate-700 align-top">{{ $spec->cpu }}</td>
                                    <td class="px-5 py-4 font-mono-code text-slate-700 align-top">{{ $spec->ram }} GB</td>
                                    <td class="px-5 py-4 font-mono-code text-slate-700 align-top">{{ $spec->disk }} GB NVMe</td>
                                    <td class="px-5 py-4 font-mono-code text-slate-700 align-top">{{ $spec->bandwidth }} GB</td>
                                    <td class="px-5 py-4 font-bold text-slate-900 align-top whitespace-nowrap">
                                        Rp {{ number_format($spec->sell_price, 0, ',', '.') }}
                                    </td>
                                    <td class="px-5 py-4 align-top">
                                        @if($spec->is_active)
                                            <a href="{{ route('checkout', $spec->id) }}"
                                               class="inline-flex items-center whitespace-nowrap px-3.5 py-2 rounded-lg bg-slate-900 text-white text-xs font-bold hover:bg-black transition-colors">
                                                Pesan
                                            </a>
                                        @else
                                            <span class="inline-flex items-center whitespace-nowrap px-3 py-1.5 rounded-lg bg-slate-100 text-slate-500 text-xs font-semibold border border-slate-200 cursor-not-allowed" title="Stok sedang habis atau tidak tersedia">
                                                Stok Habis
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="text-[11px] text-slate-500">
                    Harga belum termasuk pajak bila berlaku. Lihat
                    <a href="{{ route('terms') }}" class="text-[#4A6FA5] font-semibold hover:underline">Ketentuan Layanan</a>
                    dan
                    <a href="{{ route('refund') }}" class="text-[#4A6FA5] font-semibold hover:underline">Kebijakan Pengembalian Dana</a>.
                </p>
            @endif
        </section>

        <section aria-labelledby="judul-sorotan" class="space-y-4">
            <h2 id="judul-sorotan" class="text-xl font-bold text-slate-900 tracking-tight">
                Yang Anda dapat
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach ($produk['sorotan'] as $sorotan)
                    <div class="p-5 rounded-xl border border-slate-200 bg-white space-y-1.5">
                        <h3 class="font-bold text-slate-900 text-sm">{{ $sorotan['judul'] }}</h3>
                        <p class="text-slate-600 text-[13px] leading-relaxed">{{ $sorotan['isi'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="judul-tanya" class="space-y-4">
            <h2 id="judul-tanya" class="text-xl font-bold text-slate-900 tracking-tight">
                Pertanyaan yang sering muncul
            </h2>

            <div class="divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white">
                @foreach ($produk['tanya'] as $butir)
                    <details class="group p-5">
                        <summary class="flex items-start justify-between gap-4 cursor-pointer list-none">
                            <h3 class="font-bold text-slate-900 text-sm">{{ $butir['tanya'] }}</h3>
                            <svg class="w-4 h-4 text-slate-400 shrink-0 mt-0.5 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </summary>
                        <p class="text-slate-600 text-[13px] leading-relaxed mt-2.5">{{ $butir['jawab'] }}</p>
                    </details>
                @endforeach
            </div>
        </section>

        {{-- Tautan ke dokumentasi yang relevan. Selain berguna bagi pembaca, ini
             yang menghubungkan halaman produk dengan halaman dokumentasi: halaman
             yang tidak ditaut dari mana pun jarang ditelusuri perayap. --}}
        <section aria-labelledby="judul-docs" class="space-y-4">
            <h2 id="judul-docs" class="text-xl font-bold text-slate-900 tracking-tight">
                Baca sebelum mulai
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                @foreach ($produk['docs'] as $slugKelompok)
                    @php($kelompok = \App\Support\KatalogDokumentasi::ambil($slugKelompok))
                    <a href="{{ route('docs.kelompok', $slugKelompok) }}"
                       class="p-5 rounded-xl border border-slate-200 bg-white hover:border-slate-300 transition-colors group space-y-1.5">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $kelompok['ikon'] }}"/></svg>
                            <h3 class="font-bold text-slate-900 text-sm group-hover:text-black">{{ $kelompok['judul'] }}</h3>
                        </div>
                        <p class="text-slate-500 text-[11px] leading-relaxed">{{ $kelompok['ringkas'] }}</p>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6 sm:p-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="space-y-1">
                <h2 class="font-bold text-slate-900">Belum yakin paket mana?</h2>
                <p class="text-slate-600 text-[13px] leading-relaxed">
                    Sampaikan beban kerja Anda, tim kami bantu memilihkan ukurannya.
                </p>
            </div>
            <a href="{{ route('contact') }}"
               class="inline-flex justify-center items-center whitespace-nowrap px-5 py-2.5 rounded-lg bg-slate-900 text-white text-sm font-bold hover:bg-black transition-colors">
                Hubungi Tim CravaCloud
            </a>
        </section>

    </div>

</div>
@endsection
