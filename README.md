# CravaCloud

Platform Cloud VPS & Infrastructure as a Service (IaaS) dengan performa enterprise, storage NVMe RAID-10 ultra-cepat, akses root SSH penuh, dan integrasi 1-click stack untuk developer & pelajar di Indonesia.

## Fitur Utama

- **Cloud VPS**: Paket Student Basic, Freelance Starter, Production Pro, hingga Enterprise Node.
- **AI & Automation Stacks**: 1-click Ollama AI, AnythingLLM, LibreChat, n8n Automation, Agent Zero, OpenClaw, 9Router, OmniRoute.
- **Control Panels**: Coolify, Dokploy, aaPanel, CloudPanel, CyberPanel, HestiaCP.
- **Managed Databases**: PostgreSQL, MySQL, Redis, MongoDB, Qdrant Vector DB, CloudBeaver.
- **Authentication**: Email/Password dengan 4-segment Strongmeter, 2FA TOTP Authenticator, Google OAuth 2.0.
- **Multi-tenant Organization & Team Management**: Role-based access control (Admin, Member) & Invitation System.
- **Payment Integration**: Midtrans Snap & Sandbox/Dev Simulator dengan dukungan QRIS, Virtual Account (BCA, Mandiri, BNI, BRI, BSI, Permata, CIMB), E-Wallet, dan Retail.
- **Shopee Integration & Auto-Provisioning**: Alur sinkronisasi order marketplace dengan provisioning otomatis.

## Persyaratan Sistem

- PHP >= 8.2 (ekstensi: pdo, pdo_mysql, mbstring, curl, gd, zip, bcmath)
- Composer >= 2.x
- Node.js >= 18.x & NPM
- MySQL >= 8.0

## Instalasi & Setup Lokal

1. **Clone repository:**
   ```bash
   git clone https://github.com/your-org/cravacloud.git
   cd cravacloud
   ```

2. **Install dependensi PHP & Node.js:**
   ```bash
   composer install
   npm install
   ```

3. **Salin file konfigurasi:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Konfigurasi Database & Jalankan Migrasi:**
   Sesuaikan `DB_*` pada file `.env`, lalu jalankan:
   ```bash
   php artisan migrate --seed
   ```

5. **Jalankan Aplikasi:**
   ```bash
   # Terminal 1: Backend Server
   php artisan serve --port=8050

   # Terminal 2: Queue Listener
   php artisan queue:listen

   # Terminal 3: Frontend Asset Compiler
   npm run dev
   ```

## Branching Strategy

- `main` : Production-ready branch.
- `staging` : Pre-production / staging environment branch.
- `dev` : Active development branch.

---
© 2026 CravaCloud. All rights reserved. Created by cravacloud.
