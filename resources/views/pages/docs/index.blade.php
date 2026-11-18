@extends('pages.docs.kerangka')

@section('dokumentasi')

    <div class="space-y-3">
        <div class="flex items-center gap-2.5">
            <x-ui.badge variant="electric">Knowledge Base</x-ui.badge>
            <span class="font-mono text-xs font-semibold text-muted uppercase">CravaCloud</span>
        </div>
        <h1 class="font-display text-2xl sm:text-4xl font-black text-ink uppercase tracking-tight">
            Dokumentasi Teknis CravaCloud
        </h1>
        <p class="text-muted text-sm leading-relaxed">
            Panduan menyiapkan, mengamankan, dan mengoperasikan VPS CravaCloud — dari server yang
            baru aktif sampai integrasi lewat API. Pilih topik di bawah, atau tekan
            <kbd class="font-mono text-[10px] bg-creamDark border border-hairline px-1.5 py-0.5 font-bold text-ink">Ctrl&nbsp;+&nbsp;K</kbd>
            untuk mencari.
        </p>
    </div>

    <hr class="border-hairline">

    <div class="grid grid-cols-1 sm:grid-cols-2 border-t border-l border-hairline">
        @foreach (\App\Support\KatalogDokumentasi::kelompok() as $slugKelompok => $kelompok)
            <a href="{{ route('docs.kelompok', $slugKelompok) }}"
               class="p-5 bg-cream/50 hover:bg-white border-r border-b border-hairline transition-colors group space-y-2.5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-electric" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $kelompok['ikon'] }}"/></svg>
                        <h2 class="font-display font-bold text-ink text-sm uppercase group-hover:text-electric transition-colors">{{ $kelompok['judul'] }}</h2>
                    </div>
                    <span class="font-mono text-xs text-muted group-hover:text-electric">↗</span>
                </div>
                <p class="text-muted text-xs leading-relaxed">{{ $kelompok['ringkas'] }}</p>
                <ul class="font-mono text-[11px] text-ink/80 space-y-1 pt-1">
                    @foreach ($kelompok['seksi'] as $judulSeksi)
                        <li class="flex items-start gap-1.5">
                            <span class="text-electric mt-px">›</span>
                            <span>{{ $judulSeksi }}</span>
                        </li>
                    @endforeach
                </ul>
            </a>
        @endforeach
    </div>

    <script type="application/json" id="peta-seksi-dokumentasi">
        @json(\App\Support\KatalogDokumentasi::petaSeksi())
    </script>
    <script>
        (function () {
            var anchor = window.location.hash.replace('#', '');
            if (! anchor) return;

            var peta = JSON.parse(document.getElementById('peta-seksi-dokumentasi').textContent);
            if (peta[anchor]) {
                window.location.replace('{{ url('docs') }}/' + peta[anchor] + '#' + anchor);
            }
        })();
    </script>

@endsection
