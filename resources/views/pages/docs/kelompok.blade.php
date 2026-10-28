@extends('pages.docs.kerangka')

@section('dokumentasi')

    <div class="space-y-3">
        <div class="flex items-center gap-2">
            <a href="{{ route('docs') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-900 transition-colors">Dokumentasi</a>
            <span class="text-slate-300 text-xs">/</span>
            <span class="text-xs font-semibold text-slate-900">{{ $kelompok['judul'] }}</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
            {{ $kelompok['judul'] }}
        </h1>
        <p class="text-slate-600 text-sm leading-relaxed">
            {{ $kelompok['deskripsi'] }}
        </p>
    </div>

    <hr class="border-slate-200">

    @foreach (array_keys($kelompok['seksi']) as $urutanSeksi => $idSeksi)
        @if ($urutanSeksi > 0)
            <hr class="border-slate-200">
        @endif

        @include(\App\Support\KatalogDokumentasi::viewSeksi($idSeksi))
    @endforeach

    {{-- Tautan ke kelompok sebelum dan sesudahnya. Selain memudahkan membaca
         berurutan, ini yang menjaga tiap halaman dokumentasi tetap terhubung
         satu sama lain — halaman yang hanya bisa dicapai lewat sidebar mudah
         terlewat perayap. --}}
    @if ($sebelumnya || $berikutnya)
        <nav class="pt-8 border-t border-slate-200 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
            @if ($sebelumnya)
                @php($kelompokSebelumnya = \App\Support\KatalogDokumentasi::ambil($sebelumnya))
                <a href="{{ route('docs.kelompok', $sebelumnya) }}"
                   class="p-4 rounded-xl border border-slate-200 bg-white hover:border-slate-300 transition-colors group">
                    <span class="text-[11px] text-slate-400 font-semibold block mb-1">&larr; Sebelumnya</span>
                    <span class="font-bold text-slate-900 group-hover:text-black block">{{ $kelompokSebelumnya['judul'] }}</span>
                    <span class="text-slate-500 text-[11px] leading-relaxed block mt-1">{{ $kelompokSebelumnya['ringkas'] }}</span>
                </a>
            @else
                <span></span>
            @endif

            @if ($berikutnya)
                @php($kelompokBerikutnya = \App\Support\KatalogDokumentasi::ambil($berikutnya))
                <a href="{{ route('docs.kelompok', $berikutnya) }}"
                   class="p-4 rounded-xl border border-slate-200 bg-white hover:border-slate-300 transition-colors group sm:text-right">
                    <span class="text-[11px] text-slate-400 font-semibold block mb-1">Berikutnya &rarr;</span>
                    <span class="font-bold text-slate-900 group-hover:text-black block">{{ $kelompokBerikutnya['judul'] }}</span>
                    <span class="text-slate-500 text-[11px] leading-relaxed block mt-1">{{ $kelompokBerikutnya['ringkas'] }}</span>
                </a>
            @endif
        </nav>
    @endif

@endsection
