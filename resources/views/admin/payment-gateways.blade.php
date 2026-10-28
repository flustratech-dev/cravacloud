@extends('layouts.admin', ['title' => 'Payment Gateway', 'headerTitle' => 'Payment Gateway & Webhook', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6" x-data="{ editModalOpen: false, editId: null, editName: '' }">

    @php
        $inputClass = 'w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black';
        $labelClass = 'block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1';
        $failedWebhooks = $failedCount;
    @endphp

    @include('admin.partials.payment-tabs')

    @if($errors->any())
        <div class="bg-white p-5 rounded-lg border border-red-200">
            <span class="text-xs font-semibold text-red-600 uppercase tracking-wider block mb-2">Periksa Kembali Isian Anda</span>
            <ul class="list-disc list-inside space-y-1 text-xs text-red-700">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Ringkasan -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Gateway Aktif</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-emerald-600 font-mono-code">{{ $gateways->where('is_active', true)->count() }}</span>
                <span class="text-xs text-emerald-600 font-medium">dari {{ $gateways->count() }} Terdaftar</span>
            </div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Metode di Checkout</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-slate-900 font-mono-code">{{ count($activeMethods) }}</span>
                <span class="text-xs text-slate-500">Dapat Dipilih</span>
            </div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Webhook Berhasil Hari Ini</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-slate-900 font-mono-code">{{ $processedToday }}</span>
                <span class="text-xs text-slate-500">Pembayaran</span>
            </div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Webhook Gagal</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold {{ $failedCount > 0 ? 'text-red-600' : 'text-slate-900' }} font-mono-code">{{ $failedCount }}</span>
                <span class="text-xs text-slate-500">Perlu Diperiksa</span>
            </div>
        </div>
    </div>

    <div class="bg-emerald-50 p-5 rounded-lg border border-emerald-200">
        <div class="flex items-start gap-3">
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300 shrink-0">
                Sistem Terpadu (.env)
            </span>
            <p class="text-xs text-emerald-900 leading-relaxed">
                Seluruh transaksi checkout CravaCloud kini diproses secara terpusat oleh <strong>Xendit Payment Gateway</strong> dengan kredensial produksi yang dikelola langsung melalui berkas <code>.env</code> server. Gateway lama lainnya telah dinonaktifkan secara otomatis.
            </p>
        </div>
    </div>

    <!-- Tabel Gateway -->
    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h3 class="font-bold text-slate-900 text-base">Daftar Payment Gateway</h3>
            <p class="text-xs text-slate-500">Xendit beroperasi sebagai gateway tunggal. Nilai kredensial utama dibaca dari environment (.env).</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider border-y border-slate-200">
                    <tr>
                        <th class="px-5 py-3.5">Gateway</th>
                        <th class="px-5 py-3.5">Metode Checkout</th>
                        <th class="px-5 py-3.5">Kredensial</th>
                        <th class="px-5 py-3.5 text-center">Mode</th>
                        <th class="px-5 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($gateways as $gateway)
                        @php
                            $methods = array_keys(array_filter(\App\Models\PaymentGateway::METHOD_GATEWAY, fn ($g) => $g === $gateway->code));
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-5 py-3.5">
                                <span class="font-bold text-slate-900 text-sm block">{{ $gateway->name }}</span>
                                <span class="text-[11px] text-slate-400 font-mono-code">{{ $gateway->code }}</span>
                                @if($gateway->description)
                                    <span class="text-[11px] text-slate-500 italic block mt-0.5">{{ $gateway->description }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex flex-wrap gap-1">
                                    @forelse($methods as $method)
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200 font-mono-code">{{ $method }}</span>
                                    @empty
                                        <span class="text-[11px] text-slate-400">—</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                @forelse($gateway->fields() as $field => $label)
                                    <div class="text-[11px]">
                                        <span class="text-slate-500">{{ $label }}:</span>
                                        @if($gateway->hasCredential($field))
                                            <span class="font-mono-code text-slate-900">{{ $gateway->maskedCredential($field) }}</span>
                                        @else
                                            <span class="text-slate-400 italic">belum diisi{{ $gateway->code === 'lynk' || $gateway->code === 'midtrans' ? ' (memakai .env)' : '' }}</span>
                                        @endif
                                    </div>
                                @empty
                                    <span class="text-[11px] text-slate-400">Tidak memerlukan kredensial</span>
                                @endforelse
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                @if($gateway->mode === 'sandbox')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-100 text-amber-800 border border-amber-200">{{ $gateway->mode_label }}</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-100 text-slate-700 border border-slate-200">{{ $gateway->mode_label }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                @if($gateway->is_active)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>Aktif
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-slate-50 text-slate-700 border border-slate-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>Nonaktif
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <button type="button" @click="editId = {{ $gateway->id }}; editName = @js($gateway->name); editModalOpen = true"
                                        class="px-2.5 py-1 rounded text-[11px] font-semibold bg-white hover:bg-slate-50 text-slate-700 border border-slate-200">
                                    Atur
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Atur Gateway (satu form per gateway, ditampilkan sesuai editId) -->
    <div x-show="editModalOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;" @keydown.escape.window="editModalOpen = false">

        <div @click.away="editModalOpen = false"
             class="bg-white rounded-lg border border-slate-200 shadow-xl max-w-lg w-full overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-900 text-base">Atur Gateway</h3>
                    <p class="text-xs text-slate-500">Memperbarui <span class="font-semibold text-slate-800" x-text="editName"></span></p>
                </div>
                <button @click="editModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            @foreach($gateways as $gateway)
                <form x-show="editId === {{ $gateway->id }}" x-cloak
                      action="{{ route('admin.gateways.update', $gateway->id) }}" method="POST" class="p-6 space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-2 gap-3 items-end">
                        <div>
                            <label class="{{ $labelClass }}">Mode</label>
                            <select name="mode" class="{{ $inputClass }}">
                                @foreach(\App\Models\PaymentGateway::MODES as $mode)
                                    <option value="{{ $mode }}" {{ $gateway->mode === $mode ? 'selected' : '' }}>
                                        {{ $mode === 'sandbox' ? 'Sandbox' : 'Produksi' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer pb-2">
                            <input type="checkbox" name="is_active" value="1" {{ $gateway->is_active ? 'checked' : '' }}
                                   class="w-4 h-4 rounded border-slate-300 text-black focus:ring-black">
                            <span class="text-sm text-slate-700">Aktif di checkout</span>
                        </label>
                    </div>

                    @foreach($gateway->fields() as $field => $label)
                        <div>
                            <label class="{{ $labelClass }}">{{ $label }}</label>
                            <input type="password" name="credentials[{{ $field }}]" autocomplete="new-password" maxlength="500"
                                   placeholder="{{ $gateway->hasCredential($field) ? 'Tersimpan: ' . $gateway->maskedCredential($field) . ' — kosongkan bila tidak diubah' : 'Belum diisi' }}"
                                   class="{{ $inputClass }} font-mono-code">
                            @if($gateway->hasCredential($field))
                                <label class="flex items-center gap-2 cursor-pointer mt-1.5">
                                    <input type="checkbox" name="clear[{{ $field }}]" value="1"
                                           class="w-3.5 h-3.5 rounded border-slate-300 text-black focus:ring-black">
                                    <span class="text-[11px] text-slate-500">Hapus nilai tersimpan</span>
                                </label>
                            @endif
                        </div>
                    @endforeach

                    @if(empty($gateway->fields()))
                        <p class="text-xs text-slate-500">Gateway ini tidak memerlukan kredensial.</p>
                    @endif

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" @click="editModalOpen = false"
                                class="px-4 py-2 rounded-lg bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold border border-slate-200 transition-colors">
                            Batal
                        </button>
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold transition-colors">
                            Simpan Gateway
                        </button>
                    </div>
                </form>
            @endforeach
        </div>
    </div>

</div>
@endsection
