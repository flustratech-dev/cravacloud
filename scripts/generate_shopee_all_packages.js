import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';

const ROOT_DIR = process.cwd();
const OUTPUT_BASE = path.join(ROOT_DIR, 'public', 'images', 'shopee');
const PKG_DIR = path.join(OUTPUT_BASE, 'packages');
const LOGO_PATH = path.join(ROOT_DIR, 'public', 'images', 'logo.png');

if (!fs.existsSync(PKG_DIR)) {
  fs.mkdirSync(PKG_DIR, { recursive: true });
}

// Convert Logo to base64
const logoBuffer = fs.readFileSync(LOGO_PATH);
const logoBase64 = `data:image/png;base64,${logoBuffer.toString('base64')}`;

// Reusable SVG Icons
const icons = {
  cpu: `<svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 1v3M15 1v3M9 20v3M15 20v3M20 9h3M20 14h3M1 9h3M1 14h3"/></svg>`,
  ram: `<svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 12h.01M10 12h.01M14 12h.01M18 12h.01M6 18v2M10 18v2M14 18v2M18 18v2"/></svg>`,
  disk: `<svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="18" rx="2"/><path d="M7 8h10M7 12h10M7 16h4"/></svg>`,
  network: `<svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/></svg>`,
  shield: `<svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>`,
  bolt: `<svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>`,
  terminal: `<svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>`,
  check: `<svg width="20" height="20" fill="none" stroke="#6ABD73" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>`,
  checkSolid: `<svg width="18" height="18" viewBox="0 0 20 20" fill="#38BDF8"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>`,
  star: `<svg width="18" height="18" fill="#F59E0B" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>`,
  server: `<svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>`,
  database: `<svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>`,
  flagId: `<span style="display:inline-flex; width:22px; height:15px; border-radius:2px; overflow:hidden; border:1px solid rgba(255,255,255,0.2);"><span style="flex:1; background:#ef4444;"></span><span style="flex:1; background:#ffffff;"></span></span>`
};

// Base HTML Wrapper for 1200x1200px Canvas
function wrapHtml(content) {
  return `<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>CravaCloud Shopee Banner</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    width: 1200px;
    height: 1200px;
    background-color: #0B0F19;
    background-image: 
      radial-gradient(circle at 15% 15%, rgba(74, 111, 165, 0.35) 0%, transparent 45%),
      radial-gradient(circle at 85% 85%, rgba(106, 189, 115, 0.20) 0%, transparent 45%),
      radial-gradient(circle at 50% 50%, rgba(56, 189, 248, 0.08) 0%, transparent 60%);
    color: #F8FAFC;
    font-family: 'Plus Jakarta Sans', sans-serif;
    overflow: hidden;
    position: relative;
  }
  .mono { font-family: 'JetBrains Mono', monospace; }
  
  .canvas-frame {
    width: 1152px;
    height: 1152px;
    margin: 24px;
    border-radius: 28px;
    border: 1.5px solid rgba(74, 111, 165, 0.45);
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(20px);
    box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.7), inset 0 1px 0 rgba(255, 255, 255, 0.1);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 34px 40px;
    position: relative;
    overflow: hidden;
  }
  
  .canvas-frame::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: 
      linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none;
    z-index: 0;
  }

  .content-layer {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    height: 100%;
    justify-content: space-between;
  }

  .top-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 20px;
    border-bottom: 1.5px solid rgba(255, 255, 255, 0.08);
  }
  .brand-group {
    display: flex;
    align-items: center;
    gap: 16px;
  }
  .brand-logo {
    height: 50px;
    width: auto;
    object-fit: contain;
    filter: drop-shadow(0 4px 12px rgba(74, 111, 165, 0.4));
  }
  .brand-title {
    font-size: 27px;
    font-weight: 800;
    letter-spacing: -0.5px;
    color: #ffffff;
  }
  .brand-title span {
    color: #4A6FA5;
  }
  .store-tag {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(74, 111, 165, 0.18);
    border: 1.5px solid rgba(74, 111, 165, 0.6);
    padding: 8px 18px;
    border-radius: 9999px;
    font-size: 15px;
    font-weight: 700;
    color: #38BDF8;
    box-shadow: 0 0 15px rgba(56, 189, 248, 0.2);
  }
  .digital-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, rgba(106, 189, 115, 0.2), rgba(16, 185, 129, 0.3));
    border: 1.5px solid rgba(106, 189, 115, 0.7);
    padding: 8px 18px;
    border-radius: 9999px;
    font-size: 15px;
    font-weight: 800;
    color: #6ABD73;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .bottom-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 18px;
    border-top: 1.5px solid rgba(255, 255, 255, 0.08);
    font-size: 15px;
    color: #94A3B8;
  }
  .footer-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
  }
  .footer-item span.highlight {
    color: #ffffff;
  }

  .badge-category {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(56, 189, 248, 0.12);
    border: 1px solid rgba(56, 189, 248, 0.4);
    color: #38BDF8;
    padding: 6px 16px;
    border-radius: 8px;
    font-size: 13.5px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
  }
  .glow-title {
    font-size: 46px;
    font-weight: 900;
    line-height: 1.15;
    letter-spacing: -1px;
    color: #FFFFFF;
    text-shadow: 0 4px 20px rgba(0,0,0,0.5);
  }
  .glow-title span.accent {
    background: linear-gradient(135deg, #38BDF8 0%, #4A6FA5 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
  }
  .glow-title span.green {
    background: linear-gradient(135deg, #6ABD73 0%, #10B981 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
  }
  .price-box {
    background: linear-gradient(135deg, #1E293B, #0F172A);
    border: 2px solid rgba(245, 158, 11, 0.6);
    box-shadow: 0 10px 30px rgba(245, 158, 11, 0.15);
    border-radius: 20px;
    padding: 16px 26px;
    display: inline-flex;
    flex-direction: column;
  }
  .price-label {
    font-size: 13px;
    font-weight: 700;
    color: #FBBF24;
    text-transform: uppercase;
    letter-spacing: 1px;
  }
  .price-val {
    font-size: 40px;
    font-weight: 900;
    color: #FFFFFF;
    line-height: 1.1;
  }
  .price-val span {
    font-size: 17px;
    font-weight: 600;
    color: #94A3B8;
  }

  .pill-spec {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: rgba(30, 41, 59, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 12px;
    padding: 11px 16px;
    font-size: 15px;
    font-weight: 700;
    color: #F1F5F9;
  }
  .pill-spec svg {
    color: #38BDF8;
  }
</style>
</head>
<body>
<div class="canvas-frame">
  <div class="content-layer">
    <!-- Header -->
    <div class="top-bar">
      <div class="brand-group">
        <img src="${logoBase64}" alt="CravaCloud Logo" class="brand-logo">
        <div class="brand-title">Crava<span>Host</span></div>
      </div>
      <div class="store-tag">
        ${icons.checkSolid}
        Official Store: <strong>cravacloud</strong>
      </div>
      <div class="digital-badge">
        ⚡ INSTANT DELIVERY 24/7
      </div>
    </div>

    <!-- Main Content Area -->
    ${content}

    <!-- Footer -->
    <div class="bottom-bar">
      <div class="footer-item">
        ${icons.flagId}
        <span>Datacenter Jakarta Indonesia (<10ms)</span>
      </div>
      <div class="footer-item">
        ${icons.shield}
        <span>Garansi Uptime 99.9% • Full Root SSH</span>
      </div>
      <div class="footer-item">
        <span style="color:#FBBF24;">★★★★★</span>
        <span>Shopee: <span class="highlight">cravacloud</span></span>
      </div>
    </div>
  </div>
</div>
</body>
</html>`;
}

// -------------------------------------------------------------
// DEFINITION OF ALL 13 INDIVIDUAL PACKAGES
// -------------------------------------------------------------

