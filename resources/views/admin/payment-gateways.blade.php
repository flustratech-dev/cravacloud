@extends('layouts.admin', ['title' => 'Payment Gateway', 'headerTitle' => 'Payment Gateway & Webhook', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6" x-data="{ editModalOpen: false, editId: null, editName: '' }">

    @php
        $inputClass = 'w-full px-3 py-2 border border-hairline text-xs text-ink bg-white focus:outline-none focus:border-electric';
        $labelClass = 'block font-mono text-[10px] font-bold text-muted uppercase tracking-widest mb-1.5';
        $failedWebhooks = $failedCount;
    @endphp

    @include('admin.partials.payment-tabs')

    @if($errors->any())
        <div class="bg-white p-5 border-2 border-ink">
            <span class="font-mono text-[10px] font-bold text-ink uppercase tracking-widest block mb-2">Periksa Kembali Isian Anda</span>
            <ul class="list-disc list-inside space-y-1 text-xs text-ink">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Ringkasan -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-px bg-hairline border border-hairline">
        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Gateway Aktif</span>
            <div class="flex items-baseline gap-2">
                <span class="font-display text-2xl font-bold text-electric">{{ $gateways->where('is_active', true)->count() }}</span>
                <span class="font-mono text-[11px] text-electric font-bold">dari {{ $gateways->count() }} Terdaftar</span>
            </div>
        </div>
        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Metode di Checkout</span>
            <div class="flex items-baseline gap-2">
                <span class="font-display text-2xl font-bold text-ink">{{ count($activeMethods) }}</span>
                <span class="font-mono text-[11px] text-muted">Dapat Dipilih</span>
            </div>
        </div>
        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Webhook Berhasil Hari Ini</span>
            <div class="flex items-baseline gap-2">
                <span class="font-display text-2xl font-bold text-ink">{{ $processedToday }}</span>
                <span class="font-mono text-[11px] text-muted">Pembayaran</span>
            </div>
        </div>
        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Webhook Gagal</span>
            <div class="flex items-baseline gap-2">
                <span class="font-display text-2xl font-bold text-ink">{{ $failedCount }}</span>
                <span class="font-mono text-[11px] text-muted">Perlu Diperiksa</span>
            </div>
        </div>
    </div>

    <div class="bg-white p-5 border border-electric">
        <div class="flex items-start gap-3">
            <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase bg-electric text-white shrink-0 mt-0.5">
                Gateway Tunggal
            </span>
            <div class="space-y-1.5">
                <p class="text-xs text-ink leading-relaxed">
                    Seluruh transaksi checkout, perpanjangan, dan upgrade CravaCloud diproses melalui <strong>Midtrans Payment Gateway</strong>. Kredensial dapat diatur di panel ini atau fallback dari berkas <code class="font-mono bg-creamDark px-1">.env</code> server.
                </p>
                <p class="font-mono text-[11px] text-muted">
                    Webhook Notification URL: <code class="bg-creamDark px-1.5 py-0.5 text-ink select-all">{{ url('/api/webhooks/payment') }}</code>
                </p>
            </div>
        </div>
    </div>

    <!-- Tabel Gateway -->
    <div class="bg-white border border-hairline overflow-hidden">
        <div class="px-5 py-4 border-b border-hairline">
            <h3 class="font-display font-bold text-ink text-base">Daftar Payment Gateway</h3>
            <p class="text-xs text-muted mt-0.5">Midtrans beroperasi sebagai gateway tunggal. Kredensial yang kosong di panel otomatis membaca environment (.env).</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-creamDark/60 text-muted font-mono text-[10px] font-bold uppercase tracking-widest border-y border-hairline">
                    <tr>
                        <th class="px-5 py-3.5">Gateway</th>
                        <th class="px-5 py-3.5">Metode Checkout</th>
                        <th class="px-5 py-3.5">Kredensial</th>
                        <th class="px-5 py-3.5 text-center">Mode</th>
                        <th class="px-5 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @foreach($gateways as $gateway)
                        @php
                            $methods = array_keys(array_filter(\App\Models\PaymentGateway::METHOD_GATEWAY, fn ($g) => $g === $gateway->code));
                        @endphp
                        <tr class="hover:bg-creamDark/30 transition-colors">
                            <td class="px-5 py-3.5">
                                <span class="font-bold text-ink text-sm block">{{ $gateway->name }}</span>
                                <span class="text-[11px] text-muted font-mono">{{ $gateway->code }}</span>
                                @if($gateway->description)
                                    <span class="text-[11px] text-muted italic block mt-0.5">{{ $gateway->description }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex flex-wrap gap-1">
                                    @forelse($methods as $method)
                                        <span class="px-1.5 py-0.5 text-[10px] font-bold bg-creamDark text-ink border border-hairline font-mono">{{ $method }}</span>
                                    @empty
                                        <span class="text-[11px] text-muted">—</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                @forelse($gateway->fields() as $field => $label)
                                    @php
                                        $envVal = $gateway->code === 'midtrans' ? (string) config('services.midtrans.' . $field, '') : '';
                                        $envMasked = $envVal !== '' ? (strlen($envVal) <= 8 ? str_repeat('•', strlen($envVal)) : substr($envVal, 0, 4) . str_repeat('•', 6) . substr($envVal, -2)) : null;
                                    @endphp
                                    <div class="text-[11px]">
                                        <span class="text-muted">{{ $label }}:</span>
                                        @if($gateway->hasCredential($field))
                                            <span class="font-mono text-ink font-semibold">{{ $gateway->maskedCredential($field) }}</span>
                                        @elseif($envMasked)
                                            <span class="font-mono text-ink font-semibold">{{ $envMasked }}</span>
                                            <span class="text-muted italic">(.env)</span>
                                        @else
                                            <span class="text-muted italic">belum diisi</span>
                                        @endif
                                    </div>
                                @empty
                                    <span class="text-[11px] text-muted">Tidak memerlukan kredensial</span>
                                @endforelse
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                @if($gateway->mode === 'sandbox')
                                    <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase bg-creamDark text-ink border border-ink">{{ $gateway->mode_label }}</span>
                                @else
                                    <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase bg-creamDark text-ink border border-hairline">{{ $gateway->mode_label }}</span>
                                @endif
                                @if($gateway->code === 'midtrans' && !$gateway->hasCredential('server_key') && (bool) config('services.midtrans.is_production', false) !== ($gateway->mode === 'production'))
                                    <span class="block text-[10px] font-mono text-amber-800 mt-1">
                                        .env = {{ config('services.midtrans.is_production') ? 'Produksi' : 'Sandbox' }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                @if($gateway->is_active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-electric text-white">Aktif</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-creamDark border border-hairline text-muted">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <button type="button" @click="editId = {{ $gateway->id }}; editName = @js($gateway->name); editModalOpen = true"
                                        class="px-3 py-1 font-mono text-[10px] font-bold uppercase bg-white hover:border-ink text-ink border border-hairline cursor-pointer">
                                    Atur
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Atur Gateway -->
    <div x-show="editModalOpen" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-ink/50 flex items-center justify-center p-4"
         style="display: none;" @keydown.escape.window="editModalOpen = false">

        <div @click.away="editModalOpen = false"
             class="bg-white border border-ink shadow-xl max-w-lg w-full overflow-hidden">
            <div class="px-6 py-4 border-b border-hairline flex items-center justify-between bg-creamDark/40">
                <div>
                    <h3 class="font-display font-bold text-ink text-base">Atur Gateway</h3>
                    <p class="text-xs text-muted">Memperbarui <span class="font-semibold text-ink" x-text="editName"></span></p>
                </div>
                <button @click="editModalOpen = false" class="text-muted hover:text-ink p-1 font-mono text-base cursor-pointer">&times;</button>
            </div>

            @foreach($gateways as $gateway)
                <form x-show="editId === {{ $gateway->id }}" x-cloak
                      action="{{ route('admin.gateways.update', $gateway->id) }}" method="POST" class="p-6 space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-2 gap-3 items-end">
                        <div>
                            <label class="{{ $labelClass }}">Mode</label>
                            <select name="mode" class="{{ $inputClass }} font-mono">
                                @foreach(\App\Models\PaymentGateway::MODES as $mode)
                                    <option value="{{ $mode }}" {{ $gateway->mode === $mode ? 'selected' : '' }}>
                                        {{ $mode === 'sandbox' ? 'Sandbox' : 'Produksi' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer pb-2">
                            <input type="checkbox" name="is_active" value="1" {{ $gateway->is_active ? 'checked' : '' }}
                                   class="w-4 h-4 border-ink accent-electric">
                            <span class="text-xs text-ink">Aktif di checkout</span>
                        </label>
                    </div>

                    @foreach($gateway->fields() as $field => $label)
                        <div>
                            <label class="{{ $labelClass }}">{{ $label }}</label>
                            <input type="password" name="credentials[{{ $field }}]" autocomplete="new-password" maxlength="500"
                                   placeholder="{{ $gateway->hasCredential($field) ? 'Tersimpan: ' . $gateway->maskedCredential($field) . ' — kosongkan bila tidak diubah' : 'Belum diisi' }}"
                                   class="{{ $inputClass }} font-mono">
                            @if($gateway->hasCredential($field))
                                <label class="flex items-center gap-2 cursor-pointer mt-1.5">
                                    <input type="checkbox" name="clear[{{ $field }}]" value="1"
                                           class="w-3.5 h-3.5 border-ink accent-electric">
                                    <span class="text-[11px] text-muted">Hapus nilai tersimpan</span>
                                </label>
                            @endif
                        </div>
                    @endforeach

                    @if(empty($gateway->fields()))
                        <p class="text-xs text-muted">Gateway ini tidak memerlukan kredensial.</p>
                    @endif

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-hairline">
                        <button type="button" @click="editModalOpen = false"
                                class="px-4 py-2 bg-white hover:border-ink text-ink font-mono text-xs font-bold uppercase border border-hairline transition-colors cursor-pointer">
                            Batal
                        </button>
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                            Simpan Gateway
                        </button>
                    </div>
                </form>
            @endforeach
        </div>
    </div>

</div>
@endsection