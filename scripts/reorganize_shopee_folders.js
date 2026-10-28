import fs from 'fs';
import path from 'path';

const ROOT_DIR = process.cwd();
const SHOPEE_DIR = path.join(ROOT_DIR, 'public', 'images', 'shopee');
const OLD_PKG_DIR = path.join(SHOPEE_DIR, 'packages');

// Mapping of packages to their clean, descriptive folder names
const packageDefinitions = [
  {
    oldId: '01_student_basic',
    cleanFolderName: '01 - VPS Student Basic (Rp 80.000)',
    catSlug: 'vps',
    catName: 'Cloud VPS Reguler',
    name: 'Student Basic',
    badge: 'Entry Level',
    price: 'Rp 80.000/bln'
  },
  {
    oldId: '02_mahasiswa_basic',
    cleanFolderName: '02 - VPS Mahasiswa Basic (Rp 90.000)',
    catSlug: 'vps',
    catName: 'Cloud VPS Reguler',
    name: 'Mahasiswa Basic',
    badge: 'Khusus Mahasiswa',
    price: 'Rp 90.000/bln'
  },
  {
    oldId: '03_standard',
    cleanFolderName: '03 - VPS Standard 4GB RAM (Rp 110.000)',
    catSlug: 'vps',
    catName: 'Cloud VPS Reguler',
    name: 'Standard 4GB RAM',
    badge: '⭐ Paling Terpopuler',
    price: 'Rp 110.000/bln'
  },
  {
    oldId: '04_premium',
    cleanFolderName: '04 - VPS Premium 8GB RAM (Rp 210.000)',
    catSlug: 'vps',
    catName: 'Cloud VPS Reguler',
    name: 'Premium 8GB RAM',
    badge: 'High Memory',
    price: 'Rp 210.000/bln'
  },
  {
    oldId: '05_startup',
    cleanFolderName: '05 - VPS Startup 4 Core (Rp 350.000)',
    catSlug: 'vps',
    catName: 'Cloud VPS Reguler',
    name: 'Startup 4 Core',
    badge: 'Scale Up',
    price: 'Rp 350.000/bln'
  },
  {
    oldId: '06_business',
    cleanFolderName: '06 - VPS Business 8 Core 16GB (Rp 590.000)',
    catSlug: 'vps',
    catName: 'Cloud VPS Reguler',
    name: 'Business 8 Core / 16GB',
    badge: 'Enterprise Tier',
    price: 'Rp 590.000/bln'
  },
  {
    oldId: '07_terminal_coding_agent',
    cleanFolderName: '07 - AI Terminal Coding Agent (Rp 115.000)',
    catSlug: 'ai_combo',
    catName: 'AI Stack & Workstation',
    name: 'Terminal Coding Agent',
    badge: 'Terminal / SSH AI',
    price: 'Rp 115.000/bln'
  },
  {
    oldId: '08_cloud_ai_workstation',
    cleanFolderName: '08 - AI Cloud Workstation VS Code (Rp 150.000)',
    catSlug: 'ai_combo',
    catName: 'AI Stack & Workstation',
    name: 'Cloud AI Workstation',
    badge: '⭐ Paling Populer / Best Value',
    price: 'Rp 150.000/bln'
  },
  {
    oldId: '09_hermes_autonomous_hub',
    cleanFolderName: '09 - AI Hermes Autonomous Hub (Rp 185.000)',
    catSlug: 'ai_combo',
    catName: 'AI Stack & Workstation',
    name: 'Hermes Autonomous Hub',
    badge: 'Autonomous AI Hub',
    price: 'Rp 185.000/bln'
  },
  {
    oldId: '10_enterprise_private_ai_rag',
    cleanFolderName: '10 - AI Enterprise Private RAG (Rp 265.000)',
    catSlug: 'ai_combo',
    catName: 'AI Stack & Workstation',
    name: 'Enterprise Private AI & RAG',
    badge: 'Private AI & RAG',
    price: 'Rp 265.000/bln'
  },
  {
    oldId: '11_db_micro',
    cleanFolderName: '11 - Database Micro (Rp 95.000)',
    catSlug: 'managed_db',
    catName: 'Managed Database VPS',
    name: 'DB Micro',
    badge: 'DB Entry Level',
    price: 'Rp 95.000/bln'
  },
  {
    oldId: '12_db_standard',
    cleanFolderName: '12 - Database Standard 4GB RAM (Rp 145.000)',
    catSlug: 'managed_db',
    catName: 'Managed Database VPS',
    name: 'DB Standard 4GB RAM',
    badge: '⭐ Paling Terpopuler',
    price: 'Rp 145.000/bln'
  },
  {
    oldId: '13_db_enterprise_vector',
    cleanFolderName: '13 - Database Enterprise AI Vector (Rp 225.000)',
    catSlug: 'managed_db',
    catName: 'Managed Database VPS',
    name: 'DB Enterprise / AI Vector',
    badge: 'Enterprise & AI Vector',
    price: 'Rp 225.000/bln'
  },
];