const allPackages = [
  // 1. Student Basic
  {
    id: '01_student_basic',
    catSlug: 'vps',
    catName: 'CLOUD VPS REGULER',
    name: 'Student Basic',
    badge: 'Entry Level',
    price: 'Rp 80.000',
    tagline: 'Cocok untuk belajar Linux, portofolio web dasar, & mini server pemula.',
    audience: 'Pelajar, Mahasiswa Baru, Pemula Linux',
    specs: { cpu: '1 Core vCPU', ram: '1 GB RAM DDR4', disk: '20 GB NVMe Gen4', bw: '30 Mbps Fair Use' },
    stack: 'Linux (Ubuntu/Debian) + Full Root SSH',
    features: [
      '1 Core vCPU High Performance KVM',
      '1 GB RAM DDR4 + Swap Memory Otomatis',
      '20 GB Enterprise NVMe PCIe RAID-10',
      'Dedicated Static Public IPv4',
      'Port 1 Gbps (Datacenter Jakarta <10ms)',
      'Akses Penuh Root SSH Tanpa Restriksi'
    ],
    deepSpecs: [
      { label: 'Processor (vCPU)', val: '1 Core vCPU KVM Virtualization', note: 'Komputasi stabil untuk web dasar' },
      { label: 'Memory (RAM)', val: '1 GB DDR4 + 1 GB Swap Memory', note: 'Anti crash saat menjalankan service' },
      { label: 'Storage NVMe', val: '20 GB Enterprise NVMe RAID-10', note: 'Speed read/write hingga 3000 MB/s' },
      { label: 'Network & Port', val: 'Port 1 Gbps • 30 Mbps Fair Use', note: 'Latensi lokal super rendah <10ms' },
      { label: 'IP Publik', val: '1 Dedicated Static IPv4 Publik', note: 'Bisa diarahkan ke custom domain' },
      { label: 'Akses & Kontrol', val: 'Full Root Access via SSH / PuTTY', note: 'Kebebasan penuh instalasi aplikasi' },
    ],
    reasons: [
      { title: 'Harga Paling Ramah Kantong', desc: 'Hanya Rp 80rb/bln flat, solusi paling hemat untuk belajar sysadmin & hosting web pertama Anda.', icon: 'bolt' },
      { title: 'Datacenter Tier-3 Jakarta', desc: 'Server berada di Indonesia dengan latensi sangat rendah (3-10ms), anti lag saat diakses.', icon: 'flagId' },
      { title: '100% Enterprise NVMe SSD', desc: 'Bukan SSD SATA biasa. Booting dan respon sistem terasa sangat cepat dan responsif.', icon: 'disk' },
      { title: 'Panduan Ramah Pemula', desc: 'Belum pernah pakai VPS? Tim support siap membantu cara login SSH dan instalasi dasar via Chat Shopee.', icon: 'terminal' },
      { title: 'Bebas Install Software Apa Saja', desc: 'Mendukung Nginx, Apache, PHP, Python, Node.js, Git, bot Discord/Telegram, dsb.', icon: 'server' },
      { title: 'Garansi Aktif & Uptime 99.9%', desc: 'Server aktif 24 jam nonstop dengan proteksi DDoS terintegrasi.', icon: 'shield' },
    ]
  },

  // 2. Mahasiswa Basic
  {
    id: '02_mahasiswa_basic',
    catSlug: 'vps',
    catName: 'CLOUD VPS REGULER',
    name: 'Mahasiswa Basic',
    badge: 'Khusus Mahasiswa',
    price: 'Rp 90.000',
    tagline: 'Resource 2 vCPU lega untuk praktikum kuliah, mini server & bot 24 jam.',
    audience: 'Mahasiswa IT, Sistem Informasi, Praktikum & Tugas Akhir',
    specs: { cpu: '2 Core vCPU', ram: '2 GB RAM DDR4', disk: '40 GB NVMe Gen4', bw: '30 Mbps Fair Use' },
    stack: 'Linux + Bebas Install Panel & Framework',
    features: [
      '2 Core vCPU Lega (Multi-tasking Kuat)',
      '2 GB RAM DDR4 + Alokasi Swap Memory',
      '40 GB Enterprise NVMe PCIe RAID-10',
      'Dedicated Static Public IPv4',
      'Datacenter Jakarta Tier-3 (<10ms)',
      'Bebas Reinstall OS Ubuntu / Debian'
    ],
    deepSpecs: [
      { label: 'Processor (vCPU)', val: '2 Core vCPU KVM Dedicated Thread', note: 'Mampu handle komputasi praktikum & API' },
      { label: 'Memory (RAM)', val: '2 GB DDR4 RAM + Virtual Swap', note: 'Cukup untuk Laravel + MySQL / Node.js' },
      { label: 'Storage NVMe', val: '40 GB Enterprise NVMe RAID-10', note: 'Kapasitas lega untuk database & file tugas' },
      { label: 'Network & Port', val: 'Port 1 Gbps • 30 Mbps Fair Use', note: 'Koneksi secepat kilat ke provider lokal' },
      { label: 'IP Publik', val: '1 Dedicated Static IPv4 Publik', note: 'Bisa custom domain .id atau .com' },
      { label: 'Akses & Kontrol', val: 'Akses Penuh Root SSH 100%', note: 'Bebas pasang HestiaCP, aaPanel, Docker' },
    ],
    reasons: [
      { title: 'Spesifikasi Pas untuk Skripsi & Tugas', desc: 'Resource 2 vCPU dan 2 GB RAM cukup tangguh untuk demo tugas kuliah dan aplikasi skripsi.', icon: 'cpu' },
      { title: 'Server Jakarta Tanpa Lemot', desc: 'Dosen dan teman Anda dapat mengakses web tugas kuliah dengan loading instan.', icon: 'flagId' },
      { title: 'Jalan 24 Jam untuk Bot Kampus', desc: 'Cocok untuk menjalankan bot Telegram, Discord, atau scraper Python terus-menerus.', icon: 'bolt' },
      { title: 'Aman Tanpa Kartu Kredit', desc: 'Bayar mudah lewat Shopee / SPayLater tanpa repot tagihan otomatis dollar luar negeri.', icon: 'star' },
      { title: 'Bantuan Setup dari Nol', desc: 'Diberikan tutorial koneksi SSH via Termius/PuTTY serta panduan konfigurasi dasar.', icon: 'terminal' },
      { title: 'Pengiriman Cepat via Chat', desc: 'Order di Shopee, konfirmasi di chat, akun root server langsung dikirim dalam menit.', icon: 'check' },
    ]
  },

  // 3. Standard
  {
    id: '03_standard',
    catSlug: 'vps',
    catName: 'CLOUD VPS REGULER',
    name: 'Standard 4GB RAM',
    badge: '⭐ Paling Terpopuler',
    price: 'Rp 110.000',
    tagline: 'Pilihan ideal website bisnis, REST API, bot 24 jam, & PaaS Coolify.',
    audience: 'Developer, UKM, Freelancer, Software Agency',
    specs: { cpu: '2 Core vCPU', ram: '4 GB RAM DDR4', disk: '60 GB NVMe Gen4', bw: '30 Mbps Fair Use' },
    stack: 'Coolify / Docker / CyberPanel Ready',
    features: [
      '2 Core vCPU KVM Dedicated Thread',
      '4 GB RAM DDR4 High-Speed',
      '60 GB Enterprise NVMe PCIe RAID-10',
      'Support 1-Click Coolify PaaS & Docker',
      'Dedicated Static Public IPv4',
      'Uptime 99.9% Production Ready'
    ],
    deepSpecs: [
      { label: 'Processor (vCPU)', val: '2 Core vCPU KVM High Compute', note: 'Handle ratusan request web bersamaan' },
      { label: 'Memory (RAM)', val: '4 GB DDR4 + Anti-Crash Swap', note: 'Lega untuk Docker container & MySQL' },
      { label: 'Storage NVMe', val: '60 GB Enterprise NVMe RAID-10', note: 'Kecepatan read/write PCIe Gen4' },
      { label: 'Network & Port', val: 'Port 1 Gbps • 30 Mbps Fair Use', note: 'Datacenter Jakarta Tier-3 (<10ms)' },
      { label: 'IP Publik', val: '1 Dedicated Static IPv4 Publik', note: 'Bisa diarahkan ke Cloudflare / SSL' },
      { label: 'Control Panel', val: 'Bisa Request Pre-install Coolify', note: 'Alternatif Vercel / Heroku mandiri' },
    ],
    reasons: [
      { title: 'Kapasitas RAM 4GB Lega', desc: 'Bebas resiko out-of-memory saat menjalankan Nginx, Node.js/PHP, dan MySQL dalam satu VPS.', icon: 'ram' },
      { title: 'Paling Laris untuk Produksi Bisnis', desc: 'Sangat stabil untuk toko online WooCommerce, web company profile, dan backend API mobile app.', icon: 'bolt' },
      { title: 'Siap Pakai Coolify PaaS', desc: 'Ubah VPS menjadi private PaaS seperti Vercel. Tinggal git push, aplikasi langsung deploy otomatis.', icon: 'server' },
      { title: 'Koneksi Super Cepat Indonesia', desc: 'Datacenter Jakarta menjamin kecepatan respon aplikasi pelanggan di Indonesia.', icon: 'flagId' },
      { title: 'Garansi Uptime 99.9% SLA', desc: 'Pemantauan berkala dan infrastruktur andal menjaga website Anda aktif tanpa gangguan.', icon: 'shield' },
      { title: 'Dukungan CS Ramah & Fast Response', desc: 'Tim technical CravaCloud siap sedia mendampingi jika ada kendala deployment.', icon: 'check' },
    ]
  },

  // 4. Premium
  {
    id: '04_premium',
    catSlug: 'vps',
    catName: 'CLOUD VPS REGULER',
    name: 'Premium 8GB RAM',
    badge: 'High Memory',
    price: 'Rp 210.000',
    tagline: 'Multi-aplikasi Docker & production klien beban tinggi dengan RAM 8 GB.',
    audience: 'Freelancer, Digital Agency, Software House, Multi-Project',
    specs: { cpu: '2 Core vCPU', ram: '8 GB RAM DDR4', disk: '100 GB NVMe Gen4', bw: '30 Mbps Fair Use' },
    stack: 'Multi-Container Docker & Production Klien',
    features: [
      '2 Core vCPU KVM Virtualization',
      '8 GB RAM DDR4 Lega',
      '100 GB Enterprise NVMe PCIe RAID-10',
      'Beban Multi-Container Docker Stabil',
      'Dedicated Static Public IPv4',
      'Port 1 Gbps Datacenter Jakarta'
    ],
    deepSpecs: [
      { label: 'Processor (vCPU)', val: '2 Core vCPU KVM Virtualization', note: 'Performa konsisten tanpa throttling' },
      { label: 'Memory (RAM)', val: '8 GB DDR4 High-Capacity', note: 'Multi Docker containers & background worker' },
      { label: 'Storage NVMe', val: '100 GB Enterprise NVMe RAID-10', note: 'Kapasitas masif untuk banyak project' },
      { label: 'Network & Port', val: 'Port 1 Gbps • 30 Mbps Fair Use', note: 'Datacenter Tier-3 Jakarta (<10ms)' },
      { label: 'IP Publik', val: '1 Dedicated Static IPv4 Publik', note: 'Aman untuk multi sub-domain' },
      { label: 'Virtualisasi', val: 'Hardware KVM Murni (Resource Dijamin)', note: 'Bebas overselling dari pengguna lain' },
    ],
    reasons: [
      { title: 'RAM 8 GB Lega untuk Multi-Project', desc: 'Tampung belasan website klien atau container Docker dalam satu server tanpa lag.', icon: 'ram' },
      { title: 'Storage 100 GB NVMe Super Besar', desc: 'Penyimpanan sangat lega untuk file media, database bertumbuh, dan backup lokal.', icon: 'disk' },
      { title: 'Solusi Agency & Freelancer Hemat', desc: 'Hemat jutaan per bulan dibanding menyewa banyak hosting terpisah.', icon: 'bolt' },
      { title: 'Datacenter Lokal Jakarta', desc: 'Akses database dan file super cepat untuk pengguna di seluruh wilayah Indonesia.', icon: 'flagId' },
      { title: 'Full Akses Root Tanpa Batasan', desc: 'Bebas atur reverse proxy, SSL wildcard, firewall, dan cron jobs sesuai kebutuhan.', icon: 'terminal' },
      { title: 'Garansi Aktif & Proteksi DDoS', desc: 'Infrastruktur terlindungi dari serangan DDoS network dan gangguan jaringan.', icon: 'shield' },
    ]
  },

  // 5. Startup
  {
    id: '05_startup',
    catSlug: 'vps',
    catName: 'CLOUD VPS REGULER',
    name: 'Startup 4 Core',
    badge: 'Scale Up',
    price: 'Rp 350.000',
    tagline: 'Solusi tangguh 4 core untuk aplikasi startup, backend API & traffic padat.',
    audience: 'Startup, Software House, SaaS, Aplikasi Mobile Traffic Tinggi',
    specs: { cpu: '4 Core vCPU', ram: '8 GB RAM DDR4', disk: '80 GB NVMe Gen4', bw: '100 Mbps Dedicated' },
    stack: 'High Throughput Backend & Microservices',
    features: [
      '4 Core vCPU High Compute Power',
      '8 GB RAM DDR4 High-Speed',
      '80 GB Enterprise NVMe PCIe RAID-10',
      '100 Mbps Bandwidth Kencang',
      'Dedicated Static Public IPv4',
      'Cocok untuk Microservices & Queue'
    ],
    deepSpecs: [
      { label: 'Processor (vCPU)', val: '4 Core vCPU High-Compute KVM', note: 'Kemampuan komputasi paralel masif' },
      { label: 'Memory (RAM)', val: '8 GB DDR4 High-Speed', note: 'Stabil untuk Redis, RabbitMQ & Web App' },
      { label: 'Storage NVMe', val: '80 GB Enterprise NVMe RAID-10', note: 'Throughput I/O tinggi anti antrean disk' },
      { label: 'Network & Port', val: '100 Mbps Bandwidth (High Throughput)', note: 'Transfer data masif tanpa pembatasan ketat' },
      { label: 'IP Publik', val: '1 Dedicated Static IPv4 Publik', note: 'Static routing langsung Jakarta DC' },
      { label: 'SLA Uptime', val: 'Garansi Uptime 99.9% SLA', note: 'Keandalan untuk aplikasi operasional' },
    ],
    reasons: [
      { title: 'Komputasi 4 Core Bertenaga', desc: 'Memproses ribuan request per menit, queue worker, dan background job tanpa lonjakan CPU.', icon: 'cpu' },
      { title: 'Bandwidth 100 Mbps Ekstra Kencang', desc: 'Streaming data, pengiriman payload API besar, dan file transfer berjalan mulus.', icon: 'network' },
      { title: 'Dirancang untuk Startup Berkembang', desc: 'Pilihan tepat saat aplikasi mulai memiliki ribuan active users setiap harinya.', icon: 'bolt' },
      { title: 'Latensi Jakarta Ultra Cepat', desc: 'Pengguna mobile app Anda akan merasakan response time di bawah 10ms.', icon: 'flagId' },
      { title: 'Akses Root & Fleksibilitas Arsitektur', desc: 'Bebas install Kubernetes microk8s, Docker Swarm, atau multi-instance Nginx.', icon: 'terminal' },
      { title: 'Pemesanan Resmi di Shopee', desc: 'Transaksi aman dengan garansi uang kembali Shopee jika server tidak sesuai spesifikasi.', icon: 'shield' },
    ]
  },

  // 6. Business
  {
    id: '06_business',
    catSlug: 'vps',
    catName: 'CLOUD VPS REGULER',
    name: 'Business 8 Core / 16GB',
    badge: 'Enterprise Tier',
    price: 'Rp 590.000',
    tagline: 'Resource 8 core masif untuk database besar, enterprise ERP, & sistem kritis.',
    audience: 'Enterprise, Korporasi, ERP, Database Berat, Big Data',
    specs: { cpu: '8 Core vCPU', ram: '16 GB RAM DDR4', disk: '80 GB NVMe Gen4', bw: '100 Mbps Dedicated' },
    stack: 'Enterprise Dokploy / Heavy Database',
    features: [
      '8 Core vCPU Monster Performance',
      '16 GB RAM DDR4 Kapasitas Besar',
      '80 GB Enterprise NVMe PCIe RAID-10',
      '100 Mbps Bandwidth Masif',
      'Dedicated Static Public IPv4',
      'Toleransi Beban Ekstrem 24/7'
    ],
    deepSpecs: [
      { label: 'Processor (vCPU)', val: '8 Core vCPU Enterprise Compute', note: 'Multi-threaded load balancing lancar' },
      { label: 'Memory (RAM)', val: '16 GB DDR4 Enterprise Memory', note: 'Bebas bottleneck RAM untuk ERP / CRM' },
      { label: 'Storage NVMe', val: '80 GB Enterprise NVMe RAID-10', note: 'Kecepatan read/write enterprise level' },
      { label: 'Network & Port', val: '100 Mbps Dedicated Bandwidth', note: 'Port 1 Gbps Datacenter Jakarta' },
      { label: 'IP Publik', val: '1 Dedicated Static IPv4 Publik', note: 'Dedicated routing anti-interferensi' },
      { label: 'Virtualisasi', val: 'KVM Dedicated Virtualization', note: '100% isolasi penuh standar enterprise' },
    ],
    reasons: [
      { title: 'Performa Monster 8 Core & 16GB RAM', desc: 'Sanggup menjalankan sistem ERP perusahaan, sistem antrean tiket, dan database masif.', icon: 'cpu' },
      { title: 'Stabil di Bawah Tekanan Beban Ekstrem', desc: 'Tidak akan down saat flash sale atau lonjakan traffic puluhan ribu pengguna.', icon: 'bolt' },
      { title: 'Hemat Puluhan Juta Biaya Server Fisik', desc: 'Dapatkan performa setara server on-premise kantor tanpa biaya listrik dan maintenance.', icon: 'server' },
      { title: 'Keamanan Data & Isolasi Penuh', desc: 'Lingkungan server virtual terisolasi murni dengan akses root hanya di tangan Anda.', icon: 'shield' },
      { title: 'Koneksi Langsung Datacenter Jakarta', desc: 'Koneksi peering langsung ke IIX / OpenIXP untuk kecepatan transfer data tertinggi.', icon: 'flagId' },
      { title: 'Dukungan Prioritas Shopee CravaCloud', desc: 'Konsultasi arsitektur dan penanganan kendala diprioritaskan oleh tim senior engineer.', icon: 'star' },
    ]
  },

  // 7. Terminal Coding Agent
  {
    id: '07_terminal_coding_agent',
    catSlug: 'ai_combo',
    catName: 'AI COMBO STACK',
    name: 'Terminal Coding Agent',
    badge: 'Terminal / SSH AI',
    price: 'Rp 115.000',
    tagline: 'Ubah terminal VPS kamu jadi mesin coding otomatis bertenaga AI kelas dunia.',
    audience: 'Mahasiswa Teknik Informatika, Backend Developer, DevOps',
    specs: { cpu: '2 Core vCPU', ram: '4 GB RAM DDR4', disk: '60 GB NVMe Gen4', bw: '30 Mbps Fair Use' },
    stack: 'Claude Code CLI + OpenCode CLI + Dev Essentials',
    features: [
      'Claude Code CLI Resmi dari Anthropic Siap Pakai',
      'OpenCode CLI (Multi-Provider Open Source)',
      'Pre-configured: Git, GitHub CLI, Docker, Node, Python 3.12',
      'Anti-Crash Swap Memory Virtual',
      'Dedicated Static Public IPv4',
      'Datacenter Jakarta Latensi <10ms'
    ],
    deepSpecs: [
      { label: 'AI CLI Tool', val: 'Claude Code CLI (Resmi Anthropic)', note: 'Tinggal masukkan API key Anthropic Anda' },
      { label: 'Alternative AI', val: 'OpenCode CLI Multi-Provider', note: 'Support OpenAI, DeepSeek, Google Gemini' },
      { label: 'Dev Essentials', val: 'Git, gh CLI, Docker, Node.js LTS, Python 3.12', note: 'Zsh/Bash dengan autocomplete kencang' },
      { label: 'Hardware Spec', val: '2 Core vCPU • 4 GB RAM • 60 GB NVMe', note: 'Virtual KVM Datacenter Jakarta' },
      { label: 'Anti-Crash Memory', val: 'Alokasi Swap Virtual Otomatis', note: 'Compile proyek berat tetap stabil' },
      { label: 'Koneksi & Akses', val: 'Akses SSH via PuTTY / Termius / Terminal', note: 'Bisa diakses dari perangkat apapun' },
    ],
    reasons: [
      { title: 'Coding Otomatis via AI di Terminal', desc: 'Ketik "claude" atau "opencode", biarkan AI membaca repository, memperbaiki error & buat fitur.', icon: 'terminal' },
      { title: 'Zero Setup — Langsung Pakai', desc: 'Tidak perlu pusing install Python, Node, atau Docker berjam-jam. Semua sudah siap.', icon: 'check' },
      { title: 'Laptop Kentang Tetap Adem', desc: 'Komputasi berat ditangani server VPS. Laptop Anda baterainya hemat dan tidak cepat panas.', icon: 'bolt' },
      { title: 'Tugas Kuliah & Project Klien Cepat Selesai', desc: 'Bantu analisa ratusan baris kode dan refactor secara mandiri dalam hitungan menit.', icon: 'cpu' },
      { title: 'Hemat Biaya & Waktu', desc: 'Hanya Rp 115rb/bln sudah punya server coding AI pribadi yang aktif 24 jam nonstop.', icon: 'star' },
      { title: 'Panduan Lengkap dari Nol via Chat', desc: 'Diberikan cara menempelkan API key dan panduan prompt coding efektif.', icon: 'shield' },
    ]
  },

  // 8. Cloud AI Workstation
  {
    id: '08_cloud_ai_workstation',
    catSlug: 'ai_combo',
    catName: 'AI COMBO STACK',
    name: 'Cloud AI Workstation',
    badge: '⭐ Paling Populer / Best Value',
    price: 'Rp 150.000',
    tagline: 'Koding dengan asisten AI dari browser di mana saja — iPad, tablet, atau laptop kentang.',
    audience: 'Developer Freelance, Pelajar Mobilitas Tinggi, Pengguna iPad/Mac/Windows',
    specs: { cpu: '2 Core vCPU', ram: '6 GB RAM DDR4', disk: '80 GB NVMe Gen4', bw: '50 Mbps Fair Use' },
    stack: 'Web VS Code Server HTTPS + Claude Code + OpenCode',
    features: [
      'Web VS Code Server (Akses via Google Chrome)',
      'Claude Code CLI & OpenCode di Terminal VS Code',
      'Ekstensi Populer Siap Pakai (Tailwind, Prettier, Python)',
      'Auto-Save Cloud & Background Runner 24 Jam',
      'Spek Lega: 2 Core • 6 GB RAM • 80 GB NVMe',
      'Aman dengan Enkripsi SSL/HTTPS & Password'
    ],
    deepSpecs: [
      { label: 'Code Editor', val: 'Web VS Code Server (code-server)', note: 'Buka via browser Chrome/Safari dengan HTTPS aman' },
      { label: 'AI Integration', val: 'Claude Code & OpenCode Pre-installed', note: 'Terpasang langsung di terminal terintegrasi' },
      { label: 'Ekstensi Terpasang', val: 'Tailwind CSS, Prettier, Python, Docker, GitLens', note: 'Bebas pasang ekstensi favorit lainnya' },
      { label: 'Hardware Spec', val: '2 Core vCPU • 6 GB RAM • 80 GB NVMe', note: 'Datacenter Tier-3 Jakarta (<10ms)' },
      { label: 'Background Run', val: 'Pekerjaan tetap jalan meski laptop ditutup', note: 'Server API & compile berjalan di cloud' },
      { label: 'Keamanan Akses', val: 'Dedicated IP Publik + Password Enkripsi', note: 'Hanya Anda yang memiliki akses login' },
    ],
    reasons: [
      { title: 'Ngoding di Mana Saja Tanpa Laptop Berat', desc: 'Cukup buka browser dari laptop lama, warnet, maupun iPad sambil ngopi santai di cafe.', icon: 'server' },
      { title: 'Asisten AI Claude Code Langsung di Bawah', desc: 'Buka file di atas, minta AI menulis kode di terminal bawah. Alur kerja koding tercepat saat ini.', icon: 'terminal' },
      { title: 'Laptop Tidak Lagi Lag & Panas', desc: 'Seluruh RAM dan CPU berat ditanggung oleh server CravaCloud yang berkapasitas 6 GB RAM.', icon: 'bolt' },
      { title: 'Pekerjaan Aman dari Laptop Mati Lampu', desc: 'File tersimpan otomatis di server cloud. Saat laptop mati, kodingan Anda tetap aman utuh.', icon: 'shield' },
      { title: 'Harga Sangat Terjangkau di Shopee', desc: 'Rp 150.000/bln adalah investasi terbaik untuk produktivitas koding harian Anda.', icon: 'star' },
      { title: 'Tinggal Klik Link & Masukkan Password', desc: 'Detail link HTTPS dan kredensial langsung dikirim via Chat Shopee setelah pembayaran.', icon: 'check' },
    ]
  },

  // 9. Hermes Autonomous Hub
  {
    id: '09_hermes_autonomous_hub',
    catSlug: 'ai_combo',
    catName: 'AI COMBO STACK',
    name: 'Hermes Autonomous Hub',
    badge: 'Autonomous AI Hub',
    price: 'Rp 185.000',
    tagline: 'Server AI mandiri dengan multi-model routing & autonomous decision making.',
    audience: 'AI Engineer, Researcher, Agency Automation, Power User',
    specs: { cpu: '4 Core vCPU', ram: '8 GB RAM DDR4', disk: '100 GB NVMe Gen4', bw: '100 Mbps Dedicated' },
    stack: 'Hermes Agent Runtime + OmniRoute / 9router',
    features: [
      'Hermes Agent Runtime (Autonomous AI Agent)',
      'OmniRoute / 9router Pre-installed (Proxy Multi-Provider)',
      'Fallback Otomatis Jika Salah Satu AI Provider Down',
      'Web Management Dashboard Visual',
      'Spek Bertenaga: 4 Core • 8 GB RAM • 100 GB NVMe',
      '100 Mbps Bandwidth Datacenter Jakarta'
    ],
    deepSpecs: [
      { label: 'Autonomous Core', val: 'Hermes Agent Runtime Environment', note: 'AI agent dengan eksekusi terminal & browsing' },
      { label: 'AI Proxy Router', val: 'OmniRoute / 9router Multi-Model Gateway', note: 'Satukan OpenAI, Claude, DeepSeek, Gemini' },
      { label: 'Failover System', val: 'Auto Fallback ke model cadangan', note: 'Server AI tetap responsif tanpa gangguan downtime' },
      { label: 'Monitoring Tool', val: 'Web Management Dashboard Terintegrasi', note: 'Pantau riwayat prompt & utilisasi token visual' },
      { label: 'Hardware Spec', val: '4 Core vCPU • 8 GB RAM • 100 GB NVMe', note: 'Daya komputasi tinggi untuk otomasi agen' },
      { label: 'Jaringan Server', val: '100 Mbps Port 1 Gbps Datacenter Jakarta', note: 'Throughput transfer data cepat' },
    ],
    reasons: [
      { title: 'Server Sebagai Karyawan AI Mandiri', desc: 'Delegasikan tugas scraping, riset, dan analisis data ke server tanpa perlu laptop menyala.', icon: 'cpu' },
      { title: 'Proxy Cerdas Multi-Model AI', desc: 'Hubungkan banyak akun provider AI dalam satu endpoint URL API lokal yang sangat praktis.', icon: 'network' },
      { title: 'Anti Downtime dengan Smart Fallback', desc: 'Jika OpenAI atau Claude mengalami lonjakan error, router otomatis mengalihkan ke model lain.', icon: 'shield' },
      { title: 'Hemat Kuota & Resource Lokal', desc: 'Seluruh beban transmisi data AI dijalankan langsung dari infrastruktur cloud kecepatan tinggi.', icon: 'bolt' },
      { title: 'Dashboard Pantau Biaya & Token', desc: 'Ketahui model mana yang paling efisien dan lacak log eksekusi tugas secara transparan.', icon: 'server' },
      { title: 'Panduan Konfigurasi Lengkap Shopee', desc: 'Dilengkapi dokumentasi panduan setting API key dan contoh prompt otomatisasi.', icon: 'check' },
    ]
  },

  // 10. Enterprise Private AI & RAG
  {
    id: '10_enterprise_private_ai_rag',
    catSlug: 'ai_combo',
    catName: 'AI COMBO STACK',
    name: 'Enterprise Private AI & RAG',
    badge: 'Private AI & RAG',
    price: 'Rp 265.000',
    tagline: 'Platform AI private: buat chatbot dokumen perusahaan tanpa data bocor ke luar.',
    audience: 'Startup, UMKM, Kantor Konsultan, Peneliti Skripsi RAG / Dokumen',
    specs: { cpu: '4 Core vCPU', ram: '16 GB RAM DDR4', disk: '120 GB NVMe Gen4', bw: '100 Mbps Dedicated' },
    stack: 'Dify AI / Flowise + Ollama + Vector DB (Qdrant/Chroma)',
    features: [
      'Dify AI / Flowise Studio Visual Drag-and-Drop',
      'Ollama Local Engine (DeepSeek-R1 / Llama 3 / Qwen)',
      'Integrated Vector Database (Qdrant / Chroma)',
      'AnythingLLM Web Interface Chatbot Dokumen',
      '16 GB RAM Masif & 120 GB NVMe SSD',
      'Data 100% Privat di Server Sendiri'
    ],
    deepSpecs: [
      { label: 'Visual AI Studio', val: 'Dify AI / Flowise Studio', note: 'Buat AI workflow & chatbot WhatsApp tanpa coding' },
      { label: 'Local LLM Engine', val: 'Ollama Pre-installed (DeepSeek, Llama, Qwen)', note: 'Eksekusi model lokal tanpa biaya per-token' },
      { label: 'Vector Database', val: 'Qdrant / Chroma Vector Store Terintegrasi', note: 'Indexing dokumen PDF, Word, Excel super cepat' },
      { label: 'Web Chat UI', val: 'AnythingLLM Web Chat Interface', note: 'Antarmuka tanya-jawab dokumen mirip ChatGPT' },
      { label: 'Hardware Spec', val: '4 Core vCPU • 16 GB RAM • 120 GB NVMe', note: 'Alokasi RAM masif untuk embedding dokumen' },
      { label: 'Keamanan Data', val: '100% Terisolasi di Server Pribadi', note: 'Dokumen rahasia perusahaan tidak bocor ke pihak luar' },
    ],
    reasons: [
      { title: 'Miliki "ChatGPT" Internal Perusahaan', desc: 'Unggah ribuan SOP, katalog produk, dan laporan keuangan, lalu tanyakan apa saja ke AI.', icon: 'database' },
      { title: 'Data Rahasia 100% Aman & Terlindungi', desc: 'Tidak ada data yang dikirim ke server luar. Semua model dan database berjalan di VPS Anda.', icon: 'shield' },
      { title: 'Tanpa Biaya Token Tambahan', desc: 'Jalankan model lokal open-source gratis via Ollama sepuasnya tanpa takut tagihan membengkak.', icon: 'bolt' },
      { title: 'Buat Chatbot CS WhatsApp & Web', desc: 'Integrasikan Dify AI dengan WhatsApp API atau widget website bisnis Anda dengan mudah.', icon: 'server' },
      { title: 'Resource 16 GB RAM yang Sangat Lega', desc: 'Menjamin proses indexing dokumen ratusan halaman berjalan lancar tanpa kehabisan memori.', icon: 'ram' },
      { title: 'Setup Siap Pakai & Support Teknisi', desc: 'Lingkungan RAG dan Studio AI telah dikonfigurasi rapi, siap pakai sejak hari pertama order.', icon: 'check' },
    ]
  },

  // 11. DB Micro
  {
    id: '11_db_micro',
    catSlug: 'managed_db',
    catName: 'MANAGED DATABASE VPS',
    name: 'DB Micro',
    badge: 'DB Entry Level',
    price: 'Rp 95.000',
    tagline: 'Development, tugas kuliah, staging, & web traffic ringan.',
    audience: 'Mahasiswa, Developer, Staging Project, Aplikasi Beban Ringan',
    specs: { cpu: '2 Core vCPU', ram: '2 GB RAM DDR4', disk: '40 GB NVMe Gen4', bw: '20 Mbps (512 GB)' },
    stack: 'PostgreSQL 16 / MySQL 8 / MariaDB / Redis',
    features: [
      'Menampung ~50–80 Koneksi Bersamaan',
      'Optimal untuk Database Aktif 10–20 GB',
      'Latensi Lokal 3–10 ms Datacenter Jakarta',
      'Bebas Konek dari Vercel, Railway, DBeaver',
      'Dedicated Static Public IPv4',
      'Auto-Backup Harian Terjadwal'
    ],
    deepSpecs: [
      { label: 'Kapasitas Beban', val: 'Hingga ~50–80 Koneksi Bersamaan', note: 'Tanpa drop performa saat testing & staging' },
      { label: 'Volume Data', val: 'Optimal untuk Data Aktif 10–20 GB', note: 'Mampu menampung jutaan baris data transaksi' },
      { label: 'Pilihan Engine', val: 'PostgreSQL 16, MySQL 8.0, MariaDB, Redis', note: 'Request engine database sesuai kebutuhan' },
      { label: 'Hardware Spec', val: '2 Core vCPU • 2 GB RAM • 40 GB NVMe', note: 'Enterprise NVMe Gen4 anti bottleneck' },
      { label: 'Universal Access', val: 'Dedicated Public Static IPv4', note: 'Langsung tembak dari Vercel / Railway / App' },
      { label: 'Keamanan Data', val: 'Auto-Backup Harian + Memory Swap', note: 'Database tidak crash saat ada query berat' },
    ],
    reasons: [
      { title: 'Database Terisolasi Bebas Crash', desc: 'Jika web server aplikasi Anda crash atau restart, database tetap hidup dan data aman 100%.', icon: 'shield' },
      { title: 'Respon Query Secepat Kilat', desc: 'Latensi lokal hanya 3–10ms langsung dari Datacenter Jakarta, query data terasa instan.', icon: 'bolt' },
      { title: 'Konek dari Mana Saja via IP Publik', desc: 'Akses langsung dari Vercel, Railway, Supabase alternative, DBeaver, atau TablePlus.', icon: 'network' },
      { title: 'Hemat Biaya Dibanding DB Cloud Asing', desc: 'Hanya Rp 95rb/bln dibanding sewa database cloud luar negeri yang mengenakan tarif per-query.', icon: 'star' },
      { title: 'Skrip Backup Otomatis Harian', desc: 'Data tugas kuliah atau testing bisnis Anda aman dari risiko kehilangan data.', icon: 'database' },
      { title: 'Bantuan Setup Engine Gratis via Chat', desc: 'Beri tahu kami engine yang Anda mau (MySQL/Postgres/Redis), langsung kami buatkan.', icon: 'check' },
    ]
  },

  // 12. DB Standard
  {
    id: '12_db_standard',
    catSlug: 'managed_db',
    catName: 'MANAGED DATABASE VPS',
    name: 'DB Standard 4GB RAM',
    badge: '⭐ Paling Terpopuler',
    price: 'Rp 145.000',
    tagline: 'Production UMKM, SaaS early stage, e-commerce, & backend API.',
    audience: 'UMKM, Startup SaaS, E-Commerce Toko Online, Backend Developer',
    specs: { cpu: '2 Core vCPU', ram: '4 GB RAM DDR4', disk: '60 GB NVMe Gen4', bw: '30 Mbps (1.54 TB)' },
    stack: 'PostgreSQL 16 / MySQL 8 / MongoDB 7 / Redis',
    features: [
      'Kuat hingga ~200–350 Koneksi Bersamaan',
      'Optimal untuk Database Aktif 40–50 GB',
      'Alokasi Query Cache Dedicated 2.5 GB',
      'Connection Pooling Ready untuk Lonjakan Traffic',
      '100% Resource Terisolasi Bebas Gangguan',
      'Dedicated Static Public IPv4 + Backup Harian'
    ],
    deepSpecs: [
      { label: 'Kapasitas Beban', val: 'Kuat ~200–350 Koneksi Bersamaan', note: 'Sanggup handle flash sale & lonjakan pengunjung' },
      { label: 'Volume Data', val: 'Optimal untuk Data Aktif 40–50 GB', note: 'Index cepat untuk puluhan juta baris record' },
      { label: 'Dedicated Cache', val: 'Alokasi Cache Query 2.5 GB', note: 'Pembacaan data berulang secepat kilat' },
      { label: 'Pilihan Engine', val: 'PostgreSQL 16, MySQL 8.0, MongoDB 7, Redis', note: 'Siap request multi-engine jika diperlukan' },
      { label: 'Hardware Spec', val: '2 Core vCPU • 4 GB RAM • 60 GB NVMe', note: 'PCIe Gen4 NVMe RAID-10 kencang' },
      { label: 'Proteksi Data', val: 'Dedicated Static IP + Auto Backup Harian', note: 'Kredensial SSL/TLS aman terenkripsi' },
    ],
    reasons: [
      { title: 'Tahan Banting Menghadapi Traffic Tinggi', desc: 'Arsitektur connection pooling menjaga database tetap adem saat website diserbu ribuan pengunjung.', icon: 'cpu' },
      { title: 'Pembacaan Data Kilat dengan 2.5GB Cache', desc: 'Query berulang tidak lagi membebani disk, langsung di-serve dari RAM super kencang.', icon: 'bolt' },
      { title: '100% Bebas Gangguan Web Server', desc: 'Pemisahan database server adalah standar wajib aplikasi profesional agar tidak terjadi single point of failure.', icon: 'shield' },
      { title: 'Universal Connection ke Semua Framework', desc: 'Konek dari Laravel, Next.js, Django, Express, Go Fiber, maupun CMS WordPress.', icon: 'network' },
      { title: 'Paling Banyak Dipilih Toko Online', desc: 'Sweet spot antara harga ekonomis dan keandalan tinggi untuk transaksi bisnis sehari-hari.', icon: 'star' },
      { title: 'Garansi Uptime & Penggantian Cepat', desc: 'Jaminan keamanan data dan bantuan troubleshooting database jika ada query macet.', icon: 'check' },
    ]
  },

  // 13. DB Enterprise / AI Vector
  {
    id: '13_db_enterprise_vector',
    catSlug: 'managed_db',
    catName: 'MANAGED DATABASE VPS',
    name: 'DB Enterprise / AI Vector',
    badge: 'Enterprise & AI Vector',
    price: 'Rp 225.000',
    tagline: 'High-traffic SQL, Vector Search (Qdrant/Chroma), & sistem RAG AI.',
    audience: 'Enterprise, AI Developer, Startup RAG, Big Data Analytics',
    specs: { cpu: '2 Core vCPU', ram: '8 GB RAM High-Speed', disk: '80 GB NVMe Gen4', bw: '30 Mbps (2.56 TB)' },
    stack: 'Qdrant / Chroma / PostgreSQL (pgvector) / Redis Cluster',
    features: [
      'Menampung ~800–1.200+ Koneksi Bersamaan',
      'AI Vector Search Latensi Sangat Cepat (<10ms)',
      'Dirancang untuk Puluhan Juta Baris Data / Embedding',
      'Throughput Tertinggi: Transfer Data hingga 2.56 TB/bln',
      'Toleransi Beban Ekstrem & Join Multi-Tabel Berat',
      'Dedicated Static Public IPv4 + Backup Otomatis'
    ],
    deepSpecs: [
      { label: 'Kapasitas Beban', val: 'Sanggup ~800–1.200+ Koneksi Bersamaan', note: 'Pemrosesan query analitik kompleks' },
      { label: 'AI Vector Search', val: 'Vector Latency (<10ms) Ultra Cepat', note: 'Siap untuk chatbot dokumen RAG & semantic search' },
      { label: 'Pilihan Engine', val: 'Qdrant, Chroma DB, PostgreSQL (pgvector), MySQL', note: 'Engine database vektor mutakhir siap pakai' },
      { label: 'Throughput Data', val: 'Bandwidth 30 Mbps (2.56 TB / Bulan)', note: 'Transfer data masif tanpa lag' },
      { label: 'Hardware Spec', val: '2 Core vCPU • 8 GB RAM • 80 GB NVMe', note: 'RAM lega menjamin stabilitas vector indexing' },
      { label: 'Reliability', val: 'Dedicated Static IP + Auto Backup Harian', note: 'Infrastruktur database level korporat' },
    ],
    reasons: [
      { title: 'Didesain Khusus untuk Ekosistem AI & RAG', desc: 'Penyimpanan embedding vektor super responsif untuk asisten AI dan sistem pencarian dokumen cerdas.', icon: 'cpu' },
      { title: 'Daya Tampung Masif 1.200+ Koneksi', desc: 'Menjamin aplikasi skala besar dan microservices dapat melakukan query secara paralel tanpa antrean.', icon: 'bolt' },
      { title: 'RAM 8 GB untuk Query Analitik Berat', desc: 'Eksekusi query JOIN antar tabel berukuran gigabyte tetap lancar tanpa risiko out-of-memory.', icon: 'ram' },
      { title: 'Datacenter Jakarta Peering Langsung', desc: 'Kecepatan throughput transfer data tertinggi untuk operasional server di Indonesia.', icon: 'flagId' },
      { title: 'Penghematan Signifikan Dibanding Pinecone/AWS', desc: 'Hemat jutaan per bulan untuk database vector dedicated tanpa batasan jumlah request.', icon: 'database' },
      { title: 'Layanan Setup & Konsultasi Arsitektur', desc: 'Dibantu setup pgvector, Qdrant, atau Chroma DB sampai siap ditembak dari aplikasi AI Anda.', icon: 'shield' },
    ]
  }
];

