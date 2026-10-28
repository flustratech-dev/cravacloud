@extends('layouts.admin', ['title' => 'Broadcast Darurat', 'headerTitle' => 'Broadcast Pemberitahuan Pelanggan', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6" x-data="{
    templates: {{ Js::from($templates->mapWithKeys(fn($t) => [$t->code => ['subject' => $t->subject, 'body' => $t->body]])) }},
    subject: '',
    body: '',
    templateCode: '',
    applyTemplate() {
        if (this.templateCode && this.templates[this.templateCode]) {
            this.subject = this.templates[this.templateCode].subject;
            this.body = this.templates[this.templateCode].body;
        }
    }
}">

    @php
        $inputClass = 'w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black';
        $labelClass = 'block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1';
    @endphp

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

    <!-- Peringatan nada pesan -->
    <div class="bg-white p-5 rounded-lg border border-amber-200">
        <div class="flex items-start gap-3">
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200 shrink-0">
                Penting
            </span>
            <div>
                <div class="text-sm font-bold text-slate-900">Jangan Menuduh Penerima Melanggar</div>
                <p class="text-xs text-slate-600 mt-0.5 leading-relaxed">
                    Broadcast ini menjangkau seluruh pelanggan, yang mayoritasnya tidak melakukan pelanggaran apa pun.
                    Gunakan rumusan gangguan di sisi upstream. Untuk pelanggan yang memang melanggar, pakai menu
                    <a href="{{ route('admin.abuse.index') }}" class="font-semibold text-slate-800 underline underline-offset-2">Kasus Pelanggaran</a>.
                </p>
            </div>
        </div>
    </div>

    <!-- Ringkasan penerima -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Seluruh Pelanggan</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-slate-900 font-mono-code">{{ $recipientCounts['all'] }}</span>
                <span class="text-xs text-slate-500">Penerima</span>
            </div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Punya Layanan Aktif</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-emerald-600 font-mono-code">{{ $recipientCounts['active_customers'] }}</span>
                <span class="text-xs text-emerald-600 font-medium">Penerima</span>
            </div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Instance Bermasalah</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-amber-600 font-mono-code">{{ $recipientCounts['affected_instances'] }}</span>
                <span class="text-xs text-slate-500">Penerima</span>
            </div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Broadcast Terakhir</span>
            <div class="flex items-baseline gap-2">
                <span class="text-sm font-extrabold text-slate-900 font-mono-code">
                    {{ $lastSent?->sent_at?->timezone('Asia/Jakarta')->format('d M H:i') ?? '—' }}
                </span>
            </div>
        </div>
    </div>

    <!-- Formulir Broadcast -->
    <form action="{{ route('admin.broadcast.send') }}" method="POST"
          class="bg-white rounded-lg border border-slate-200 overflow-hidden"
          onsubmit="return confirm('Kirim broadcast ke seluruh penerima pada segmen yang dipilih?');">
        @csrf

        <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="font-bold text-slate-900 text-base">Susun Broadcast</h3>
                {{-- Literal kurung kurawal ditulis dengan @ agar tidak ikut dikompilasi Blade. --}}
                <p class="text-xs text-slate-500">Placeholder <span class="font-mono-code">@{{nama}}</span> otomatis diisi nama tiap penerima.</p>
            </div>
            <a href="{{ route('admin.broadcast.export') }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold border border-slate-200 transition-colors shrink-0">
                Ekspor Kontak CSV
            </a>
        </div>

        <div class="p-5 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $labelClass }}">Segmen Penerima</label>
                    <select name="audience" required class="{{ $inputClass }}">
                        @foreach($audiences as $key => $label)
                            <option value="{{ $key }}">{{ $label }} ({{ $recipientCounts[$key] ?? 0 }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Muat dari Template</label>
                    <select name="template_code" x-model="templateCode" @change="applyTemplate()" class="{{ $inputClass }}">
                        <option value="">— Tulis manual —</option>
                        @foreach($templates as $template)
                            <option value="{{ $template->code }}">{{ $template->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="{{ $labelClass }}">Subjek</label>
                <input type="text" name="subject" x-model="subject" required maxlength="200" class="{{ $inputClass }}">
            </div>

            <div>
                <label class="{{ $labelClass }}">Isi Pesan</label>
                <textarea name="body" x-model="body" rows="12" required maxlength="5000"
                          class="{{ $inputClass }} font-mono-code text-xs"></textarea>
            </div>

            <label class="flex items-start gap-2 cursor-pointer px-3 py-3 rounded-lg border border-slate-200 bg-slate-50">
                <input type="checkbox" name="confirm" value="1" required
                       class="w-4 h-4 mt-0.5 rounded border-slate-300 text-black focus:ring-black shrink-0">
                <span class="text-xs text-slate-700 leading-relaxed">
                    Saya sudah memeriksa isi pesan dan memastikan tidak ada tuduhan pelanggaran
                    terhadap pelanggan yang tidak bersalah.
                </span>
            </label>
        </div>

        <div class="px-5 py-4 border-t border-slate-100 flex justify-end">
            <button type="submit"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold transition-colors">
                Kirim Broadcast
            </button>
        </div>
    </form>

    <!-- Riwayat -->
    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h3 class="font-bold text-slate-900 text-base">Riwayat Broadcast</h3>
            <p class="text-xs text-slate-500">Bukti kapan pemberitahuan dikirim dan ke berapa penerima.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider border-y border-slate-200">
                    <tr>
                        <th class="px-5 py-3.5">Subjek</th>
                        <th class="px-5 py-3.5">Segmen</th>
                        <th class="px-5 py-3.5 text-center">Terkirim</th>
                        <th class="px-5 py-3.5 text-center">Gagal</th>
                        <th class="px-5 py-3.5">Waktu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($logs as $log)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-5 py-3.5">
                                <span class="font-bold text-slate-900 text-sm block">{{ Str::limit($log->subject, 55) }}</span>
                                <span class="text-[11px] text-slate-400 font-mono-code">
                                    ID: #BC-{{ str_pad($log->id, 3, '0', STR_PAD_LEFT) }}
                                    @if($log->sender) &middot; {{ $log->sender->name }} @endif
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-slate-700">{{ $log->audience_label }}</td>
                            <td class="px-5 py-3.5 text-center font-mono-code font-semibold text-emerald-700">
                                {{ $log->sent_count }}
                            </td>
                            <td class="px-5 py-3.5 text-center font-mono-code font-semibold {{ $log->failed_count > 0 ? 'text-red-700' : 'text-slate-400' }}">
                                {{ $log->failed_count }}
                            </td>
                            <td class="px-5 py-3.5 font-mono-code text-slate-600">
                                {{ $log->sent_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-slate-500 text-sm">
                                Belum ada broadcast yang pernah dikirim.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div class="px-5 py-4 border-t border-slate-100">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
