@extends('pages.docs.kerangka')

@section('dokumentasi')

    <div class="space-y-3">
        <div class="flex items-center gap-2">
            <span class="px-2.5 py-0.5 rounded text-[10px] font-bold font-mono-code bg-slate-900 text-white uppercase">Knowledge Base</span>
            <span class="text-xs font-semibold text-slate-500">CravaCloud</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
            Dokumentasi Teknis CravaCloud
        </h1>
        <p class="text-slate-600 text-sm leading-relaxed">
            Panduan menyiapkan, mengamankan, dan mengoperasikan VPS CravaCloud — dari server yang
            baru aktif sampai integrasi lewat API. Pilih topik di bawah, atau tekan
            <kbd class="font-mono-code text-[10px] bg-slate-100 border border-slate-200 px-1.5 py-0.5 rounded font-bold text-slate-800">Ctrl&nbsp;+&nbsp;K</kbd>
            untuk mencari.
        </p>
    </div>

    <hr class="border-slate-200">

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @foreach (\App\Support\KatalogDokumentasi::kelompok() as $slugKelompok => $kelompok)
            <a href="{{ route('docs.kelompok', $slugKelompok) }}"
               class="p-5 rounded-xl border border-slate-200 bg-white hover:border-slate-300 transition-colors group space-y-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $kelompok['ikon'] }}"/></svg>
                    <h2 class="font-bold text-slate-900 text-sm group-hover:text-black">{{ $kelompok['judul'] }}</h2>
                </div>
                <p class="text-slate-500 text-[11px] leading-relaxed">{{ $kelompok['ringkas'] }}</p>
                <ul class="text-[11px] text-slate-600 space-y-0.5 pt-1">
                    @foreach ($kelompok['seksi'] as $judulSeksi)
                        <li class="flex items-start gap-1.5">
                            <span class="text-slate-300 mt-px">&bull;</span>
                            <span>{{ $judulSeksi }}</span>
                        </li>
                    @endforeach
                </ul>
            </a>
        @endforeach
    </div>

    {{-- Tautan lama berbentuk /docs#stack-coolify sudah tersebar di riwayat chat
         dan bookmark pelanggan. Anchor tidak pernah dikirim ke server, jadi tidak
         ada redirect yang bisa menanganinya — hanya halaman ini yang tahu, dan
         hanya setelah halamannya termuat. --}}
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
