@extends('layouts.admin', ['title' => 'Pengaturan Sistem', 'headerTitle' => 'Pengaturan Sistem & Branding', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6" x-data="{ tab: '{{ $tab }}' }">

    @php
        $inputClass = 'w-full px-3 py-2 border border-hairline text-xs text-ink bg-white focus:outline-none focus:border-electric';
        $labelClass = 'block font-mono text-[10px] font-bold text-muted uppercase tracking-widest mb-1.5';
        $tabBase = 'px-4 py-2 font-mono text-xs font-bold uppercase tracking-wider transition-colors whitespace-nowrap cursor-pointer';
        $globalOn = $settings->bool('maintenance_global_enabled', false);
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

    @if($globalOn)
        <div class="bg-creamDark p-5 border-2 border-ink">
            <div class="flex items-start gap-3">
                <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase bg-ink text-cream shrink-0 mt-0.5">
                    Aktif
                </span>
                <div>
                    <div class="font-display text-sm font-bold text-ink">Maintenance Global Sedang Menyala</div>
                    <p class="text-xs text-muted mt-0.5">
                        Seluruh situs tertutup untuk pengunjung. Admin dan rute webhook pembayaran tetap dapat diakses.
                    </p>
                </div>
            </div>
        </div>
    @endif

    <!-- Navigasi Tab -->
    <div class="bg-white p-1.5 border border-hairline flex flex-wrap items-center gap-1">
        <button type="button" @click="tab = 'brand'"
                :class="tab === 'brand' ? 'bg-electric text-white' : 'text-ink hover:bg-creamDark/60'"
                class="{{ $tabBase }}">Identitas Merek</button>
        <button type="button" @click="tab = 'company'"
                :class="tab === 'company' ? 'bg-electric text-white' : 'text-ink hover:bg-creamDark/60'"
                class="{{ $tabBase }}">Profil Perusahaan</button>
        <button type="button" @click="tab = 'support'"
                :class="tab === 'support' ? 'bg-electric text-white' : 'text-ink hover:bg-creamDark/60'"
                class="{{ $tabBase }}">Kontak &amp; Dukungan</button>
        <button type="button" @click="tab = 'notification'"
                :class="tab === 'notification' ? 'bg-electric text-white' : 'text-ink hover:bg-creamDark/60'"
                class="{{ $tabBase }}">Notifikasi &amp; Tenggang</button>
        <button type="button" @click="tab = 'mail'"
                :class="tab === 'mail' ? 'bg-electric text-white' : 'text-ink hover:bg-creamDark/60'"
                class="{{ $tabBase }}">Email &amp; Pengirim</button>
        <button type="button" @click="tab = 'maintenance'"
                :class="tab === 'maintenance' ? 'bg-electric text-white' : 'text-ink hover:bg-creamDark/60'"
                class="{{ $tabBase }}">Maintenance</button>
    </div>

    <!-- ============ TAB: IDENTITAS MEREK ============ -->
    <div x-show="tab === 'brand'" x-cloak class="space-y-6">
        <form action="{{ route('admin.settings.brand') }}" method="POST" enctype="multipart/form-data"
              class="bg-white border border-hairline overflow-hidden">
            @csrf
            <div class="px-5 py-4 border-b border-hairline">
                <h3 class="font-display font-bold text-ink text-base">Identitas Merek</h3>
                <p class="text-xs text-muted mt-0.5">Nama, logo, dan warna yang dipakai di seluruh halaman serta surel.</p>
            </div>

            <div class="p-5 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $labelClass }}">Nama Merek</label>
                        <input type="text" name="brand_name" required maxlength="60"
                               value="{{ old('brand_name', $settings->get('brand_name', 'CravaCloud')) }}"
                               class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Warna Aksen</label>
                        <div class="flex items-center gap-2.5">
                            <input type="color" name="brand_accent_color"
                                   value="{{ old('brand_accent_color', $settings->get('brand_accent_color', '#001AFF')) }}"
                                   class="h-10 w-14 border border-hairline bg-white cursor-pointer">
                            <span class="font-mono text-xs text-muted">
                                {{ $settings->get('brand_accent_color', '#001AFF') }}
                            </span>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Tagline</label>
                    <input type="text" name="brand_tagline" maxlength="160"
                           value="{{ old('brand_tagline', $settings->get('brand_tagline', '')) }}"
                           class="{{ $inputClass }}">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $labelClass }}">Logo (maks. 1 MB)</label>
                        @if($settings->get('brand_logo_path'))
                            <img src="{{ asset('storage/' . $settings->get('brand_logo_path')) }}"
                                 alt="Logo" class="h-10 w-auto object-contain mb-2">
                        @endif
                        <input type="file" name="brand_logo" accept="image/*"
                               class="w-full text-xs text-ink file:mr-3 file:px-3 file:py-2 file:border file:border-hairline file:bg-creamDark file:font-mono file:text-[11px] file:font-bold file:text-ink cursor-pointer">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Favicon (maks. 256 KB)</label>
                        @if($settings->get('brand_favicon_path'))
                            <img src="{{ asset('storage/' . $settings->get('brand_favicon_path')) }}"
                                 alt="Favicon" class="h-8 w-8 object-contain mb-2">
                        @endif
                        <input type="file" name="brand_favicon" accept="image/*"
                               class="w-full text-xs text-ink file:mr-3 file:px-3 file:py-2 file:border file:border-hairline file:bg-creamDark file:font-mono file:text-[11px] file:font-bold file:text-ink cursor-pointer">
                    </div>
                </div>
            </div>

            <div class="px-5 py-4 border-t border-hairline flex justify-end bg-creamDark/30">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                    Simpan Identitas Merek
                </button>
            </div>
        </form>
    </div>

    <!-- ============ TAB: PROFIL PERUSAHAAN ============ -->
    <div x-show="tab === 'company'" x-cloak class="space-y-6">
        <form action="{{ route('admin.settings.company') }}" method="POST"
              class="bg-white border border-hairline overflow-hidden">
            @csrf
            <div class="px-5 py-4 border-b border-hairline">
                <h3 class="font-display font-bold text-ink text-base">Profil Perusahaan</h3>
                <p class="text-xs text-muted mt-0.5">Dipakai ulang oleh faktur PDF, faktur cetak, dan surel sistem.</p>
            </div>

            <div class="p-5 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $labelClass }}">Nama Badan Hukum</label>
                        <input type="text" name="company_legal_name" required maxlength="120"
                               value="{{ old('company_legal_name', $settings->get('company_legal_name', '')) }}"
                               class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">NPWP</label>
                        <input type="text" name="company_npwp" maxlength="30"
                               value="{{ old('company_npwp', $settings->get('company_npwp', '')) }}"
                               class="{{ $inputClass }} font-mono">
                    </div>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Alamat</label>
                    <textarea name="company_address" rows="2" maxlength="500"
                              class="{{ $inputClass }}">{{ old('company_address', $settings->get('company_address', '')) }}</textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="{{ $labelClass }}">Kota</label>
                        <input type="text" name="company_city" maxlength="80"
                               value="{{ old('company_city', $settings->get('company_city', '')) }}"
                               class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Kode Pos</label>
                        <input type="text" name="company_postal_code" maxlength="10"
                               value="{{ old('company_postal_code', $settings->get('company_postal_code', '')) }}"
                               class="{{ $inputClass }} font-mono">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Telepon</label>
                        <input type="text" name="company_phone" maxlength="30"
                               value="{{ old('company_phone', $settings->get('company_phone', '')) }}"
                               class="{{ $inputClass }} font-mono">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $labelClass }}">Surel Perusahaan</label>
                        <input type="email" name="company_email" maxlength="120"
                               value="{{ old('company_email', $settings->get('company_email', '')) }}"
                               class="{{ $inputClass }} font-mono">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Situs Web</label>
                        <input type="url" name="company_website" maxlength="120"
                               value="{{ old('company_website', $settings->get('company_website', '')) }}"
                               class="{{ $inputClass }} font-mono">
                    </div>
                </div>

                <div class="pt-4 border-t border-hairline">
                    <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-3">Penanda Tangan Faktur</span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="{{ $labelClass }}">Nama</label>
                            <input type="text" name="invoice_signature_name" maxlength="80"
                                   value="{{ old('invoice_signature_name', $settings->get('invoice_signature_name', '')) }}"
                                   class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label class="{{ $labelClass }}">Jabatan</label>
                            <input type="text" name="invoice_signature_title" maxlength="80"
                                   value="{{ old('invoice_signature_title', $settings->get('invoice_signature_title', '')) }}"
                                   class="{{ $inputClass }}">
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="{{ $labelClass }}">Catatan Kaki Faktur</label>
                        <textarea name="invoice_footer_note" rows="2" maxlength="300"
                                  class="{{ $inputClass }}">{{ old('invoice_footer_note', $settings->get('invoice_footer_note', '')) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="px-5 py-4 border-t border-hairline flex justify-end bg-creamDark/30">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                    Simpan Profil Perusahaan
                </button>
            </div>
        </form>
    </div>

    <!-- ============ TAB: KONTAK & DUKUNGAN ============ -->
    <div x-show="tab === 'support'" x-cloak class="space-y-6">
        <form action="{{ route('admin.settings.support') }}" method="POST"
              class="bg-white border border-hairline overflow-hidden">
            @csrf
            <div class="px-5 py-4 border-b border-hairline">
                <h3 class="font-display font-bold text-ink text-base">Kontak &amp; Dukungan</h3>
                <p class="text-xs text-muted mt-0.5">Tampil di halaman publik, surel, dan halaman maintenance.</p>
            </div>

            <div class="p-5 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $labelClass }}">Surel Dukungan</label>
                        <div class="p-3 bg-creamDark/40 border border-hairline text-xs text-muted space-y-1">
                            <p>Surel kontak dukungan kini dikelola terpusat di tab <button type="button" @click="tab = 'mail'" class="font-bold text-electric hover:underline cursor-pointer">Email &amp; Pengirim</button>.</p>
                            <p class="text-[11px]">Nilai aktif: <span class="font-mono font-bold text-ink">{{ \App\Support\MailIdentity::contactEmail() }}</span></p>
                        </div>
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Nomor WhatsApp</label>
                        <input type="text" name="support_whatsapp" maxlength="30" placeholder="+62..."
                               value="{{ old('support_whatsapp', $settings->get('support_whatsapp', '')) }}"
                               class="{{ $inputClass }} font-mono">
                    </div>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Jam Operasional</label>
                    <input type="text" name="support_hours" maxlength="120"
                           value="{{ old('support_hours', $settings->get('support_hours', '')) }}"
                           class="{{ $inputClass }}">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-4 border-t border-hairline">
                    <div>
                        <label class="{{ $labelClass }}">Instagram</label>
                        <input type="url" name="social_instagram" maxlength="160"
                               value="{{ old('social_instagram', $settings->get('social_instagram', '')) }}"
                               class="{{ $inputClass }} font-mono">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">X / Twitter</label>
                        <input type="url" name="social_twitter" maxlength="160"
                               value="{{ old('social_twitter', $settings->get('social_twitter', '')) }}"
                               class="{{ $inputClass }} font-mono">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">LinkedIn</label>
                        <input type="url" name="social_linkedin" maxlength="160"
                               value="{{ old('social_linkedin', $settings->get('social_linkedin', '')) }}"
                               class="{{ $inputClass }} font-mono">
                    </div>
                </div>
            </div>

            <div class="px-5 py-4 border-t border-hairline flex justify-end bg-creamDark/30">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                    Simpan Kontak Dukungan
                </button>
            </div>
        </form>
    </div>

    <!-- ============ TAB: NOTIFIKASI & TENGGANG ============ -->
    <div x-show="tab === 'notification'" x-cloak class="space-y-6">
        <form action="{{ route('admin.settings.notification') }}" method="POST"
              class="bg-white border border-hairline overflow-hidden">
            @csrf
            <div class="px-5 py-4 border-b border-hairline">
                <h3 class="font-display font-bold text-ink text-base">Notifikasi &amp; Masa Tenggang</h3>
                <p class="text-xs text-muted mt-0.5">Penerima digest harian dan batas tenggang per jenis produk.</p>
            </div>

            <div class="p-5 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $labelClass }}">Surel Penerima Notifikasi Admin</label>
                        <div class="p-3 bg-creamDark/40 border border-hairline text-xs text-muted space-y-1">
                            <p>Penerima notifikasi email admin dikelola terpusat di tab <button type="button" @click="tab = 'mail'" class="font-bold text-electric hover:underline cursor-pointer">Email &amp; Pengirim</button>.</p>
                            <p class="text-[11px]">Nilai aktif: <span class="font-mono font-bold text-ink">{{ implode(', ', \App\Support\MailIdentity::adminRecipients()) }}</span></p>
                        </div>
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Ambang Waktu Tanggap Pemenuhan (menit)</label>
                        <input type="number" name="fulfillment_sla_minutes" required min="5" max="1440"
                               value="{{ old('fulfillment_sla_minutes', $settings->int('fulfillment_sla_minutes', 120)) }}"
                               class="{{ $inputClass }} font-mono">
                    </div>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Nomor WhatsApp Admin (Order Lunas &amp; Pengingat Setup)</label>
                    <input type="text" name="admin_notification_whatsapp" maxlength="200" placeholder="6281234567890, 6289876543210"
                           value="{{ old('admin_notification_whatsapp', $settings->get('admin_notification_whatsapp', '')) }}"
                           class="{{ $inputClass }} font-mono">
                    @error('admin_notification_whatsapp') <p class="font-mono text-[11px] font-bold text-ink mt-1">{{ $message }}</p> @enderror
                    <p class="text-[11px] text-muted mt-1">
                        Boleh lebih dari satu, pisahkan dengan koma. Setiap order lunas langsung dikabarkan ke WA &amp; surel di atas,
                        lalu diingatkan 30 menit, 1 jam, dan tiap 2 jam sampai layanan diserahkan.
                        Gunakan nomor yang <strong class="text-ink">berbeda</strong> dari nomor pengirim WA Gateway.
                    </p>
                    @if (trim((string) $settings->get('admin_notification_whatsapp', '')) === '')
                        <p class="font-mono text-[11px] text-ink mt-1 font-bold">[PERHATIAN] Belum diisi — admin tidak akan menerima notifikasi WhatsApp.</p>
                    @endif
                </div>

                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="digest_enabled" value="1"
                           {{ old('digest_enabled', $settings->bool('digest_enabled', true)) ? 'checked' : '' }}
                           class="w-4 h-4 border-ink accent-electric">
                    <span class="text-xs text-ink font-medium">Aktifkan digest harian ke admin</span>
                </label>

                <div class="pt-4 border-t border-hairline">
                    <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-2">Masa Tenggang per Jenis Produk</span>
                    <div class="border border-ink bg-creamDark/50 p-3.5 mb-4">
                        <p class="text-[11px] text-ink leading-relaxed">
                            Tenggang CravaCloud wajib <strong>lebih pendek</strong> daripada batas Supplier.
                            Supplier menghapus VPS setelah <strong>7 hari</strong> suspensi, sedangkan database
                            dan aplikasi setelah <strong>30 hari</strong>. Selisihnya adalah ruang Anda untuk bertindak.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="{{ $labelClass }}">Tenggang VPS (hari)</label>
                            <input type="number" name="renewal_grace_days_vps" required min="1" max="6"
                                   value="{{ old('renewal_grace_days_vps', $settings->int('renewal_grace_days_vps', 5)) }}"
                                   class="{{ $inputClass }} font-mono">
                            <p class="text-[11px] text-muted mt-1">Batas Supplier: 7 hari</p>
                        </div>
                        <div>
                            <label class="{{ $labelClass }}">Tenggang Database (hari)</label>
                            <input type="number" name="renewal_grace_days_database" required min="1" max="29"
                                   value="{{ old('renewal_grace_days_database', $settings->int('renewal_grace_days_database', 25)) }}"
                                   class="{{ $inputClass }} font-mono">
                            <p class="text-[11px] text-muted mt-1">Batas Supplier: 30 hari</p>
                        </div>
                        <div>
                            <label class="{{ $labelClass }}">Tenggang Aplikasi &amp; AI (hari)</label>
                            <input type="number" name="renewal_grace_days_app" required min="1" max="29"
                                   value="{{ old('renewal_grace_days_app', $settings->int('renewal_grace_days_app', 25)) }}"
                                   class="{{ $inputClass }} font-mono">
                            <p class="text-[11px] text-muted mt-1">Batas Supplier: 30 hari</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-5 py-4 border-t border-hairline flex justify-end bg-creamDark/30">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                    Simpan Pengaturan Notifikasi
                </button>
            </div>
        </form>

        <form action="{{ route('admin.settings.notification.test') }}" method="POST"
              class="bg-white border border-hairline px-5 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            @csrf
            <div>
                <h3 class="font-display font-bold text-ink text-sm">Tes Notifikasi Admin</h3>
                <p class="text-xs text-muted">Kirim pesan uji ke surel &amp; nomor WA admin yang sudah disimpan.</p>
            </div>
            <button type="submit"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 border border-ink bg-white hover:bg-creamDark text-ink font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                Kirim Tes
            </button>
        </form>
    </div>

    <!-- ============ TAB: EMAIL & PENGIRIM ============ -->
    <div x-show="tab === 'mail'" x-cloak class="space-y-6">
        <form action="{{ route('admin.settings.mail') }}" method="POST"
              class="bg-white border border-hairline overflow-hidden">
            @csrf
            <div class="px-5 py-4 border-b border-hairline">
                <h3 class="font-display font-bold text-ink text-base">Identitas Email &amp; Pengirim</h3>
                <p class="text-xs text-muted mt-0.5">
                    Konfigurasi alamat pengirim (noreply &amp; support), Reply-To, dan penerima notifikasi admin.
                </p>
            </div>

            <div class="p-5 space-y-4">
                <div class="border border-electric bg-white p-3.5">
                    <p class="text-[11px] text-ink leading-relaxed">
                        Domain terverifikasi pengirim: <strong class="font-mono">{{ implode(', ', (array) config('mail.allowed_sender_domains', ['cravacloud.id'])) }}</strong>.<br>
                        Field yang dibiarkan kosong akan otomatis menggunakan nilai default dari konfigurasi lingkungan (<code class="font-mono bg-creamDark px-1">.env</code>).
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $labelClass }}">Nama Pengirim Noreply</label>
                        <input type="text" name="mail_noreply_name" maxlength="120"
                               placeholder="{{ config('mail.from.name', 'CravaCloud') }}"
                               value="{{ old('mail_noreply_name', $settings->get('mail_noreply_name', '')) }}"
                               class="{{ $inputClass }}">
                        <p class="text-[11px] text-muted mt-1">Dipakai untuk email otomatis (tagihan, peringatan, sistem).</p>
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Alamat Pengirim Noreply</label>
                        <input type="email" name="mail_noreply_address" maxlength="120"
                               placeholder="{{ config('mail.from.address', 'noreply@cravacloud.id') }}"
                               value="{{ old('mail_noreply_address', $settings->get('mail_noreply_address', '')) }}"
                               class="{{ $inputClass }} font-mono">
                        <p class="text-[11px] text-muted mt-1">Wajib menggunakan domain terverifikasi.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $labelClass }}">Nama Pengirim Support</label>
                        <input type="text" name="mail_support_name" maxlength="120"
                               placeholder="{{ config('mail.support.name', 'CravaCloud Support') }}"
                               value="{{ old('mail_support_name', $settings->get('mail_support_name', '')) }}"
                               class="{{ $inputClass }}">
                        <p class="text-[11px] text-muted mt-1">Dipakai untuk tiket bantuan dan penolakan bayar.</p>
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Alamat Pengirim Support</label>
                        <input type="email" name="mail_support_address" maxlength="120"
                               placeholder="{{ config('mail.support.address', 'support@cravacloud.id') }}"
                               value="{{ old('mail_support_address', $settings->get('mail_support_address', '')) }}"
                               class="{{ $inputClass }} font-mono">
                        <p class="text-[11px] text-muted mt-1">Wajib menggunakan domain terverifikasi.</p>
                    </div>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Alamat Reply-To</label>
                    <input type="email" name="mail_reply_to_address" maxlength="120"
                           placeholder="{{ config('mail.reply_to.address', 'support@cravacloud.id') }}"
                           value="{{ old('mail_reply_to_address', $settings->get('mail_reply_to_address', '')) }}"
                           class="{{ $inputClass }} font-mono">
                    <p class="text-[11px] text-muted mt-1">Semua email (noreply maupun support) akan diarahkan ke alamat ini saat penerima membalas.</p>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Daftar Email Penerima Admin</label>
                    <textarea name="mail_admin_addresses" rows="3"
                              placeholder="{{ implode(', ', (array) config('mail.admin_addresses', ['support@cravacloud.id', 'admin@gmail.com'])) }}"
                              class="{{ $inputClass }} font-mono">{{ old('mail_admin_addresses', $settings->get('mail_admin_addresses', '')) }}</textarea>
                    <p class="text-[11px] text-muted mt-1">
                        Bebas domain. Pisahkan dengan koma atau baris baru. Alert order lunas, provisioning, dan SLA dikirim ke seluruh alamat ini.
                    </p>
                </div>
            </div>

            <div class="px-5 py-3.5 bg-creamDark/30 border-t border-hairline flex justify-end">
                <button type="submit"
                        class="px-5 py-2.5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                    Simpan Pengaturan Email
                </button>
            </div>
        </form>

        <!-- Formulir Tes Kirim Email -->
        <form action="{{ route('admin.settings.mail.test') }}" method="POST"
              class="bg-white border border-hairline p-5 flex flex-col sm:flex-row items-stretch sm:items-end justify-between gap-4">
            @csrf
            <div class="space-y-1 flex-1">
                <h3 class="font-display font-bold text-ink text-sm">Kirim Email Tes Identitas</h3>
                <p class="text-xs text-muted">Uji pengiriman sinkron melalui Brevo SMTP untuk memastikan identitas From dan Reply-To berjalan semestinya.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                    <div>
                        <label class="{{ $labelClass }}">Tipe Identitas</label>
                        <select name="type" class="{{ $inputClass }} font-mono">
                            <option value="noreply">Noreply ({{ \App\Support\MailIdentity::noreply()['address'] }})</option>
                            <option value="support">Support ({{ \App\Support\MailIdentity::support()['address'] }})</option>
                        </select>
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Email Tujuan</label>
                        <input type="email" name="to" required placeholder="admin@domain.com" class="{{ $inputClass }} font-mono">
                    </div>
                </div>
            </div>
            <button type="submit"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 border border-ink bg-white hover:bg-creamDark text-ink font-mono text-xs font-bold uppercase tracking-wider transition-colors shrink-0 cursor-pointer">
                Kirim Email Tes
            </button>
        </form>
    </div>

    <!-- ============ TAB: MAINTENANCE ============ -->
    <div x-show="tab === 'maintenance'" x-cloak class="space-y-6">
        <form action="{{ route('admin.settings.maintenance') }}" method="POST"
              class="bg-white border border-hairline overflow-hidden">
            @csrf
            <div class="px-5 py-4 border-b border-hairline">
                <h3 class="font-display font-bold text-ink text-base">Maintenance Mode</h3>
                <p class="text-xs text-muted mt-0.5">
                    Rute webhook pembayaran selalu dikecualikan agar notifikasi pembayaran tidak pernah tertolak.
                </p>
            </div>

            <div class="p-5 space-y-5">
                <!-- Tingkat 1 -->
                <div class="border border-hairline p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="font-display text-sm font-bold text-ink">Tingkat 1 — Maintenance Global</div>
                            <p class="text-xs text-muted mt-0.5">Seluruh situs tertutup kecuali admin dan rute yang dikecualikan.</p>
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer shrink-0">
                            <input type="checkbox" name="maintenance_global_enabled" value="1"
                                   {{ $globalOn ? 'checked' : '' }}
                                   class="w-4 h-4 border-ink accent-electric">
                            <span class="font-mono text-xs font-bold text-ink uppercase">Aktifkan</span>
                        </label>
                    </div>
                </div>

                <!-- Tingkat 2 -->
                <div class="border border-hairline p-4">
                    <div class="font-display text-sm font-bold text-ink mb-1">Tingkat 2 — Per Cakupan</div>
                    <p class="text-xs text-muted mb-3">Tutup sebagian sistem tanpa menutup seluruhnya.</p>

                    <div class="space-y-2">
                        @foreach($scopeStates as $scope => $state)
                            <label class="flex items-center justify-between gap-3 px-3 py-2 border border-hairline hover:bg-creamDark/30 cursor-pointer">
                                <div class="flex items-center gap-2.5">
                                    <input type="checkbox" name="scope_{{ $scope }}" value="1"
                                           {{ $state['manual'] ? 'checked' : '' }}
                                           class="w-4 h-4 border-ink accent-electric">
                                    <span class="text-xs text-ink font-medium">{{ $state['label'] }}</span>
                                </div>
                                @if($state['window'])
                                    <span class="inline-flex items-center px-2 py-0.5 font-mono text-[10px] font-bold uppercase bg-electric text-white">
                                        Jendela Aktif
                                    </span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Pesan Maintenance</label>
                    <textarea name="maintenance_message" rows="2" maxlength="300"
                              class="{{ $inputClass }}">{{ old('maintenance_message', $settings->get('maintenance_message', '')) }}</textarea>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Daftar IP yang Diizinkan</label>
                    <input type="text" name="maintenance_allowed_ips" maxlength="300"
                           placeholder="103.10.1.5, 103.10.1.6"
                           value="{{ old('maintenance_allowed_ips', $settings->get('maintenance_allowed_ips', '')) }}"
                           class="{{ $inputClass }} font-mono">
                    <p class="text-[11px] text-muted mt-1">Pisahkan dengan koma. IP ini selalu dapat mengakses situs saat maintenance.</p>
                </div>
            </div>

            <div class="px-5 py-4 border-t border-hairline flex justify-end bg-creamDark/30">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                    Simpan Pengaturan Maintenance
                </button>
            </div>
        </form>

        <!-- Token jalur pintas -->
        <div class="bg-white border border-hairline overflow-hidden">
            <div class="px-5 py-4 border-b border-hairline">
                <h3 class="font-display font-bold text-ink text-base">Token Jalur Pintas</h3>
                <p class="text-xs text-muted mt-0.5">Untuk menguji situs dalam mode maintenance tanpa membukanya ke publik.</p>
            </div>
            <div class="p-5">
                <div class="border border-hairline bg-creamDark/40 p-3.5 mb-3">
                    <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Tautan Jalur Pintas</span>
                    <code class="font-mono text-xs text-ink break-all">
                        {{ url('/') }}?bypass={{ $settings->get('maintenance_bypass_token', '(belum dibuat)') }}
                    </code>
                </div>
                <form action="{{ route('admin.settings.bypass-token') }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-white hover:border-ink text-ink font-mono text-xs font-bold uppercase tracking-wider border border-hairline transition-colors cursor-pointer">
                        Buat Ulang Token
                    </button>
                </form>
            </div>
        </div>

        <!-- Status komponen -->
        <div class="bg-white border border-hairline overflow-hidden">
            <div class="px-5 py-4 border-b border-hairline">
                <h3 class="font-display font-bold text-ink text-base">Status Komponen</h3>
                <p class="text-xs text-muted mt-0.5">Status ini tampil di halaman status publik.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-creamDark/60 text-muted font-mono text-[10px] font-bold uppercase tracking-widest border-y border-hairline">
                        <tr>
                            <th class="px-5 py-3.5">Komponen</th>
                            <th class="px-5 py-3.5">Status Saat Ini</th>
                            <th class="px-5 py-3.5">Ubah Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline">
                        @foreach($components as $component)
                            <tr class="hover:bg-creamDark/30 transition-colors">
                                <td class="px-5 py-3.5">
                                    <span class="font-bold text-ink text-sm block">{{ $component->name }}</span>
                                    <span class="text-[11px] text-muted">{{ $component->description }}</span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex items-center px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase {{ $component->status === 'operational' ? 'bg-electric text-white' : ($component->status === 'outage' ? 'bg-ink text-cream' : 'bg-creamDark border border-ink text-ink') }}">
                                        {{ $component->status_label }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <form action="{{ route('admin.settings.components.update', $component->id) }}"
                                          method="POST" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                                        @csrf
                                        <select name="status"
                                                class="px-3 py-2 border border-hairline font-mono text-xs text-ink bg-white focus:outline-none focus:border-electric">
                                            @foreach($componentStatuses as $status)
                                                <option value="{{ $status }}" {{ $component->status === $status ? 'selected' : '' }}>
                                                    {{ ucfirst($status) }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <input type="text" name="status_note" maxlength="160" placeholder="Catatan singkat"
                                               value="{{ $component->status_note }}"
                                               class="px-3 py-2 border border-hairline text-xs text-ink bg-white focus:outline-none focus:border-electric">
                                        <button type="submit"
                                                class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-ink hover:bg-electric text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors shrink-0 cursor-pointer">
                                            Simpan
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection