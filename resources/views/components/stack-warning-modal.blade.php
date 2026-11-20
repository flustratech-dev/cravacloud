@php
    $modalContext = $context ?? 'checkout';
@endphp
<div x-show="warningModalOpen"
     x-cloak
     style="display: none;"
     class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-ink/70"
     role="dialog"
     aria-modal="true"
     @keydown.escape.window="if (warningModalOpen) closeStackWarning()">
    <div class="bg-white border-2 border-ink max-w-lg w-full overflow-hidden text-ink shadow-2xl"
         @click.away="closeStackWarning()">
        {{-- Contextual Header --}}
        <div class="p-4 sm:p-5 flex items-start justify-between gap-3 border-b-2"
             :class="warningModalMode === 'ram'
                ? 'border-red-500 bg-red-50/90'
                : (warningModalMode === 'license'
                    ? 'border-amber-500 bg-amber-50/90'
                    : 'border-sky-500 bg-sky-50/90')">
            <div class="flex items-start gap-3 min-w-0">
                <div class="w-10 h-10 rounded-xl bg-white/95 border border-black/10 p-1.5 shrink-0 flex items-center justify-center relative">
                    <template x-if="warningModalStack && warningModalStack.logoUrl">
                        <img :src="warningModalStack.logoUrl"
                             :alt="warningModalStack.label || 'Stack logo'"
                             loading="lazy"
                             class="w-full h-full object-contain">
                    </template>
                    <template x-if="!warningModalStack || !warningModalStack.logoUrl">
                        <svg class="w-5 h-5 text-ink" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="square" stroke-linejoin="miter" d="M12 9v2m0 4h.01M5 19h14L12 5 5 19z"/>
                        </svg>
                    </template>
                    <template x-if="warningModalStack && warningModalStack.secondaryLogoUrl">
                        <img :src="warningModalStack.secondaryLogoUrl"
                             :alt="warningModalStack.label || 'Secondary logo'"
                             loading="lazy"
                             class="w-4 h-4 rounded-full bg-white border border-black/15 p-0.5 object-contain absolute -bottom-1 -right-1 shadow-sm">
                    </template>
                </div>
                <div class="min-w-0">
                    <span class="inline-flex items-center px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider mb-1"
                          :class="warningModalMode === 'ram'
                            ? 'bg-red-600 text-white'
                            : (warningModalMode === 'license'
                                ? 'bg-amber-500 text-ink'
                                : 'bg-sky-600 text-white')"
                          x-text="warningModalMode === 'ram'
                            ? 'RAM TIDAK MENCUKUPI'
                            : (warningModalMode === 'license'
                                ? 'BELUM TERMASUK LISENSI · BYOL'
                                : 'INFO AKTIVASI & JARINGAN')"></span>
                    <h3 class="font-display text-base sm:text-lg font-bold text-ink leading-snug"
                        x-text="warningModalMode === 'ram'
                            ? ('Butuh Min. ' + (warningModalStack?.minRamGb || 4) + ' GB RAM — ' + (warningModalStack?.label || ''))
                            : (warningModalStack?.warningTitle || warningModalStack?.label || 'Konfirmasi Stack')"></h3>
                </div>
            </div>
            <button type="button"
                    @click="closeStackWarning()"
                    class="p-1.5 border border-hairline bg-white hover:border-ink text-ink shrink-0 cursor-pointer"
                    aria-label="Tutup popup konfirmasi">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Body --}}
        <div class="p-5 sm:p-6 space-y-4 text-xs sm:text-sm">
            <template x-if="warningModalMode === 'ram'">
                <div class="space-y-3">
                    <p class="text-ink leading-relaxed">
                        Paket Anda saat ini:
                        <strong class="font-mono" x-text="(planName || 'Paket Aktif') + ' (' + planRamGb + ' GB RAM)'"></strong>.
                        Stack <strong x-text="warningModalStack?.label"></strong> membutuhkan minimal
                        <strong class="font-mono text-red-700" x-text="(warningModalStack?.minRamGb || 4) + ' GB RAM'"></strong>
                        agar proses instalasi Docker dan service berjalan stabil tanpa gagal OOM.
                    </p>
                    <div class="p-3 bg-red-50 border border-red-300 text-xs text-red-900 font-mono">
                        Opsi ini dikunci di server untuk mencegah instalasi gagal kehabisan memori.
                    </div>
                </div>
            </template>

            <template x-if="warningModalMode !== 'ram'">
                <div class="space-y-3">
                    <p class="text-ink leading-relaxed"
                       x-text="warningModalStack?.warningMessage || 'Pastikan Anda memahami ketentuan penggunaan stack ini sebelum melanjutkan.'"></p>
                    <template x-if="Array.isArray(warningModalStack?.warningBullets) && warningModalStack.warningBullets.length > 0">
                        <ul class="space-y-1.5 pl-4 list-disc text-xs text-ink/90"
                            :class="warningModalMode === 'license' ? 'marker:text-amber-600' : 'marker:text-sky-600'">
                            <template x-for="(bullet, bIdx) in warningModalStack.warningBullets" :key="bIdx">
                                <li class="leading-relaxed" x-text="bullet"></li>
                            </template>
                        </ul>
                    </template>
                </div>
            </template>
        </div>

        {{-- Actions --}}
        <div class="px-5 sm:px-6 py-4 bg-creamDark/40 border-t border-hairline flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2.5">
            <template x-if="warningModalMode === 'ram'">
                <div class="flex flex-col-reverse sm:flex-row sm:items-center gap-2.5 w-full sm:w-auto sm:justify-end">
                    <button type="button"
                            @click="closeStackWarning()"
                            class="w-full sm:w-auto rounded-none px-4 py-2.5 border border-ink bg-white hover:bg-creamDark text-ink font-mono text-xs font-bold uppercase cursor-pointer">
                        Tutup
                    </button>
                    @if($modalContext === 'checkout')
                        <a href="{{ route('home') }}#pricing"
                           class="w-full sm:w-auto rounded-none px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white font-mono text-xs font-bold uppercase text-center transition-colors">
                            Pilih Paket ≥ <span x-text="warningModalStack?.minRamGb || 4"></span> GB
                        </a>
                    @else
                        @if(!empty($isAutomated) && !empty($day2['upgrade']))
                            <button type="button"
                                    @click="closeStackWarning(); reinstallModal = false; activeTab = 'day2'"
                                    class="w-full sm:w-auto rounded-none px-5 py-2.5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase cursor-pointer">
                                Upgrade Paket RAM
                            </button>
                        @endif
                        <button type="button"
                                @click="closeStackWarning()"
                                class="w-full sm:w-auto rounded-none px-5 py-2.5 bg-ink hover:bg-electric text-white font-mono text-xs font-bold uppercase cursor-pointer">
                            Pilih Stack Lain
                        </button>
                    @endif
                </div>
            </template>

            <template x-if="warningModalMode === 'license'">
                <div class="flex flex-col-reverse sm:flex-row sm:items-center gap-2.5 w-full sm:w-auto sm:justify-end">
                    <button type="button"
                            @click="closeStackWarning()"
                            class="w-full sm:w-auto rounded-none px-4 py-2.5 border border-ink bg-white hover:bg-creamDark text-ink font-mono text-xs font-bold uppercase cursor-pointer">
                        Batal
                    </button>
                    <button type="button"
                            @click="confirmStackWarning()"
                            class="w-full sm:w-auto rounded-none px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-ink font-mono text-xs font-bold uppercase cursor-pointer">
                        Saya Mengerti, Pilih Panel Ini
                    </button>
                </div>
            </template>

            <template x-if="warningModalMode !== 'ram' && warningModalMode !== 'license'">
                <div class="flex flex-col-reverse sm:flex-row sm:items-center gap-2.5 w-full sm:w-auto sm:justify-end">
                    <button type="button"
                            @click="closeStackWarning()"
                            class="w-full sm:w-auto rounded-none px-4 py-2.5 border border-ink bg-white hover:bg-creamDark text-ink font-mono text-xs font-bold uppercase cursor-pointer">
                        Batal
                    </button>
                    <button type="button"
                            @click="confirmStackWarning()"
                            class="w-full sm:w-auto rounded-none px-5 py-2.5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase cursor-pointer">
                        Mengerti, Pilih Aplikasi Ini
                    </button>
                </div>
            </template>
        </div>
    </div>
</div>