// -------------------------------------------------------------
// HTML TEMPLATE BUILDERS FOR PHOTO 1, 2, 3
// -------------------------------------------------------------

function renderPhoto1(p) {
  const pills = [
    { icon: 'cpu', text: p.specs.cpu },
    { icon: 'ram', text: p.specs.ram },
    { icon: 'disk', text: p.specs.disk },
    { icon: 'network', text: p.specs.bw },
    { icon: 'terminal', text: 'Full Root Access SSH' },
    { icon: 'shield', text: 'Datacenter Jakarta Tier-3' }
  ];

  const pillsHtml = pills.map(pill => `
    <div class="pill-spec">
      ${icons[pill.icon] || icons.check}
      <span>${pill.text}</span>
    </div>
  `).join('');

  const content = `
    <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 20px;">
      <!-- Category Badge & Price Box -->
      <div style="display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
          <div class="badge-category">${p.catName} • ${p.badge}</div>
          <div class="glow-title" style="margin-top: 10px;">
            ${p.name.toUpperCase()}
          </div>
          <div style="font-size: 17.5px; color: #CBD5E1; max-width: 700px; line-height: 1.5; margin-top: 8px;">
            ${p.tagline}
          </div>
        </div>
        <div class="price-box">
          <div class="price-label">Harga Spesial</div>
          <div class="price-val">${p.price}<span>/bulan</span></div>
        </div>
      </div>

      <!-- Feature Pills Grid (3x2) -->
      <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 5px;">
        ${pillsHtml}
      </div>

      <!-- Centerpiece Hero Card -->
      <div style="background: rgba(15, 23, 42, 0.85); border: 1.5px solid rgba(56, 189, 248, 0.4); border-radius: 20px; padding: 24px 30px; box-shadow: 0 15px 35px rgba(0,0,0,0.5);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; border-bottom:1px solid rgba(255,255,255,0.08); padding-bottom:12px;">
          <div style="display:flex; align-items:center; gap:10px;">
            <span style="width:12px; height:12px; border-radius:50%; background:#10B981; box-shadow:0 0 10px #10B981;"></span>
            <span class="mono" style="font-size:15px; color:#38BDF8; font-weight:700;">SERVER STATUS: READY TO DEPLOY</span>
          </div>
          <span style="background:rgba(106,189,115,0.2); color:#6ABD73; border:1px solid #6ABD73; padding:4px 12px; border-radius:6px; font-size:13px; font-weight:800;">100% GARANSI AKTIF</span>
        </div>
        <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:16px;">
          <div style="background:rgba(30,41,59,0.7); padding:14px; border-radius:12px; text-align:center;">
            <div style="color:#94A3B8; font-size:13px; font-weight:700;">PROCESSOR & RAM</div>
            <div class="mono" style="color:#38BDF8; font-size:20px; font-weight:800; margin-top:4px;">${p.specs.cpu}</div>
            <div style="color:#64748B; font-size:12px;">${p.specs.ram}</div>
          </div>
          <div style="background:rgba(30,41,59,0.7); padding:14px; border-radius:12px; text-align:center;">
            <div style="color:#94A3B8; font-size:13px; font-weight:700;">STORAGE NVME</div>
            <div class="mono" style="color:#6ABD73; font-size:20px; font-weight:800; margin-top:4px;">${p.specs.disk}</div>
            <div style="color:#64748B; font-size:12px;">PCIe Gen4 RAID-10</div>
          </div>
          <div style="background:rgba(30,41,59,0.7); padding:14px; border-radius:12px; text-align:center;">
            <div style="color:#94A3B8; font-size:13px; font-weight:700;">DATACENTER</div>
            <div class="mono" style="color:#FBBF24; font-size:20px; font-weight:800; margin-top:4px;">JAKARTA</div>
            <div style="color:#64748B; font-size:12px;">Latensi &lt; 5-10 ms</div>
          </div>
        </div>
        <div style="margin-top:16px; padding-top:12px; border-top:1px solid rgba(255,255,255,0.06); display:flex; justify-content:space-between; align-items:center;">
          <span style="font-size:14px; color:#CBD5E1;">Stack / Default: <strong>${p.stack}</strong></span>
          <span class="mono" style="font-size:13px; color:#38BDF8; background:rgba(56,189,248,0.1); padding:4px 10px; border-radius:6px;">Static Dedicated IP</span>
        </div>
      </div>
    </div>
  `;

  return wrapHtml(content);
}

