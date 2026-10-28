<section id="security-ssh" class="scroll-mt-36 space-y-4">
    <div class="flex items-center gap-2">
        <span class="px-2.5 py-0.5 rounded text-[10px] font-bold font-mono-code bg-slate-900 text-white uppercase">Hardening</span>
        <span class="text-xs font-semibold text-slate-500">SSH Cryptography</span>
    </div>
    <h2 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
        Hardening SSH (Key-Only Authentication)
    </h2>
    <p class="text-slate-600 text-sm leading-relaxed">
        Untuk mematikan 100% risiko peretasan via serangan <em>brute-force dictionary</em> pada akun root, Anda wajib menonaktifkan autentikasi password dan menggantinya dengan pasangan Public/Private Key berbasis Ed25519.
    </p>

    <div class="p-4 rounded-xl border border-slate-200 bg-white text-xs space-y-2 text-slate-700">
        <p class="font-bold text-slate-900">Ubah konfigurasi pada file <code>/etc/ssh/sshd_config</code>:</p>
        <pre class="bg-slate-900 text-slate-100 p-3 rounded-lg font-mono-code text-[11px] overflow-x-auto">
PasswordAuthentication no
PermitEmptyPasswords no
PubkeyAuthentication yes
ChallengeResponseAuthentication no</pre>
        <p class="text-slate-500 text-[11px]">Setelah menyimpan perubahan, jalankan <code class="font-mono-code text-slate-800">sudo systemctl restart sshd</code>. Pastikan Anda telah menguji koneksi key pada terminal baru sebelum menutup sesi yang sedang aktif!</p>
    </div>
</section>
