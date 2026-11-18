@extends('layouts.app', [
    'title' => $produk['judul'],
    'description' => $produk['deskripsi'],
    'breadcrumbs' => $remah,
    'faq' => $produk['tanya'],
])

@section('content')
<div class="min-h-screen bg-cream">

    <div class="max-w-[1380px] mx-auto px-5 sm:px-10 lg:px-14 py-12 sm:py-16 space-y-14">

        <!-- Header -->
        <header class="space-y-4 max-w-3xl border-b border-hairline pb-8">
            <nav class="flex items-center gap-2 font-mono text-xs uppercase tracking-wider">
                <a href="{{ url('/') }}" class="font-semibold text-muted hover:text-electric transition-colors">Beranda</a>
                <span class="text-hairline">/</span>
                <span class="font-bold text-ink">{{ $produk['h1'] }}</span>
            </nav>

            <div class="flex items-center space-x-2">
                <span class="w-2 h-2 bg-electric"></span>
                <span class="font-mono text-[11px] font-bold tracking-[0.2em] text-electric uppercase">
                    SPESIFIKASI KATALOG
                </span>
            </div>

            <h1 class="font-display font-black text-3xl sm:text-5xl text-ink uppercase tracking-tight leading-none">
                {{ $produk['h1'] }}
            </h1>

            <p class="text-muted text-sm sm:text-base leading-relaxed">
                {{ $produk['intro'] }}
            </p>
        </header>

        <!-- Comparison Table -->
        <section aria-labelledby="judul-paket" class="space-y-4">
            <div class="flex items-center justify-between border-b border-hairline pb-3">
                <h2 id="judul-paket" class="font-display font-bold text-xl sm:text-2xl text-ink uppercase tracking-tight">
                    Paket &amp; Harga
                </h2>
                <span class="font-mono text-[11px] uppercase tracking-widest text-muted">IDR / BULAN</span>
            </div>

            @if ($paket->isEmpty())
                <x-ui.alert type="info">
                    Paket untuk kategori ini sedang tidak tersedia.
                    <a href="{{ \App\Support\NomorWhatsApp::tautan() }}" target="_blank" rel="noopener" class="text-electric font-bold underline">Hubungi tim kami</a>
                    untuk penawaran khusus.
                </x-ui.alert>
            @else
                <div class="overflow-x-auto border border-hairline bg-white rounded-none">
                    <table class="w-full text-sm text-left border-collapse">
                        <caption class="sr-only">Perbandingan paket {{ $produk['h1'] }} CravaCloud</caption>
                        <thead class="font-mono text-[11px] uppercase tracking-wider text-ink border-b border-ink bg-creamDark">
                            <tr>
                                <th scope="col" class="px-5 py-3.5 font-bold">Paket</th>
                                <th scope="col" class="px-5 py-3.5 font-bold">vCPU</th>
                                <th scope="col" class="px-5 py-3.5 font-bold">RAM</th>
                                <th scope="col" class="px-5 py-3.5 font-bold">Penyimpanan</th>
                                <th scope="col" class="px-5 py-3.5 font-bold">Bandwidth</th>
                                <th scope="col" class="px-5 py-3.5 font-bold">Harga / bulan</th>
                                <th scope="col" class="px-5 py-3.5"><span class="sr-only">Pesan</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hairline">
                            @foreach ($paket as $spec)
                                <tr class="hover:bg-cream/60 transition-colors">
                                    <th scope="row" class="px-5 py-4 font-display font-bold text-ink uppercase align-top">
                                        {{ $spec->name }}
                                        @if ($spec->tagline)
                                            <span class="block font-sans font-normal normal-case text-xs text-muted mt-1 max-w-[16rem]">{{ $spec->tagline }}</span>
                                        @endif
                                    </th>
                                    <td class="px-5 py-4 font-mono text-xs text-ink align-top">{{ $spec->cpu }} vCPU</td>
                                    <td class="px-5 py-4 font-mono text-xs text-ink align-top">{{ $spec->ram }} GB</td>
                                    <td class="px-5 py-4 font-mono text-xs text-ink align-top">{{ $spec->disk }} GB NVMe</td>
                                    <td class="px-5 py-4 font-mono text-xs text-ink align-top">
                                        {{ $spec->bandwidth }} Mbps
                                        @if($spec->category === 'vps' && method_exists($spec, 'bandwidthQuotaLabel'))
                                            <span class="block text-[10px] text-muted">{{ $spec->bandwidthQuotaLabel() }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 font-display font-extrabold text-base text-ink align-top whitespace-nowrap">
                                        Rp {{ number_format($spec->sell_price, 0, ',', '.') }}
                                    </td>
                                    <td class="px-5 py-4 align-top">
                                        @if($spec->is_active)
                                            <a href="{{ route('checkout', $spec->id) }}"
                                               class="inline-flex items-center gap-2 whitespace-nowrap px-4 py-2 rounded-none bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors">
                                                <span>Pesan</span>
                                                <span>↗</span>
                                            </a>
                                        @else
                                            <span class="inline-flex items-center whitespace-nowrap px-3 py-1.5 rounded-none bg-creamDark text-muted font-mono text-xs font-bold uppercase border border-hairline cursor-not-allowed" title="Stok sedang habis atau tidak tersedia">
                                                Stok Habis
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="font-mono text-[11px] text-muted">
                    Harga belum termasuk pajak bila berlaku. Lihat
                    <a href="{{ route('terms') }}" class="text-electric font-bold hover:underline">Ketentuan Layanan</a>
                    dan
                    <a href="{{ route('refund') }}" class="text-electric font-bold hover:underline">Kebijakan Pengembalian Dana</a>.
                </p>
            @endif
        </section>

        <!-- Highlights Grid -->
        <section aria-labelledby="judul-sorotan" class="space-y-4">
            <h2 id="judul-sorotan" class="font-display font-bold text-xl sm:text-2xl text-ink uppercase tracking-tight">
                Yang Anda Dapat
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 border-t border-l border-hairline">
                @foreach ($produk['sorotan'] as $idx => $sorotan)
                    <div class="p-6 bg-white border-r border-b border-hairline space-y-2">
                        <span class="font-mono text-[10px] font-bold tracking-widest uppercase text-electric block">
                            SPEC // 0{{ $idx + 1 }}
                        </span>
                        <h3 class="font-display font-bold text-ink text-base uppercase">{{ $sorotan['judul'] }}</h3>
                        <p class="text-muted text-xs sm:text-sm leading-relaxed">{{ $sorotan['isi'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <!-- FAQ Accordion -->
        <section aria-labelledby="judul-tanya" class="space-y-4">
            <h2 id="judul-tanya" class="font-display font-bold text-xl sm:text-2xl text-ink uppercase tracking-tight">
                Pertanyaan yang Sering Muncul
            </h2>

            <div class="divide-y divide-hairline border border-hairline bg-white">
                @foreach ($produk['tanya'] as $butir)
                    <details class="group p-5">
                        <summary class="flex items-start justify-between gap-4 cursor-pointer list-none">
                            <h3 class="font-display font-bold text-ink text-sm sm:text-base">{{ $butir['tanya'] }}</h3>
                            <span class="font-mono text-xs text-electric shrink-0 mt-0.5 transition-transform group-open:rotate-180">▼</span>
                        </summary>
                        <p class="text-muted text-xs sm:text-sm leading-relaxed mt-3 pt-3 border-t border-hairline/60">{{ $butir['jawab'] }}</p>
                    </details>
                @endforeach
            </div>
        </section>

        <!-- Related Docs -->
        <section aria-labelledby="judul-docs" class="space-y-4">
            <h2 id="judul-docs" class="font-display font-bold text-xl sm:text-2xl text-ink uppercase tracking-tight">
                Baca Sebelum Mulai
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 border-t border-l border-hairline">
                @foreach ($produk['docs'] as $slugKelompok)
                    @php($kelompok = \App\Support\KatalogDokumentasi::ambil($slugKelompok))
                    <a href="{{ route('docs.kelompok', $slugKelompok) }}"
                       class="p-6 bg-white hover:bg-cream border-r border-b border-hairline transition-colors group space-y-2">
                        <div class="flex items-center justify-between">
                            <svg class="w-4 h-4 text-electric" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $kelompok['ikon'] }}"/></svg>
                            <span class="font-mono text-xs text-muted group-hover:text-electric">↗</span>
                        </div>
                        <h3 class="font-display font-bold text-ink text-sm uppercase group-hover:text-electric transition-colors">{{ $kelompok['judul'] }}</h3>
                        <p class="text-muted text-xs leading-relaxed">{{ $kelompok['ringkas'] }}</p>
                    </a>
                @endforeach
            </div>
        </section>

        <!-- Bottom CTA -->
        <section class="border-2 border-ink bg-ink text-cream p-7 sm:p-9 flex flex-col sm:flex-row sm:items-center justify-between gap-6">
            <div class="space-y-1.5">
                <span class="font-mono text-[10px] font-bold tracking-[0.2em] text-electric uppercase block">● INFRASTRUCTURE ADVISORY</span>
                <h2 class="font-display font-bold text-xl sm:text-2xl text-white uppercase">Belum Yakin Paket Mana?</h2>
                <p class="text-cream/70 text-xs sm:text-sm leading-relaxed">
                    Sampaikan beban kerja Anda, tim teknis CravaCloud bantu memilihkan kapasitas yang tepat.
                </p>
            </div>
            <a href="{{ \App\Support\NomorWhatsApp::tautan() }}?text={{ rawurlencode('Halo CravaCloud, saya ingin konsultasi pemilihan paket ' . $produk['h1']) }}"
               target="_blank"
               rel="noopener"
               class="inline-flex justify-center items-center gap-3 whitespace-nowrap px-6 py-4 rounded-none bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors shrink-0">
                <span>Hubungi Tim CravaCloud</span>
                <span>↗</span>
            </a>
        </section>

    </div>

</div>
@endsection