function renderPhoto2(p) {
  const rowsHtml = p.deepSpecs.map((row, idx) => `
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 13px 14px; background: ${idx % 2 === 0 ? 'rgba(30, 41, 59, 0.4)' : 'transparent'}; border-bottom: ${idx === p.deepSpecs.length - 1 ? 'none' : '1px solid rgba(255,255,255,0.06)'};">
      <div style="width: 220px; font-size: 15px; font-weight: 700; color: #38BDF8;">${row.label}</div>
      <div style="flex: 1; font-size: 15.5px; font-weight: 700; color: #FFFFFF;">${row.val}</div>
      <div style="width: 320px; text-align: right; font-size: 13px; color: #94A3B8;">${row.note}</div>
    </div>
  `).join('');

  const checklistHtml = p.features.map(f => `
    <div style="display: flex; align-items: center; gap: 12px; font-size: 15px; color: #E2E8F0; font-weight: 600;">
      ${icons.check}
      <span>${f}</span>
    </div>
  `).join('');

  const content = `
    <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 20px;">
      <!-- Title -->
      <div>
        <div class="badge-category">${p.catName} • SPESIFIKASI LENGKAP</div>
        <div class="glow-title" style="margin-top: 8px; font-size: 42px;">
          SPESIFIKASI: ${p.name.toUpperCase()}
        </div>
        <div style="font-size: 16px; color: #CBD5E1; margin-top: 4px;">
          Rincian lengkap alokasi resource dan kapabilitas server yang Anda dapatkan di Shopee:
        </div>
      </div>

      <!-- Spec Deep-Dive Table -->
      <div style="background: rgba(15, 23, 42, 0.7); border: 1.5px solid rgba(255, 255, 255, 0.1); border-radius: 16px; overflow: hidden; padding: 4px 12px;">
        ${rowsHtml}
      </div>

      <!-- Bottom Feature Card -->
      <div style="background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(74, 111, 165, 0.4); border-radius: 16px; padding: 20px 24px;">
        <div style="font-size: 14px; font-weight: 800; color: #38BDF8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;">
          FITUR & FASILITAS TERMASUK DALAM PAKET INI:
        </div>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px 24px;">
          ${checklistHtml}
        </div>
      </div>
    </div>
  `;

  return wrapHtml(content);
}

