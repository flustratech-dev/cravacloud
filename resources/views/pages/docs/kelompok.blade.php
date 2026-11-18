@extends('pages.docs.kerangka')

@section('dokumentasi')

    <div class="space-y-3">
        <div class="flex items-center gap-2 font-mono text-xs uppercase tracking-wider">
            <a href="{{ route('docs') }}" class="font-semibold text-muted hover:text-electric transition-colors">Dokumentasi</a>
            <span class="text-hairline">/</span>
            <span class="font-bold text-ink">{{ $kelompok['judul'] }}</span>
        </div>
        <h1 class="font-display text-2xl sm:text-4xl font-black text-ink uppercase tracking-tight">
            {{ $kelompok['judul'] }}
        </h1>
        <p class="text-muted text-sm leading-relaxed">
            {{ $kelompok['deskripsi'] }}
        </p>
    </div>

    <hr class="border-hairline">

    @foreach (array_keys($kelompok['seksi']) as $urutanSeksi => $idSeksi)
        @if ($urutanSeksi > 0)
            <hr class="border-hairline">
        @endif

        @include(\App\Support\KatalogDokumentasi::viewSeksi($idSeksi))
    @endforeach

    @if ($sebelumnya || $berikutnya)
        <nav class="pt-8 border-t border-hairline grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
            @if ($sebelumnya)
                @php($kelompokSebelumnya = \App\Support\KatalogDokumentasi::ambil($sebelumnya))
                <a href="{{ route('docs.kelompok', $sebelumnya) }}"
                   class="p-4 border border-hairline bg-cream/60 hover:bg-white hover:border-ink transition-colors group">
                    <span class="font-mono text-[10px] text-electric font-bold uppercase block mb-1">&larr; Sebelumnya</span>
                    <span class="font-display font-bold text-ink uppercase block">{{ $kelompokSebelumnya['judul'] }}</span>
                    <span class="text-muted text-[11px] leading-relaxed block mt-1">{{ $kelompokSebelumnya['ringkas'] }}</span>
                </a>
            @else
                <span></span>
            @endif

            @if ($berikutnya)
                @php($kelompokBerikutnya = \App\Support\KatalogDokumentasi::ambil($berikutnya))
                <a href="{{ route('docs.kelompok', $berikutnya) }}"
                   class="p-4 border border-hairline bg-cream/60 hover:bg-white hover:border-ink transition-colors group sm:text-right">
                    <span class="font-mono text-[10px] text-electric font-bold uppercase block mb-1">Berikutnya &rarr;</span>
                    <span class="font-display font-bold text-ink uppercase block">{{ $kelompokBerikutnya['judul'] }}</span>
                    <span class="text-muted text-[11px] leading-relaxed block mt-1">{{ $kelompokBerikutnya['ringkas'] }}</span>
                </a>
            @endif
        </nav>
    @endif

@endsection