console.log('=== REORGANIZING SHOPEE ASSETS INTO CLEAN FOLDERS ===\n');

const manifest = [];

// 1. Move/Copy files into each package's clean dedicated folder
for (const pkg of packageDefinitions) {
  const targetFolder = path.join(SHOPEE_DIR, pkg.cleanFolderName);
  if (!fs.existsSync(targetFolder)) {
    fs.mkdirSync(targetFolder, { recursive: true });
  }

  const srcDir = path.join(OLD_PKG_DIR, pkg.oldId);

  const fileMapping = [
    { src: '1_utama.png', dest: '1_foto_utama.png', label: 'Foto 1 (Utama / Cover Shopee)' },
    { src: '2_detail.png', dest: '2_foto_detail_spesifikasi.png', label: 'Foto 2 (Detail Spesifikasi)' },
    { src: '3_keunggulan.png', dest: '3_foto_keunggulan_daya_tarik.png', label: 'Foto 3 (Daya Tarik Pembeli)' },
  ];

  const photos = [];

  for (const fm of fileMapping) {
    const srcFile = path.join(srcDir, fm.src);
    const destFile = path.join(targetFolder, fm.dest);

    if (fs.existsSync(srcFile)) {
      fs.copyFileSync(srcFile, destFile);
      const stat = fs.statSync(destFile);
      photos.push({
        name: fm.dest,
        label: fm.label,
        sizeKb: Math.round(stat.size / 1024),
        relPath: `${encodeURIComponent(pkg.cleanFolderName)}/${fm.dest}`
      });
    }
  }

  manifest.push({
    ...pkg,
    folder: pkg.cleanFolderName,
    photos
  });

  console.log(`[OK] Folder siap: "${pkg.cleanFolderName}" (3 foto)`);
}

// 2. Clean up old loose files & old redundant directories in public/images/shopee/
console.log('\nCleaning loose root files and obsolete directories...');
const allItems = fs.readdirSync(SHOPEE_DIR);

const keepItems = new Set([
  ...packageDefinitions.map(p => p.cleanFolderName),
  'index.html',
  'manifest_folders.json'
]);

for (const item of allItems) {
  if (keepItems.has(item)) continue;

  const itemPath = path.join(SHOPEE_DIR, item);
  try {
    const stat = fs.statSync(itemPath);
    if (stat.isDirectory()) {
      fs.rmSync(itemPath, { recursive: true, force: true });
      console.log(`  - Removed obsolete folder: ${item}`);
    } else {
      fs.unlinkSync(itemPath);
      console.log(`  - Removed loose file: ${item}`);
    }
  } catch (err) {
    console.error(`  - Failed to remove ${item}:`, err.message);
  }
}

// 3. Save clean manifest
fs.writeFileSync(path.join(SHOPEE_DIR, 'manifest_folders.json'), JSON.stringify(manifest, null, 2), 'utf-8');

// 4. Generate updated index.html pointing to the clean folders
generateUpdatedGalleryHtml(manifest);

console.log('\n=============================================');
console.log('REORGANIZATION COMPLETE! All 13 packages are now in clean 1:1 dedicated folders.');
console.log(`Location: ${SHOPEE_DIR}`);
console.log('=============================================\n');