function renderPhoto3(p) {
  const reasonsHtml = p.reasons.map(r => `
    <div style="background: rgba(15, 23, 42, 0.7); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 16px; padding: 20px; display: flex; gap: 16px; align-items: flex-start;">
      <div style="background: rgba(74, 111, 165, 0.25); border: 1px solid rgba(56, 189, 248, 0.4); border-radius: 12px; padding: 10px; color: #38BDF8; display: flex; align-items: center; justify-content: center; shrink-0;">
        ${icons[r.icon] || icons.check}
      </div>
      <div>
        <div style="font-size: 17px; font-weight: 800; color: #FFFFFF; margin-bottom: 4px;">${r.title}</div>
        <div style="font-size: 13.5px; color: #94A3B8; line-height: 1.45;">${r.desc}</div>
      </div>
    </div>
  `).join('');

  const content = `
    <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 20px;">
      <!-- Title -->
      <div>
        <div class="badge-category" style="color: #6ABD73; border-color: rgba(106, 189, 115, 0.4); background: rgba(106, 189, 115, 0.12);">
          KENAPA HARUS MEMILIH PAKET INI?
        </div>
        <div class="glow-title" style="margin-top: 8px; font-size: 40px;">
          KEUNGGULAN & DAYA TARIK PEMBELI
        </div>
        <div style="font-size: 16px; color: #CBD5E1; margin-top: 4px;">
          Alasan mengapa paket <strong>${p.name}</strong> adalah pilihan terbaik untuk kebutuhan Anda di Shopee:
        </div>
      </div>

      <!-- 6 Reason Cards Grid (2x3) -->
      <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px;">
        ${reasonsHtml}
      </div>

      <!-- 3-Step Shopee Order Guide -->
      <div style="background: linear-gradient(135deg, rgba(30, 41, 59, 0.9), rgba(15, 23, 42, 0.9)); border: 1.5px solid rgba(245, 158, 11, 0.5); border-radius: 16px; padding: 18px 24px; display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 14px;">
          <div style="background: #F59E0B; color: #000; font-weight: 900; font-size: 15px; padding: 6px 12px; border-radius: 8px;">
            CARA ORDER
          </div>
          <div style="display: flex; gap: 24px; font-size: 14px; font-weight: 600; color: #E2E8F0;">
            <div><strong style="color: #FBBF24;">1.</strong> Checkout Paket di Shopee</div>
            <div style="color: #64748B;">➔</div>
            <div><strong style="color: #FBBF24;">2.</strong> Kirim Alamat Email di Chat</div>
            <div style="color: #64748B;">➔</div>
            <div><strong style="color: #FBBF24;">3.</strong> Server Siap & Aktif dalam Menit!</div>
          </div>
        </div>
        <div class="digital-badge" style="padding: 6px 14px; font-size: 13px;">
          GARANSI AKTIF 100%
        </div>
      </div>
    </div>
  `;

  return wrapHtml(content);
}

