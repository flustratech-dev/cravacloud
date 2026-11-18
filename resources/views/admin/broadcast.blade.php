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
        $inputClass = 'w-full px-3 py-2 border border-hairline text-xs text-ink bg-white focus:outline-none focus:border-electric';
        $labelClass = 'block font-mono text-[10px] font-bold text-muted uppercase tracking-widest mb-1.5';
    @endphp

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

    <!-- Peringatan nada pesan -->
    <div class="bg-white p-5 border border-ink">
        <div class="flex items-start gap-3">
            <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase bg-ink text-cream shrink-0 mt-0.5">
                Penting
            </span>
            <div>
                <div class="font-display text-sm font-bold text-ink">Jangan Menuduh Penerima Melanggar</div>
                <p class="text-xs text-muted mt-1 leading-relaxed">
                    Broadcast ini menjangkau seluruh pelanggan, yang mayoritasnya tidak melakukan pelanggaran apa pun.
                    Gunakan rumusan gangguan di sisi upstream. Untuk pelanggan yang memang melanggar, pakai menu
                    <a href="{{ route('admin.abuse.index') }}" class="font-semibold text-electric hover:underline">Kasus Pelanggaran</a>.
                </p>
            </div>
        </div>
    </div>

    <!-- Ringkasan penerima -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-px bg-hairline border border-hairline">
        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Seluruh Pelanggan</span>
            <div class="flex items-baseline gap-2">
                <span class="font-display text-2xl font-bold text-ink">{{ $recipientCounts['all'] }}</span>
                <span class="font-mono text-[11px] text-muted">Penerima</span>
            </div>
        </div>
        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Punya Layanan Aktif</span>
            <div class="flex items-baseline gap-2">
                <span class="font-display text-2xl font-bold text-electric">{{ $recipientCounts['active_customers'] }}</span>
                <span class="font-mono text-[11px] text-electric font-bold">Penerima</span>
            </div>
        </div>
        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Instance Bermasalah</span>
            <div class="flex items-baseline gap-2">
                <span class="font-display text-2xl font-bold text-ink">{{ $recipientCounts['affected_instances'] }}</span>
                <span class="font-mono text-[11px] text-muted">Penerima</span>
            </div>
        </div>
        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Broadcast Terakhir</span>
            <div class="flex items-baseline gap-2">
                <span class="font-mono text-sm font-bold text-ink">
                    {{ $lastSent?->sent_at?->timezone('Asia/Jakarta')->format('d M H:i') ?? '—' }}
                </span>
            </div>
        </div>
    </div>

    <!-- Formulir Broadcast -->
    <form action="{{ route('admin.broadcast.send') }}" method="POST"
          class="bg-white border border-hairline overflow-hidden"
          onsubmit="return confirm('Kirim broadcast ke seluruh penerima pada segmen yang dipilih?');">
        @csrf

        <div class="px-5 py-4 border-b border-hairline flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="font-display font-bold text-ink text-base">Susun Broadcast</h3>
                {{-- Literal kurung kurawal ditulis dengan @ agar tidak ikut dikompilasi Blade. --}}
                <p class="text-xs text-muted mt-0.5">Placeholder <span class="font-mono font-semibold text-ink">@{{nama}}</span> otomatis diisi nama tiap penerima.</p>
            </div>
            <a href="{{ route('admin.broadcast.export') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-white hover:border-ink text-ink font-mono text-xs font-bold uppercase tracking-wider border border-hairline transition-colors shrink-0">
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
                          class="{{ $inputClass }} font-mono"></textarea>
            </div>

            <label class="flex items-start gap-2.5 cursor-pointer px-3.5 py-3 border border-hairline bg-creamDark/40">
                <input type="checkbox" name="confirm" value="1" required
                       class="w-4 h-4 mt-0.5 border-ink accent-electric shrink-0">
                <span class="text-xs text-ink leading-relaxed">
                    Saya sudah memeriksa isi pesan dan memastikan tidak ada tuduhan pelanggaran
                    terhadap pelanggan yang tidak bersalah.
                </span>
            </label>
        </div>

        <div class="px-5 py-4 border-t border-hairline flex justify-end">
            <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                Kirim Broadcast
            </button>
        </div>
    </form>

    <!-- Riwayat -->
    <div class="bg-white border border-hairline overflow-hidden">
        <div class="px-5 py-4 border-b border-hairline">
            <h3 class="font-display font-bold text-ink text-base">Riwayat Broadcast</h3>
            <p class="text-xs text-muted mt-0.5">Bukti kapan pemberitahuan dikirim dan ke berapa penerima.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-creamDark/60 text-muted font-mono text-[10px] font-bold uppercase tracking-widest border-y border-hairline">
                    <tr>
                        <th class="px-5 py-3.5">Subjek</th>
                        <th class="px-5 py-3.5">Segmen</th>
                        <th class="px-5 py-3.5 text-center">Terkirim</th>
                        <th class="px-5 py-3.5 text-center">Gagal</th>
                        <th class="px-5 py-3.5">Waktu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @forelse($logs as $log)
                        <tr class="hover:bg-creamDark/30 transition-colors">
                            <td class="px-5 py-3.5">
                                <span class="font-bold text-ink text-sm block">{{ Str::limit($log->subject, 55) }}</span>
                                <span class="text-[11px] text-muted font-mono">
                                    ID: #BC-{{ str_pad($log->id, 3, '0', STR_PAD_LEFT) }}
                                    @if($log->sender) &middot; {{ $log->sender->name }} @endif
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-ink">{{ $log->audience_label }}</td>
                            <td class="px-5 py-3.5 text-center font-mono font-bold text-electric">
                                {{ $log->sent_count }}
                            </td>
                            <td class="px-5 py-3.5 text-center font-mono font-bold {{ $log->failed_count > 0 ? 'text-ink' : 'text-muted' }}">
                                {{ $log->failed_count }}
                            </td>
                            <td class="px-5 py-3.5 font-mono text-muted">
                                {{ $log->sent_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center font-mono text-xs text-muted">
                                Belum ada broadcast yang pernah dikirim.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div class="px-5 py-4 border-t border-hairline">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

</div>
@endsection