function generateUpdatedGalleryHtml(manifest) {
  const html = `<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CravaCloud — Foto Produk Shopee (13 Folder Paket)</title>
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
  .folder-badge {
    background: rgba(106, 189, 115, 0.15);
    border: 1px solid #6ABD73;
    color: #6ABD73;
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
    padding: 6px 12px;
    border-radius: 8px;
    font-weight: 700;
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
    <h1>Katalog 13 Folder Paket Shopee <span>CravaCloud</span></h1>
    <p class="subtitle">Setiap paket telah dipisahkan ke dalam 1 folder khusus di Windows Explorer. Tiap folder berisi 3 foto beresolusi tinggi (1200 x 1200 px HD) yang siap langsung di-upload ke Shopee <strong>cravacloud</strong>.</p>
    <div class="badge-official">
      ✓ 13 Folder Khusus • 3 Foto Tiap Folder • Siap Upload ke Shopee Seller Centre
    </div>
  </header>

  <!-- Filter Tabs -->
  <div class="tabs">
    <button class="tab-btn active" onclick="filterCat('all', this)">Semua 13 Folder Paket</button>
    <button class="tab-btn" onclick="filterCat('vps', this)">Cloud VPS Reguler (6 Folder)</button>
    <button class="tab-btn" onclick="filterCat('ai_combo', this)">AI Stack & Workstation (4 Folder)</button>
    <button class="tab-btn" onclick="filterCat('managed_db', this)">Managed Database VPS (3 Folder)</button>
  </div>

  ${manifest.map((p) => `
    <div class="prod-card" data-cat="${p.catSlug}">
      <div class="prod-header">
        <div>
          <div style="font-size: 12px; font-weight: 800; color: #38BDF8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">
            ${p.catName}
          </div>
          <div class="prod-title">
            <span>${p.name}</span>
            <span class="badge-tag">${p.badge}</span>
          </div>
        </div>
        <div style="display: flex; align-items: center; gap: 14px;">
          <div class="badge-price">${p.price}</div>
          <div class="folder-badge">📁 ${p.folder}</div>
        </div>
      </div>

      <div class="photo-grid">
        ${p.photos.map((photo, i) => `
          <div class="photo-item">
            <div class="img-wrapper">
              <a href="${photo.relPath}" target="_blank">
                <img src="${photo.relPath}" alt="${photo.label}" loading="lazy">
              </a>
            </div>
            <div class="photo-meta">
              <div>
                <div class="photo-label">FOTO ${i + 1}: ${i === 0 ? 'UTAMA / COVER' : i === 1 ? 'DETAIL SPEK' : 'DAYA TARIK PEMBELI'}</div>
                <div class="photo-desc">${photo.name} (${photo.sizeKb} KB, 1200x1200px)</div>
              </div>
              <a href="${photo.relPath}" download="${photo.name}" class="btn-download">
                ⬇ Unduh Foto ${i + 1}
              </a>
            </div>
          </div>
        `).join('')}
      </div>
    </div>
  `).join('')}

  <div class="tips-box">
    <div class="tips-title">💡 Struktur Folder di Komputer Anda:</div>
    <p style="color: #CBD5E1; font-size: 14px; margin-bottom: 12px;">
      Buka Windows Explorer di: <code>d:\\Project - CravaCloud\\cravacloud\\public\\images\\shopee\\</code><br>
      Anda akan melihat 13 folder yang tersusun rapi dari nomor 01 sampai 13. Cukup buka folder paket yang ingin Anda upload ke Shopee, di dalamnya sudah tersedia 3 file berurutan:
    </p>
    <ul style="padding-left: 20px; color: #94A3B8; font-size: 13.5px; display: flex; flex-direction: column; gap: 6px;">
      <li><code>1_foto_utama.png</code> ➔ Jadikan sebagai Cover Utama Produk di Shopee.</li>
      <li><code>2_foto_detail_spesifikasi.png</code> ➔ Masukkan sebagai Foto ke-2 (Rincian spek hardware lengkap).</li>
      <li><code>3_foto_keunggulan_daya_tarik.png</code> ➔ Masukkan sebagai Foto ke-3 (6 alasan memilih & panduan order).</li>
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

  fs.writeFileSync(path.join(SHOPEE_DIR, 'index.html'), html, 'utf-8');
}