// -------------------------------------------------------------
// BROWSER RENDERING ENGINE (CHROME HEADLESS)
// -------------------------------------------------------------

const chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const tmpProfile = path.resolve(process.env.TEMP || 'C:\\temp', 'chrome_shopee_profile_all');

async function renderAllPackages() {
  console.log('=== CRAVACLOUD SHOPEE BANNER GENERATOR (ALL 13 INDIVIDUAL PACKAGES) ===');
  console.log(`Generating images for ${allPackages.length} packages (3 photos each = ${allPackages.length * 3} banners)...\n`);

  const manifest = [];

  for (let i = 0; i < allPackages.length; i++) {
    const pkg = allPackages[i];
    const pkgNum = i + 1;
    console.log(`[${pkgNum}/${allPackages.length}] Processing Package: ${pkg.name} (${pkg.catName})`);

    const pkgFolder = path.join(PKG_DIR, pkg.id);
    if (!fs.existsSync(pkgFolder)) {
      fs.mkdirSync(pkgFolder, { recursive: true });
    }

    const photos = [
      { num: 1, name: '1_utama.png', type: 'Foto Utama (Hero / Cover Shopee)', html: renderPhoto1(pkg) },
      { num: 2, name: '2_detail.png', type: 'Foto Detail (Spesifikasi Lengkap Paket)', html: renderPhoto2(pkg) },
      { num: 3, name: '3_keunggulan.png', type: 'Foto Daya Tarik Pembeli (Keunggulan & Trust)', html: renderPhoto3(pkg) },
    ];

    const pkgRecord = {
      id: pkg.id,
      num: pkgNum,
      name: pkg.name,
      catSlug: pkg.catSlug,
      catName: pkg.catName,
      badge: pkg.badge,
      price: pkg.price,
      folder: `packages/${pkg.id}`,
      photos: []
    };

    for (const photo of photos) {
      const htmlPath = path.join(pkgFolder, `temp_${photo.name}.html`);
      const imgPath = path.join(pkgFolder, photo.name);
      const flatImgPath = path.join(OUTPUT_BASE, `pkg_${pkg.id}_${photo.name}`);

      fs.writeFileSync(htmlPath, photo.html, 'utf-8');
      const fileUrl = 'file:///' + htmlPath.replace(/\\/g, '/');

      const cmd = `"${chromePath}" --headless=new --disable-gpu --no-sandbox --disable-setuid-sandbox --user-data-dir="${tmpProfile}" --screenshot="${imgPath}" --window-size=1200,1200 "${fileUrl}"`;

      try {
        execSync(cmd, { stdio: 'pipe' });
        fs.copyFileSync(imgPath, flatImgPath);

        const stat = fs.statSync(imgPath);
        console.log(`  -> Photo ${photo.num} (${photo.name}): [OK] ${Math.round(stat.size / 1024)} KB`);

        pkgRecord.photos.push({
          num: photo.num,
          filename: photo.name,
          flatFilename: `pkg_${pkg.id}_${photo.name}`,
          type: photo.type,
          sizeKb: Math.round(stat.size / 1024),
          relPath: `packages/${pkg.id}/${photo.name}`
        });
      } catch (err) {
        console.error(`  -> [ERROR] Failed to render ${photo.name}:`, err.message);
      } finally {
        if (fs.existsSync(htmlPath)) {
          fs.unlinkSync(htmlPath);
        }
      }
    }

    manifest.push(pkgRecord);
  }

  // Save manifest json
  fs.writeFileSync(path.join(OUTPUT_BASE, 'manifest_all_packages.json'), JSON.stringify(manifest, null, 2), 'utf-8');

  // Generate Interactive Gallery HTML for All 13 Packages
  generateComprehensiveGalleryHtml(manifest);

  console.log('\n=============================================');
  console.log(`ALL ${allPackages.length * 3} SHOPEE BANNERS GENERATED SUCCESSFULLY!`);
  console.log(`Packages Directory: ${PKG_DIR}`);
  console.log(`Interactive Catalog Preview: ${path.join(OUTPUT_BASE, 'index.html')}`);
  console.log('=============================================\n');
}

