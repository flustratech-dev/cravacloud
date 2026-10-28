@extends('layouts.app', [
    'title' => 'Checkout Layanan — CravaCloud',
    'description' => 'Selesaikan pemesanan layanan CravaCloud.',
    'robots' => 'noindex, follow',
])

@section('content')
<div class="pt-6 sm:pt-10 pb-36 sm:pb-20 bg-white min-h-screen" id="checkout-wizard-top" x-data="checkoutState()" x-init="init()">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Back link --}}
        <a :href="isDatabasePackage ? '{{ route('home') }}#database-packages' : (isAiPackage ? '{{ route('home') }}#ai-packages' : '{{ route('home') }}#pricing')" class="inline-flex items-center gap-1.5 text-xs sm:text-sm text-slate-500 hover:text-black transition-colors mb-5">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            <span x-text="isDatabasePackage ? 'Kembali ke Katalog Managed Database' : (isAiPackage ? 'Kembali ke Katalog AI Combo' : 'Kembali ke Pilihan Paket VPS')"></span>
        </a>

        {{-- ========================================================
             PROGRESS STEPPER (TOP POSITION - FULLY RESPONSIVE)
             ======================================================== --}}
        {{-- Desktop & Tablet Stepper (sm:block) --}}
        <div class="hidden sm:block mb-8">
            {{-- Direct Checkout Stepper (2 Steps: AI / Managed DB) --}}
            <template x-if="isDirectCheckout">
                <div class="flex items-center justify-between max-w-xl mx-auto px-6 py-4 rounded-2xl bg-slate-50 border border-slate-200/80 shadow-xs">
                    <div class="flex items-center gap-2.5 shrink-0">
                        <span class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0 transition-all"
                              :class="currentSlide === 1 ? 'bg-black text-white ring-4 ring-black/10' : (currentSlide > 1 ? 'bg-emerald-600 text-white shadow-2xs' : 'bg-white text-slate-400 border border-slate-300')">
                            <template x-if="currentSlide > 1">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            </template>
                            <template x-if="currentSlide <= 1"><span>1</span></template>
                        </span>
                        <span class="text-xs md:text-sm font-bold whitespace-nowrap transition-colors"
                              :class="currentSlide === 1 ? 'text-black' : (currentSlide > 1 ? 'text-emerald-700' : 'text-slate-400')">Informasi Akun</span>
                    </div>
                    <div class="flex-1 h-0.5 mx-3 md:mx-5 transition-colors" :class="currentSlide > 1 ? 'bg-emerald-500' : 'bg-slate-200'"></div>
                    <div class="flex items-center gap-2.5 shrink-0">
                        <span class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0 transition-all"
                              :class="currentSlide === 2 ? 'bg-black text-white ring-4 ring-black/10' : 'bg-white text-slate-400 border border-slate-300'">2</span>
                        <span class="text-xs md:text-sm font-bold whitespace-nowrap transition-colors"
                              :class="currentSlide === 2 ? 'text-black' : 'text-slate-400'">Konfirmasi Pembayaran</span>
                    </div>
                </div>
            </template>

            {{-- Standard VPS Stepper (3 Steps) --}}
            <template x-if="!isDirectCheckout">
                <div class="flex items-center justify-between max-w-4xl mx-auto px-6 py-4 rounded-2xl bg-slate-50 border border-slate-200/80 shadow-xs">
                    @foreach(['Konfigurasi VPS', 'Informasi Akun', 'Konfirmasi Pembayaran'] as $index => $step)
                        <div class="flex items-center gap-2.5 shrink-0">
                            <span class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0 transition-all"
                                  :class="currentSlide === {{ $index }} ? 'bg-black text-white ring-4 ring-black/10' : (currentSlide > {{ $index }} ? 'bg-emerald-600 text-white shadow-2xs' : 'bg-white text-slate-400 border border-slate-300')">
                                <template x-if="currentSlide > {{ $index }}">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                </template>
                                <template x-if="currentSlide <= {{ $index }}">
                                    <span>{{ $index + 1 }}</span>
                                </template>
                            </span>
                            <span class="text-xs md:text-sm font-bold whitespace-nowrap transition-colors"
                                  :class="currentSlide === {{ $index }} ? 'text-black' : (currentSlide > {{ $index }} ? 'text-emerald-700' : 'text-slate-400')">
                                {{ $step }}
                            </span>
                        </div>
                        @if($index < 2)
                            <div class="flex-1 h-0.5 mx-2 md:mx-4 transition-colors"
                                 :class="currentSlide > {{ $index }} ? 'bg-emerald-500' : 'bg-slate-200'"></div>
                        @endif
                    @endforeach
                </div>
            </template>
        </div>

        {{-- Mobile Stepper (< sm) --}}
        <div class="sm:hidden mb-5 bg-slate-50 border border-slate-200/80 rounded-2xl p-4 shadow-xs">
            {{-- Step Circles & Tracks --}}
            <div class="flex items-center justify-between gap-1 mb-3">
                <template x-if="!isDirectCheckout">
                    <div class="flex items-center justify-between w-full">
                        @foreach(['Konfigurasi VPS', 'Informasi Akun', 'Konfirmasi Pembayaran'] as $index => $step)
                            <div class="flex items-center gap-1 shrink-0">
                                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-all"
                                      :class="currentSlide === {{ $index }} ? 'bg-black text-white ring-3 ring-black/10' : (currentSlide > {{ $index }} ? 'bg-emerald-600 text-white' : 'bg-white text-slate-400 border border-slate-300')">
                                    <template x-if="currentSlide > {{ $index }}">
                                        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    </template>
                                    <template x-if="currentSlide <= {{ $index }}">
                                        <span>{{ $index + 1 }}</span>
                                    </template>
                                </span>
                            </div>
                            @if($index < 2)
                                <div class="flex-1 h-0.5 mx-1.5 transition-colors"
                                     :class="currentSlide > {{ $index }} ? 'bg-emerald-500' : 'bg-slate-200'"></div>
                            @endif
                        @endforeach
                    </div>
                </template>
                <template x-if="isDirectCheckout">
                    <div class="flex items-center justify-between w-full">
                        <div class="flex items-center gap-1 shrink-0">
                            <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-all"
                                  :class="currentSlide === 1 ? 'bg-black text-white ring-3 ring-black/10' : (currentSlide > 1 ? 'bg-emerald-600 text-white' : 'bg-white text-slate-400 border border-slate-300')">
                                <template x-if="currentSlide > 1">
                                    <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                </template>
                                <template x-if="currentSlide <= 1"><span>1</span></template>
                            </span>
                        </div>
                        <div class="flex-1 h-0.5 mx-1.5 transition-colors" :class="currentSlide > 1 ? 'bg-emerald-500' : 'bg-slate-200'"></div>
                        <div class="flex items-center gap-1 shrink-0">
                            <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-all"
                                  :class="currentSlide === 2 ? 'bg-black text-white ring-3 ring-black/10' : 'bg-white text-slate-400 border border-slate-300'">
                                <span>2</span>
                            </span>
                        </div>
                    </div>
                </template>
            </div>
            {{-- Active Step Label --}}
            <div class="flex items-center justify-between text-xs pt-2.5 border-t border-slate-200/70">
                <span class="text-slate-500 font-medium">Langkah <span x-text="currentStepNumber" class="font-bold text-slate-900"></span> dari <span x-text="totalStepCount" class="font-bold text-slate-900"></span></span>
                <span class="font-bold text-slate-900 bg-white px-2.5 py-0.5 rounded-full border border-slate-200 shadow-2xs text-[11px]" x-text="currentStepTitle"></span>
            </div>
        </div>


        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Left: Configuration Slides --}}
            <div class="lg:col-span-2">
                {{-- Banner Peringatan: Paket Dinonaktifkan Sementara (Maintenance) --}}
                <div x-show="isPackageInactive" @if(!$selectedSpec || $selectedSpec->is_active) x-cloak @endif
                     class="mb-6 p-4 sm:p-5 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 shadow-xs flex items-start gap-3.5">
                    <div class="w-8 h-8 rounded-lg bg-amber-100 border border-amber-200 text-amber-800 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-4 h-4 text-amber-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                            <h3 class="text-sm font-bold text-amber-900">Paket Sementara Dinonaktifkan</h3>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-200/80 text-amber-900 border border-amber-300">Maintenance</span>
                        </div>
                        <p class="text-xs text-amber-800 leading-relaxed">
                            Paket layanan ini sementara waktu dinonaktifkan karena tim CravaCloud sedang melakukan pemeliharaan infrastruktur hingga batas waktu yang belum dapat ditentukan. Pemesanan untuk paket ini ditangguhkan sementara.
                        </p>
                    </div>
                </div>

                <div class="relative overflow-hidden">
                    {{-- Slide 1: Konfigurasi VPS (Khusus VPS biasa, dilewati untuk AI & DB) --}}
                    <div x-show="!isDirectCheckout && currentSlide === 0" x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 translate-x-10" x-transition:enter-end="opacity-100 translate-x-0"
                         x-transition:leave="transition ease-in duration-200"
                         x-transition:leave-start="opacity-100 translate-x-0" x-transition:leave-end="opacity-0 -translate-x-10"
                         class="space-y-6">
                        @include('order.partials.slide-configuration')
                    </div>

                    {{-- Slide 2: Informasi Akun (Start langsung di sini jika paket AI atau Managed DB) --}}
                    <div x-show="currentSlide === 1" x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 translate-x-10" x-transition:enter-end="opacity-100 translate-x-0"
                         x-transition:leave="transition ease-in duration-200"
                         x-transition:leave-start="opacity-100 translate-x-0" x-transition:leave-end="opacity-0 -translate-x-10"
                         class="space-y-6">

                        {{-- Alert khusus paket AI Combo: Konfigurasi Otomatis --}}
                        <template x-if="isAiPackage && currentSpec">
                            <div class="p-4 sm:p-5 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 space-y-2 shadow-xs">
                                <div class="flex items-center justify-between flex-wrap gap-2">
                                    <span class="text-xs font-bold uppercase tracking-wider text-slate-600">Paket AI Combo</span>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-white text-slate-800 border border-slate-200" x-text="currentSpec.badge || 'AI Combo'"></span>
                                </div>
                                <h2 class="text-lg font-bold text-slate-900" x-text="currentSpec.name"></h2>
                            </div>
                        </template>

                        {{-- Alert khusus paket Managed Database: Konfigurasi Otomatis & Pilihan Engine/Tools --}}
                        <template x-if="isDatabasePackage && currentSpec">
                            <div class="space-y-6 mb-6">
                                <div class="p-4 sm:p-5 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 space-y-2 shadow-xs">
                                    <div class="flex items-center justify-between flex-wrap gap-2">
                                        <span class="text-xs font-bold uppercase tracking-wider text-slate-600">Paket Managed Database</span>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200" x-text="currentSpec.badge || 'Dedicated DB'"></span>
                                    </div>
                                    <h2 class="text-lg font-bold text-slate-900" x-text="currentSpec.name"></h2>
                                </div>

                                {{-- Pilihan Database Engine (Visual Radio Selector) --}}
                                <div class="border border-slate-200 rounded-xl p-5 bg-white space-y-3.5 shadow-xs">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-sm font-bold text-slate-900">Pilih Database Engine</h3>
                                        <span class="text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-700">Wajib</span>
                                    </div>

                                    <div class="grid grid-cols-1 gap-2.5">
                                        {{-- PostgreSQL 16 --}}
                                        <label @click="dbEngine = 'postgres'" class="flex items-center gap-3.5 p-3 rounded-lg border cursor-pointer transition-all"
                                               :class="dbEngine === 'postgres' ? 'border-black bg-slate-50 ring-1 ring-black' : 'border-slate-200 hover:border-slate-300 bg-white'">
                                            <input type="radio" name="_radio_db_engine" value="postgres" :checked="dbEngine === 'postgres'" class="text-black focus:ring-black">
                                            <img src="{{ asset('images/logos/databases/postgres.svg') }}" alt="PostgreSQL" class="w-6 h-6 object-contain shrink-0">
                                            <div class="flex-1 min-w-0 flex items-center justify-between gap-2">
                                                <span class="text-xs font-bold text-slate-900">PostgreSQL 16</span>
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-black text-white">Default Recommended</span>
                                            </div>
                                        </label>

                                        {{-- MySQL 8.0 / MariaDB 11 --}}
                                        <label @click="dbEngine = 'mysql'" class="flex items-center gap-3.5 p-3 rounded-lg border cursor-pointer transition-all"
                                               :class="dbEngine === 'mysql' ? 'border-black bg-slate-50 ring-1 ring-black' : 'border-slate-200 hover:border-slate-300 bg-white'">
                                            <input type="radio" name="_radio_db_engine" value="mysql" :checked="dbEngine === 'mysql'" class="text-black focus:ring-black">
                                            <img src="{{ asset('images/logos/databases/mysql.svg') }}" alt="MySQL" class="w-6 h-6 object-contain shrink-0">
                                            <div class="flex-1 min-w-0 flex items-center justify-between gap-2">
                                                <span class="text-xs font-bold text-slate-900">MySQL 8.0 / MariaDB 11</span>
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-black text-white">PHP &amp; CMS</span>
                                            </div>
                                        </label>

                                        {{-- Redis 7 --}}
                                        <label @click="dbEngine = 'redis'" class="flex items-center gap-3.5 p-3 rounded-lg border cursor-pointer transition-all"
                                               :class="dbEngine === 'redis' ? 'border-black bg-slate-50 ring-1 ring-black' : 'border-slate-200 hover:border-slate-300 bg-white'">
                                            <input type="radio" name="_radio_db_engine" value="redis" :checked="dbEngine === 'redis'" class="text-black focus:ring-black">
                                            <img src="{{ asset('images/logos/databases/redis.svg') }}" alt="Redis" class="w-6 h-6 object-contain shrink-0">
                                            <div class="flex-1 min-w-0 flex items-center justify-between gap-2">
                                                <span class="text-xs font-bold text-slate-900">Redis 7 In-Memory Cache</span>
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-black text-white">Cache &amp; Queue</span>
                                            </div>
                                        </label>

                                        {{-- MongoDB 7 --}}
                                        <label @click="dbEngine = 'mongodb'" class="flex items-center gap-3.5 p-3 rounded-lg border cursor-pointer transition-all"
                                               :class="dbEngine === 'mongodb' ? 'border-black bg-slate-50 ring-1 ring-black' : 'border-slate-200 hover:border-slate-300 bg-white'">
                                            <input type="radio" name="_radio_db_engine" value="mongodb" :checked="dbEngine === 'mongodb'" class="text-black focus:ring-black">
                                            <img src="{{ asset('images/logos/databases/mongodb.svg') }}" alt="MongoDB" class="w-6 h-6 object-contain shrink-0">
                                            <div class="flex-1 min-w-0 flex items-center justify-between gap-2">
                                                <span class="text-xs font-bold text-slate-900">MongoDB 7 Community</span>
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-black text-white">NoSQL Document</span>
                                            </div>
                                        </label>

                                        {{-- Qdrant / pgvector --}}
                                        <label @click="dbEngine = 'vector'" class="flex items-center gap-3.5 p-3 rounded-lg border cursor-pointer transition-all"
                                               :class="dbEngine === 'vector' ? 'border-black bg-slate-50 ring-1 ring-black' : 'border-slate-200 hover:border-slate-300 bg-white'">
                                            <input type="radio" name="_radio_db_engine" value="vector" :checked="dbEngine === 'vector'" class="text-black focus:ring-black">
                                            <img src="{{ asset('images/logos/databases/qdrant.svg') }}" alt="Qdrant" class="w-6 h-6 object-contain shrink-0">
                                            <div class="flex-1 min-w-0 flex items-center justify-between gap-2">
                                                <span class="text-xs font-bold text-slate-900">Qdrant / pgvector</span>
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-black text-white">AI &amp; RAG</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                {{-- Pilihan Tools Pengelola Web (Opsional) --}}
                                <div class="border border-slate-200 rounded-xl p-5 bg-white space-y-3.5 shadow-xs">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-sm font-bold text-slate-900">Pilih Tools Pengelola Web (Opsional)</h3>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        {{-- CloudBeaver --}}
                                        <label @click="dbManager = 'cloudbeaver'" class="flex items-center gap-3 p-3.5 rounded-lg border cursor-pointer transition-all"
                                               :class="dbManager === 'cloudbeaver' ? 'border-black bg-slate-50 ring-1 ring-black' : 'border-slate-200 hover:border-slate-300 bg-white'">
                                            <input type="radio" name="_radio_db_manager" value="cloudbeaver" :checked="dbManager === 'cloudbeaver'" class="text-black focus:ring-black">
                                            <img src="{{ asset('images/logos/databases/cloudbeaver.svg') }}" alt="CloudBeaver" class="w-6 h-6 object-contain shrink-0">
                                            <div class="flex-1 min-w-0">
                                                <span class="text-xs font-bold text-slate-900 block">CloudBeaver Web GUI</span>
                                            </div>
                                        </label>

                                        {{-- CLI Only --}}
                                        <label @click="dbManager = 'cli_only'" class="flex items-center gap-3 p-3.5 rounded-lg border cursor-pointer transition-all"
                                               :class="dbManager === 'cli_only' ? 'border-black bg-slate-50 ring-1 ring-black' : 'border-slate-200 hover:border-slate-300 bg-white'">
                                            <input type="radio" name="_radio_db_manager" value="cli_only" :checked="dbManager === 'cli_only'" class="text-black focus:ring-black">
                                            <div class="w-6 h-6 flex items-center justify-center shrink-0 font-mono text-xs font-bold text-slate-800 bg-slate-100 rounded">&gt;_</div>
                                            <div class="flex-1 min-w-0">
                                                <span class="text-xs font-bold text-slate-900 block">CLI Only (Headless)</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Konfigurasi Hostname & Root Password (Khusus AI Combo & Managed Database) --}}
                        <template x-if="isDirectCheckout">
                            <div class="border border-slate-200 rounded-xl p-5 bg-white space-y-4 shadow-xs mb-6">
                                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                    <h3 class="text-sm font-bold text-slate-900">Nama Server &amp; Kredensial Root</h3>
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded bg-rose-50 text-rose-600 border border-rose-200">Wajib</span>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                        Hostname / Server Name <span class="text-rose-600">*</span>
                                    </label>
                                    <input type="text" x-model="vpsName" required minlength="3" maxlength="63" pattern="[A-Za-z0-9][A-Za-z0-9-]*" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm font-mono-code focus:border-black focus:ring-1 focus:ring-black">
                                    <p class="text-[11px] text-slate-500 mt-2 flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                        <span>Password root akan dibuat secara otomatis &amp; aman oleh retail provider kami dan dikirimkan saat instance aktif.</span>
                                    </p>
                                </div>
                            </div>
                        </template>

                        @include('order.partials.slide-account')
                    </div>

                    {{-- Slide 3: Konfirmasi Pembayaran (Langkah Terakhir - Menggantikan Slide 4) --}}
                    <div x-show="currentSlide === 2" x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 translate-x-10" x-transition:enter-end="opacity-100 translate-x-0"
                         x-transition:leave="transition ease-in duration-200"
                         x-transition:leave-start="opacity-100 translate-x-0" x-transition:leave-end="opacity-0 -translate-x-10"
                         class="space-y-6">
                        @include('order.partials.slide-confirmation')
                    </div>
                </div>

                {{-- Navigation Buttons --}}
                <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-3 pt-6 sm:pt-8 mt-6 sm:mt-8 border-t border-slate-200">
                    <div class="w-full sm:w-auto">
                        {{-- Tombol Kembali jika Direct Checkout (AI Package / Managed DB) --}}
                        <template x-if="isDirectCheckout">
                            <div>
                                <button type="button" @click="previousSlide()" x-show="currentSlide > 1"
                                        class="w-full sm:w-auto px-5 py-3 sm:py-2.5 rounded-xl sm:rounded-lg border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-semibold transition-colors flex items-center justify-center gap-1.5">
                                    ← <span>Kembali ke Informasi Akun</span>
                                </button>
                            </div>
                        </template>

                        {{-- Tombol Kembali jika Standard VPS --}}
                        <template x-if="!isDirectCheckout">
                            <button type="button" @click="previousSlide()" x-show="currentSlide > 0"
                                    class="w-full sm:w-auto px-5 py-3 sm:py-2.5 rounded-xl sm:rounded-lg border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-semibold transition-colors flex items-center justify-center gap-1.5">
                                ← <span x-text="currentSlide === 2 ? 'Kembali ke Informasi Akun' : 'Kembali ke Konfigurasi VPS'"></span>
                            </button>
                        </template>
                    </div>

                    {{-- Tombol Lanjut / Submit --}}
                    {{-- Slide 0 (VPS biasa): Menuju Slide 1 --}}
                    <div x-show="!isDirectCheckout && currentSlide === 0" class="w-full sm:w-auto sm:ml-auto">
                        <button type="button" @click="isPackageInactive ? null : nextSlide()"
                                :disabled="isPackageInactive"
                                :class="isPackageInactive ? 'opacity-50 cursor-not-allowed bg-slate-400 hover:bg-slate-400' : 'bg-black hover:bg-neutral-800'"
                                class="w-full sm:w-auto px-6 py-3.5 sm:py-2.5 rounded-xl sm:rounded-lg text-white text-sm font-bold transition-colors flex items-center justify-center gap-2 shadow-xs">
                            <span x-show="!isPackageInactive">Lanjut ke Informasi Akun →</span>
                            <span x-show="isPackageInactive" class="inline-flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                Pemesanan Dikunci
                            </span>
                        </button>
                    </div>

                    {{-- Slide 1: Menuju Slide 2 (Konfirmasi Pembayaran) --}}
                    <div x-show="currentSlide === 1" class="w-full sm:w-auto sm:ml-auto">
                        <button type="button" @click="isPackageInactive ? null : nextSlide()"
                                :disabled="isPackageInactive"
                                :class="isPackageInactive ? 'opacity-50 cursor-not-allowed bg-slate-400 hover:bg-slate-400' : 'bg-black hover:bg-neutral-800'"
                                class="w-full sm:w-auto px-6 py-3.5 sm:py-2.5 rounded-xl sm:rounded-lg text-white text-sm font-bold transition-colors flex items-center justify-center gap-2 shadow-xs">
                            <span x-show="!isPackageInactive">Lanjut ke Konfirmasi Pembayaran →</span>
                            <span x-show="isPackageInactive" class="inline-flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                Pemesanan Dikunci
                            </span>
                        </button>
                    </div>

                    {{-- Slide 2: Eksekusi Langsung Pembayaran (Bayar Sekarang) --}}
                    <div x-show="currentSlide === 2" class="w-full sm:w-auto sm:ml-auto flex flex-col items-stretch sm:items-end gap-1.5">
                        <p x-show="!termsAccepted && !isPackageInactive" x-cloak class="text-xs font-semibold text-slate-500 text-center sm:text-right">
                            Centang persetujuan ketentuan untuk melanjutkan.
                        </p>
                        <p x-show="isPackageInactive" x-cloak class="text-xs font-semibold text-amber-600 text-center sm:text-right">
                            Paket dinonaktifkan sementara untuk pemeliharaan sistem.
                        </p>
                        <button type="button" @click="isPackageInactive ? null : submitCheckout()" :disabled="isSubmitting || !termsAccepted || isPackageInactive"
                                class="w-full sm:w-auto px-7 py-3.5 sm:py-3 rounded-xl sm:rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 shadow-sm">
                            <svg x-show="!isSubmitting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            <svg x-show="isSubmitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span x-text="isSubmitting ? 'Memproses Pesanan...' : (isPackageInactive ? 'Paket Dinonaktifkan' : 'Bayar Sekarang')"></span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Right: Sticky Summary (Desktop only - Mobile has top accordion) --}}
            <div class="hidden lg:block lg:col-span-1">
                <div class="sticky top-24 border border-slate-200 rounded-xl p-6 space-y-5 bg-white shadow-sm">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <h3 class="font-bold text-slate-900 text-base">Ringkasan Pesanan</h3>
                        <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase"
                              :class="isDatabasePackage ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : (isAiPackage ? 'bg-blue-50 text-[#4A6FA5] border border-blue-200' : 'bg-slate-100 text-slate-700')">
                            <span x-text="isDatabasePackage ? 'Managed DB' : (isAiPackage ? 'AI Combo' : 'Cloud VPS')"></span>
                        </span>
                    </div>

                    {{-- Rincian jika Paket Direct Checkout (AI & Database) --}}
                    <template x-if="isDirectCheckout && currentSpec">
                        <div class="space-y-3.5 text-xs text-slate-700">
                            <div>
                                <span class="text-slate-400 block mb-0.5">Nama Paket:</span>
                                <span class="font-bold text-slate-900 text-sm block" x-text="currentSpec.name"></span>
                                <span class="text-slate-500 text-[11px] italic" x-text="currentSpec.tagline"></span>
                            </div>

                            <div class="bg-slate-50 p-3 rounded-lg border border-slate-100 space-y-1">
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Resource:</span>
                                    <span class="font-bold text-slate-900" x-text="currentSpec.cpu + ' Core vCPU · ' + currentSpec.ram + ' GB RAM'"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Storage:</span>
                                    <span class="font-bold text-slate-900" x-text="currentSpec.disk + ' GB NVMe SSD'"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Bandwidth:</span>
                                    <span class="font-bold text-slate-900" x-text="currentSpec.bandwidth + ' Mbps Unmetered'"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Setup:</span>
                                    <span class="font-bold text-slate-900">Pre-configured (Otomatis)</span>
                                </div>
                                <template x-if="isDatabasePackage">
                                    <div class="border-t border-slate-200 pt-2 mt-2 space-y-1">
                                        <div class="flex justify-between">
                                            <span class="text-slate-500">Database Engine:</span>
                                            <span class="font-bold text-[#4A6FA5]" x-text="dbEngineLabel"></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-slate-500">Pengelola Web:</span>
                                            <span class="font-bold text-slate-800" x-text="dbManager === 'cloudbeaver' ? 'CloudBeaver Web GUI' : 'CLI Only'"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <div x-show="parsedFeatures.length > 0">
                                <span class="text-slate-400 block mb-1 font-semibold" x-text="isDatabasePackage ? 'Fitur Server Database:' : 'Bundling Pre-configured:'"></span>
                                <ul class="space-y-1.5 pl-1">
                                    <template x-for="(feat, idx) in parsedFeatures" :key="idx">
                                        <li class="flex items-start gap-1.5 text-[11px] text-slate-600">
                                            <span class="text-emerald-500 font-bold">✓</span>
                                            <span class="leading-tight">
                                                <template x-if="feat.includes(':')">
                                                    <span>
                                                        <strong class="text-slate-900 font-semibold" x-text="feat.split(':')[0] + ':'"></strong>
                                                        <span x-text="feat.substring(feat.indexOf(':') + 1)"></span>
                                                    </span>
                                                </template>
                                                <template x-if="!feat.includes(':')">
                                                    <span x-text="feat"></span>
                                                </template>
                                            </span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                    </template>

                    {{-- Rincian jika Standard VPS --}}
                    <template x-if="!isDirectCheckout">
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span class="text-slate-500">VPS Name</span>
                                <span class="font-medium text-slate-900 font-mono-code" x-text="vpsName || '-'"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Paket</span>
                                <span class="font-medium text-slate-900" x-text="currentSpec ? currentSpec.name : 'Pilih Paket'"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Provider</span>
                                <span class="font-medium text-slate-900" x-text="provider === 'tencent' ? 'Tencent Cloud' : 'Cloudeka by Lintasarta'"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Datacenter</span>
                                <span class="font-medium text-slate-900 capitalize" x-text="datacenter"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">OS</span>
                                <span class="font-medium text-slate-900" x-text="os === 'ubuntu2404' ? 'Ubuntu 24.04 LTS' : 'Ubuntu 22.04 LTS'"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Stack</span>
                                <span class="font-medium text-slate-900" x-text="controlPanelLabel"></span>
                            </div>
                        </div>
                    </template>

                    {{-- Price Calculation breakdown --}}
                    <div class="border-t border-slate-200 pt-4 space-y-2 text-xs">
                        <div class="flex justify-between text-slate-900 font-bold pt-2 border-t border-slate-200">
                            <span>Total Pembayaran</span>
                            <span class="font-mono-code text-lg" x-text="formatRupiah(totalPrice)"></span>
                        </div>
                    </div>

                    {{-- Payment Logos --}}
                    <div class="border-t border-slate-200 pt-4">
                        <p class="text-xs text-slate-500 mb-3 text-center">Metode Pembayaran Tersedia</p>
                        <div class="flex flex-wrap justify-center gap-2.5">
                            @foreach([
                                'qris' => 'QRIS',
                                'va_bca' => 'BCA',
                                'va_mandiri' => 'Mandiri', 
                                'va_bri' => 'BRI',
                                'va_bni' => 'BNI',
                                'va_permata' => 'Permata',
                                'va_cimb' => 'CIMB Niaga',
                                'gopay' => 'GoPay',
                                'ewallet_ovo' => 'OVO',
                                'dana' => 'DANA',
                                'ewallet_shopeepay' => 'ShopeePay'
                            ] as $file => $name)
                                <div class="w-10 h-6 flex items-center justify-center bg-white border border-slate-200 rounded" title="{{ $name }}">
                                    <img src="{{ asset('images/payments/' . $file . '.svg') }}" alt="{{ $name }}" class="h-4">
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ========================================================
         MOBILE FLOATING BOTTOM NAV & ORDER SUMMARY (< lg)
         ======================================================== --}}
    <!-- Backdrop Overlay saat Bottom Summary Dibuka -->
    <div x-show="mobileSummaryOpen" x-cloak
         x-transition:enter="transition-opacity ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="mobileSummaryOpen = false"
         class="lg:hidden fixed inset-0 bg-slate-900/40 backdrop-blur-2xs z-40"></div>

    <!-- Floating Bottom Nav Bar & Expandable Drawer Container -->
    <div class="lg:hidden fixed bottom-3 sm:bottom-4 inset-x-3 sm:inset-x-4 z-50 max-w-lg mx-auto pointer-events-none">
        <div class="pointer-events-auto bg-white/95 backdrop-blur-md border border-slate-200/90 rounded-2xl shadow-xl shadow-slate-900/15 overflow-hidden transition-all duration-300">
            
            <!-- Expanded Content Drawer (Buka / Tutup) -->
            <div x-show="mobileSummaryOpen" x-cloak
                 x-transition:enter="transition-all ease-out duration-250"
                 x-transition:enter-start="opacity-0 -translate-y-2 max-h-0"
                 x-transition:enter-end="opacity-100 translate-y-0 max-h-[75vh]"
                 x-transition:leave="transition-all ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 max-h-[75vh]"
                 x-transition:leave-end="opacity-0 -translate-y-2 max-h-0"
                 class="border-b border-slate-100 overflow-hidden flex flex-col">
                
                <!-- Drawer Top Bar & Header -->
                <div class="px-4 pt-2.5 pb-2.5 bg-slate-50 border-b border-slate-100 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Ringkasan Pesanan</h3>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase"
                              :class="isDatabasePackage ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : (isAiPackage ? 'bg-blue-50 text-[#4A6FA5] border border-blue-200' : 'bg-slate-100 text-slate-700 border border-slate-200')"
                              x-text="isDatabasePackage ? 'Managed DB' : (isAiPackage ? 'AI Combo' : 'Cloud VPS')"></span>
                    </div>
                    <button type="button" @click="mobileSummaryOpen = false"
                            class="w-7 h-7 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200/70 transition-colors flex items-center justify-center"
                            aria-label="Tutup Rincian">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Drawer Scrollable Content -->
                <div class="p-4 space-y-3 max-h-[55vh] overflow-y-auto text-xs bg-white">
                    <!-- Paket & Spesifikasi Utama -->
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 space-y-2">
                        <div class="flex justify-between items-start gap-2">
                            <div>
                                <span class="text-[10px] text-slate-400 uppercase tracking-wider font-semibold block">Paket Layanan</span>
                                <span class="font-bold text-slate-900 text-sm block" x-text="currentSpec ? currentSpec.name : 'Pilih Paket'"></span>
                                <span class="text-[11px] text-slate-500 italic" x-text="currentSpec ? currentSpec.tagline : ''"></span>
                            </div>
                            <span class="font-mono-code font-bold text-slate-900 text-xs shrink-0" x-text="formatRupiah(totalPrice)"></span>
                        </div>

                        <div class="border-t border-slate-200/70 pt-2 grid grid-cols-2 gap-2 text-[11px]">
                            <div>
                                <span class="text-slate-400 block">Resource:</span>
                                <span class="font-semibold text-slate-800" x-text="currentSpec ? (currentSpec.cpu + ' vCPU · ' + currentSpec.ram + ' GB RAM') : '-'"></span>
                            </div>
                            <div>
                                <span class="text-slate-400 block">Storage:</span>
                                <span class="font-semibold text-slate-800" x-text="currentSpec ? (currentSpec.disk + ' GB NVMe SSD') : '-'"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Direct Checkout Specifics (Database & AI) -->
                    <template x-if="isDirectCheckout && currentSpec">
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 space-y-2">
                            <span class="text-[10px] text-slate-400 uppercase tracking-wider font-semibold block">Spesifikasi Layanan</span>
                            <div class="space-y-1.5 text-[11px]">
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Nama Host / Instance:</span>
                                    <span class="font-mono-code text-slate-800 font-semibold" x-text="vpsName || '-'"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Konfigurasi Setup:</span>
                                    <span class="text-slate-800 font-medium">Pre-configured (Otomatis)</span>
                                </div>
                                <template x-if="isDatabasePackage">
                                    <div class="space-y-1 border-t border-slate-200/70 pt-1.5">
                                        <div class="flex justify-between">
                                            <span class="text-slate-500">Database Engine:</span>
                                            <span class="font-bold text-[#4A6FA5]" x-text="dbEngineLabel"></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-slate-500">Panel Kelola Web:</span>
                                            <span class="font-medium text-slate-800" x-text="dbManager === 'cloudbeaver' ? 'CloudBeaver Web GUI' : 'CLI Only'"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- Standard Cloud VPS Specifics -->
                    <template x-if="!isDirectCheckout">
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 space-y-2">
                            <span class="text-[10px] text-slate-400 uppercase tracking-wider font-semibold block">Konfigurasi Server</span>
                            <div class="space-y-1.5 text-[11px]">
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Hostname / VPS:</span>
                                    <span class="font-mono-code text-slate-800 font-semibold" x-text="vpsName || '-'"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Provider:</span>
                                    <span class="text-slate-800 font-medium" x-text="provider === 'tencent' ? 'Tencent Cloud' : 'Cloudeka by Lintasarta'"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Datacenter:</span>
                                    <span class="capitalize text-slate-800 font-medium" x-text="datacenter"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Sistem Operasi:</span>
                                    <span class="text-slate-800 font-medium" x-text="operatingSystems[provider] && operatingSystems[provider][os] ? operatingSystems[provider][os] : (os === 'ubuntu2404' ? 'Ubuntu 24.04 LTS' : 'Ubuntu 22.04 LTS')"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Control Panel / Stack:</span>
                                    <span class="text-slate-800 font-medium" x-text="controlPanelLabel"></span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Biaya & Total -->
                    <div class="border-t border-slate-200/80 pt-2.5 space-y-1.5">
                        <div class="flex justify-between text-slate-600 text-xs">
                            <span>Siklus Penagihan</span>
                            <span class="capitalize font-medium text-slate-900" x-text="billingCycle === 'monthly' ? 'Bulanan' : (billingCycle === 'annually' ? 'Tahunan' : 'Triwulanan')"></span>
                        </div>
                        <div class="flex justify-between items-center pt-2 border-t border-slate-200 text-slate-900 font-bold">
                            <span class="text-xs">Total Tagihan Bersih</span>
                            <span class="font-mono-code text-base text-slate-900 font-extrabold" x-text="formatRupiah(totalPrice)"></span>
                        </div>
                    </div>

                    <!-- Tombol Tutup Rincian -->
                    <button type="button" @click="mobileSummaryOpen = false"
                            class="w-full py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 active:bg-slate-300 text-slate-700 text-xs font-semibold transition-colors text-center cursor-pointer">
                        Tutup Rincian
                    </button>
                </div>
            </div>

            <!-- Persistent Collapsed Floating Bar (Tampil di Bawah, Floating & Klik untuk Buka) -->
            <button type="button"
                    @click="mobileSummaryOpen = !mobileSummaryOpen"
                    class="w-full px-4 py-3 flex items-center justify-between bg-white hover:bg-slate-50 active:bg-slate-100 transition-colors text-left select-none cursor-pointer">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-black text-white flex items-center justify-center shrink-0 shadow-xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-bold text-slate-900 truncate">Ringkasan Pesanan</span>
                            <span class="text-[10px] font-semibold px-1.5 py-0.2 rounded bg-slate-100 text-slate-600 border border-slate-200 shrink-0"
                                  x-text="isDatabasePackage ? 'DB' : (isAiPackage ? 'AI' : 'VPS')"></span>
                        </div>
                        <span class="text-[11px] text-slate-500 block truncate" x-text="specs[selectedSpec] ? specs[selectedSpec].name : 'Pilih Paket'"></span>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 shrink-0 pl-2">
                    <div class="text-right">
                        <span class="text-[10px] text-slate-400 block leading-tight">Total</span>
                        <span class="font-mono-code font-bold text-slate-900 text-sm" x-text="formatRupiah(totalPrice)"></span>
                    </div>
                    <div class="w-7 h-7 rounded-lg bg-slate-100 flex items-center justify-center text-slate-600 transition-transform duration-200"
                         :class="mobileSummaryOpen ? 'rotate-180 bg-slate-200 text-slate-900' : ''">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                        </svg>
                    </div>
                </div>
            </button>

        </div>
    </div>
