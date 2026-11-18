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
class="min-h-screen bg-cream text-ink selection:bg-electric selection:text-white">

    <!-- Sub-Header Docs Utility Bar -->
    <div class="bg-creamDark/90 backdrop-blur-md border-b border-hairline sticky top-20 z-30">
        <div class="max-w-[1380px] mx-auto px-5 sm:px-10 lg:px-14 h-14 flex items-center justify-between gap-4">
            <div class="flex items-center space-x-3">
                <div class="flex items-center space-x-2 font-mono text-xs uppercase tracking-wider">
                    <span class="font-bold text-muted">Dokumentasi Teknis</span>
                    <span class="text-hairline">/</span>
                    <span class="font-bold text-electric">v1.4 Enterprise</span>
                </div>
                <a href="{{ route('status') }}" class="hidden sm:inline-flex items-center px-2.5 py-0.5 border border-hairline bg-white hover:border-electric font-mono text-[10px] font-bold uppercase text-ink transition-colors">
                    Cek Status Layanan →
                </a>
            </div>

            <div class="flex items-center space-x-3">
                <button @click="docsSearchOpen = true; $nextTick(() => $refs.searchInput.focus())"
                        type="button"
                        class="flex items-center gap-2.5 px-3.5 py-1.5 border border-ink bg-white hover:border-electric text-ink font-mono text-xs transition-colors cursor-pointer group">
                    <svg class="w-3.5 h-3.5 text-muted group-hover:text-electric" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <span class="hidden sm:inline text-muted">Cari topik / API...</span>
                    <span class="sm:hidden text-muted">Cari...</span>
                    <kbd class="hidden sm:inline-flex items-center font-mono text-[10px] font-bold bg-creamDark px-1.5 py-0.5 border border-hairline text-ink">Ctrl K</kbd>
                </button>

                <a href="{{ route('dashboard.index') }}" class="hidden md:inline-flex items-center gap-1.5 font-mono text-xs font-bold uppercase tracking-wider text-ink hover:text-electric px-2 py-1.5 transition-colors">
                    <span>Client Portal</span>
                    <span>→</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Search Modal (Cmd+K / Ctrl+K) -->
    <div x-show="docsSearchOpen"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-ink/75 backdrop-blur-xs flex items-start justify-center pt-20 px-4"
         style="display: none;"
         @keydown.escape.window="docsSearchOpen = false">

        <div @click.away="docsSearchOpen = false"
             class="bg-cream border-2 border-ink shadow-2xl max-w-2xl w-full overflow-hidden rounded-none">
            <div class="p-4 bg-white border-b border-ink flex items-center gap-3">
                <svg class="w-5 h-5 text-electric shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input x-ref="searchInput"
                       x-model="searchQuery"
                       type="text"
                       placeholder="Ketik topik, perintah SSH, spesifikasi, atau endpoint API..."
                       class="w-full text-xs sm:text-sm font-mono text-ink placeholder:text-muted/60 bg-transparent focus:outline-none">
                <button type="button" @click="docsSearchOpen = false" class="font-mono text-xs font-bold text-ink hover:bg-ink hover:text-white px-2 py-1 border border-hairline cursor-pointer">ESC</button>
            </div>

            <div class="max-h-96 overflow-y-auto divide-y divide-hairline bg-cream">
                <template x-for="item in filteredSections" :key="item.id">
                    <button type="button" @click="buka(item)"
                            class="w-full text-left p-3.5 hover:bg-white flex items-center justify-between group transition-colors cursor-pointer">
                        <div>
                            <span class="font-mono text-[10px] font-bold text-electric uppercase tracking-wider block" x-text="item.category"></span>
                            <span class="font-display text-sm font-bold text-ink" x-text="item.title"></span>
                        </div>
                        <span class="font-mono text-xs text-muted group-hover:text-electric">→</span>
                    </button>
                </template>
                <div x-show="filteredSections.length === 0" class="p-8 text-center font-mono text-xs text-muted">
                    Tidak ada topik dokumentasi yang cocok dengan kata kunci tersebut.
                </div>
            </div>

            <div class="px-4 py-2.5 bg-creamDark border-t border-hairline font-mono text-[11px] text-muted flex items-center justify-between">
                <span>Navigasi cepat dokumentasi CravaCloud</span>
                <span class="text-[10px]">Klik topik atau ESC untuk tutup</span>
            </div>
        </div>
    </div>

    <!-- 3-Column Documentation Grid -->
    <div class="max-w-[1380px] mx-auto px-5 sm:px-10 lg:px-14 py-10">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">

            <!-- LEFT: Navigation Tree -->
            <aside class="lg:col-span-3 lg:sticky lg:top-36 lg:max-h-[calc(100vh-10rem)] lg:overflow-y-auto pr-2 space-y-6 text-xs">
                @foreach (\App\Support\KatalogDokumentasi::kelompok() as $slugKelompok => $kelompok)
                    @php($iniKelompokAktif = ($kelompokAktif ?? null) === $slugKelompok)
                    <div class="border-b border-hairline pb-4 last:border-b-0">
                        <div class="flex items-center gap-2 font-mono text-[10px] font-bold uppercase tracking-widest {{ $iniKelompokAktif ? 'text-electric' : 'text-muted' }} mb-2.5">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $kelompok['ikon'] }}"/></svg>
                            <span>{{ $kelompok['navigasi'] }}</span>
                        </div>
                        <ul class="space-y-0.5 font-mono text-[11px]">
                            @foreach ($kelompok['seksi'] as $idSeksi => $judulSeksi)
                                <li>
                                    <a href="{{ route('docs.kelompok', $slugKelompok) }}#{{ $idSeksi }}"
                                       @class([
                                           'block px-2.5 py-1.5 border-l-2 transition-colors',
                                           'border-transparent text-muted hover:text-ink hover:border-ink hover:bg-white' => ! $iniKelompokAktif,
                                           'border-electric text-ink font-semibold bg-white' => $iniKelompokAktif,
                                       ])>
                                        {{ $judulSeksi }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach

                <div class="p-5 border border-ink bg-white space-y-2.5">
                    <p class="font-display font-bold text-ink text-sm uppercase">Butuh Bantuan Teknis?</p>
                    <p class="text-muted text-xs leading-relaxed">Tim CravaCloud membantu kendala server Anda lewat tiket support resmi.</p>
                    <a href="{{ route('dashboard.support') }}" class="flex items-center justify-between py-2.5 px-3 bg-electric hover:bg-electricHover text-white font-mono font-bold text-[11px] uppercase tracking-wider transition-colors">
                        <span>Buka Tiket Dukungan</span>
                        <span>↗</span>
                    </a>
                </div>
            </aside>

            <!-- CENTER: Main Content -->
            <main class="lg:col-span-6 space-y-12 bg-white border border-hairline p-6 sm:p-9">
                @yield('dokumentasi')

                <div class="pt-8 border-t border-hairline flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <span class="font-display text-xs font-bold uppercase text-ink block">CravaCloud Engineering Knowledge Base</span>
                        <span class="font-mono text-[11px] text-muted">Diperbarui 2026 • Rilis Produksi v1.4</span>
                    </div>
                    <button type="button" @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
                            class="inline-flex items-center gap-2 px-3.5 py-2 border border-ink bg-cream hover:bg-ink hover:text-white font-mono text-xs font-bold uppercase text-ink transition-colors cursor-pointer">
                        <span>↑ Kembali ke Atas</span>
                    </button>
                </div>
            </main>

            <!-- RIGHT: On This Page -->
            <aside class="lg:col-span-3 lg:sticky lg:top-36 hidden lg:block space-y-6 text-xs">
                @isset($kelompokAktif)
                    <div class="p-5 border border-hairline bg-white">
                        <p class="font-mono font-bold text-ink uppercase tracking-widest text-[10px] mb-3 pb-2 border-b border-hairline">
                            // DI HALAMAN INI
                        </p>
                        <nav class="space-y-1.5 font-mono text-[11px] text-muted">
                            @foreach (\App\Support\KatalogDokumentasi::ambil($kelompokAktif)['seksi'] as $idSeksi => $judulSeksi)
                                <a href="#{{ $idSeksi }}" class="block hover:text-electric transition-colors py-0.5">{{ $judulSeksi }}</a>
                            @endforeach
                        </nav>
                    </div>
                @endisset

                <div class="p-5 border border-hairline bg-white font-mono">
                    <span class="text-[10px] font-bold text-electric uppercase tracking-widest block mb-3">Shortcut Keyboard</span>
                    <div class="flex items-center justify-between text-ink py-1.5 border-b border-hairline text-[11px]">
                        <span>Pencarian Cepat</span>
                        <kbd class="text-[10px] bg-creamDark border border-hairline px-1.5 py-0.5 font-bold">Ctrl + K</kbd>
                    </div>
                    <div class="flex items-center justify-between text-ink py-1.5 text-[11px]">
                        <span>Tutup Modal</span>
                        <kbd class="text-[10px] bg-creamDark border border-hairline px-1.5 py-0.5 font-bold">ESC</kbd>
                    </div>
                </div>
            </aside>

        </div>
    </div>

</div>
@endsection