// -------------------------------------------------------------
// COMPREHENSIVE GALLERY HTML GENERATOR WITH CATEGORY FILTERS
// -------------------------------------------------------------

function generateComprehensiveGalleryHtml(manifest) {
  const html = `<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CravaCloud — Katalog Lengkap 13 Paket Produk Shopee Official</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: #0B0F19;
    color: #F8FAFC;
    font-family: 'Plus Jakarta Sans', sans-serif;
    padding: 40px 20px;
    line-height: 1.6;
  }
  .container {
    max-width: 1350px;
    margin: 0 auto;
  }
  header {
    text-align: center;
    margin-bottom: 40px;
    padding-bottom: 30px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  }
  h1 {
    font-size: 38px;
    font-weight: 800;
    margin-bottom: 12px;
  }
  h1 span { color: #4A6FA5; }
  .subtitle {
    font-size: 18px;
    color: #94A3B8;
    max-width: 850px;
    margin: 0 auto;
  }
  .badge-official {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(74, 111, 165, 0.2);
    border: 1.5px solid #4A6FA5;
    padding: 8px 20px;
    border-radius: 9999px;
    color: #38BDF8;
    font-weight: 700;
    margin-top: 16px;
  }

  /* Filter Tabs */
  .tabs {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-bottom: 40px;
    flex-wrap: wrap;
  }
  .tab-btn {
    background: rgba(30, 41, 59, 0.8);
    border: 1.5px solid rgba(255, 255, 255, 0.1);
    color: #CBD5E1;
    padding: 12px 24px;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
  }
  .tab-btn:hover {
    border-color: #38BDF8;
    color: #FFFFFF;
  }
  .tab-btn.active {
    background: #4A6FA5;
    border-color: #38BDF8;
    color: #FFFFFF;
    box-shadow: 0 0 20px rgba(74, 111, 165, 0.4);
  }

  .prod-card {
    background: #111827;
    border: 1px solid rgba(74, 111, 165, 0.3);
    border-radius: 20px;
    padding: 28px;
    margin-bottom: 40px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.4);
    transition: transform 0.2s;
  }
  .prod-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 22px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    padding-bottom: 16px;
  }
  .prod-title {
    font-size: 24px;
    font-weight: 800;
    color: #FFFFFF;
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .badge-tag {
    font-size: 12px;
    font-weight: 800;
    padding: 4px 10px;
    border-radius: 6px;
    background: rgba(56, 189, 248, 0.15);
    color: #38BDF8;
    border: 1px solid #38BDF8;
  }
  .badge-price {
    font-size: 20px;
    font-weight: 800;
    color: #FBBF24;
    background: rgba(245, 158, 11, 0.12);
    border: 1px solid rgba(245, 158, 11, 0.4);
    padding: 6px 16px;
    border-radius: 10px;
  }
  .photo-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 22px;
  }
  .photo-item {
    background: #0B0F19;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: transform 0.2s, border-color 0.2s;
  }
  .photo-item:hover {
    transform: translateY(-4px);
    border-color: #38BDF8;
  }
  .img-wrapper {
    position: relative;
    width: 100%;
    aspect-ratio: 1 / 1;
    background: #050811;
  }
  .img-wrapper img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }
  .photo-meta {
    padding: 16px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    flex: 1;
  }
  .photo-label {
    font-size: 13.5px;
    font-weight: 800;
    color: #38BDF8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
  }
  .photo-desc {
    font-size: 12.5px;
    color: #94A3B8;
    margin-bottom: 12px;
  }
  .btn-download {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: #4A6FA5;
    color: #FFFFFF;
    text-decoration: none;
    font-weight: 700;
    font-size: 13px;
    padding: 10px 14px;
    border-radius: 8px;
    transition: background 0.2s;
  }
  .btn-download:hover {
    background: #38BDF8;
    color: #0B0F19;
  }
  .tips-box {
    background: rgba(30, 41, 59, 0.7);
    border: 1px solid rgba(106, 189, 115, 0.4);
    border-radius: 16px;
    padding: 24px;
    margin-top: 40px;
  }
  .tips-title {
    color: #6ABD73;
    font-size: 18px;
    font-weight: 800;
    margin-bottom: 10px;
  }
</style>
</head>
<body>
<div class="container">
  <header>
    <h1>Katalog Lengkap Foto Produk Shopee <span>CravaCloud</span></h1>
    <p class="subtitle">Semua 13 Paket Dipisah Tersendiri (1 Produk = 3 Foto: Utama, Detail Spesifikasi, dan Daya Tarik Pembeli). Total 39 Foto HD 1200 x 1200 px Siap Upload di Shopee <strong>cravacloud</strong>.</p>
    <div class="badge-official">
      ✓ Official Store: cravacloud • 13 Produk Berdiri Sendiri • 39 Foto HD (1200x1200px)
    </div>
  </header>

  <!-- Filter Tabs -->
  <div class="tabs">
    <button class="tab-btn active" onclick="filterCat('all', this)">Semua 13 Paket (39 Foto)</button>
    <button class="tab-btn" onclick="filterCat('vps', this)">Cloud VPS Reguler (6 Paket / 18 Foto)</button>
    <button class="tab-btn" onclick="filterCat('ai_combo', this)">AI Stack & Workstation (4 Paket / 12 Foto)</button>
    <button class="tab-btn" onclick="filterCat('managed_db', this)">Managed Database VPS (3 Paket / 9 Foto)</button>
  </div>

  ${manifest.map((p) => `
    <div class="prod-card" data-cat="${p.catSlug}">
      <div class="prod-header">
        <div>
          <div style="font-size: 12px; font-weight: 800; color: #38BDF8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">
            PRODUK ${p.num} • ${p.catName}
          </div>
          <div class="prod-title">
            <span>${p.name}</span>
            <span class="badge-tag">${p.badge}</span>
          </div>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
          <div class="badge-price">${p.price}/bln</div>
          <span style="font-size: 13px; color: #94A3B8;">Folder: <code>${p.folder}/</code></span>
        </div>
      </div>

      <div class="photo-grid">
        ${p.photos.map(photo => `
          <div class="photo-item">
            <div class="img-wrapper">
              <a href="${photo.relPath}" target="_blank">
                <img src="${photo.relPath}" alt="${photo.type}" loading="lazy">
              </a>
            </div>
            <div class="photo-meta">
              <div>
                <div class="photo-label">FOTO ${photo.num}: ${photo.num === 1 ? 'UTAMA (COVER)' : photo.num === 2 ? 'DETAIL SPESIFIKASI' : 'DAYA TARIK PEMBELI'}</div>
                <div class="photo-desc">${photo.type} (${photo.sizeKb} KB, 1200x1200px)</div>
              </div>
              <a href="${photo.relPath}" download="${p.id}_foto_${photo.num}.png" class="btn-download">
                ⬇ Unduh Foto ${photo.num}
              </a>
            </div>
          </div>
        `).join('')}
      </div>
    </div>
  `).join('')}

  <div class="tips-box">
    <div class="tips-title">💡 Panduan Upload 13 Produk di Shopee Seller Centre:</div>
    <ul style="padding-left: 20px; color: #CBD5E1; font-size: 14px; display: flex; flex-direction: column; gap: 8px;">
      <li><strong>1 Paket = 1 Produk Shopee:</strong> Tiap paket dapat Anda jadikan listing produk terpisah di Shopee agar mendominasi halaman pencarian untuk kata kunci spesifik (misal: "VPS Mahasiswa Murah", "VPS AI Coding Workstation", "Managed Database MySQL PostgreSQL").</li>
      <li><strong>Urutan Upload Tiap Produk:</strong>
        <ol style="padding-left: 20px; margin-top: 4px;">
          <li>Foto 1: Cover Utama (Hero dengan nama paket, harga, spek kunci, dan status online).</li>
          <li>Foto 2: Detail Spesifikasi (Tabel rincian hardware CPU, RAM, NVMe, IP Publik, OS support).</li>
          <li>Foto 3: Daya Tarik Pembeli (6 alasan memilih paket ini + panduan order 3 langkah).</li>
        </ol>
      </li>
      <li><strong>Pengiriman Digital:</strong> Aktifkan "Termasuk Ongkos Kirim" (Rp 0). Setelah pembeli order, kirimkan kredensial server via Chat Shopee / Email.</li>
    </ul>
  </div>
</div>

<script>
function filterCat(cat, btn) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const cards = document.querySelectorAll('.prod-card');
  cards.forEach(c => {
    if (cat === 'all' || c.getAttribute('data-cat') === cat) {
      c.style.display = 'block';
    } else {
      c.style.display = 'none';
    }
  });
}
</script>
</body>
</html>`;

  fs.writeFileSync(path.join(OUTPUT_BASE, 'index.html'), html, 'utf-8');
}

renderAllPackages().catch(console.error);