</div>

<script>
function checkoutState() {
    return {
        currentSlide: 0,
        mobileSummaryOpen: false,
        isSubmitting: false,
        // PHASE 2 - persetujuan ketentuan wajib dicentang sebelum pesanan dikirim.
        termsAccepted: false,
        hasExplicitSpec: {{ (!empty($hasExplicitSpec)) ? 'true' : 'false' }},
        selectedSpec: {{ $selectedSpec ? $selectedSpec->id : 'null' }},
        specs: {{ Js::from($specs->keyBy('id')) }},
        billingCycle: 'monthly',
        provider: 'tencent',
        vpsName: '',
        rootPassword: '',
        showRootPassword: false,
        stackType: 'none',
        controlPanel: 'none',
        datacenter: 'singapore',
        os: 'ubuntu2404',
        dbEngine: 'postgres',
        dbManager: 'cloudbeaver',
        operatingSystems: {
            tencent: {
                windows2012r2: 'Windows Server 2012 R2 DataCenter 64bit EN', windows2016: 'Windows Server 2016 DataCenter 64bit EN', windows2019: 'Windows Server 2019 DataCenter 64bit EN', windows2022: 'Windows Server 2022 DataCenter 64bit EN',
                ubuntu2404: 'Ubuntu Server 24.04 LTS 64bit', ubuntu2204: 'Ubuntu Server 22.04 LTS 64bit', opencloudos9: 'OpenCloudOS 9', opencloudos8: 'OpenCloudOS 8', centos76: 'CentOS 7.6 64bit', centos_stream9: 'CentOS Stream 9 64bit', debian12: 'Debian 12.0 64bit', rocky94: 'Rocky Linux 9.4 64bit', debian11: 'Debian 11.1 64bit', debian10: 'Debian 10.2 64bit'
            },
            cloudeka: { ubuntu2404: 'Ubuntu Server 24.04 LTS 64bit', ubuntu2204: 'Ubuntu Server 22.04 LTS 64bit' }
        },
        paymentMethod: 'online_payment',
        selectedPaymentImage: 'qris.svg',
        paymentMethods: {
            online_payment: { name: 'Pembayaran Otomatis Online (Instan)', image: 'qris.svg', description: 'QRIS, Mandiri VA, BNI VA, BRI VA, CIMB Niaga, Permata, Indomaret, AstraPay, Akulaku' },
            qris: { name: 'QRIS', image: 'qris.svg', description: 'Semua e-wallet & mobile banking instan' },
            mandiri_va: { name: 'Mandiri Virtual Account', image: 'va_mandiri.svg', description: 'Virtual Account otomatis 24 jam' },
            bni_va: { name: 'BNI Virtual Account', image: 'va_bni.svg', description: 'Virtual Account otomatis 24 jam' },
            bri_va: { name: 'BRI Virtual Account', image: 'va_bri.svg', description: 'Virtual Account otomatis 24 jam' },
            cimb_va: { name: 'CIMB Niaga VA', image: 'va_cimb.svg', description: 'Virtual Account otomatis 24 jam' },
            permata_va: { name: 'Permata Virtual Account', image: 'va_permata.svg', description: 'Virtual Account otomatis 24 jam' },
            indomaret: { name: 'Indomaret', image: 'indomaret.svg', description: 'Bayar tunai di gerai Indomaret' },
            astrapay: { name: 'AstraPay', image: 'astrapay.svg', description: 'Aplikasi AstraPay & Dompet Digital' },
            akulaku: { name: 'Akulaku PayLater', image: 'akulaku.svg', description: 'Cicilan & PayLater tanpa kartu' },
            bca_va: { name: 'BCA Virtual Account', image: 'va_bca.svg', description: 'Sedang dinonaktifkan' },
            bsi_va: { name: 'BSI Virtual Account', image: 'va_bsi.svg', description: 'Sedang dinonaktifkan' },
            danamon_va: { name: 'Danamon Virtual Account', image: 'va_danamon.svg', description: 'Sedang dinonaktifkan' },
            seabank_va: { name: 'SeaBank Virtual Account', image: 'va_seabank.svg', description: 'Sedang dinonaktifkan' },
            credit_card: { name: 'Kartu Kredit / Debit', image: 'credit_card.svg', description: 'Sedang dinonaktifkan' },
            gopay: { name: 'GoPay', image: 'gopay.svg', description: 'Sedang dinonaktifkan' },
            ovo: { name: 'OVO', image: 'ewallet_ovo.svg', description: 'Sedang dinonaktifkan' },
            dana: { name: 'DANA', image: 'dana.svg', description: 'Sedang dinonaktifkan' },
            shopeepay: { name: 'ShopeePay', image: 'ewallet_shopeepay.svg', description: 'Sedang dinonaktifkan' }
        },
        qrisSvgDataUri: '{{ app(\App\Services\QrisService::class)->generateDataUri($selectedSpec ? $selectedSpec->sell_price : 80000) }}',

        // Auth & User State
        isLoggedIn: {{ auth()->check() ? 'true' : 'false' }},
        currentUser: {
            id: {{ auth()->id() ?? 'null' }},
            full_name: '{{ addslashes(auth()->user()?->full_name ?? '') }}',
            email: '{{ addslashes(auth()->user()?->email ?? '') }}',
            phone: '{{ addslashes(auth()->user()?->phone ?? '') }}'
        },
        authTab: 'register',
        registerFullName: '',
        registerUsername: '',
        registerEmail: '',
        registerPhone: '',
        registerPassword: '',
        registerPasswordConfirmation: '',
        showRegisterPassword: false,
        showRegisterConfirmPassword: false,
        loginIdentifier: '',
        loginPassword: '',
        showLoginPassword: false,
        isLoggingIn: false,
        loginError: '',
        csrfToken: '{{ csrf_token() }}',

        async refreshQris() {
            try {
                const res = await fetch('/qris/render?amount=' + this.totalPrice);
                const data = await res.json();
                if (data.svg_data_uri) {
                    this.qrisSvgDataUri = data.svg_data_uri;
                }
            } catch (e) {
                console.error('Error refreshing QRIS:', e);
            }
        },

        // Computed properties
        get selectedPaymentMethod() {
            return this.paymentMethods[this.paymentMethod] || null;
        },
        get currentSpec() {
            return this.specs[this.selectedSpec] || null;
        },
        get isPackageInactive() {
            return Boolean(this.currentSpec && !this.currentSpec.is_active);
        },
        get isAiPackage() {
            const s = this.currentSpec;
            if (!s) return false;
            return s.category === 'ai_combo' || Boolean(s.is_ai_package);
        },
        get isDatabasePackage() {
            const s = this.currentSpec;
            if (!s) return false;
            return s.category === 'managed_db' || Boolean(s.is_database_package);
        },
        get isDirectCheckout() {
            return this.isAiPackage || this.isDatabasePackage;
        },
        get totalStepCount() {
            return this.isDirectCheckout ? 2 : 3;
        },
        get currentStepNumber() {
            if (this.isDirectCheckout) {
                return this.currentSlide === 1 ? 1 : 2;
            }
            return this.currentSlide + 1;
        },
        get currentStepTitle() {
            if (this.isDirectCheckout) {
                return this.currentSlide === 1 ? 'Informasi Akun' : 'Konfirmasi Pembayaran';
            }
            const titles = ['Konfigurasi VPS', 'Informasi Akun', 'Konfirmasi Pembayaran'];
            return titles[this.currentSlide] || 'Konfigurasi VPS';
        },
        get dbEngineLabel() {
            const labels = {
                postgres: 'PostgreSQL 16',
                mysql: 'MySQL 8.0 / MariaDB 11',
                redis: 'Redis 7 In-Memory Cache',
                mongodb: 'MongoDB 7 Community',
                vector: 'Qdrant / pgvector (AI & RAG)'
            };
            return labels[this.dbEngine] || 'PostgreSQL 16';
        },
        get parsedFeatures() {
            const s = this.currentSpec;
            if (!s || !s.features) return [];
            if (Array.isArray(s.features)) return s.features;
            try {
                return JSON.parse(s.features);
            } catch (e) {
                return [];
            }
        },
        get currentBaseMonthlyPrice() {
            return this.currentSpec ? parseFloat(this.currentSpec.sell_price) : 80000;
        },
        get totalPrice() {
            return this.currentBaseMonthlyPrice;
        },
        get controlPanelLabel() {
            const labels = {
                none: 'Tanpa Control Panel', coolify: 'Coolify', dokploy: 'Dokploy', aapanel: 'aaPanel',
                cloudpanel: 'CloudPanel', docker: 'Docker', cyberpanel: 'CyberPanel', hestiacp: 'HestiaCP',
                hermes_agent: 'Hermes Agent', openclaw: 'OpenClaw', omniroute: 'OmniRoute', '9router': '9router',
                agent_zero: 'Agent Zero', n8n: 'n8n', ollama: 'Ollama', anythingllm: 'AnythingLLM',
                librechat: 'LibreChat', vscode_server: 'Visual Studio Code Server', gitea_forgejo: 'Gitea / Forgejo',
                uptime_kuma: 'Uptime Kuma', netdata_beszel: 'Netdata / Beszel', wordpress: 'WordPress',
                ghost: 'Ghost', strapi_directus: 'Strapi / Directus', prestashop_bagisto: 'PrestaShop / Bagisto',
                claude_opencode: 'Claude Code & OpenCode CLI', dify_ollama: 'Dify AI + Ollama RAG',
                managed_database: 'Dedicated Managed Database'
            };
            return labels[this.controlPanel] || 'Tanpa Control Panel';
        },
        
        canUseProvider(p) {
            const spec = this.currentSpec;
            if (!spec || !spec.allowed_providers) return true;
            return spec.allowed_providers.includes(p);
        },

        syncProviderWithSpec() {
            const spec = this.currentSpec;
            if (!spec) return;
            if (!this.canUseProvider(this.provider)) {
                this.provider = spec.default_provider || 'tencent';
            }
            if (this.provider === 'cloudeka') {
                this.datacenter = 'indonesia';
            }
            if (!this.operatingSystems[this.provider] || !this.operatingSystems[this.provider][this.os]) {
                this.os = Object.keys(this.operatingSystems[this.provider])[0];
            }
        },

        applyAiPackageDefaults() {
            const spec = this.currentSpec;
            if (!spec) return;
            const clean = (spec.name || 'ai-agent').toLowerCase().replace(/[^a-z0-9]/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
            const rnd = Math.random().toString(36).substring(2, 6);
            if (!this.vpsName || this.vpsName.startsWith('cv-ai-') || this.vpsName.startsWith('cv-db-') || this.vpsName === '') {
                this.vpsName = 'cv-ai-' + clean.substring(0, 14) + '-' + rnd;
            }
            this.controlPanel = spec.default_stack || 'vscode_server';
            this.datacenter = 'indonesia';
            this.os = 'ubuntu2404';
            if (spec.allowed_providers && spec.allowed_providers.length > 0) {
                this.provider = spec.default_provider || spec.allowed_providers[0];
            } else {
                this.provider = 'cloudeka';
            }
        },

        applyDatabaseDefaults() {
            const spec = this.currentSpec;
            if (!spec) return;
            const clean = (spec.name || 'db').toLowerCase().replace(/[^a-z0-9]/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
            const rnd = Math.random().toString(36).substring(2, 6);
            if (!this.vpsName || this.vpsName.startsWith('cv-db-') || this.vpsName.startsWith('cv-ai-') || this.vpsName === '') {
                this.vpsName = 'cv-db-' + clean.substring(0, 14) + '-' + rnd;
            }
            this.controlPanel = spec.default_stack || 'managed_database';
            this.datacenter = 'indonesia';
            this.os = 'ubuntu2404';
            if (!this.dbEngine) this.dbEngine = 'postgres';
            if (!this.dbManager) this.dbManager = 'cloudbeaver';
            if (spec.allowed_providers && spec.allowed_providers.length > 0) {
                this.provider = spec.default_provider || spec.allowed_providers[0];
            } else {
                this.provider = 'tencent';
            }
        },
        
        // Methods
        init() {
            // Restore draft dari localStorage jika ada
            this.restoreDraft();

            if (!this.selectedSpec && Object.keys(this.specs).length > 0) {
                this.selectedSpec = parseInt(Object.keys(this.specs)[0]);
            }
            if (this.isDirectCheckout) {
                if (this.currentSlide === 0) this.currentSlide = 1;
                if (this.isDatabasePackage) {
                    this.applyDatabaseDefaults();
                } else {
                    this.applyAiPackageDefaults();
                }
            } else {
                if (this.controlPanel === 'managed_database') {
                    this.controlPanel = 'none';
                    this.stackType = 'none';
                }
                this.syncProviderWithSpec();
            }

            // Segera sinkronkan draft aktif dengan paket yang sedang dibuka
            this.saveDraft();

            this.$watch('selectedSpec', () => {
                this.refreshQris();
                if (this.isDirectCheckout) {
                    if (this.currentSlide === 0) this.currentSlide = 1;
                    if (this.isDatabasePackage) {
                        this.applyDatabaseDefaults();
                    } else {
                        this.applyAiPackageDefaults();
                    }
                } else {
                    if (this.controlPanel === 'managed_database') {
                        this.controlPanel = 'none';
                        this.stackType = 'none';
                    }
                    this.syncProviderWithSpec();
                }
                this.saveDraft();
            });

            // Watchers untuk auto-save draft ke localStorage
            this.$watch('vpsName', () => this.saveDraft());
            this.$watch('provider', () => this.saveDraft());
            this.$watch('datacenter', () => this.saveDraft());
            this.$watch('os', () => this.saveDraft());
            this.$watch('controlPanel', () => this.saveDraft());
            this.$watch('dbEngine', () => this.saveDraft());
            this.$watch('dbManager', () => this.saveDraft());
            this.$watch('billingCycle', () => this.saveDraft());
            this.$watch('paymentMethod', () => this.saveDraft());
            this.$watch('registerFullName', () => this.saveDraft());
            this.$watch('registerUsername', () => this.saveDraft());
            this.$watch('registerEmail', () => this.saveDraft());
            this.$watch('registerPhone', () => this.saveDraft());
        },

        saveDraft() {
            try {
                const draft = {
                    selectedSpec: this.selectedSpec,
                    billingCycle: this.billingCycle,
                    provider: this.provider,
                    vpsName: this.vpsName,
                    controlPanel: this.controlPanel,
                    datacenter: this.datacenter,
                    os: this.os,
                    dbEngine: this.dbEngine,
                    dbManager: this.dbManager,
                    paymentMethod: this.paymentMethod,
                    registerFullName: this.registerFullName,
                    registerUsername: this.registerUsername,
                    registerEmail: this.registerEmail,
                    registerPhone: this.registerPhone,
                };
                localStorage.setItem('cv_checkout_draft', JSON.stringify(draft));
            } catch (e) {
                // ignore storage error
            }
        },

        restoreDraft() {
            try {
                const raw = localStorage.getItem('cv_checkout_draft');
                if (!raw) return;
                const draft = JSON.parse(raw);

                // Prioritaskan pilihan paket dari URL/tombol yang diklik user.
                // Draft hanya boleh menimpa selectedSpec jika user membuka /checkout tanpa parameter spesifik.
                if (!this.hasExplicitSpec && draft.selectedSpec && this.specs[draft.selectedSpec]) {
                    this.selectedSpec = draft.selectedSpec;
                }

                if (draft.billingCycle) this.billingCycle = draft.billingCycle;
                if (draft.provider && this.canUseProvider(draft.provider)) this.provider = draft.provider;

                // Pastikan draft hostname tidak tertukar antar jenis paket
                if (draft.vpsName) {
                    const isDraftDb = draft.vpsName.startsWith('cv-db-');
                    const isDraftAi = draft.vpsName.startsWith('cv-ai-');
                    if (this.isDatabasePackage && !isDraftDb) {
                        // Biarkan generate default nama database
                    } else if (this.isAiPackage && !isDraftAi) {
                        // Biarkan generate default nama AI
                    } else if (!this.isDatabasePackage && !this.isAiPackage && (isDraftDb || isDraftAi)) {
                        // Reset jika sebelumnya draft DB/AI tapi sekarang memilih VPS biasa
                        this.vpsName = '';
                    } else {
                        this.vpsName = draft.vpsName;
                    }
                }
                if (draft.controlPanel && !this.isDirectCheckout) {
                    if (draft.controlPanel === 'managed_database') {
                        this.controlPanel = 'none';
                        this.stackType = 'none';
                    } else {
                        this.controlPanel = draft.controlPanel;
                        if (draft.controlPanel === 'none') {
                            this.stackType = 'none';
                        } else if (['coolify', 'dokploy', 'aapanel', 'cloudpanel', 'cyberpanel', 'hestiacp'].includes(draft.controlPanel)) {
                            this.stackType = 'control_panel';
                        } else {
                            this.stackType = 'app';
                        }
                    }
                }
                if (draft.datacenter) this.datacenter = draft.datacenter;
                if (draft.os && !this.isDirectCheckout) this.os = draft.os;
                if (draft.dbEngine && this.isDatabasePackage) this.dbEngine = draft.dbEngine;
                if (draft.dbManager && this.isDatabasePackage) this.dbManager = draft.dbManager;
                if (draft.paymentMethod && this.paymentMethods[draft.paymentMethod]) {
                    this.paymentMethod = draft.paymentMethod;
                } else {
                    this.paymentMethod = 'online_payment';
                }
                if (!this.isLoggedIn) {
                    if (draft.registerFullName) this.registerFullName = draft.registerFullName;
                    if (draft.registerUsername) this.registerUsername = draft.registerUsername;
                    if (draft.registerEmail) this.registerEmail = draft.registerEmail;
                    if (draft.registerPhone) this.registerPhone = draft.registerPhone;
                }
            } catch (e) {
                console.warn('Could not restore draft:', e);
            }
        },

        clearDraft() {
            try {
                localStorage.removeItem('cv_checkout_draft');
            } catch (e) {}
        },

        async performQuickLogin() {
            this.loginError = '';
            if (!this.loginIdentifier || !this.loginPassword) {
                this.loginError = 'Harap isi email/username dan password akun Anda.';
                showAlert(this.loginError, { icon: 'warning', title: 'Data Login Belum Lengkap' });
                return false;
            }

            this.isLoggingIn = true;
            try {
                const res = await fetch('/checkout/quick-login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken
                    },
                    body: JSON.stringify({
                        login: this.loginIdentifier,
                        password: this.loginPassword
                    })
                });

                const data = await res.json().catch(() => null);

                if (!res.ok) {
                    this.loginError = data?.message || 'Login gagal. Silakan periksa kembali email/username dan password.';
                    showAlert(this.loginError, { icon: 'error', title: 'Login Gagal' });
                    this.isLoggingIn = false;
                    return false;
                }

                if (data?.requires_2fa) {
                    this.saveDraft();
                    window.location.href = data.redirect || '{{ route('two-factor.challenge') }}';
                    return false;
                }

                if (data?.success && data?.user) {
                    this.currentUser = data.user;
                    this.isLoggedIn = true;
                    if (data.csrf_token) {
                        this.csrfToken = data.csrf_token;
                        document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', data.csrf_token);
                    }
                    this.loginPassword = '';
                    this.saveDraft();
                    showAlert('Berhasil masuk sebagai ' + (this.currentUser.full_name || this.currentUser.email), {
                        icon: 'success',
                        title: 'Login Berhasil'
                    });
                    return true;
                }
                return false;
            } catch (err) {
                console.error('Quick login error:', err);
                this.loginError = 'Terjadi kesalahan jaringan saat mencoba masuk. Silakan coba lagi.';
                showAlert(this.loginError, { icon: 'error', title: 'Login Gagal' });
                return false;
            } finally {
                this.isLoggingIn = false;
            }
        },

        selectStack(type) {
            this.stackType = type;
            this.controlPanel = type === 'none' ? 'none' : (type === 'control_panel' ? 'coolify' : 'hermes_agent');
        },

        selectProvider(provider) {
            if (!this.canUseProvider(provider)) {
                const spec = this.currentSpec;
                const specName = spec ? spec.name : 'Paket ini';
                if (provider === 'tencent') {
                    showAlert(specName + ' hanya tersedia di provider Cloudeka by Lintasarta (Datacenter Indonesia).');
                } else {
                    showAlert(specName + ' hanya tersedia di provider Tencent Cloud.');
                }
                return;
            }
            this.provider = provider;
            if (provider === 'cloudeka') this.datacenter = 'indonesia';
            if (!this.operatingSystems[provider][this.os]) this.os = Object.keys(this.operatingSystems[provider])[0];
        },
        
        formatRupiah(number) {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(number);
        },
        
        scrollToWizardTop() {
            this.$nextTick(() => {
                const el = document.getElementById('checkout-wizard-top');
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
        },

        previousSlide() {
            this.mobileSummaryOpen = false;
            if (this.isDirectCheckout) {
                if (this.currentSlide === 2) {
                    this.currentSlide = 1;
                    this.scrollToWizardTop();
                } else if (this.currentSlide === 1) {
                    window.location.href = this.isDatabasePackage ? "{{ route('home') }}#database-packages" : "{{ route('home') }}#ai-packages";
                }
                return;
            }
            if (this.currentSlide > 0) {
                this.currentSlide--;
                this.scrollToWizardTop();
            }
        },
        
        async nextSlide() {
            this.mobileSummaryOpen = false;
            if (this.isPackageInactive) {
                showAlert('Paket layanan ini sedang dinonaktifkan sementara untuk pemeliharaan sistem.', {
                    icon: 'warning',
                    title: 'Pemesanan Ditangguhkan'
                });
                return;
            }

            if (this.isDirectCheckout) {
                if (this.currentSlide === 1) {
                    if (!this.vpsName || !/^[A-Za-z0-9][A-Za-z0-9-]*$/.test(this.vpsName)) {
                        showAlert('Hostname / Nama Server wajib diisi (gunakan huruf, angka, dan tanda hubung).');
                        return;
                    }
                    if (!await this.validateAccount()) {
                        return;
                    }
                    this.currentSlide = 2;
                    this.scrollToWizardTop();
                }
                return;
            }

            if (this.currentSlide === 0) {
                if (!this.validateConfiguration()) {
                    return;
                }
                this.currentSlide = 1;
                this.scrollToWizardTop();
                return;
            }

            if (this.currentSlide === 1) {
                if (!await this.validateAccount()) {
                    return;
                }
                this.currentSlide = 2;
                this.scrollToWizardTop();
                return;
            }
        },
        
        validateConfiguration() {
            if (!this.vpsName || !/^[A-Za-z0-9][A-Za-z0-9-]*$/.test(this.vpsName)) {
                showAlert('VPS Name wajib diisi dengan huruf, angka, atau tanda hubung.');
                return false;
            }
            if (!this.selectedSpec) {
                showAlert('Silakan pilih paket VPS terlebih dahulu');
                return false;
            }
            if (!this.canUseProvider(this.provider)) {
                const spec = this.currentSpec;
                showAlert('Pilihan provider tidak tersedia untuk ' + (spec ? spec.name : 'paket ini') + '.');
                this.syncProviderWithSpec();
                return false;
            }
            return true;
        },
        
        async validateAccount() {
            if (this.isLoggedIn) {
                return true;
            }

            if (this.authTab === 'login') {
                if (!this.loginIdentifier || !this.loginPassword) {
                    showAlert('Silakan lengkapi Email/Username dan Password akun Anda untuk melanjutkan.');
                    return false;
                }
                // Eksekusi quick login secara otomatis di latar belakang
                const loginSuccess = await this.performQuickLogin();
                return Boolean(loginSuccess);
            }

            if (!this.registerFullName || !this.registerEmail || !this.registerPassword) {
                showAlert('Silakan lengkapi Nama Lengkap, Email, dan Password pendaftaran terlebih dahulu.');
                return false;
            }

            if (this.registerPassword.length < 8) {
                showAlert('Password pendaftaran minimal 8 karakter.');
                return false;
            }

            if (this.registerPasswordConfirmation && this.registerPassword !== this.registerPasswordConfirmation) {
                showAlert('Konfirmasi password tidak cocok dengan password yang Anda masukkan.');
                return false;
            }

            return true;
        },
        
        selectPayment(method, isActive = true) {
            if (!isActive) {
                const methodName = this.paymentMethods[method]?.name || method;
                showAlert('Metode pembayaran ' + methodName + ' sedang tidak aktif. Silakan pilih metode pembayaran lain yang bertanda aktif.', {
                    icon: 'info',
                    title: 'Metode Pembayaran Nonaktif'
                });
                return;
            }
            this.paymentMethod = method;
            this.selectedPaymentImage = this.paymentMethods[method]?.image || 'qris.svg';
        },
        
        async submitCheckout() {
            if (this.isPackageInactive) {
                showAlert('Paket layanan ini sedang dinonaktifkan sementara untuk pemeliharaan sistem.', {
                    icon: 'warning',
                    title: 'Pemesanan Ditangguhkan'
                });
                return;
            }

            if (!this.paymentMethod) {
                showAlert('Silakan pilih metode pembayaran terlebih dahulu');
                return;
            }

            // PHASE 2 - persetujuan ketentuan wajib. Server memvalidasi ulang,
            // pemeriksaan di sini hanya agar pesannya muncul lebih cepat.
            if (!this.termsAccepted) {
                showAlert('Anda wajib menyetujui Ketentuan Layanan dan Ketentuan Penggunaan sebelum melanjutkan pemesanan');
                return;
            }

            if (this.isDatabasePackage) {
                this.applyDatabaseDefaults();
            } else if (this.isAiPackage) {
                this.applyAiPackageDefaults();
            }

            this.isSubmitting = true;
            
            try {
                const formData = {
                    _token: this.csrfToken,
                    vps_spec_id: this.selectedSpec,
                    billing_cycle: this.billingCycle,
                    provider: this.provider,
                    control_panel: this.controlPanel,
                    datacenter_location: this.datacenter,
                    os: this.os,
                    payment_method: this.paymentMethod,
                    db_engine: this.isDatabasePackage ? this.dbEngine : null,
                    db_manager: this.isDatabasePackage ? this.dbManager : null,
                    hostname: this.vpsName,
                    terms_accepted: this.termsAccepted ? 1 : 0,
                    phone: this.isLoggedIn
                        ? (this.currentUser?.phone || '') 
                        : (this.registerPhone || ''),
                };

                if (!this.isLoggedIn) {
                    formData.full_name = this.registerFullName;
                    formData.username = this.registerUsername ? this.registerUsername.trim() : null;
                    formData.email = this.registerEmail;
                    formData.password = this.registerPassword;
                }

                const response = await fetch('/checkout', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken
                    },
                    body: JSON.stringify(formData)
                });

                const data = await response.json().catch(() => null);

                if (!response.ok) {
                    let errorMsg = 'Terjadi kesalahan saat memproses pesanan.';
                    if (data) {
                        if (data.message) {
                            errorMsg = data.message;
                        }
                        if (data.errors) {
                            errorMsg = Object.values(data.errors).flat().join('\n');
                        }
                    }
                    showAlert(errorMsg, {
                        icon: 'error',
                        title: 'Checkout Gagal',
                    });
                    this.isSubmitting = false;
                    return;
                }

                // Hapus draft saat checkout berhasil disubmit
                this.clearDraft();

                // 1. Jika backend mengembalikan snap_token dan script Snap tersedia, buka popup langsung di halaman checkout
                if (data && data.snap_token && window.snap) {
                    this.isSubmitting = false;
                    window.snap.pay(data.snap_token, {
                        onSuccess: (result) => {
                            window.location.href = '/order/payment/' + data.order_id + '/status';
                        },
                        onPending: (result) => {
                            window.location.href = '/order/payment/' + data.order_id + '/status';
                        },
                        onError: (result) => {
                            showAlert('Pembayaran tidak berhasil atau dibatalkan. Anda dapat mengulangi proses pembayaran.', {
                                icon: 'error',
                                title: 'Pembayaran Gagal'
                            });
                            this.isSubmitting = false;
                        },
                        onClose: () => {
                            showAlert('Jendela pembayaran ditutup. Anda dapat melanjutkan pembayaran nanti melalui menu Tagihan di Dashboard.', {
                                icon: 'info',
                                title: 'Pembayaran Ditunda'
                            });
                            this.isSubmitting = false;
                        }
                    });
                    return;
                }

                // 2. Fallback jika snap script terhalang peramban: langsung alihkan ke halaman Midtrans tanpa halaman perantara
                if (data && data.snap_redirect_url) {
                    window.location.href = data.snap_redirect_url;
                    return;
                }

                if (data && data.redirect_url) {
                    window.location.href = data.redirect_url;
                } else if (data && data.order_id) {
                    window.location.href = '/order/payment/' + data.order_id + '/status';
                } else {
                    window.location.href = '/dashboard';
                }
            } catch (error) {
                console.error('Checkout error:', error);
                showAlert('Terjadi kesalahan jaringan atau koneksi terputus. Silakan coba lagi.', {
                    icon: 'error',
                    title: 'Checkout gagal',
                });
                this.isSubmitting = false;
            }
        }
    };
}
</script>
@endsection

@push('scripts')
    @php
        $midtransClientKey = \App\Services\Payments\MidtransService::getClientKey();
        $midtransSnapJsUrl = \App\Services\Payments\MidtransService::getSnapJsUrl();
    @endphp
    @if(!empty($midtransClientKey))
        <script src="{{ $midtransSnapJsUrl }}" data-client-key="{{ $midtransClientKey }}"></script>
    @endif
@endpush
