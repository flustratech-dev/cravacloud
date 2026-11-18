<!-- Global Invoice PDF Viewer Modal -->
<div x-data="{
    open: false,
    pdfUrl: '',
    loading: true,
    init() {
        window.addEventListener('open-invoice-modal', (e) => {
            if (e.detail && e.detail.url) {
                let cleanUrl = e.detail.url;
                this.pdfUrl = cleanUrl.includes('#') ? cleanUrl : cleanUrl + '#toolbar=1&navpanes=0&view=FitH';
                this.loading = true;
                this.open = true;
                document.body.style.overflow = 'hidden';
            }
        });
    },
    close() {
        this.open = false;
        this.pdfUrl = '';
        this.loading = false;
        document.body.style.overflow = '';
    }
}"
@keydown.escape.window="close()"
x-show="open"
x-cloak
class="fixed inset-0 flex items-center justify-center p-2 sm:p-4 md:p-6"
style="display: none; z-index: 99999;">

    <div class="fixed inset-0 bg-ink/80 transition-opacity" @click="close()"></div>

    <div class="relative w-full overflow-hidden bg-ink border-2 border-ink"
         style="width: 95vw; max-width: 1100px; height: 94vh; max-height: 96vh; display: flex; flex-direction: column; z-index: 10;"
         @click.stop>

        <!-- Utility Header Bar -->
        <div class="flex items-center justify-between px-4 py-2 bg-ink border-b border-white/15 text-cream text-xs select-none"
             style="height: 44px; min-height: 44px;">
            <div class="flex items-center gap-2 font-mono uppercase tracking-wider font-bold">
                <svg class="w-4 h-4 text-electric" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>Faktur / Invoice Resmi (PDF)</span>
            </div>
            <div class="flex items-center gap-2">
                <a :href="pdfUrl" target="_blank"
                   class="hover:bg-white/10 transition-colors inline-flex items-center gap-1.5 font-mono text-[11px] uppercase px-2.5 py-1 border border-white/20"
                   title="Buka di tab baru">
                    <span>Tab Baru ↗</span>
                </a>
                <button type="button"
                        @click="close()"
                        class="p-1.5 hover:bg-white/10 text-cream/80 hover:text-white transition-colors cursor-pointer"
                        title="Tutup (Esc)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- PDF Reader Iframe Container -->
        <div class="relative w-full overflow-hidden"
             style="flex: 1 1 auto; height: calc(100% - 44px); min-height: 0; background-color: #3f3f46; display: flex; flex-direction: column;">
            <div x-show="loading" class="absolute inset-0 flex items-center justify-center bg-ink/90 z-20">
                <div class="flex flex-col items-center gap-3 text-white text-xs font-mono uppercase">
                    <svg class="animate-spin h-6 w-6 text-electric" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span>Memuat Faktur PDF...</span>
                </div>
            </div>

            <iframe :src="pdfUrl"
                    class="w-full h-full border-0"
                    style="width: 100%; height: 100%; flex: 1 1 auto; min-height: 0; display: block; border: none; background-color: #3f3f46;"
                    @load="loading = false">
            </iframe>
        </div>
    </div>
</div>
