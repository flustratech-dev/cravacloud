<section id="security-ssh" class="scroll-mt-36 space-y-4">
    <div class="flex items-center gap-2">
        <span class="px-2.5 py-0.5 rounded-none text-[10px] font-bold font-mono bg-electric text-white uppercase">Hardening</span>
        <span class="text-xs font-semibold text-muted">SSH Cryptography</span>
    </div>
    <h2 class="font-display text-xl sm:text-2xl font-bold text-ink uppercase tracking-tight">
        Hardening SSH (Key-Only Authentication)
    </h2>
    <p class="text-muted text-sm leading-relaxed">
        Untuk mematikan 100% risiko peretasan via serangan <em>brute-force dictionary</em> pada akun root, Anda wajib menonaktifkan autentikasi password dan menggantinya dengan pasangan Public/Private Key berbasis Ed25519.
    </p>

    <div class="p-4 rounded-none border border-hairline bg-white text-xs space-y-2 text-ink">
        <p class="font-bold text-ink">Ubah konfigurasi pada file <code>/etc/ssh/sshd_config</code>:</p>
        <pre class="bg-ink text-cream p-3 rounded-none font-mono text-[11px] overflow-x-auto">
PasswordAuthentication no
PermitEmptyPasswords no
PubkeyAuthentication yes
ChallengeResponseAuthentication no</pre>
        <p class="text-muted text-[11px]">Setelah menyimpan perubahan, jalankan <code class="font-mono text-ink">sudo systemctl restart sshd</code>. Pastikan Anda telah menguji koneksi key pada terminal baru sebelum menutup sesi yang sedang aktif!</p>
    </div>
</section>
