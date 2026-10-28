@extends('layouts.app', [
    'title' => $seoJudul,
    'description' => $seoDeskripsi,
    'breadcrumbs' => $remah,
])

@section('content')
<div x-data="{
    docsSearchOpen: false,
    searchQuery: '',
    copied: {},
    activeTabSnippet: {},
    activeSection: 'intro',
    copyToClipboard(text, id) {
        navigator.clipboard.writeText(text).then(() => {
            this.copied[id] = true;
            setTimeout(() => { this.copied[id] = false; }, 2000);
        });
    },
    sections: [
        @foreach (\App\Support\KatalogDokumentasi::kelompok() as $slugKelompok => $kelompok)
            @foreach ($kelompok['seksi'] as $idSeksi => $judulSeksi)
        { id: '{{ $idSeksi }}', title: @js($judulSeksi), category: @js($kelompok['judul']), url: '{{ route('docs.kelompok', $slugKelompok) }}#{{ $idSeksi }}' },
            @endforeach
        @endforeach
    ],
    get filteredSections() {
        if (!this.searchQuery.trim()) return this.sections;
        const q = this.searchQuery.toLowerCase();
        return this.sections.filter(s => s.title.toLowerCase().includes(q) || s.category.toLowerCase().includes(q));
    },
    buka(item) {
        this.docsSearchOpen = false;
        window.location = item.url;
    }
}"
@keydown.window.prevent.cmd.k="docsSearchOpen = true; $nextTick(() => $refs.searchInput.focus())"
@keydown.window.prevent.ctrl.k="docsSearchOpen = true; $nextTick(() => $refs.searchInput.focus())"
class="min-h-screen bg-slate-50 selection:bg-slate-900 selection:text-white">

    <!-- Top Sub-Header Bar (Enterprise Documentation Banner) -->
    <div class="bg-white border-b border-slate-200 sticky top-16 z-30 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="flex items-center space-x-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Dokumentasi Teknis</span>
                    <span class="text-slate-300">/</span>
                    <span class="text-xs font-semibold text-slate-900 font-mono-code">v1.4 Enterprise</span>
                </div>
                <a href="{{ route('status') }}" class="hidden sm:inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-50 text-slate-700 border border-slate-200 hover:bg-slate-100">
                    Cek Status Layanan &rarr;
                </a>
            </div>

            <!-- Quick Search Input Trigger -->
            <div class="flex items-center space-x-3">
                <button @click="docsSearchOpen = true; $nextTick(() => $refs.searchInput.focus())"
                        type="button"
                        class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-500 hover:text-slate-800 text-xs transition-colors shadow-2xs group">
                    <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <span class="hidden sm:inline">Cari dokumentasi atau endpoint API...</span>
                    <span class="sm:hidden">Cari...</span>
                    <kbd class="hidden sm:inline-flex items-center gap-0.5 font-mono-code text-[10px] font-semibold bg-white px-1.5 py-0.5 rounded border border-slate-200 text-slate-600 shadow-2xs">Ctrl K</kbd>
                </button>

                <a href="{{ route('dashboard.index') }}" class="hidden md:inline-flex items-center gap-1 text-xs font-semibold text-slate-700 hover:text-black px-3 py-1.5 rounded-lg hover:bg-slate-100 transition-colors">
                    <span>Client Portal</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
        </div>
    </div>

    <!-- Search Modal (Cmd+K / Ctrl+K) -->
    <div x-show="docsSearchOpen"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/60 backdrop-blur-xs flex items-start justify-center pt-20 px-4"
         style="display: none;"
         @keydown.escape.window="docsSearchOpen = false">

        <div @click.away="docsSearchOpen = false"
             class="bg-white rounded-xl border border-slate-200 shadow-2xl max-w-2xl w-full overflow-hidden">
            <div class="p-4 border-b border-slate-200 flex items-center gap-3">
                <svg class="w-5 h-5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input x-ref="searchInput"
                       x-model="searchQuery"
                       type="text"
                       placeholder="Ketik topik, perintah SSH, spesifikasi, atau endpoint API..."
                       class="w-full text-sm text-slate-900 placeholder-slate-400 bg-transparent focus:outline-none">
                <button @click="docsSearchOpen = false" class="text-xs font-semibold text-slate-400 hover:text-slate-600 px-2 py-1 rounded bg-slate-100">ESC</button>
            </div>

            <div class="max-h-96 overflow-y-auto p-2 divide-y divide-slate-100">
                <template x-for="item in filteredSections" :key="item.id">
                    <button @click="buka(item)"
                            class="w-full text-left p-3 hover:bg-slate-50 rounded-lg flex items-center justify-between group transition-colors">
                        <div>
                            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block font-mono-code" x-text="item.category"></span>
                            <span class="text-sm font-semibold text-slate-900 group-hover:text-black" x-text="item.title"></span>
                        </div>
                        <svg class="w-4 h-4 text-slate-300 group-hover:text-slate-700 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </template>
                <div x-show="filteredSections.length === 0" class="p-8 text-center text-xs text-slate-400">
                    Tidak ada topik dokumentasi yang cocok dengan kata kunci tersebut.
                </div>
            </div>

            <div class="px-4 py-2.5 bg-slate-50 border-t border-slate-200 text-[11px] text-slate-500 flex items-center justify-between">
                <span>Navigasi cepat dokumentasi CravaCloud</span>
                <span class="font-mono-code text-[10px]">Gunakan panah & enter untuk membuka</span>
            </div>
        </div>
    </div>

    <!-- Tata letak tiga kolom: navigasi, isi dokumentasi, daftar isi halaman -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

            <!-- KIRI: navigasi antar kelompok dokumentasi -->
            <aside class="lg:col-span-3 sticky top-32 max-h-[calc(100vh-9rem)] overflow-y-auto pr-2 space-y-6 text-xs">

                @foreach (\App\Support\KatalogDokumentasi::kelompok() as $slugKelompok => $kelompok)
                    @php($iniKelompokAktif = ($kelompokAktif ?? null) === $slugKelompok)
                    <div>
                        <div class="flex items-center gap-2 font-bold uppercase tracking-wider text-slate-400 mb-2 px-3">
                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $kelompok['ikon'] }}"/></svg>
                            <span>{{ $kelompok['navigasi'] }}</span>
                        </div>
                        <ul class="space-y-0.5">
                            @foreach ($kelompok['seksi'] as $idSeksi => $judulSeksi)
                                <li>
                                    <a href="{{ route('docs.kelompok', $slugKelompok) }}#{{ $idSeksi }}"
                                       @class([
                                           'block px-3 py-2 rounded-lg transition-colors',
                                           'text-slate-600 hover:bg-slate-100 hover:text-slate-900 font-medium' => ! $iniKelompokAktif,
                                           'text-slate-900 font-semibold hover:bg-slate-200/70' => $iniKelompokAktif,
                                       ])>
                                        {{ $judulSeksi }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach

                <!-- Tiket dukungan -->
                <div class="p-4 rounded-xl border border-slate-200 bg-white">
                    <p class="font-bold text-slate-900 text-xs mb-1">Butuh Bantuan Teknis?</p>
                    <p class="text-slate-500 text-[11px] leading-relaxed mb-3">Tim CravaCloud membantu kendala server Anda pada jam kerja.</p>
                    <a href="{{ route('dashboard.support') }}" class="block text-center py-2 px-3 rounded-lg bg-slate-900 text-white font-bold text-[11px] hover:bg-black transition-colors">
                        Buka Tiket Dukungan
                    </a>
                </div>

            </aside>

            <!-- TENGAH: isi dokumentasi -->
            <main class="lg:col-span-6 space-y-16">

                @yield('dokumentasi')

                <div class="pt-8 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div>
                        <span class="text-xs font-semibold text-slate-900 block">CravaCloud Engineering Knowledge Base</span>
                        <span class="text-[11px] text-slate-400">Terakhir diperbarui: September 2026 &bull; Rilis Produksi v1.4</span>
                    </div>
                    <button @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold text-slate-600 hover:text-black hover:bg-white transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                        <span>Kembali ke Atas</span>
                    </button>
                </div>

            </main>

            <!-- KANAN: daftar isi halaman yang sedang dibuka -->
            <aside class="lg:col-span-3 sticky top-32 hidden lg:block space-y-6 text-xs">
                @isset($kelompokAktif)
                    <div class="p-5 rounded-xl border border-slate-200 bg-white">
                        <p class="font-bold text-slate-900 uppercase tracking-wider text-[11px] mb-3 pb-2 border-b border-slate-100 flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                            <span>Di Halaman Ini</span>
                        </p>
                        <nav class="space-y-1.5 text-slate-600 font-medium">
                            @foreach (\App\Support\KatalogDokumentasi::ambil($kelompokAktif)['seksi'] as $idSeksi => $judulSeksi)
                                <a href="#{{ $idSeksi }}" class="block hover:text-slate-900 transition-colors py-0.5">{{ $judulSeksi }}</a>
                            @endforeach
                        </nav>
                    </div>
                @endisset

                <div class="p-4 rounded-xl border border-slate-200 bg-white">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Shortcut Keyboard</span>
                    <div class="flex items-center justify-between text-slate-700 py-1">
                        <span>Pencarian Cepat</span>
                        <kbd class="font-mono-code text-[10px] bg-slate-100 border border-slate-200 px-1.5 py-0.5 rounded font-bold text-slate-800">Ctrl + K</kbd>
                    </div>
                    <div class="flex items-center justify-between text-slate-700 py-1">
                        <span>Tutup Modal</span>
                        <kbd class="font-mono-code text-[10px] bg-slate-100 border border-slate-200 px-1.5 py-0.5 rounded font-bold text-slate-800">ESC</kbd>
                    </div>
                </div>
            </aside>

        </div>
    </div>

</div>
@endsection
