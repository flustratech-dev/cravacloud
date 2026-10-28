import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';

const ROOT_DIR = process.cwd();
const OUTPUT_DIR = path.join(ROOT_DIR, 'public', 'images', 'shopee');
const LOGO_PATH = path.join(ROOT_DIR, 'public', 'images', 'logo.png');

if (!fs.existsSync(OUTPUT_DIR)) {
  fs.mkdirSync(OUTPUT_DIR, { recursive: true });
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
  
  /* Outer Tech Border */
  .canvas-frame {
    width: 1152px;
    height: 1152px;
    margin: 24px;
    border-radius: 28px;
    border: 1.5px solid rgba(74, 111, 165, 0.45);
    background: rgba(15, 23, 42, 0.7);
    backdrop-filter: blur(20px);
    box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.7), inset 0 1px 0 rgba(255, 255, 255, 0.1);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 36px 42px;
    position: relative;
    overflow: hidden;
  }
  
  /* Subtle Tech Grid overlay */
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

  /* Header Styles */
  .top-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 22px;
    border-bottom: 1.5px solid rgba(255, 255, 255, 0.08);
  }
  .brand-group {
    display: flex;
    align-items: center;
    gap: 16px;
  }
  .brand-logo {
    height: 52px;
    width: auto;
    object-fit: contain;
    filter: drop-shadow(0 4px 12px rgba(74, 111, 165, 0.4));
  }
  .brand-title {
    font-size: 28px;
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

  /* Footer Styles */
  .bottom-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 20px;
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

  /* Reusable Components */
  .badge-category {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(56, 189, 248, 0.12);
    border: 1px solid rgba(56, 189, 248, 0.4);
    color: #38BDF8;
    padding: 6px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
  }
  .glow-title {
    font-size: 50px;
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
    padding: 16px 28px;
    display: inline-flex;
    flex-direction: column;
  }
  .price-label {
    font-size: 14px;
    font-weight: 700;
    color: #FBBF24;
    text-transform: uppercase;
    letter-spacing: 1px;
  }
  .price-val {
    font-size: 42px;
    font-weight: 900;
    color: #FFFFFF;
    line-height: 1.1;
  }
  .price-val span {
    font-size: 18px;
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
    padding: 12px 18px;
    font-size: 16px;
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
        <span>Garansi Uptime 99.9% • Full Root SSH Access</span>
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
// DEFINITION OF THE 6 PRODUCT SETS (18 BANNERS)
// -------------------------------------------------------------

const products = [
  // 1. MASTER CLOUD VPS KVM
  {
    id: 'vps_master',
    folderName: 'cloud-vps-kvm',
    title: 'Cloud VPS KVM NVMe Indonesia',
    p1: {
      category: 'ENTERPRISE PERFORMANCE • HARGA MAHASISWA',
      headline: 'CLOUD VPS KVM NVMe <span class="accent">INDONESIA</span>',
      subhead: 'Deploy bot 24 jam, web bisnis, REST API, hingga multi-container Docker dengan performa 100% NVMe kencang & stabil.',
      price: 'Rp 80.000',
      pricePeriod: '/bulan',
      pills: [
        { icon: 'cpu', text: '1 s/d 8 Core vCPU' },
        { icon: 'ram', text: '1 GB s/d 16 GB RAM' },
        { icon: 'disk', text: '100% NVMe PCIe RAID-10' },
        { icon: 'network', text: 'Port 1 Gbps Unlimited Fair' },
        { icon: 'terminal', text: 'Akses Penuh Root SSH' },
        { icon: 'shield', text: 'Virtualisasi KVM Murni' },
      ],
      heroCard: `
        <div style="background: rgba(15, 23, 42, 0.85); border: 1.5px solid rgba(56, 189, 248, 0.4); border-radius: 20px; padding: 24px 30px; box-shadow: 0 15px 35px rgba(0,0,0,0.5);">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; border-bottom:1px solid rgba(255,255,255,0.08); padding-bottom:12px;">
            <div style="display:flex; align-items:center; gap:10px;">
              <span style="width:12px; height:12px; border-radius:50%; background:#10B981; box-shadow:0 0 10px #10B981;"></span>
              <span class="mono" style="font-size:15px; color:#38BDF8; font-weight:700;">SERVER STATUS: ONLINE (JAKARTA)</span>
            </div>
            <span style="background:rgba(106,189,115,0.2); color:#6ABD73; border:1px solid #6ABD73; padding:4px 12px; border-radius:6px; font-size:13px; font-weight:800;">READY TO DEPLOY</span>
          </div>
          <div style="display:grid; grid-cols: 3; grid-template-columns: repeat(3, 1fr); gap:16px;">
            <div style="background:rgba(30,41,59,0.7); padding:14px; border-radius:12px; text-align:center;">
              <div style="color:#94A3B8; font-size:13px; font-weight:700;">LATENSI LOKAL</div>
              <div class="mono" style="color:#38BDF8; font-size:22px; font-weight:800; margin-top:4px;">&lt; 5 ms</div>
              <div style="color:#64748B; font-size:11px;">Datacenter Jakarta</div>
            </div>
            <div style="background:rgba(30,41,59,0.7); padding:14px; border-radius:12px; text-align:center;">
              <div style="color:#94A3B8; font-size:13px; font-weight:700;">STORAGE DISK</div>
              <div class="mono" style="color:#6ABD73; font-size:22px; font-weight:800; margin-top:4px;">100% NVMe</div>
              <div style="color:#64748B; font-size:11px;">Gen4 Ultra Fast</div>
            </div>
            <div style="background:rgba(30,41,59,0.7); padding:14px; border-radius:12px; text-align:center;">
              <div style="color:#94A3B8; font-size:13px; font-weight:700;">KONTROL SISTEM</div>
              <div class="mono" style="color:#FBBF24; font-size:22px; font-weight:800; margin-top:4px;">ROOT SSH</div>
              <div style="color:#64748B; font-size:11px;">Bebas Install Apapun</div>
            </div>
          </div>
          <div style="margin-top:16px; padding-top:12px; border-top:1px solid rgba(255,255,255,0.06); display:flex; justify-content:space-between; align-items:center;">
            <span style="font-size:14px; color:#CBD5E1;">Support OS: <strong>Ubuntu 22/24 • Debian 11/12 • AlmaLinux • Docker</strong></span>
            <span class="mono" style="font-size:13px; color:#38BDF8; background:rgba(56,189,248,0.1); padding:4px 10px; border-radius:6px;">Static Dedicated IP</span>
          </div>
        </div>
      `
    },
    p2: {
      title: 'DETAIL SPESIFIKASI & PILIHAN PAKET',
      subtitle: 'Tersedia 6 variasi paket sesuai kebutuhan workload Anda di Shopee:',
      tableRows: [
        { name: 'Student Basic', cpu: '1 vCPU', ram: '1 GB', disk: '20 GB NVMe', bw: '30 Mbps', price: 'Rp 80.000', note: 'Belajar Linux & portfolio web dasar' },
        { name: 'Mahasiswa Basic', cpu: '2 vCPU', ram: '2 GB', disk: '40 GB NVMe', bw: '30 Mbps', price: 'Rp 90.000', note: 'Praktikum TI, mini server & bot WA/TG' },
        { name: 'Standard ⭐', cpu: '2 vCPU', ram: '4 GB', disk: '60 GB NVMe', bw: '30 Mbps', price: 'Rp 110.000', note: 'Terpopuler! Web bisnis, Coolify & REST API' },
        { name: 'Premium', cpu: '2 vCPU', ram: '8 GB', disk: '100 GB NVMe', bw: '30 Mbps', price: 'Rp 210.000', note: 'Multi Docker Container & beban tinggi' },
        { name: 'Startup', cpu: '4 vCPU', ram: '8 GB', disk: '80 GB NVMe', bw: '100 Mbps', price: 'Rp 350.000', note: 'Scale up traffic & backend aplikasi startup' },
        { name: 'Business', cpu: '8 vCPU', ram: '16 GB', disk: '80 GB NVMe', bw: '100 Mbps', price: 'Rp 590.000', note: 'Database besar & enterprise reliability' },
      ],
      features: [
        'Dedicated Public IPv4 Statis (Bukan IP Shared)',
        'Virtualisasi Hardware KVM Murni (Resource Dijamin 100%)',
        'Bebas Reinstall OS Kapan Saja via Panel / Request Chat',
        'Tersedia Pilihan 1-Click App: Coolify, Dokploy, aaPanel, Docker',
        'Tanpa Kontrak Mengikat — Sistem Langganan Bulanan Fleksibel',
      ]
    },
    p3: {
      title: 'KENAPA HARUS MEMILIH CRAVACLOUDCLOUD?',
      subtitle: 'Keunggulan utama yang membuat ribuan pengguna memilih CravaCloud di Shopee:',
      reasons: [
        { title: 'Datacenter Tier-3 Jakarta', desc: 'Latensi super rendah 3–10ms untuk pengunjung Indonesia. Anti-lag & respon server secepat kilat.', icon: 'flagId' },
        { title: '100% Enterprise NVMe SSD', desc: 'Kecepatan read/write 5x lipat dibanding SSD biasa. Booting dan compile kode dalam hitungan detik.', icon: 'bolt' },
        { title: 'Garansi Uptime 99.9%', desc: 'Infrastruktur andal, proteksi DDoS terintegrasi, menjaga bot & website Anda aktif 24 jam nonstop.', icon: 'shield' },
        { title: 'Full Akses Root SSH', desc: 'Kebebasan penuh tanpa batasan. Bebas install Python, Node.js, Laravel, Go, VPN, atau scraper bot.', icon: 'terminal' },
        { title: 'Bantuan Setup & Ramah Pemula', desc: 'Bingung cara pakai? Tim support siap membantu panduan SSH dan instalasi via WhatsApp & Chat Shopee.', icon: 'ram' },
        { title: 'Aktivasi Kilat via Chat', desc: 'Setelah checkout di Shopee, kirim email di chat, detail server langsung dikirim dalam hitungan menit!', icon: 'check' },
      ]
    }
  },

  // 2. MASTER AI COMBO & WORKSTATION
  {
    id: 'ai_master',
    folderName: 'vps-ai-combo',
    title: 'Cloud VPS AI Stack & Coding Agent',
    p1: {
      category: 'ZERO SETUP • SIAP CODING DALAM 5 MENIT',
      headline: 'VPS AI CODING & <span class="accent">WORKSTATION</span>',
      subhead: 'Ngoding ditemani AI Agent langsung di browser iPad, laptop kentang, atau tablet. Claude Code CLI, OpenCode, Ollama, & Dify siap pakai!',
      price: 'Rp 115.000',
      pricePeriod: '/bulan',
      pills: [
        { icon: 'terminal', text: 'Claude Code CLI Pre-installed' },
        { icon: 'server', text: 'VS Code Web Browser HTTPS' },
        { icon: 'cpu', text: 'OpenCode CLI Multi-Provider' },
        { icon: 'ram', text: 'Anti-Crash Swap Memory' },
        { icon: 'disk', text: 'Docker, Git, Node, Python 3.12' },
        { icon: 'bolt', text: 'Ollama & Dify AI Studio Ready' },
      ],
      heroCard: `
        <div style="background: rgba(15, 23, 42, 0.85); border: 1.5px solid rgba(56, 189, 248, 0.4); border-radius: 20px; padding: 24px 30px; box-shadow: 0 15px 35px rgba(0,0,0,0.5);">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; border-bottom:1px solid rgba(255,255,255,0.08); padding-bottom:12px;">
            <div style="display:flex; align-items:center; gap:10px;">
              <span style="width:12px; height:12px; border-radius:50%; background:#38BDF8; box-shadow:0 0 10px #38BDF8;"></span>
              <span class="mono" style="font-size:15px; color:#38BDF8; font-weight:700;">AI ENVIRONMENT: READY (ZERO SETUP)</span>
            </div>
            <span style="background:rgba(56,189,248,0.15); color:#38BDF8; border:1px solid #38BDF8; padding:4px 12px; border-radius:6px; font-size:13px; font-weight:800;">PRE-CONFIGURED</span>
          </div>
          <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:16px;">
            <div style="background:rgba(30,41,59,0.7); padding:14px; border-radius:12px; text-align:center;">
              <div style="color:#94A3B8; font-size:13px; font-weight:700;">VS CODE BROWSER</div>
              <div class="mono" style="color:#38BDF8; font-size:20px; font-weight:800; margin-top:4px;">Web GUI</div>
              <div style="color:#64748B; font-size:11px;">Buka dari Chrome / Safari</div>
            </div>
            <div style="background:rgba(30,41,59,0.7); padding:14px; border-radius:12px; text-align:center;">
              <div style="color:#94A3B8; font-size:13px; font-weight:700;">AI CODING AGENT</div>
              <div class="mono" style="color:#6ABD73; font-size:20px; font-weight:800; margin-top:4px;">Claude Code</div>
              <div style="color:#64748B; font-size:11px;">+ OpenCode CLI Ready</div>
            </div>
            <div style="background:rgba(30,41,59,0.7); padding:14px; border-radius:12px; text-align:center;">
              <div style="color:#94A3B8; font-size:13px; font-weight:700;">LOCAL ENGINE</div>
              <div class="mono" style="color:#FBBF24; font-size:20px; font-weight:800; margin-top:4px;">Ollama / Dify</div>
              <div style="color:#64748B; font-size:11px;">Private RAG & Workflows</div>
            </div>
          </div>
          <div style="margin-top:16px; padding-top:12px; border-top:1px solid rgba(255,255,255,0.06); display:flex; justify-content:space-between; align-items:center;">
            <span style="font-size:14px; color:#CBD5E1;">Hemat Baterai & Laptop Dingin • Auto-Save di Cloud 24 Jam</span>
            <span class="mono" style="font-size:13px; color:#6ABD73; background:rgba(106,189,115,0.1); padding:4px 10px; border-radius:6px;">Tinggal Tempel API Key</span>
          </div>
        </div>
      `
    },
    p2: {
      title: 'DETAIL FITUR & PILIHAN STACK AI',
      subtitle: 'Pilih spesifikasi AI Workstation sesuai kebutuhan development Anda di Shopee:',
      tableRows: [
        { name: 'Terminal Coding Agent', cpu: '2 vCPU', ram: '4 GB', disk: '60 GB NVMe', bw: '30 Mbps', price: 'Rp 115.000', note: 'Claude Code CLI + OpenCode + Dev Essentials' },
        { name: 'Cloud AI Workstation ⭐', cpu: '2 vCPU', ram: '6 GB', disk: '80 GB NVMe', bw: '50 Mbps', price: 'Rp 150.000', note: 'Best Value! Web VS Code Server + AI Terminal' },
        { name: 'Hermes Autonomous Hub', cpu: '4 vCPU', ram: '8 GB', disk: '100 GB NVMe', bw: '100 Mbps', price: 'Rp 185.000', note: 'Hermes Agent Runtime + OmniRoute Proxy AI' },
        { name: 'Enterprise Private AI & RAG', cpu: '4 vCPU', ram: '16 GB', disk: '120 GB NVMe', bw: '100 Mbps', price: 'Rp 265.000', note: 'Dify AI + Ollama Engine + Qdrant Vector DB' },
      ],
      features: [
        'Pre-Configured: Langsung ngoding tanpa pusing instalasi dependensi rumit',
        'Web VS Code Server: Akses editor lengkap via browser Google Chrome / iPad',
        'Ekstensi Siap Pakai: Tailwind CSS, Prettier, Python, Docker, GitLens',
        'Anti-Crash Swap Memory: Alokasi virtual RAM otomatis agar compile kode berat tetap stabil',
        'Auto-Save Cloud: Laptop ditutup atau mati lampu, coding & AI task tetap berjalan di server',
      ]
    },
    p3: {
      title: 'MENGAPA DEVELOPER WAJIB PUNYA VPS AI INI?',
      subtitle: 'Revolusi cara coding modern bertenaga Artificial Intelligence:',
      reasons: [
        { title: 'Ngoding di Mana Saja', desc: 'Cukup buka browser dari laptop spek rendah, iPad, atau tablet. Semua komputasi berat ditangani cloud.', icon: 'server' },
        { title: 'AI Coding Agent Mandiri', desc: 'Ketik "claude" atau "opencode", asisten AI langsung menganalisis file, refactor, dan bikin fitur otomatis.', icon: 'terminal' },
        { title: 'Laptop Dingin & Baterai Awet', desc: 'Proses compile, build Docker, dan training model berjalan di server CravaCloud, laptop Anda tetap adem.', icon: 'bolt' },
        { title: 'Keamanan Data Privat (RAG)', desc: 'Eksekusi model LLM secara lokal via Ollama dan Qdrant tanpa khawatir data rahasia perusahaan bocor.', icon: 'shield' },
        { title: 'Sangat Cocok untuk Mahasiswa', desc: 'Solusi terbaik untuk praktikum, tugas akhir skripsi AI, backend development tanpa perlu ganti laptop baru.', icon: 'ram' },
        { title: 'Zero Setup & Panduan Lengkap', desc: 'Tinggal klik link web VS Code, masukkan password yang dikirim via chat, dan langsung mulai koding.', icon: 'check' },
      ]
    }
  },

  // 3. MASTER MANAGED DATABASE DEDICATED VPS
  {
    id: 'db_master',
    folderName: 'managed-database-vps',
    title: 'Managed Database Dedicated VPS',
    p1: {
      category: 'ISOLASI MURNI BEBAS CRASH • LATENSI <10MS',
      headline: 'MANAGED DATABASE <span class="accent">DEDICATED VPS</span>',
      subhead: 'Database server terisolasi murni bebas gangguan web server. Siap ditembak langsung dari Vercel, Railway, VPS aplikasi, maupun local.',
      price: 'Rp 95.000',
      pricePeriod: '/bulan',
      pills: [
        { icon: 'database', text: 'PostgreSQL 16 • MySQL 8 • MariaDB' },
        { icon: 'bolt', text: 'Redis Cache • MongoDB 7' },
        { icon: 'network', text: 'Dedicated Static IPv4 Publik' },
        { icon: 'shield', text: 'Auto Backup Harian Terjadwal' },
        { icon: 'ram', text: 'Kapasitas hingga 1.200+ Koneksi' },
        { icon: 'cpu', text: 'AI Vector (Qdrant / Chroma / pgvector)' },
      ],
      heroCard: `
        <div style="background: rgba(15, 23, 42, 0.85); border: 1.5px solid rgba(56, 189, 248, 0.4); border-radius: 20px; padding: 24px 30px; box-shadow: 0 15px 35px rgba(0,0,0,0.5);">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; border-bottom:1px solid rgba(255,255,255,0.08); padding-bottom:12px;">
            <div style="display:flex; align-items:center; gap:10px;">
              <span style="width:12px; height:12px; border-radius:50%; background:#10B981; box-shadow:0 0 10px #10B981;"></span>
              <span class="mono" style="font-size:15px; color:#38BDF8; font-weight:700;">DATABASE CLUSTER: ISOLATED & SECURE</span>
            </div>
            <span style="background:rgba(106,189,115,0.15); color:#6ABD73; border:1px solid #6ABD73; padding:4px 12px; border-radius:6px; font-size:13px; font-weight:800;">100% UPTIME SAFE</span>
          </div>
          <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:16px;">
            <div style="background:rgba(30,41,59,0.7); padding:14px; border-radius:12px; text-align:center;">
              <div style="color:#94A3B8; font-size:13px; font-weight:700;">CONCURRENT CONNS</div>
              <div class="mono" style="color:#38BDF8; font-size:20px; font-weight:800; margin-top:4px;">Hingga 1.200+</div>
              <div style="color:#64748B; font-size:11px;">Anti drop saat lonjakan</div>
            </div>
            <div style="background:rgba(30,41,59,0.7); padding:14px; border-radius:12px; text-align:center;">
              <div style="color:#94A3B8; font-size:13px; font-weight:700;">QUERY LATENCY</div>
              <div class="mono" style="color:#6ABD73; font-size:20px; font-weight:800; margin-top:4px;">&lt; 3-8 ms</div>
              <div style="color:#64748B; font-size:11px;">Datacenter Jakarta</div>
            </div>
            <div style="background:rgba(30,41,59,0.7); padding:14px; border-radius:12px; text-align:center;">
              <div style="color:#94A3B8; font-size:13px; font-weight:700;">DATA SAFETY</div>
              <div class="mono" style="color:#FBBF24; font-size:20px; font-weight:800; margin-top:4px;">Daily Backup</div>
              <div style="color:#64748B; font-size:11px;">Otomatis & Terisolasi</div>
            </div>
          </div>
          <div style="margin-top:16px; padding-top:12px; border-top:1px solid rgba(255,255,255,0.06); display:flex; justify-content:space-between; align-items:center;">
            <span style="font-size:14px; color:#CBD5E1;">Support GUI Client: <strong>DBeaver • TablePlus • Navicat • pgAdmin • phpMyAdmin</strong></span>
            <span class="mono" style="font-size:13px; color:#38BDF8; background:rgba(56,189,248,0.1); padding:4px 10px; border-radius:6px;">SSL / TLS Encrypted</span>
          </div>
        </div>
      `
    },
    p2: {
      title: 'SPESIFIKASI & KAPASITAS MANAGED DATABASE',
      subtitle: 'Pilih tier database sesuai volume data & proyeksi traffic aplikasi Anda di Shopee:',
      tableRows: [
        { name: 'DB Micro', cpu: '2 vCPU', ram: '2 GB', disk: '40 GB NVMe', bw: '20 Mbps', price: 'Rp 95.000', note: 'Dev, staging, tugas kuliah, traffic 50-80 koneksi' },
        { name: 'DB Standard ⭐', cpu: '2 vCPU', ram: '4 GB', disk: '60 GB NVMe', bw: '30 Mbps', price: 'Rp 145.000', note: 'Terpopuler! UMKM & SaaS, 200-350 koneksi, 2.5GB Cache' },
        { name: 'DB Enterprise / Vector', cpu: '2 vCPU', ram: '8 GB', disk: '80 GB NVMe', bw: '30 Mbps', price: 'Rp 225.000', note: 'Big Data & AI Vector Search, 800-1200+ koneksi' },
      ],
      features: [
        'Dedicated Public Static IP: Bebas diakses dari Vercel, Railway, VPS, atau AWS',
        'Pilihan Engine: PostgreSQL 16, MySQL 8.0, MariaDB, Redis, MongoDB 7, Qdrant',
        '100% Resource Terisolasi: Web server aplikasi crash, data bisnis Anda tetap 100% aman',
        'Memory Swap Anti-Crash: Database tetap stabil saat menghadapi query join multi-tabel berat',
        'Auto-Backup Harian: Tersimpan rapi untuk menjamin data tidak hilang saat insiden',
      ]
    },
    p3: {
      title: 'MENGAPA DATABASE ANDA WAJIB DEDICATED?',
      subtitle: 'Solusi profesional agar website & backend aplikasi Anda anti-down:',
      reasons: [
        { title: '100% Bebas Crash Terpisah', desc: 'Saat web aplikasi kehabisan RAM atau error, database tetap hidup & stabil melayani koneksi lain.', icon: 'shield' },
        { title: 'Query Respon Secepat Kilat', desc: 'Latensi Jakarta super cepat 3–8ms dengan storage NVMe Gen4 dan alokasi memory query cache khusus.', icon: 'bolt' },
        { title: 'Universal Connection', desc: 'Langsung konek dari Vercel, Supabase alternative, VPS frontend, hingga GUI DBeaver via IP publik aman.', icon: 'network' },
        { title: 'Skrip Backup Otomatis Harian', desc: 'Data transaksi bisnis Anda dicadangkan secara berkala untuk perlindungan keamanan maksimal.', icon: 'database' },
        { title: 'Siap untuk AI Vector & RAG', desc: 'Tersedia Qdrant, Chroma, & pgvector untuk kebutuhan embedding dokumen dan chatbot AI modern.', icon: 'cpu' },
        { title: 'Bantuan Setup Engine Gratis', desc: 'Tim kami bantu install database pilihan Anda (MySQL/Postgres/Redis) sampai siap dikoneksikan.', icon: 'check' },
      ]
    }
  },

  // 4. PAKET SPESIFIK: VPS MAHASISWA & PELAJAR (Best Budget Seller)
  {
    id: 'vps_mahasiswa',
    folderName: 'vps-mahasiswa',
    title: 'Cloud VPS Mahasiswa & Pelajar Murah',
    p1: {
      category: 'PAKET KHUSUS MAHASISWA IT & PEMULA',
      headline: 'CLOUD VPS <span class="green">MAHASISWA</span> BASIC',
      subhead: 'Resource 2 vCPU lega & 2 GB RAM dengan storage 40 GB NVMe kencang. Pilihan terbaik untuk praktikum kampus, bot Telegram 24 jam, & skripsi!',
      price: 'Rp 90.000',
      pricePeriod: '/bulan flat',
      pills: [
        { icon: 'cpu', text: '2 Core vCPU Lega' },
        { icon: 'ram', text: '2 GB RAM DDR4' },
        { icon: 'disk', text: '40 GB Enterprise NVMe' },
        { icon: 'terminal', text: 'Full Root Access SSH' },
        { icon: 'network', text: 'Port 1 Gbps / Jakarta DC' },
        { icon: 'shield', text: 'Bebas Reinstall OS' },
      ],
      heroCard: `
        <div style="background: rgba(15, 23, 42, 0.85); border: 1.5px solid rgba(106, 189, 115, 0.5); border-radius: 20px; padding: 24px 30px; box-shadow: 0 15px 35px rgba(0,0,0,0.5);">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; border-bottom:1px solid rgba(255,255,255,0.08); padding-bottom:12px;">
            <span style="background:rgba(106,189,115,0.2); color:#6ABD73; border:1px solid #6ABD73; padding:4px 12px; border-radius:6px; font-size:13px; font-weight:800;">REKOMENDASI KAMPUS & PRAKTIKUM</span>
            <span class="mono" style="font-size:13px; color:#FBBF24;">HARGA RAMAH KANTONG</span>
          </div>
          <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:16px;">
            <div style="background:rgba(30,41,59,0.7); padding:14px; border-radius:12px; text-align:center;">
              <div style="color:#94A3B8; font-size:13px; font-weight:700;">PROCESSOR</div>
              <div class="mono" style="color:#6ABD73; font-size:22px; font-weight:800; margin-top:4px;">2 vCPU</div>
              <div style="color:#64748B; font-size:11px;">KVM Virtualization</div>
            </div>
            <div style="background:rgba(30,41,59,0.7); padding:14px; border-radius:12px; text-align:center;">
              <div style="color:#94A3B8; font-size:13px; font-weight:700;">MEMORY</div>
              <div class="mono" style="color:#38BDF8; font-size:22px; font-weight:800; margin-top:4px;">2 GB RAM</div>
              <div style="color:#64748B; font-size:11px;">+ Anti-Crash Swap</div>
            </div>
            <div style="background:rgba(30,41,59,0.7); padding:14px; border-radius:12px; text-align:center;">
              <div style="color:#94A3B8; font-size:13px; font-weight:700;">PENYIMPANAN</div>
              <div class="mono" style="color:#FBBF24; font-size:22px; font-weight:800; margin-top:4px;">40 GB NVMe</div>
              <div style="color:#64748B; font-size:11px;">Super Kencang</div>
            </div>
          </div>
          <div style="margin-top:16px; padding-top:12px; border-top:1px solid rgba(255,255,255,0.06); display:flex; justify-content:space-between; align-items:center;">
            <span style="font-size:14px; color:#CBD5E1;">Ideal untuk: Bot Discord/WA, Web Laravel, API Node.js, Praktikum Linux</span>
            <span class="mono" style="font-size:13px; color:#10B981; font-weight:700;">Dibantu Panduan dari 0</span>
          </div>
        </div>
      `
    },
    p2: {
      title: 'SPESIFIKASI LENGKAP PAKET MAHASISWA',
      subtitle: 'Spesifikasi detail server KVM yang Anda dapatkan saat order di Shopee:',
      tableRows: [
        { name: 'Processor', cpu: '2 Core vCPU KVM (High Performance Compute)', ram: '', disk: '', bw: '', price: '', note: 'Mampu handle multi-tasking komputasi' },
        { name: 'Memory (RAM)', cpu: '2 GB DDR4 RAM + Swap Memory Virtual Otomatis', ram: '', disk: '', bw: '', price: '', note: 'Bebas out-of-memory saat compile' },
        { name: 'Storage Disk', cpu: '40 GB Enterprise NVMe PCIe RAID-10', ram: '', disk: '', bw: '', price: '', note: 'I/O speed up to 3.000 MB/s' },
        { name: 'Koneksi Network', cpu: 'Port 1 Gbps • 30 Mbps Bandwidth (Fair Use)', ram: '', disk: '', bw: '', price: '', note: 'Datacenter Tier-3 Jakarta (<10ms)' },
        { name: 'Akses & Kontrol', cpu: 'Akses Root SSH Penuh (Privilege 100% Milik Anda)', ram: '', disk: '', bw: '', price: '', note: 'Bebas install panel & framework apa pun' },
        { name: 'Dedicated IP', cpu: '1 Dedicated IPv4 Publik Statis', ram: '', disk: '', bw: '', price: '', note: 'Bisa diarahkan ke custom domain (.com/.id)' },
      ],
      features: [
        'Pilihan OS Populer: Ubuntu 24.04 LTS, Ubuntu 22.04 LTS, Debian 12, Debian 11',
        'Support Semua Framework: Laravel, Node.js, Express, FastAPI, Django, Flutter Web',
        'Bisa Pasang Control Panel: HestiaCP, aaPanel, CloudPanel, CyberPanel',
        'Aktif 24 Jam Nonstop: Bot trading, bot Discord, atau bot WhatsApp jalan terus',
      ]
    },
    p3: {
      title: 'KEUNGGULAN BELI DI CRAVACLOUDCLOUD',
      subtitle: 'Mengapa mahasiswa & pelajar di seluruh Indonesia mempercayai CravaCloud:',
      reasons: [
        { title: 'Harga Sangat Terjangkau', desc: 'Hanya Rp 90.000/bulan flat tanpa biaya tersembunyi. Tidak perlu kartu kredit (bisa bayar via Shopee/SPayLater).', icon: 'bolt' },
        { title: 'Panduan Ramah Pemula', desc: 'Baru pertama kali pakai VPS? Diberikan tutorial login SSH via PuTTY/Termius dan konsultasi gratis via chat.', icon: 'terminal' },
        { title: 'Server Lokal Anti-Lemot', desc: 'Server berlokasi di Jakarta Indonesia, ping ke provider lokal (Indihome, Biznet, Telkomsel) di bawah 10ms.', icon: 'flagId' },
        { title: 'Garansi Uptime & Penggantian', desc: 'Jaminan server aktif stabil. Jika ada kendala teknis, tim teknis sigap membantu penyelesaian.', icon: 'shield' },
        { title: 'Aman untuk Skripsi & Ujian', desc: 'Resource dedicated KVM menjamin aplikasi tidak akan terganggu tetangga server lain.', icon: 'ram' },
        { title: 'Pengiriman Cepat via Chat Shopee', desc: 'Order masuk langsung diproses. Detail IP, username root, dan password dikirim via chat Shopee.', icon: 'check' },
      ]
    }
  },

  // 5. PAKET SPESIFIK: CLOUD AI WORKSTATION (Best Seller AI)
  {
    id: 'vps_ai_workstation',
    folderName: 'cloud-ai-workstation',
    title: 'Cloud AI Workstation (Web VS Code + Claude Code)',
    p1: {
      category: '⭐ PALING POPULER • BEST VALUE AI',
      headline: 'CLOUD AI <span class="accent">WORKSTATION</span>',
      subhead: 'Koding dengan asisten AI dari browser Google Chrome / iPad di mana saja. Dilengkapi Web VS Code Server resmi & Claude Code CLI!',
      price: 'Rp 150.000',
      pricePeriod: '/bulan',
      pills: [
        { icon: 'cpu', text: '2 Core vCPU KVM' },
        { icon: 'ram', text: '6 GB RAM High-Speed' },
        { icon: 'disk', text: '80 GB Enterprise NVMe' },
        { icon: 'server', text: 'Web VS Code Server' },
        { icon: 'terminal', text: 'Claude Code & OpenCode' },
        { icon: 'bolt', text: 'Auto-Save & Background Runner' },
      ],
      heroCard: `
        <div style="background: rgba(15, 23, 42, 0.85); border: 1.5px solid rgba(56, 189, 248, 0.5); border-radius: 20px; padding: 24px 30px; box-shadow: 0 15px 35px rgba(0,0,0,0.5);">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; border-bottom:1px solid rgba(255,255,255,0.08); padding-bottom:12px;">
            <span style="background:rgba(56,189,248,0.2); color:#38BDF8; border:1px solid #38BDF8; padding:4px 12px; border-radius:6px; font-size:13px; font-weight:800;">BROWSER-BASED DEVELOPMENT ENVIRONMENT</span>
            <span class="mono" style="font-size:13px; color:#6ABD73; font-weight:700;">HTTPS SECURE ACCESS</span>
          </div>
          <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:16px;">
            <div style="background:rgba(30,41,59,0.7); padding:14px; border-radius:12px; text-align:center;">
              <div style="color:#94A3B8; font-size:13px; font-weight:700;">EDITOR CLOUD</div>
              <div class="mono" style="color:#38BDF8; font-size:20px; font-weight:800; margin-top:4px;">VS Code Web</div>
              <div style="color:#64748B; font-size:11px;">Buka di Browser</div>
            </div>
            <div style="background:rgba(30,41,59,0.7); padding:14px; border-radius:12px; text-align:center;">
              <div style="color:#94A3B8; font-size:13px; font-weight:700;">AI INTEGRATED</div>
              <div class="mono" style="color:#6ABD73; font-size:20px; font-weight:800; margin-top:4px;">Claude + Open</div>
              <div style="color:#64748B; font-size:11px;">CLI Terminal Ready</div>
            </div>
            <div style="background:rgba(30,41,59,0.7); padding:14px; border-radius:12px; text-align:center;">
              <div style="color:#94A3B8; font-size:13px; font-weight:700;">SPEK SERVER</div>
              <div class="mono" style="color:#FBBF24; font-size:20px; font-weight:800; margin-top:4px;">6 GB / 80 GB</div>
              <div style="color:#64748B; font-size:11px;">NVMe SSD Lega</div>
            </div>
          </div>
          <div style="margin-top:16px; padding-top:12px; border-top:1px solid rgba(255,255,255,0.06); display:flex; justify-content:space-between; align-items:center;">
            <span style="font-size:14px; color:#CBD5E1;">Ekstensi Pre-installed: <strong>Tailwind, Prettier, Python, Docker, GitLens</strong></span>
            <span class="mono" style="font-size:13px; color:#38BDF8; background:rgba(56,189,248,0.1); padding:4px 10px; border-radius:6px;">Bebas Install Ekstensi Lain</span>
          </div>
        </div>
      `
    },
    p2: {
      title: 'DETAIL FITUR CLOUD AI WORKSTATION',
      subtitle: 'Semua peralatan koding modern telah terpasang rapi di dalam server:',
      tableRows: [
        { name: 'Web VS Code Server', cpu: 'Akses editor via browser HTTPS aman dengan password terenkripsi', ram: '', disk: '', bw: '', price: '', note: 'Bisa dibuka dari Mac, Windows, Linux, iPad, Android' },
        { name: 'Claude Code CLI', cpu: 'Asisten coding autonomous resmi dari Anthropic di terminal', ram: '', disk: '', bw: '', price: '', note: 'Tinggal masukkan API Key Anthropic Anda' },
        { name: 'OpenCode CLI', cpu: 'Alternatif autonomous coding agent multi-model open-source', ram: '', disk: '', bw: '', price: '', note: 'Mendukung OpenAI, DeepSeek, Google Gemini' },
        { name: 'Dev Tools Lengkap', cpu: 'Git, GitHub CLI (gh), Docker, Docker Compose, Node.js LTS, Python 3.12', ram: '', disk: '', bw: '', price: '', note: 'Terminal Zsh/Bash dengan autocomplete kencang' },
        { name: 'Alokasi Hardware', cpu: '2 Core vCPU • 6 GB RAM DDR4 • 80 GB NVMe • 50 Mbps Bandwidth', ram: '', disk: '', bw: '', price: '', note: 'Datacenter Jakarta Latensi <10ms' },
      ],
      features: [
        'Auto-Save Terus Menerus: Coding Anda aman di cloud, tak perlu takut laptop mati mendadak',
        'Background Runner: Task build kode dan server API tetap berjalan 24 jam meski browser ditutup',
        'Dukungan Terminal Terintegrasi: Jalankan perintah Linux apa pun langsung dari panel bawah VS Code',
        'Ekstensi Siap Pakai: Telah terpasang ekstensi populer untuk produktivitas instan',
      ]
    },
    p3: {
      title: 'KENAPA WORKSTATION CLOUD INI COCOK UNTUK ANDA?',
      subtitle: 'Hemat jutaan rupiah dibanding upgrade laptop baru:',
      reasons: [
        { title: 'Laptop Spek Pas-pasan Jadi Ngebut', desc: 'Beban berat compile dan AI ditanggung server cloud. Laptop Anda tidak panas dan kipas tidak berisik.', icon: 'bolt' },
        { title: 'Koding Fleksibel dari Mana Saja', desc: 'Buka dari laptop kantor, warnet, maupun iPad saat nongkrong di cafe tanpa perlu bawa laptop berat.', icon: 'server' },
        { title: 'Hemat Waktu Setup Environment', desc: 'Tidak perlu install Node, Python, Docker satu per satu. Semua sudah pre-configured dan teruji jalan.', icon: 'check' },
        { title: 'Tugas Selesai Lebih Cepat dengan AI', desc: 'Minta Claude Code menuliskan kode, memperbaiki bug, dan refaktor langsung dari terminal server.', icon: 'terminal' },
        { title: 'Privat & Milik Anda Sendiri', desc: 'Server dedicated virtual terisolasi penuh dengan akses root. Kode dan file proyek Anda aman.', icon: 'shield' },
        { title: 'Garansi & Support Ramah Shopee', desc: 'Detail login dan tutorial langsung dikirim via chat Shopee setelah checkout.', icon: 'star' },
      ]
    }
  },

  // 6. PAKET SPESIFIK: CLOUD VPS STANDARD (Best Seller Produksi)
  {
    id: 'vps_standard',
    folderName: 'vps-standard-4gb',
    title: 'Cloud VPS Standard 4GB RAM (Best Production)',
    p1: {
      category: '⭐ TERPOPULER • PILIHAN IDEAL WEBSITE & API',
      headline: 'CLOUD VPS <span class="accent">STANDARD</span> 4GB RAM',
      subhead: 'Pilihan paling populer untuk website bisnis, REST API, bot 24 jam, dan aplikasi Docker. Performa stabil dengan 2 Core vCPU & 60 GB NVMe.',
      price: 'Rp 110.000',
      pricePeriod: '/bulan flat',
      pills: [
        { icon: 'cpu', text: '2 Core vCPU KVM' },
        { icon: 'ram', text: '4 GB RAM DDR4' },
        { icon: 'disk', text: '60 GB Enterprise NVMe' },
        { icon: 'network', text: 'Dedicated IPv4 Static' },
        { icon: 'terminal', text: 'Support Coolify & Docker' },
        { icon: 'shield', text: 'Datacenter Tier-3 Jakarta' },
      ],
      heroCard: `
        <div style="background: rgba(15, 23, 42, 0.85); border: 1.5px solid rgba(56, 189, 248, 0.5); border-radius: 20px; padding: 24px 30px; box-shadow: 0 15px 35px rgba(0,0,0,0.5);">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; border-bottom:1px solid rgba(255,255,255,0.08); padding-bottom:12px;">
            <span style="background:rgba(56,189,248,0.2); color:#38BDF8; border:1px solid #38BDF8; padding:4px 12px; border-radius:6px; font-size:13px; font-weight:800;">PAKET TERLARIS DI SHOPEE</span>
            <span class="mono" style="font-size:13px; color:#FBBF24;">RECOMMENDED PRODUCTION</span>
          </div>
          <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:16px;">
            <div style="background:rgba(30,41,59,0.7); padding:14px; border-radius:12px; text-align:center;">
              <div style="color:#94A3B8; font-size:13px; font-weight:700;">PROCESSOR</div>
              <div class="mono" style="color:#38BDF8; font-size:22px; font-weight:800; margin-top:4px;">2 vCPU</div>
              <div style="color:#64748B; font-size:11px;">KVM Virtualization</div>
            </div>
            <div style="background:rgba(30,41,59,0.7); padding:14px; border-radius:12px; text-align:center;">
              <div style="color:#94A3B8; font-size:13px; font-weight:700;">MEMORY RAM</div>
              <div class="mono" style="color:#6ABD73; font-size:22px; font-weight:800; margin-top:4px;">4 GB RAM</div>
              <div style="color:#64748B; font-size:11px;">DDR4 High-Speed</div>
            </div>
            <div style="background:rgba(30,41,59,0.7); padding:14px; border-radius:12px; text-align:center;">
              <div style="color:#94A3B8; font-size:13px; font-weight:700;">STORAGE NVME</div>
              <div class="mono" style="color:#FBBF24; font-size:22px; font-weight:800; margin-top:4px;">60 GB NVMe</div>
              <div style="color:#64748B; font-size:11px;">PCIe Gen4 RAID-10</div>
            </div>
          </div>
          <div style="margin-top:16px; padding-top:12px; border-top:1px solid rgba(255,255,255,0.06); display:flex; justify-content:space-between; align-items:center;">
            <span style="font-size:14px; color:#CBD5E1;">Tersedia Opsi 1-Click Stack: <strong>Coolify PaaS • CyberPanel • Dokploy • Docker</strong></span>
            <span class="mono" style="font-size:13px; color:#38BDF8; background:rgba(56,189,248,0.1); padding:4px 10px; border-radius:6px;">Uptime 99.9% SLA</span>
          </div>
        </div>
      `
    },
    p2: {
      title: 'DETAIL SPESIFIKASI PAKET STANDARD',
      subtitle: 'Spesifikasi seimbang untuk aplikasi backend dan website produksi bisnis:',
      tableRows: [
        { name: 'Compute Core', cpu: '2 Core vCPU KVM (Dedicated Thread Performance)', ram: '', disk: '', bw: '', price: '', note: 'Sanggup handle ratusan concurrent requests' },
        { name: 'Memory Kapasitas', cpu: '4 GB RAM DDR4 + Alokasi Anti-Crash Swap', ram: '', disk: '', bw: '', price: '', note: 'Cukup untuk web server + DB + Redis sekaligus' },
        { name: 'Enterprise Disk', cpu: '60 GB NVMe SSD RAID-10', ram: '', disk: '', bw: '', price: '', note: 'Read/Write super kencang anti lemot' },
        { name: 'Jaringan & Bandwidth', cpu: '30 Mbps Fair Use • Port 1 Gbps Port Speed', ram: '', disk: '', bw: '', price: '', note: 'Datacenter Jakarta (<10ms ke pengguna lokal)' },
        { name: 'IP Publik & Kontrol', cpu: 'Dedicated IPv4 Publik Static + Full Root Access', ram: '', disk: '', bw: '', price: '', note: 'Bisa diarahkan ke SSL/TLS dan domain sendiri' },
      ],
      features: [
        'Sangat Pas untuk Stack Coolify: Ubah VPS Anda jadi alternatif Vercel / Heroku pribadi gratis',
        'Kuat untuk Production: Web company profile, toko online WooCommerce, REST API mobile app',
        'Bebas Reinstall OS: Ubuntu 24/22 LTS, Debian 12/11, AlmaLinux kapan saja',
        'Uptime 99.9% Terjamin: Server berjalan 24 jam nonstop dengan pemantauan otomatis',
      ]
    },
    p3: {
      title: 'MENGAPA PAKET STANDARD PALING BANYAK DIPILIH?',
      subtitle: 'Sweet spot antara harga hemat dan resource bertenaga:',
      reasons: [
        { title: 'Kapasitas RAM 4GB Lega', desc: 'Bebas kendala out-of-memory saat menjalankan Nginx, Node.js/PHP, dan MySQL dalam satu server.', icon: 'ram' },
        { title: 'Hemat Pengeluaran Hosting', desc: 'Hanya Rp 110.000/bln sudah dapat VPS sekelas server kantor tanpa harus sewa cloud mahal luar negeri.', icon: 'bolt' },
        { title: 'Koneksi Cepat Datacenter Jakarta', desc: 'Pengunjung website Anda dari seluruh Indonesia akan merasakan loading website yang instan.', icon: 'flagId' },
        { title: 'Siap Pakai Coolify PaaS', desc: 'Bisa request langsung diinstall Coolify, tinggal git push kodingan Anda langsung live otomatis.', icon: 'server' },
        { title: 'Garansi & CS Fast Response', desc: 'Tim technical CravaCloud siap membantu jika ada pertanyaan seputar konfigurasi dan deployment.', icon: 'shield' },
        { title: 'Pemesanan Praktis di Shopee', desc: 'Checkout di Shopee, konfirmasi via chat, server Anda langsung online dalam hitungan menit.', icon: 'check' },
      ]
    }
  }
];

// -------------------------------------------------------------
// HTML TEMPLATE BUILDERS FOR PHOTO 1, 2, 3
// -------------------------------------------------------------

function renderPhoto1(prod) {
  const p = prod.p1;
  const pillsHtml = p.pills.map(pill => `
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
          <div class="badge-category">${p.category}</div>
          <div class="glow-title" style="margin-top: 10px;">${p.headline}</div>
          <div style="font-size: 17px; color: #CBD5E1; max-width: 700px; line-height: 1.5; margin-top: 8px;">
            ${p.subhead}
          </div>
        </div>
        <div class="price-box">
          <div class="price-label">Mulai Dari</div>
          <div class="price-val">${p.price}<span>${p.pricePeriod}</span></div>
        </div>
      </div>

      <!-- Feature Pills Grid -->
      <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 5px;">
        ${pillsHtml}
      </div>

      <!-- Centerpiece Hero Card -->
      <div style="margin-top: 5px;">
        ${p.heroCard}
      </div>
    </div>
  `;

  return wrapHtml(content);
}

function renderPhoto2(prod) {
  const p = prod.p2;

  let tableHtml = '';
  if (p.tableRows && p.tableRows.length > 0) {
    const isMasterTable = p.tableRows[0].ram !== '';
    if (isMasterTable) {
      tableHtml = `
        <table style="width: 100%; border-collapse: separate; border-spacing: 0; background: rgba(15, 23, 42, 0.7); border: 1.5px solid rgba(255, 255, 255, 0.1); border-radius: 16px; overflow: hidden;">
          <thead>
            <tr style="background: rgba(74, 111, 165, 0.25); color: #38BDF8; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">
              <th style="padding: 12px 16px; text-align: left;">Paket</th>
              <th style="padding: 12px 14px; text-align: center;">vCPU</th>
              <th style="padding: 12px 14px; text-align: center;">RAM</th>
              <th style="padding: 12px 14px; text-align: center;">Storage</th>
              <th style="padding: 12px 14px; text-align: center;">Bandwidth</th>
              <th style="padding: 12px 16px; text-align: right;">Harga/Bln</th>
            </tr>
          </thead>
          <tbody>
            ${p.tableRows.map((r, i) => `
              <tr style="background: ${i % 2 === 0 ? 'rgba(30, 41, 59, 0.4)' : 'rgba(15, 23, 42, 0.4)'}; border-top: 1px solid rgba(255,255,255,0.06);">
                <td style="padding: 11px 16px; font-weight: 700; color: #FFFFFF; font-size: 15px;">
                  ${r.name}
                  <div style="font-size: 11px; color: #94A3B8; font-weight: 400; margin-top: 2px;">${r.note}</div>
                </td>
                <td class="mono" style="padding: 11px 14px; text-align: center; color: #38BDF8; font-weight: 700; font-size: 14px;">${r.cpu}</td>
                <td class="mono" style="padding: 11px 14px; text-align: center; color: #6ABD73; font-weight: 700; font-size: 14px;">${r.ram}</td>
                <td class="mono" style="padding: 11px 14px; text-align: center; color: #FBBF24; font-weight: 700; font-size: 14px;">${r.disk}</td>
                <td class="mono" style="padding: 11px 14px; text-align: center; color: #E2E8F0; font-size: 13px;">${r.bw}</td>
                <td class="mono" style="padding: 11px 16px; text-align: right; color: #FFFFFF; font-weight: 800; font-size: 15px;">${r.price}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      `;
    } else {
      // Single Product Deep Dive Table
      tableHtml = `
        <div style="background: rgba(15, 23, 42, 0.7); border: 1.5px solid rgba(255, 255, 255, 0.1); border-radius: 16px; overflow: hidden; padding: 6px 16px;">
          ${p.tableRows.map((r, i) => `
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 12px; border-bottom: ${i === p.tableRows.length - 1 ? 'none' : '1px solid rgba(255,255,255,0.08)'};">
              <div style="width: 220px; font-size: 16px; font-weight: 700; color: #38BDF8;">${r.name}</div>
              <div style="flex: 1; font-size: 16px; font-weight: 700; color: #FFFFFF;">${r.cpu}</div>
              <div style="width: 280px; text-align: right; font-size: 13px; color: #94A3B8;">${r.note}</div>
            </div>
          `).join('')}
        </div>
      `;
    }
  }

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
        <div class="badge-category">SPESIFIKASI & PILIHAN VARIASI</div>
        <div class="glow-title" style="margin-top: 8px; font-size: 42px;">${p.title}</div>
        <div style="font-size: 16px; color: #CBD5E1; margin-top: 4px;">
          ${p.subtitle}
        </div>
      </div>

      <!-- Spec Table -->
      ${tableHtml}

      <!-- Bottom Feature Card -->
      <div style="background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(74, 111, 165, 0.4); border-radius: 16px; padding: 20px 24px;">
        <div style="font-size: 14px; font-weight: 800; color: #38BDF8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;">
          FITUR & KEUNGGULAN TEKNIS TERMASUK:
        </div>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px 24px;">
          ${checklistHtml}
        </div>
      </div>
    </div>
  `;

  return wrapHtml(content);
}

function renderPhoto3(prod) {
  const p = prod.p3;

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
          KENAPA BELI DI CRAVACLOUDCLOUD?
        </div>
        <div class="glow-title" style="margin-top: 8px; font-size: 40px;">${p.title}</div>
        <div style="font-size: 16px; color: #CBD5E1; margin-top: 4px;">
          ${p.subtitle}
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
const tmpProfile = path.resolve(process.env.TEMP || 'C:\\temp', 'chrome_shopee_profile');

async function renderAll() {
  console.log('=== CRAVACLOUD SHOPEE BANNER GENERATOR ===');
  console.log(`Generating images for ${products.length} products (3 photos each = ${products.length * 3} banners)...`);

  const manifest = [];

  for (const prod of products) {
    console.log(`\nProcessing: [${prod.id}] ${prod.title}`);
    const prodDir = path.join(OUTPUT_DIR, prod.folderName);
    if (!fs.existsSync(prodDir)) {
      fs.mkdirSync(prodDir, { recursive: true });
    }

    const photos = [
      { num: 1, name: '1_utama.png', type: 'Foto Utama (Hero / Cover Catalog)', html: renderPhoto1(prod) },
      { num: 2, name: '2_detail.png', type: 'Foto Detail (Spesifikasi & Fitur Lengkap)', html: renderPhoto2(prod) },
      { num: 3, name: '3_keunggulan.png', type: 'Foto Daya Tarik Pembeli (Value & Trust)', html: renderPhoto3(prod) },
    ];

    const prodRecord = {
      id: prod.id,
      title: prod.title,
      folder: prod.folderName,
      photos: []
    };

    for (const photo of photos) {
      const htmlPath = path.join(prodDir, `temp_${photo.name}.html`);
      const imgPath = path.join(prodDir, photo.name);
      const rootImgPath = path.join(OUTPUT_DIR, `shopee_${prod.id}_${photo.name}`);

      fs.writeFileSync(htmlPath, photo.html, 'utf-8');
      const fileUrl = 'file:///' + htmlPath.replace(/\\/g, '/');

      console.log(`  -> Rendering Photo ${photo.num}: ${photo.name}`);
      const cmd = `"${chromePath}" --headless=new --disable-gpu --no-sandbox --disable-setuid-sandbox --user-data-dir="${tmpProfile}" --screenshot="${imgPath}" --window-size=1200,1200 "${fileUrl}"`;
      
      try {
        execSync(cmd, { stdio: 'pipe' });
        // Also copy to root shopee folder for quick flat access
        fs.copyFileSync(imgPath, rootImgPath);

        const stat = fs.statSync(imgPath);
        console.log(`     [OK] Generated (${Math.round(stat.size / 1024)} KB) -> ${imgPath}`);

        prodRecord.photos.push({
          num: photo.num,
          filename: photo.name,
          rootFilename: `shopee_${prod.id}_${photo.name}`,
          type: photo.type,
          sizeKb: Math.round(stat.size / 1024),
          relPath: `${prod.folderName}/${photo.name}`
        });
      } catch (err) {
        console.error(`     [ERROR] Failed to render ${photo.name}:`, err.message);
      } finally {
        if (fs.existsSync(htmlPath)) {
          fs.unlinkSync(htmlPath);
        }
      }
    }

    manifest.push(prodRecord);
  }

  // Save manifest json
  fs.writeFileSync(path.join(OUTPUT_DIR, 'manifest.json'), JSON.stringify(manifest, null, 2), 'utf-8');

  // Generate Interactive Gallery HTML for Easy Review & Download
  generateGalleryHtml(manifest);

  console.log('\n=============================================');
  console.log('ALL SHOPEE PRODUCT BANNERS GENERATED SUCCESSFULLY!');
  console.log(`Output Directory: ${OUTPUT_DIR}`);
  console.log(`Interactive Preview: ${path.join(OUTPUT_DIR, 'index.html')}`);
  console.log('=============================================\n');
}

// -------------------------------------------------------------
// GALLERY HTML GENERATOR
// -------------------------------------------------------------

function generateGalleryHtml(manifest) {
  const html = `<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CravaCloud — Katalog Foto Produk Shopee Official</title>
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
    max-width: 1300px;
    margin: 0 auto;
  }
  header {
    text-align: center;
    margin-bottom: 50px;
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
    max-width: 750px;
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
  .prod-card {
    background: #111827;
    border: 1px solid rgba(74, 111, 165, 0.3);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 40px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.4);
  }
  .prod-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    padding-bottom: 16px;
  }
  .prod-title {
    font-size: 24px;
    font-weight: 800;
    color: #FFFFFF;
  }
  .photo-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
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
    font-size: 14px;
    font-weight: 800;
    color: #38BDF8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
  }
  .photo-desc {
    font-size: 13px;
    color: #94A3B8;
    margin-bottom: 14px;
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
    padding: 10px 16px;
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
    <h1>Katalog Foto Produk Shopee <span>CravaCloud</span></h1>
    <p class="subtitle">18 Gambar Siap Upload Beresolusi Tinggi (1200 x 1200 px HD Rasio 1:1) dengan Tema Resmi CravaCloud & Branding Shopee <strong>cravacloud</strong>.</p>
    <div class="badge-official">
      ✓ Official Store Shopee: cravacloud • 3 Foto per Produk (Utama, Detail, Keunggulan)
    </div>
  </header>

  ${manifest.map((prod, idx) => `
    <div class="prod-card">
      <div class="prod-header">
        <div>
          <span style="font-size: 13px; font-weight: 800; color: #38BDF8; text-transform: uppercase; letter-spacing: 1px;">PRODUK ${idx + 1}</span>
          <div class="prod-title">${prod.title}</div>
        </div>
        <span style="font-size: 14px; color: #94A3B8;">Folder: <code>${prod.folder}/</code></span>
      </div>

      <div class="photo-grid">
        ${prod.photos.map(p => `
          <div class="photo-item">
            <div class="img-wrapper">
              <a href="${p.relPath}" target="_blank">
                <img src="${p.relPath}" alt="${p.type}" loading="lazy">
              </a>
            </div>
            <div class="photo-meta">
              <div>
                <div class="photo-label">FOTO ${p.num}: ${p.num === 1 ? 'UTAMA / COVER' : p.num === 2 ? 'DETAIL SPEK' : 'DAYA TARIK PEMBELI'}</div>
                <div class="photo-desc">${p.type} (${p.sizeKb} KB, 1200x1200px)</div>
              </div>
              <a href="${p.relPath}" download="${prod.id}_foto_${p.num}.png" class="btn-download">
                ⬇ Unduh Foto ${p.num}
              </a>
            </div>
          </div>
        `).join('')}
      </div>
    </div>
  `).join('')}

  <div class="tips-box">
    <div class="tips-title">💡 Panduan Upload Produk di Shopee Seller Centre:</div>
    <ul style="padding-left: 20px; color: #CBD5E1; font-size: 14px; display: flex; flex-direction: column; gap: 8px;">
      <li><strong>Urutan Foto:</strong> Jadikan <code>Foto 1 (Utama)</code> sebagai cover utama produk di Shopee, lalu masukkan <code>Foto 2 (Detail Spek)</code> di slot ke-2, dan <code>Foto 3 (Daya Tarik Pembeli)</code> di slot ke-3.</li>
      <li><strong>Kategori Shopee:</strong> Masukkan ke kategori <em>Komputer & Aksesoris ➔ Software & Aplikasi</em> atau <em>Jasa Digital / Voucher</em>.</li>
      <li><strong>Variasi:</strong> Pada tab Variasi, tambahkan nama paket (misal: Student, Mahasiswa, Standard, dst.) beserta harganya masing-masing sesuai tabel pada Foto 2.</li>
      <li><strong>Pengiriman:</strong> Aktifkan "Termasuk Ongkos Kirim" (Rp 0) untuk produk digital. Setelah pembeli order, kirimkan kredensial server via Chat Shopee / Email.</li>
    </ul>
  </div>
</div>
</body>
</html>`;

  fs.writeFileSync(path.join(OUTPUT_DIR, 'index.html'), html, 'utf-8');
}

renderAll().catch(console.error);
