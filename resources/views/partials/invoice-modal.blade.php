<!-- Global Invoice PDF Viewer Modal (Full-size Pop-up Murni Bawaan PDF) -->
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

    <!-- Dark Backdrop -->
    <div class="fixed inset-0 bg-black/80 backdrop-blur-xs transition-opacity"
         @click="close()"></div>

    <!-- Container: Pure Native PDF Viewer without extra card styling (Guaranteed full viewport size) -->
    <div class="relative w-full rounded-lg overflow-hidden shadow-2xl bg-neutral-900 border border-neutral-700/60"
         style="width: 95vw; max-width: 1100px; height: 94vh; max-height: 96vh; display: flex; flex-direction: column; z-index: 10;"
         @click.stop>
        
        <!-- Slim Utility Header Bar -->
        <div class="flex items-center justify-between px-4 py-2 bg-neutral-900 border-b border-neutral-800 text-neutral-300 text-xs select-none"
             style="height: 42px; min-height: 42px;">
            <div class="flex items-center gap-2 font-medium">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span class="text-white font-semibold tracking-wide">Faktur / Invoice Resmi (PDF)</span>
            </div>
            <div class="flex items-center gap-3">
                <a :href="pdfUrl" target="_blank"
                   class="hover:text-white transition-colors inline-flex items-center gap-1.5 text-[11px] text-neutral-400 font-medium px-2 py-1 rounded hover:bg-neutral-800"
                   title="Buka di tab baru jika diperlukan">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    <span>Tab Baru</span>
                </a>
                <button type="button"
                        @click="close()"
                        class="p-1 rounded-md hover:bg-neutral-800 text-neutral-400 hover:text-white transition-colors cursor-pointer"
                        title="Tutup (Esc)">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Pure Native PDF Viewer Container (Fills remaining height 100%) -->
        <div class="relative w-full overflow-hidden"
             style="flex: 1 1 auto; height: calc(100% - 42px); min-height: 0; background-color: #525659; display: flex; flex-direction: column;">
            
            <!-- Loading Indicator -->
            <div x-show="loading" class="absolute inset-0 flex items-center justify-center bg-neutral-900/90 z-20">
                <div class="flex flex-col items-center gap-3 text-white text-xs">
                    <svg class="animate-spin h-7 w-7 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span class="font-medium tracking-wide">Memuat Faktur PDF...</span>
                </div>
            </div>

            <!-- Native Browser PDF Reader Iframe -->
            <iframe :src="pdfUrl"
                    class="w-full h-full border-0"
                    style="width: 100%; height: 100%; flex: 1 1 auto; min-height: 0; display: block; border: none; background-color: #525659;"
                    @load="loading = false">
            </iframe>
        </div>
    </div>
</div>
