# Blueprint Katalog Lengkap Control Panel & 1-Click Applications CravaCloud (Full Hostinger Parity+)

> **Tujuan Dokumen:** Menjadi cetak biru katalog terlengkap **Control Panel** dan **1-Click Applications** agar CravaCloud tidak hanya sebanding dengan **Hostinger VPS (`OS & Panel` + `Docker Manager Catalog` + `Game Panel`)**, tetapi melampauinya dengan stack khusus developer Indonesia, AI Agents, DevOps, BaaS, Bisnis/ERP, VPN/Remote, hingga Game Server — lengkap dengan aturan **lisensi berbayar (BYOL)**, **batas minimum RAM**, **protokol firewall TCP+UDP**, dan **arsitektur Single Source of Truth**.

---

## 1. Posisi CravaCloud Saat Ini vs Hostinger VPS

### 1.1 Kondisi CravaCloud Saat Ini
- **Sistem Operasi (terverifikasi live `DescribeBlueprints` di `ap-jakarta` & `ap-singapore`):** 10 distro Linux non-EOL aktif (`ubuntu2404`, `ubuntu2204`, `ubuntu2604`, `ubuntu2004`, `debian13`, `debian12`, `debian11`, `rocky10`, `rocky94`, `centos_stream9`) + 3 image pre-baked Docker29 (`ubuntu2404_docker`, `debian13_docker`, `centos_stream9_docker`). Semua bundle CravaCloud (`_lin_`) adalah Linux-only (`SupportWindowsPlatform=false`).
- **Control Panel saat ini (7):** `Coolify`, `Dokploy`, `aaPanel`, `CloudPanel`, `Docker Engine`, `CyberPanel`, `HestiaCP`.
- **1-Click Apps saat ini (18 checkout + 1 AI CLI):** `Hermes Agent`, `OpenClaw`, `OmniRoute`, `9router`, `Agent Zero`, `n8n`, `Ollama`, `Dify + Ollama`, `AnythingLLM`, `LibreChat`, `VS Code Server`, `Gitea / Forgejo`, `Uptime Kuma`, `Netdata / Beszel`, `WordPress`, `Ghost`, `Strapi / Directus`, `PrestaShop / Bagisto`, dan `Claude Code & OpenCode`.

### 1.2 Benchmark Resmi Katalog Hostinger VPS (130+ Template)
Berdasarkan dokumentasi resmi Hostinger (`support.hostinger.com`, `hostinger.com/tutorials`, `Docker Manager Catalog`, dan `Game Panel`):
- **Control Panels:** CloudPanel, CyberPanel, aaPanel, HestiaCP, Coolify, Dokploy, Easypanel, ServerKit, Cosmos Cloud, FASTPANEL, Webmin/Virtualmin, ISPConfig, CapRover + panel komersial BYOL (cPanel/WHM, DirectAdmin, Plesk).
- **Docker Catalog & 1-Click Apps (130+):** Portainer, Nginx Proxy Manager, Traefik, Supabase, Appwrite, PocketBase, NocoDB, PostgreSQL/MySQL/MongoDB/Redis, MinIO, Meilisearch, Qdrant, Laravel/LEMP, Open WebUI, Flowise, Langfuse, n8n, Grafana, Umami, Plausible, Metabase, GitLab CE, Jenkins, BookStack, Outline, Paperless-ngx, Stirling PDF, Invoice Ninja, Chatwoot, Typebot, Listmonk, Mautic, Odoo, ERPNext, Dolibarr, Twenty CRM, Nextcloud, ownCloud, Immich, Jellyfin, Plex, Vaultwarden, WireGuard (`wg-easy`), OpenVPN, Apache Guacamole, AdGuard Home, Pi-hole, Authentik, Keycloak, Homarr, Dozzle, Watchtower, FreshRSS, SearXNG, Linkwarden, Home Assistant, dll.
- **Game Panel:** Pterodactyl, Minecraft, Palworld, Valheim, Terraria, Rust, Counter-Strike 2, Factorio, FiveM, dll.

---

## 2. Kebijakan Wajib: Panel Berbayar (BYOL) & Sistem Popup Warning Interaktif

CravaCloud menyediakan template panel berbayar (**cPanel / WHM**, **DirectAdmin**, **Plesk Obsidian**, **Webuzo**, **Enhance**) agar setara dengan Hostinger, **serta** sejumlah aplikasi yang memiliki prasyarat RAM atau aktivasi khusus (mis. token GSLT Steam, license key gratis FiveM, atau port UDP VPN). Agar user tetap paham 100% tanpa merasa diblokir diam-diam:

1. **Badge Tegas di Kartu Stack:**
   - Panel gratis: `Gratis / Open-Source`
   - Panel berbayar: **`Berbayar · BYOL`** atau **`Belum Termasuk Lisensi`** (badge amber `#F59E0B` mencolok + ikon tanda seru).
   - Prasyarat khusus / RAM kurang: badge **`Butuh Min. X GB RAM`** (merah `#EF4444`) atau **`Info Aktivasi`** (amber/cyan).
2. **Button / Kartu Tetap Bisa Diklik → Munculkan Popup Konfirmasi Kecil (`Warning Modal`):**
   - Saat user mengklik tombol/kartu panel berbayar atau stack ber-warning (dan belum menyetujui warning tersebut), stack **belum langsung aktif terpilih**, melainkan langsung memunculkan **popup modal ringkas** di tengah layar berisi alasan spesifik:
     - **Tipe `license` (Panel Berbayar BYOL):** Menegaskan bahwa CravaCloud **hanya menginstal perangkat lunak/installer resmi panel**, **tidak menjual/menyertakan license key**, estimasi biaya langsung ke vendor (*cPanel / DirectAdmin / Plesk / Webuzo / Enhance*), dan kewajiban aktivasi license/trial mandiri di dalam panel setelah VPS nyala. Tombol: **`Batal`** vs **`Saya Mengerti, Pilih Panel Ini`**.
     - **Tipe `ram` (RAM Paket < `minRamGb`):** Menegaskan paket yang dipilih saat ini (`Starter XS 2 GB` / `S 4 GB`) tidak cukup untuk menjalankan stack tersebut tanpa risiko OOM crash. Tombol: **`Tutup`** vs **`Ganti ke Paket Min. X GB`** (mengarahkan ke `/#pricing` di checkout atau info upgrade di Reinstall).
     - **Tipe `activation` / `network` (Contoh: CS2 GSLT, FiveM txAdmin key, VPN UDP):** Menampilkan 2–3 poin singkat prasyarat aktivasi setelah VPS aktif. Tombol: **`Batal`** vs **`Mengerti, Pilih Aplikasi Ini`**.
3. **State Konfirmasi & Validasi Ketat di Server (`OrderController` & `Reinstall`):**
   - Menekan tombol konfirmasi di popup mencatat persetujuan di frontend (`acknowledgedWarnings[stackKey] = true`) + menandai pill kecil **`✓ Info Disetujui`** di kartu stack.
   - Saat form checkout atau modal reinstall dikirim, backend memvalidasi `warning_acknowledged = true` (khususnya `requiresLicense`) dan `$spec->ram >= $definition->minRamGb`. Request manipulasi langsung tanpa persetujuan ditolak HTTP `422`.
4. **Tercatat Permanen di Audit Order:**
   - Disimpan ke `orders.metadata`: `license_acknowledged: true`, `warning_acknowledged: true`, `acknowledged_stack`, dan `acknowledged_at` (ISO-8601).

---

## 3. Katalog Lengkap Control Panel (Target: 27 Panel — Gratis + Berbayar)

### 3.1 Control Panel Gratis / Open-Source (0 Biaya Lisensi)
| Key | Nama Panel | Kategori UI | Status | Port Firewall | URL Akses Default | Min RAM | OS Didukung | Fungsi Singkat |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `coolify` | Coolify | PaaS & Container | **Sudah Ada** | `22, 80, 443, 8000` (TCP) | `http://{ip}:8000` | 2 GB | Ubuntu 20/22/24, Debian 11/12/13 | Self-hosted PaaS alternatif Vercel/Heroku/Netlify |
| `dokploy` | Dokploy | PaaS & Container | **Sudah Ada** | `22, 80, 443, 3000` (TCP) | `http://{ip}:3000` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | PaaS Docker + Traefik modern & ringkas |
| `docker` | Docker Engine | PaaS & Container | **Sudah Ada** | `22` (TCP) | — (CLI / SSH) | 2 GB | Ubuntu 22/24/26 | Docker CE + Docker Compose siap pakai |
| `cloudpanel` | CloudPanel | Web Hosting Panel | **Sudah Ada** | `22, 80, 443, 8443` (TCP) | `https://{ip}:8443` | 2 GB | Ubuntu 22/24, Debian 12 | Panel ringan Nginx untuk PHP, Node.js, Python, WordPress |
| `aapanel` | aaPanel | Web Hosting Panel | **Sudah Ada** | `22, 80, 443, 8888, 888, 20, 21` (TCP) | `http://{ip}:8888` | 2 GB | Ubuntu 20/22/24, Debian 11/12/13, Rocky 9/10, CentOS Stream 9 | Panel GUI lengkap LNMP/LAMP + App Store |
| `cyberpanel` | CyberPanel | Web Hosting Panel | **Sudah Ada** | `22, 80, 443, 8090, 7080` (TCP) | `https://{ip}:8090` | 2 GB | Ubuntu 22/24, Rocky 9.4, CentOS Stream 9 | Panel hosting OpenLiteSpeed + LSCache WordPress |
| `hestiacp` | HestiaCP | Web Hosting Panel | **Sudah Ada** | `22, 80, 443, 8083` (TCP) | `https://{ip}:8083` | 2 GB | Ubuntu 20/22/24, Debian 11/12/13 | Panel ringan open-source (Web, DNS, DB, Email) |
| `easypanel` | Easypanel | PaaS & Container | **BARU** | `22, 80, 443, 3000` (TCP) | `http://{ip}:3000` | 2 GB | Ubuntu 22/24, Debian 11/12/13 | Panel Docker modern dengan 100+ one-click template UI |
| `onepanel` | 1Panel | Web & Container Panel | **BARU** | `22, 80, 443, 10086` (TCP) | `http://{ip}:10086` | 2 GB | Ubuntu 22/24, Debian 11/12/13, Rocky 9.4, CentOS Stream 9 | Panel server modern berbasis container (populer di Tencent) |
| `portainer` | Portainer CE | Container GUI | **BARU** | `22, 80, 443, 9443` (TCP) | `https://{ip}:9443` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | GUI manajemen container, stack Compose, & volume Docker |
| `caprover` | CapRover | PaaS & Container | **BARU** | `22, 80, 443, 3000` (TCP) | `http://{ip}:3000` | 2 GB | Ubuntu 22/24, Debian 11/12/13 | PaaS Docker Swarm super ringan alternatif Heroku |
| `casaos` | CasaOS | Personal Cloud Panel | **BARU** | `22, 80, 443, 8080` (TCP) | `http://{ip}:8080` | 2 GB | Ubuntu 22/24, Debian 11/12 | Home/personal cloud OS dashboard ramah pemula |
| `cosmos_cloud` | Cosmos Cloud | Container & Proxy Panel | **BARU** | `22, 80, 443` (TCP) | `http://{ip}` | 2 GB | Ubuntu 22/24, Debian 11/12/13 | Self-hosted container manager + reverse proxy + SSO |
| `serverkit` | ServerKit | Web & Docker Panel | **BARU** | `22, 80, 443, 8443` (TCP) | `https://{ip}:8443` | 2 GB | Ubuntu 22/24/26 | Panel open-source Docker, DB, & 1-click app (andalan Hostinger) |
| `fastpanel` | FASTPANEL | Web Hosting Panel | **BARU** | `22, 80, 443, 8888` (TCP) | `https://{ip}:8888` | 2 GB | Ubuntu 22/24, Debian 11/12 | Panel web hosting cepat & gratis (Nginx/Apache/SSL/Backup) |
| `webmin_virtualmin` | Webmin / Virtualmin | Web & SysAdmin Panel | **BARU** | `22, 80, 443, 10000` (TCP) | `https://{ip}:10000` | 2 GB | Ubuntu 22/24, Debian 11/12/13, Rocky 9.4 | Panel sysadmin Linux klasik & manajemen virtual host |
| `ispconfig` | ISPConfig | Web Hosting Panel | **BARU** | `22, 80, 443, 8080, 8081` (TCP) | `https://{ip}:8080` | 2 GB | Ubuntu 22/24, Debian 11/12 | Panel multi-server/hosting open-source stabil |
| `froxlor` | Froxlor | Web Hosting Panel | **BARU** | `22, 80, 443` (TCP) | `http://{ip}/froxlor` | 2 GB | Ubuntu 22/24, Debian 11/12 | Panel manajemen web & reseller ISP ringan |
| `keyhelp` | KeyHelp | Web Hosting Panel | **BARU** | `22, 80, 443` (TCP) | `https://{ip}` | 2 GB | Ubuntu 22/24, Debian 11/12 | Control panel gratis lengkap untuk multi-website & email |
| `cockpit` | Cockpit Web Console | SysAdmin GUI | **BARU** | `22, 9090` (TCP) | `https://{ip}:9090` | 2 GB | All 10 verified Linux | Dashboard web resmi Linux untuk service, storage, & terminal |
| `yunohost` | YunoHost | Self-Host App Panel | **BARU** | `22, 80, 443` (TCP) | `https://{ip}` | 2 GB | Debian 12 | OS/panel self-hosting dengan ratusan app komunitas sekali klik |
| `dockge` | Dockge / Komodo | Container GUI | **BARU** | `22, 5001` (TCP) | `http://{ip}:5001` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Manajer `compose.yaml` interaktif super ringan |

### 3.2 Control Panel Komersial / Berbayar (`requiresLicense = true` — Wajib Popup Konfirmasi Warning)
| Key | Nama Panel | Kategori UI | Status | Port Firewall | URL Akses Default | Min RAM | OS Didukung | Catatan Lisensi (Wajib Tampil) |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `cpanel_whm` | cPanel / WHM | Web Panel (Berbayar) | **BARU** | `22, 80, 443, 2083, 2087` (TCP) | `https://{ip}:2087` | **4 GB** (`Starter S+`) | `ubuntu2204`, `rocky94` | **Belum Termasuk Lisensi.** Hanya instalasi WHM; beli lisensi terpisah di cPanel (~$20–$65/bln). |
| `directadmin` | DirectAdmin | Web Panel (Berbayar) | **BARU** | `22, 80, 443, 2222` (TCP) | `https://{ip}:2222` | 2 GB | `ubuntu2204`, `ubuntu2404`, `debian12`, `rocky94` | **Belum Termasuk Lisensi.** CravaCloud hanya memasang panel; license key wajib dibeli terpisah. |
| `plesk` | Plesk Obsidian | Web Panel (Berbayar) | **BARU** | `22, 80, 443, 8443, 8880` (TCP) | `https://{ip}:8443` | 2 GB | `ubuntu2204`, `ubuntu2404`, `debian12` | **Belum Termasuk Lisensi.** Linux only (`SupportWindowsPlatform=false`). |
| `webuzo` | Webuzo (Softaculous) | Web Panel (Berbayar) | **BARU** | `22, 80, 443, 2002, 2003, 2004, 2005` (TCP) | `https://{ip}:2004` | 2 GB | `ubuntu2204`, `ubuntu2404`, `rocky94` | **Belum Termasuk Lisensi.** Gratis terbatas / versi lengkap perlu lisensi Webuzo. |
| `enhance` | Enhance Control Panel | Web Panel (Berbayar) | **BARU** | `22, 80, 443` (TCP) | `https://{ip}` | **4 GB** (`Starter S+`) | `ubuntu2204`, `ubuntu2404` | **Belum Termasuk Lisensi.** Panel clustering modern; butuh akun/lisensi Enhance. |

---

## 4. Katalog Lengkap 1-Click Applications (Total: 148 Aplikasi di 11 Kategori — 18 Eksisting + 130 Baru)

### Kategori A — Dev Stack, Runtimes & Reverse Proxy (12 Stack)
| Key | Nama | Status | Port Firewall | URL Akses | Min RAM | OS Didukung | Deskripsi Singkat |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `vscode_server` | VS Code Server | **Sudah Ada** | `22, 8080` (TCP) | `http://{ip}:8080` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | IDE VS Code lengkap lewat browser |
| `lemp_laravel` | **LEMP / Laravel Optimized** | **BARU** | `22, 80, 443` (TCP) | `http://{ip}` | 2 GB | Ubuntu 22/24, Debian 12/13 | Nginx + PHP 8.3 FPM + MariaDB + Redis + Composer + Node LTS + Supervisor siap deploy Laravel |
| `nodejs_pm2` | **Node.js + PM2 + Nginx** | **BARU** | `22, 80, 443, 3000` (TCP) | `http://{ip}` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Runtime Node.js 22 LTS + PNPM + PM2 process manager + Nginx proxy |
| `python_fastapi` | **Python + FastAPI + Caddy** | **BARU** | `22, 80, 443, 8000` (TCP) | `http://{ip}` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Python 3.12 + uv/pip + Gunicorn/Uvicorn + auto SSL Caddy |
| `golang_caddy` | **Go + Caddy Stack** | **BARU** | `22, 80, 443, 8080` (TCP) | `http://{ip}` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Golang SDK terbaru + Caddy reverse proxy otomatis |
| `nginx_proxy_manager` | **Nginx Proxy Manager** | **BARU** | `22, 80, 443, 81` (TCP) | `http://{ip}:81` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Reverse proxy GUI + SSL Let's Encrypt 1-klik |
| `traefik` | **Traefik Proxy** | **BARU** | `22, 80, 443, 8080` (TCP) | `http://{ip}:8080` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Modern edge router Docker & auto HTTPS |
| `caddy` | **Caddy Web Server** | **BARU** | `22, 80, 443` (TCP) | `http://{ip}` | 2 GB | All 10 verified Linux | Web server modern dengan automatic HTTPS |
| `haproxy` | **HAProxy Load Balancer** | **BARU** | `22, 80, 443, 8404` (TCP) | `http://{ip}:8404/stats` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Load balancer L4/L7 high-availability + stats UI |
| `coder` | **Coder Cloud Dev** | **BARU** | `22, 7080` (TCP) | `http://{ip}:7080` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | Cloud workspace developer berbasis Terraform/Docker |
| `it_tools` | **IT-Tools** | **BARU** | `22, 8080` (TCP) | `http://{ip}:8080` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Kumpulan 80+ utilitas developer self-hosted (hash, JWT, regex, YAML) |
| `hoppscotch` | **Hoppscotch Self-Hosted** | **BARU** | `22, 3000, 3100, 3170` (TCP) | `http://{ip}:3000` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | Alternatif Postman self-hosted untuk testing API tim |

### Kategori B — CI/CD, Git & Observability / Monitoring (16 Stack)
| Key | Nama | Status | Port Firewall | URL Akses | Min RAM | OS Didukung | Deskripsi Singkat |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `gitea_forgejo` | Gitea / Forgejo | **Sudah Ada** | `22, 3000, 2222` (TCP) | `http://{ip}:3000` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | Git hosting & CI runner ringan self-hosted |
| `uptime_kuma` | Uptime Kuma | **Sudah Ada** | `22, 3001` (TCP) | `http://{ip}:3001` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | Monitoring uptime website/API + status page + alert WA/TG |
| `netdata_beszel` | Netdata / Beszel | **Sudah Ada** | `22, 19999, 8090` (TCP) | `http://{ip}:19999` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | Monitoring CPU, RAM, disk, & Docker real-time |
| `grafana_prometheus` | **Grafana + Prometheus** | **BARU** | `22, 3000, 9090` (TCP) | `http://{ip}:3000` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Stack observability standar industri + Node Exporter |
| `umami` | **Umami Analytics** | **BARU** | `22, 3000` (TCP) | `http://{ip}:3000` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Web analytics ringan pengganti Google Analytics |
| `plausible` | **Plausible Analytics** | **BARU** | `22, 8000` (TCP) | `http://{ip}:8000` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | Analytics bebas cookie (PostgreSQL + ClickHouse) |
| `matomo` | **Matomo Analytics** | **BARU** | `22, 8080` (TCP) | `http://{ip}:8080` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Platform web analytics lengkap kepemilikan penuh data |
| `posthog` | **PostHog Product Analytics** | **BARU** | `22, 80, 443` (TCP) | `http://{ip}` | **8 GB** (`Starter M+`) | Ubuntu 22/24 | Product analytics, session replay, & feature flags |
| `metabase` | **Metabase BI** | **BARU** | `22, 3000` (TCP) | `http://{ip}:3000` | **4 GB** (`Starter S+`) | Ubuntu 22/24/26, Debian 11/12/13 | BI dashboard & query SQL tanpa coding untuk manajemen |
| `gitlab_ce` | **GitLab CE** | **BARU** | `22, 80, 443, 2222` (TCP) | `http://{ip}` | **8 GB** (`Starter M+`) | Ubuntu 22/24, Debian 12 | DevSecOps & Git repository enterprise |
| `jenkins` | **Jenkins CI/CD** | **BARU** | `22, 8080` (TCP) | `http://{ip}:8080` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Automation server untuk pipeline build & deploy |
| `woodpecker_ci` | **Woodpecker CI / Drone** | **BARU** | `22, 8000` (TCP) | `http://{ip}:8000` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Mesin CI container-native sangat ringan |
| `dozzle` | **Dozzle Log Viewer** | **BARU** | `22, 8080` (TCP) | `http://{ip}:8080` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Monitoring log seluruh container Docker secara live di browser |
| `watchtower` | **Watchtower + Portainer Agent** | **BARU** | `22, 8080` (TCP) | — (background/metrics) | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Auto-update container Docker terjadwal |
| `glitchtip` | **GlitchTip (Error Tracking)** | **BARU** | `22, 8000` (TCP) | `http://{ip}:8000` | 2 GB | Ubuntu 22/24, Debian 12/13 | Alternatif open-source Sentry ringan (compatible SDK Sentry) |
| `gatus` | **Gatus + Healthchecks** | **BARU** | `22, 8080, 8000` (TCP) | `http://{ip}:8080` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Developer status page + monitoring cron job/heartbeat |

### Kategori C — Backend, BaaS, Databases & Messaging (15 Stack)
| Key | Nama | Status | Port Firewall | URL Akses | Min RAM | OS Didukung | Deskripsi Singkat |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `supabase` | **Supabase (Self-Hosted)** | **BARU** | `22, 80, 443, 8000` (TCP) | `http://{ip}:8000` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | Alternatif Firebase full stack (Postgres, Auth, Storage, Studio) |
| `appwrite` | **Appwrite BaaS** | **BARU** | `22, 80, 443` (TCP) | `http://{ip}` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | BaaS open-source (Auth, DB, Functions, Realtime) |
| `pocketbase` | **PocketBase** | **BARU** | `22, 8090` (TCP) | `http://{ip}:8090/_/` | 2 GB | All 10 verified Linux | BaaS super ringan 1 binary (SQLite + Realtime + Admin UI) |
| `nocodb` | **NocoDB** | **BARU** | `22, 8080` (TCP) | `http://{ip}:8080` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Alternatif Airtable open-source di atas SQL |
| `baserow` | **Baserow** | **BARU** | `22, 80, 443` (TCP) | `http://{ip}` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | No-code collaborative database alternatif Airtable |
| `grist` | **Grist Spreadsheet DB** | **BARU** | `22, 8484` (TCP) | `http://{ip}:8484` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Gabungan spreadsheet & relational database modern |
| `postgresql_pgadmin` | **PostgreSQL 16 + pgAdmin** | **BARU** | `22, 5432, 5050` (TCP) | `http://{ip}:5050` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | PostgreSQL 16 siap pakai + UI web pgAdmin 4 |
| `mysql_phpmyadmin` | **MariaDB / MySQL + phpMyAdmin** | **BARU** | `22, 3306, 8080` (TCP) | `http://{ip}:8080` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Database MariaDB/MySQL + web panel phpMyAdmin |
| `mongodb_express` | **MongoDB + Mongo Express** | **BARU** | `22, 27017, 8081` (TCP) | `http://{ip}:8081` | 2 GB | Ubuntu 22/24, Debian 12/13 | NoSQL MongoDB + Web GUI manajemen koleksi |
| `redis_insight` | **Redis + Redis Insight** | **BARU** | `22, 6379, 5540` (TCP) | `http://{ip}:5540` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | In-memory cache/queue + GUI Redis Insight |
| `meilisearch` | **Meilisearch** | **BARU** | `22, 7700` (TCP) | `http://{ip}:7700` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Full-text search engine super cepat untuk aplikasi & toko |
| `qdrant` | **Qdrant Vector DB** | **BARU** | `22, 6333, 6334` (TCP) | `http://{ip}:6333/dashboard` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Vector database untuk embedding AI & RAG |
| `clickhouse` | **ClickHouse Analytics DB** | **BARU** | `22, 8123, 9000` (TCP) | `http://{ip}:8123/play` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | Database OLAP kolom untuk analitik jutaan baris/detik |
| `rabbitmq` | **RabbitMQ + Management UI** | **BARU** | `22, 5672, 15672` (TCP) | `http://{ip}:15672` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Message broker enterprise + dashboard antrean |
| `influxdb` | **InfluxDB Time-Series** | **BARU** | `22, 8086` (TCP) | `http://{ip}:8086` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Database time-series untuk IoT, sensor, & metrik |

### Kategori D — AI, LLM, Agents & Workflow Automation (21 Stack)
| Key | Nama | Status | Port Firewall | URL Akses | Min RAM | OS Didukung | Deskripsi Singkat |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `n8n` | n8n | **Sudah Ada** | `22, 5678` (TCP) | `http://{ip}:5678` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | Otomasi workflow & AI agent node-based |
| `ollama` | Ollama | **Sudah Ada** | `22, 11434` (TCP) | `http://{ip}:11434` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | Engine menjalankan model LLM lokal |
| `dify_ollama` | Dify + Ollama | **Sudah Ada** | `22, 80, 443` (TCP) | `http://{ip}` | **4 GB** (`Starter S+`) | Ubuntu 22/24/26, Debian 12/13 | Studio orkestrasi LLM, prompt, & RAG |
| `anythingllm` | AnythingLLM | **Sudah Ada** | `22, 3001` (TCP) | `http://{ip}:3001` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | Workspace chat dokumen privat & RAG |
| `librechat` | LibreChat | **Sudah Ada** | `22, 3080` (TCP) | `http://{ip}:3080` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | Multi-model AI chat UI alternatif ChatGPT |
| `hermes_agent` | Hermes Agent | **Sudah Ada** | `22, 8000` (TCP) | `http://{ip}:8000` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | Autonomous agent runtime 24/7 |
| `openclaw` | OpenClaw | **Sudah Ada** | `22, 8080` (TCP) | `http://{ip}:8080` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | Gateway & orchestration AI Agent |
| `agent_zero` | Agent Zero | **Sudah Ada** | `22, 50001` (TCP) | `http://{ip}:50001` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | Framework AI agent dinamis |
| `omniroute` | OmniRoute | **Sudah Ada** | `22` (TCP) | — (outbound) | 2 GB | All 10 verified Linux | Smart LLM gateway outbound |
| `9router` | 9router | **Sudah Ada** | `22` (TCP) | — (outbound) | 2 GB | All 10 verified Linux | Lightweight AI outbound router |
| `open_webui` | **Open WebUI + Ollama** | **BARU** | `22, 3000, 11434` (TCP) | `http://{ip}:3000` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | Antarmuka mirip ChatGPT terpopuler di Hostinger + RAG lokal |
| `flowise` | **Flowise AI Builder** | **BARU** | `22, 3000` (TCP) | `http://{ip}:3000` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Drag-and-drop LangChain/AI agent flow builder |
| `langfuse` | **Langfuse LLM Observability** | **BARU** | `22, 3000` (TCP) | `http://{ip}:3000` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | Tracing, evaluasi, & analytics biaya prompt LLM |
| `litellm` | **LiteLLM Proxy** | **BARU** | `22, 4000` (TCP) | `http://{ip}:4000/ui` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Unified OpenAI-compatible gateway untuk 100+ model LLM |
| `activepieces` | **Activepieces** | **BARU** | `22, 80` (TCP) | `http://{ip}` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Alternatif Zapier/Make open-source ramah pengguna |
| `localai` | **LocalAI API** | **BARU** | `22, 8080` (TCP) | `http://{ip}:8080` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | Drop-in OpenAI API lokal untuk teks, audio, & gambar |
| `whisper_asr` | **Faster-Whisper API + UI** | **BARU** | `22, 8000` (TCP) | `http://{ip}:8000` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | Speech-to-text & transkripsi audio multi-bahasa (API OpenAI) |
| `libretranslate` | **LibreTranslate** | **BARU** | `22, 5000` (TCP) | `http://{ip}:5000` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | API penerjemah multi-bahasa self-hosted gratis |
| `typebot` | **Typebot Builder** | **BARU** | `22, 8081, 8082` (TCP) | `http://{ip}:8081` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | Pembuat chatbot interaktif visual untuk web & WhatsApp |
| `changedetection` | **Changedetection.io** | **BARU** | `22, 5000` (TCP) | `http://{ip}:5000` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Monitor perubahan halaman web/stok/harga + alert webhook |
| `searxng` | **SearXNG Search API** | **BARU** | `22, 8080` (TCP) | `http://{ip}:8080` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Mesin pencari privat + JSON API untuk grounding AI agent |

### Kategori E — Web CMS, E-Commerce & LMS (12 Stack)
| Key | Nama | Status | Port Firewall | URL Akses | Min RAM | OS Didukung | Deskripsi Singkat |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `wordpress` | WordPress | **Sudah Ada** | `22, 80, 443` (TCP) | `http://{ip}` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | WordPress + Nginx & database siap pakai |
| `ghost` | Ghost CMS | **Sudah Ada** | `22, 2368` (TCP) | `http://{ip}:2368` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | Platform publishing & newsletter modern |
| `strapi_directus` | Strapi / Directus | **Sudah Ada** | `22, 8055` (TCP) | `http://{ip}:8055` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | Headless CMS & REST/GraphQL API builder |
| `prestashop_bagisto` | PrestaShop / Bagisto | **Sudah Ada** | `22, 80, 443` (TCP) | `http://{ip}` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | Platform toko online open-source |
| `woocommerce` | **WordPress + WooCommerce** | **BARU** | `22, 80, 443` (TCP) | `http://{ip}` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Toko online WordPress + Redis Object Cache + Nginx FastCGI |
| `drupal` | **Drupal 10/11 CMS** | **BARU** | `22, 80, 443` (TCP) | `http://{ip}` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | CMS enterprise fleksibel (+ MariaDB) |
| `joomla` | **Joomla! CMS** | **BARU** | `22, 80, 443` (TCP) | `http://{ip}` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | CMS portal organisasi & komunitas |
| `moodle` | **Moodle LMS** | **BARU** | `22, 80, 443` (TCP) | `http://{ip}` | 2 GB (rek. 4 GB) | Ubuntu 22/24, Debian 12/13 | Platform e-learning sekolah, kampus, & kursus online |
| `payload_cms` | **Payload CMS** | **BARU** | `22, 3000` (TCP) | `http://{ip}:3000/admin` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | Next.js headless CMS + PostgreSQL modern |
| `medusajs` | **MedusaJS Commerce** | **BARU** | `22, 9000, 7001` (TCP) | `http://{ip}:9000/app` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | Headless commerce backend modern alternatif Shopify |
| `magento` | **Magento / OpenMage** | **BARU** | `22, 80, 443` (TCP) | `http://{ip}` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12 | Platform e-commerce skala menengah-besar |
| `owncast` | **Owncast Live Streaming** | **BARU** | `22, 8080, 1935` (TCP) | `http://{ip}:8080` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Server live streaming RTMP + chat web mandiri |

### Kategori F — ERP, CRM, Keuangan & Business Ops (12 Stack)
| Key | Nama | Status | Port Firewall | URL Akses | Min RAM | OS Didukung | Deskripsi Singkat |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `odoo` | **Odoo Community ERP** | **BARU** | `22, 8069` (TCP) | `http://{ip}:8069` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | ERP all-in-one (Akuntansi, Stok, POS, HR, CRM) |
| `erpnext` | **ERPNext (Frappe)** | **BARU** | `22, 80, 443, 8080` (TCP) | `http://{ip}` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12 | ERP enterprise komprehensif untuk manufaktur & distributor |
| `dolibarr` | **Dolibarr ERP & CRM** | **BARU** | `22, 80` (TCP) | `http://{ip}` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | ERP/CRM ringan untuk UKM, tagihan, & stok |
| `twenty_crm` | **Twenty CRM** | **BARU** | `22, 3000` (TCP) | `http://{ip}:3000` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | CRM open-source modern alternatif Salesforce/HubSpot |
| `espocrm` | **EspoCRM** | **BARU** | `22, 8080` (TCP) | `http://{ip}:8080` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | CRM sales pipeline, email sync, & kalender ringan |
| `invoice_ninja` | **Invoice Ninja** | **BARU** | `22, 80, 443` (TCP) | `http://{ip}` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | Aplikasi faktur, penawaran, & pencatatan pembayaran |
| `akaunting` | **Akaunting** | **BARU** | `22, 80` (TCP) | `http://{ip}` | 2 GB | Ubuntu 22/24, Debian 12/13 | Software pembukuan akuntansi UMKM berbasis Laravel |
| `firefly_iii` | **Firefly III** | **BARU** | `22, 8080` (TCP) | `http://{ip}:8080` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Pengelola keuangan & cashflow pribadi/usaha kecil |
| `kimai` | **Kimai Time Tracker** | **BARU** | `22, 8001` (TCP) | `http://{ip}:8001` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Pelacakan jam kerja tim & timesheet proyek/klien |
| `mautic` | **Mautic Marketing** | **BARU** | `22, 8080` (TCP) | `http://{ip}:8080` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | Otomasi marketing email, segmentasi lead, & landing page |
| `snipe_it` | **Snipe-IT Asset Mgmt** | **BARU** | `22, 80, 443` (TCP) | `http://{ip}` | 2 GB | Ubuntu 22/24, Debian 12/13 | Manajemen inventaris aset IT, laptop, & lisensi software |
| `glpi` | **GLPI ITSM & Helpdesk** | **BARU** | `22, 80` (TCP) | `http://{ip}` | 2 GB | Ubuntu 22/24, Debian 11/12/13 | Inventaris IT + sistem helpdesk/tiket kantor |

### Kategori G — Produktivitas, Dokumen, Wiki & Project Management (12 Stack)
| Key | Nama | Status | Port Firewall | URL Akses | Min RAM | OS Didukung | Deskripsi Singkat |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `paperless_ngx` | **Paperless-ngx** | **BARU** | `22, 8000` (TCP) | `http://{ip}:8000` | 2 GB (rek. 4 GB) | Ubuntu 22/24/26, Debian 12/13 | Arsip dokumen digital otomatis dengan OCR pencari teks |
| `stirling_pdf` | **Stirling PDF** | **BARU** | `22, 8080` (TCP) | `http://{ip}:8080` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Tool lengkap merge, split, compress, OCR, & convert PDF lokal |
| `bookstack` | **BookStack** | **BARU** | `22, 80, 443, 6875` (TCP) | `http://{ip}:6875` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Wiki SOP & dokumentasi perusahaan berbasis rak/buku |
| `outline` | **Outline Wiki** | **BARU** | `22, 3000` (TCP) | `http://{ip}:3000` | 2 GB | Ubuntu 22/24, Debian 12/13 | Knowledge base tim kolaboratif cepat ala Notion |
| `wikijs` | **Wiki.js** | **BARU** | `22, 3000` (TCP) | `http://{ip}:3000` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Wiki modern Markdown + sync Git |
| `plane` | **Plane Project Mgmt** | **BARU** | `22, 80, 443` (TCP) | `http://{ip}` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | Alternatif Jira / Linear open-source |
| `vikunja` | **Vikunja Tasks** | **BARU** | `22, 3456` (TCP) | `http://{ip}:3456` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Kanban, Gantt chart, & to-do list tim |
| `redmine` | **Redmine** | **BARU** | `22, 3000` (TCP) | `http://{ip}:3000` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Issue tracker & manajemen proyek klasik |
| `calcom` | **Cal.com Scheduling** | **BARU** | `22, 3000` (TCP) | `http://{ip}:3000` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | Alternatif Calendly untuk booking jadwal meeting/konsultasi |
| `memos` | **Memos Notes** | **BARU** | `22, 5230` (TCP) | `http://{ip}:5230` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Note-taking & mikro-blog cepat super ringan |
| `affine` | **AFFiNE Workspace** | **BARU** | `22, 3010` (TCP) | `http://{ip}:3010` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | Workspace all-in-one dokumen, whiteboard, & kanban |
| `cryptpad` | **CryptPad Collaborative Docs** | **BARU** | `22, 3000` (TCP) | `http://{ip}:3000` | 2 GB | Ubuntu 22/24, Debian 12/13 | Alternatif Google Docs/Sheets end-to-end encrypted |

### Kategori H — Komunikasi, Customer Support, Forum & Newsletter (10 Stack)
| Key | Nama | Status | Port Firewall | URL Akses | Min RAM | OS Didukung | Deskripsi Singkat |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `chatwoot` | **Chatwoot Omnichannel** | **BARU** | `22, 3000` (TCP) | `http://{ip}:3000` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | Customer support live-chat, inbox WA, email, & IG |
| `mattermost` | **Mattermost Team Chat** | **BARU** | `22, 8065` (TCP) | `http://{ip}:8065` | 2 GB (rek. 4 GB) | Ubuntu 22/24, Debian 12/13 | Chat internal tim alternatif Slack |
| `rocketchat` | **Rocket.Chat** | **BARU** | `22, 3000` (TCP) | `http://{ip}:3000` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | Team messaging & kolaborasi self-hosted |
| `zulip` | **Zulip Threaded Chat** | **BARU** | `22, 80, 443` (TCP) | `http://{ip}` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12 | Chat tim berbasis topik untuk tim remote & engineering |
| `matrix_synapse` | **Matrix Synapse + Element** | **BARU** | `22, 80, 443, 8008, 8448` (TCP) | `http://{ip}` | 2 GB (rek. 4 GB) | Ubuntu 22/24, Debian 12/13 | Server chat terdesentralisasi terenkripsi E2EE |
| `discourse` | **Discourse Forum** | **BARU** | `22, 80, 443` (TCP) | `http://{ip}` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12 | Platform forum diskusi komunitas modern |
| `flarum` | **Flarum Forum** | **BARU** | `22, 80, 443` (TCP) | `http://{ip}` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Forum komunitas sangat ringan & cepat |
| `listmonk` | **Listmonk Newsletter** | **BARU** | `22, 9000` (TCP) | `http://{ip}:9000` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Mailing list & email campaign berkecepatan tinggi |
| `mastodon` | **Mastodon Social Server** | **BARU** | `22, 80, 443` (TCP) | `http://{ip}` | **4 GB** (`Starter S+`) | Ubuntu 22/24 | Jejaring sosial microblogging federated (ActivityPub) |
| `ntfy` | **Ntfy + Gotify Notification** | **BARU** | `22, 80` (TCP) | `http://{ip}` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Server push notification untuk skrip, CI, & alert HP |

### Kategori I — Cloud Storage, Media, Foto, Smart Home & RSS (14 Stack)
| Key | Nama | Status | Port Firewall | URL Akses | Min RAM | OS Didukung | Deskripsi Singkat |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `minio` | **MinIO (S3 Storage)** | **BARU** | `22, 9000, 9001` (TCP) | `http://{ip}:9001` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Object storage kompatibel Amazon S3 |
| `nextcloud` | **Nextcloud Hub** | **BARU** | `22, 80, 443, 8080` (TCP) | `http://{ip}:8080` | 2 GB (rek. 4 GB) | Ubuntu 22/24/26, Debian 11/12/13 | Cloud storage alternatif Google Drive & kalender |
| `owncloud` | **ownCloud Infinite Scale** | **BARU** | `22, 9200` (TCP) | `http://{ip}:9200` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | File sync & sharing ringan berkinerja tinggi |
| `seafile` | **Seafile Cloud Drive** | **BARU** | `22, 80, 443` (TCP) | `http://{ip}` | 2 GB | Ubuntu 22/24, Debian 12/13 | Sinkronisasi file super cepat dengan enkripsi library |
| `filebrowser` | **FileBrowser Web Drive** | **BARU** | `22, 8080` (TCP) | `http://{ip}:8080` | 2 GB | All 10 verified Linux | File manager web ringan untuk direktori server |
| `syncthing` | **Syncthing Sync Server** | **BARU** | `22, 8384, 22000` (TCP) + `22000` (UDP) | `http://{ip}:8384` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Sinkronisasi file P2P antar laptop/HP/server |
| `immich` | **Immich Photo Cloud** | **BARU** | `22, 2283` (TCP) | `http://{ip}:2283` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | Alternatif Google Photos (backup HP + AI face search) |
| `photoprism` | **PhotoPrism** | **BARU** | `22, 2342` (TCP) | `http://{ip}:2342` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | Galeri foto AI self-hosted |
| `jellyfin` | **Jellyfin Media Server** | **BARU** | `22, 8096` (TCP) | `http://{ip}:8096` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Streaming video & musik 100% gratis tanpa paywall |
| `plex` | **Plex Media Server** | **BARU** | `22, 32400` (TCP) | `http://{ip}:32400/web` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Media library & streaming lintas perangkat |
| `navidrome` | **Navidrome + Audiobookshelf** | **BARU** | `22, 4533, 13378` (TCP) | `http://{ip}:4533` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Music streaming pribadi (Subsonic) & server audiobook |
| `home_assistant` | **Home Assistant** | **BARU** | `22, 8123` (TCP) | `http://{ip}:8123` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Hub otomasi IoT & smart home |
| `linkwarden` | **Linkwarden Bookmarks** | **BARU** | `22, 3000` (TCP) | `http://{ip}:3000` | 2 GB | Ubuntu 22/24, Debian 12/13 | Pengelola bookmark kolaboratif dengan arsip web |
| `freshrss` | **FreshRSS Reader** | **BARU** | `22, 8080` (TCP) | `http://{ip}:8080` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Agregator berita RSS self-hosted |

### Kategori J — VPN, Remote Desktop, DNS, Security & Identity (14 Stack — *Mendukung TCP + UDP*)
| Key | Nama | Status | Port Firewall | URL Akses | Min RAM | OS Didukung | Deskripsi Singkat |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `wireguard` | **WireGuard (`wg-easy`)** | **BARU** | `22, 51821` (TCP) + **`51820` (UDP)** | `http://{ip}:51821` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | VPN super cepat + web UI QR code untuk HP/PC |
| `openvpn` | **OpenVPN Server** | **BARU** | `22, 943, 443` (TCP) + **`1194` (UDP)** | `https://{ip}:943` | 2 GB | Ubuntu 22/24, Debian 11/12 | VPN server standar korporat & ekspor profil `.ovpn` |
| `headscale` | **Headscale + Headplane UI** | **BARU** | `22, 8080, 3000` (TCP) + **`3478` (UDP)** | `http://{ip}:3000` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Control server Tailscale self-hosted tanpa batas user |
| `netbird` | **NetBird Mesh VPN** | **BARU** | `22, 80, 443, 33073` (TCP) + **`3478` (UDP)** | `https://{ip}` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | Zero-trust WireGuard mesh VPN organisasi |
| `rustdesk` | **RustDesk Server** | **BARU** | `22, 21114-21119` (TCP) + **`21116` (UDP)** | `http://{ip}:21114` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Relay & ID server remote desktop alternatif TeamViewer/AnyDesk |
| `guacamole` | **Apache Guacamole** | **BARU** | `22, 8080` (TCP) | `http://{ip}:8080/guacamole` | 2 GB | Ubuntu 22/24, Debian 11/12 | Gateway remote RDP, VNC, & SSH langsung dari browser |
| `kasm_workspaces` | **Kasm Workspaces** | **BARU** | `22, 443, 3000-4000` (TCP) | `https://{ip}` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12 | Stream desktop Linux & browser terisolasi via browser |
| `vaultwarden` | **Vaultwarden** | **BARU** | `22, 80, 443, 8080` (TCP) | `http://{ip}:8080` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Password manager self-hosted kompatibel aplikasi Bitwarden |
| `adguard_home` | **AdGuard Home** | **BARU** | `22, 80, 443, 3000, 53` (TCP) + **`53` (UDP)** | `http://{ip}:3000` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Private DNS server + pemblokir iklan & DoH/DoT |
| `pihole` | **Pi-hole** | **BARU** | `22, 80, 53` (TCP) + **`53` (UDP)** | `http://{ip}/admin` | 2 GB | Ubuntu 22/24, Debian 11/12 | DNS sinkhole pemblokir iklan & pelacak jaringan |
| `authentik` | **Authentik SSO / IAM** | **BARU** | `22, 9000, 9443` (TCP) | `http://{ip}:9000` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | Identity provider SSO (OIDC, OAuth2, SAML, LDAP) |
| `keycloak` | **Keycloak IAM** | **BARU** | `22, 8080` (TCP) | `http://{ip}:8080` | 2 GB (rek. 4 GB) | Ubuntu 22/24/26, Debian 11/12/13 | IAM & SSO enterprise open-source |
| `homarr` | **Homarr + Homepage** | **BARU** | `22, 7575` (TCP) | `http://{ip}:7575` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Landing dashboard elegan seluruh layanan self-hosted Anda |
| `openspeedtest` | **OpenSpeedTest + Speedtest** | **BARU** | `22, 3000` (TCP) | `http://{ip}:3000` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Uji kecepatan jaringan server & history ping internal |

### Kategori K — Gaming & Game Server Panel (Hostinger Game Panel Parity) (10 Stack — *TCP + UDP*)
| Key | Nama | Status | Port Firewall | URL Akses | Min RAM | OS Didukung | Deskripsi Singkat |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `pterodactyl` | **Pterodactyl Panel + Wings** | **BARU** | `22, 80, 443, 8080, 2022` (TCP) | `http://{ip}` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12 | Panel manajemen server game standar industri (multi-game) |
| `crafty_controller` | **Crafty Controller** | **BARU** | `22, 8443, 25565` (TCP) + `19132` (UDP) | `https://{ip}:8443` | **4 GB** (`Starter S+`) | Ubuntu 22/24/26, Debian 12/13 | Panel web khusus kelola multiple server Minecraft |
| `minecraft_paper` | **Minecraft Java (PaperMC)** | **BARU** | `22, 25565` (TCP+UDP) + `25575` (TCP) | — (IP:25565) | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | Dedicated server Minecraft performa tinggi + plugin support |
| `minecraft_bedrock` | **Minecraft Bedrock Dedicated** | **BARU** | `22` (TCP) + **`19132, 19133` (UDP)** | — (IP:19132) | 2 GB (rek. 4 GB) | Ubuntu 22/24, Debian 12/13 | Dedicated server lintas-perangkat (HP/Console/PC) |
| `fivem_txadmin` | **FiveM GTA V RP (`txAdmin`)** | **BARU** | `22, 40120, 30120` (TCP) + **`30120` (UDP)** | `http://{ip}:40120` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12 | Server FiveM Roleplay + Web UI txAdmin langsung pakai |
| `palworld` | **Palworld Dedicated Server** | **BARU** | `22` (TCP) + **`8211, 27015` (UDP)** | — (IP:8211) | **8 GB** (`Starter M+`) | Ubuntu 22/24 | Dedicated multiplayer server Palworld (butuh RAM >= 8GB) |
| `valheim` | **Valheim Dedicated Server** | **BARU** | `22` (TCP) + **`2456-2458` (UDP)** | — (IP:2456) | **4 GB** (`Starter S+`) | Ubuntu 22/24 | Server survival co-op Valheim + dukungan Valheim Plus |
| `terraria_tshock` | **Terraria (`tShock`) Server** | **BARU** | `22, 7777, 7878` (TCP) | — (IP:7777) | 2 GB | Ubuntu 22/24, Debian 12/13 | Dedicated server Terraria + admin plugin tShock |
| `cs2_server` | **Counter-Strike 2 (CS2)** | **BARU** | `22, 27015` (TCP) + **`27015, 27020` (UDP)** | — (IP:27015) | **4 GB** (`Starter S+`) | Ubuntu 22/24 | Dedicated competitive CS2 server (Steam GSLT ready) |
| `rust_server` | **Rust Dedicated Server** | **BARU** | `22, 28016` (TCP) + **`28015, 28017` (UDP)** | — (IP:28015) | **8 GB** (`Starter M+`) | Ubuntu 22/24 | Dedicated multiplayer Rust server |

---

## 5. Prasyarat Teknis Wajib Sebelum Implementasi

Menambah katalog dari 27 menjadi **160+ stack** membutuhkan 4 pembenahan arsitektur dulu agar sistem tetap ringan, aman, dan tidak penuh duplikasi:

### 5.1 Dukungan Firewall Port UDP + Rentang Port di `StackDefinition` & `TencentLighthouseProvider`
- **Kondisi saat ini:** `TencentLighthouseProvider::openPorts()` (baris 928–946) mengunci `'Protocol' => 'TCP'` dan hanya menerima daftar integer di `StackDefinition::$ports`.
- **Perbaikan yang dibutuhkan:**
  - Tambah `public readonly array $udpPorts = []` di `StackDefinition` (supporting single port `51820` maupun rentang `'2456-2458'`/`'21114-21119'`).
  - Perbarui `TencentLighthouseProvider::openPorts()` agar membuat rule TCP dari `$ports` dan rule UDP dari `$udpPorts`, tetap di-diff terhadap `DescribeFirewallRules` agar idempoten.
  - Perbaiki parser port existing jika menerima rentang `'8000-8100'` selain port comma `'80,443'`.

### 5.2 Jadikan `StackRegistry` Single Source of Truth (Bukan Duplikasi di 6 File)
- Saat ini menambah 1 stack memaksa edit di 6 tempat berbeda (`StackRegistry`, `.sh`, `Order::stackLabels()`, validasi hardcode `in:` di `OrderController`, JS hardcode di `checkout.blade.php`, dan array `$panels`/`$apps` di `slide-configuration.blade.php`).
- **Solusi — Perluas `StackDefinition` dengan Metadata Lengkap:**
  - **Identitas & UI:**
    - `uiGroup`: `'none' | 'panel' | 'app'`
    - `category`: kategori UI (mis. `'PaaS & Container'`, `'AI & Automation'`, `'Backend & DB'`, `'Game Server'`)
    - `description`: ringkasan 1 baris
    - `logoPath`: path wajib file logo resmi asli di disk (`public/images/logos/panels/{key}.{svg|png}` atau `public/images/logos/apps/{key}.{svg|png}`) — **tanpa fallback huruf/monogram!**
    - `secondaryLogoPath`: `?string` opsional khusus stack combo (*Gitea + Forgejo*, *Grafana + Prometheus*, *Netdata + Beszel*, *PostgreSQL + pgAdmin*, *MySQL + phpMyAdmin*, *Strapi + Directus*, *PrestaShop + Bagisto*) agar kedua ikon sama-sama resmi & nyata.
  - **Kapasitas, Peringatan & Lisensi:**
    - `minRamGb`: `2` | `4` | `8`
    - `requiresLicense`: `bool` (`true` untuk `cpanel_whm`, `directadmin`, `plesk`, `webuzo`, `enhance`)
    - `warningType`: `?string` (`'license' | 'ram' | 'activation' | 'network'`)
    - `warningTitle`: `?string`
    - `warningMessage`: `?string`
    - `warningBullets`: `array` (poin rincian singkat untuk popup konfirmasi)
    - `hardBlockOnRam`: `bool` (default `true` untuk stack 4 GB/8 GB di paket lebih rendah agar tidak gagal OOM)
- **Turunkan Otomatis ke Backend & Blade:**
  - `Order::stackLabels()` mengambil langsung dari `StackRegistry` (+ label histori legacy `managed_database`, `hermes_omniroute`).
  - `OrderController::store()` & endpoint Reinstall memvalidasi `control_panel` dinamis dari `StackRegistry`, cek `$spec->ram >= $definition->minRamGb`, dan cek `warning_acknowledged` / `license_acknowledged` bila stack memiliki warning aktif.

---

### 5.3 Arsitektur Lengkap UI/UX & Popup Konfirmasi Warning (Checkout + Reinstall)

#### A. Navigasi & Pencarian Katalog Besar (175+ Stack)
- **Tab Utama Tetap Bersih (3 Tab):** `OS Bersih (Fresh)` | `Control Panel` | `1-Click Applications`
- **Search Bar + Filter Sub-Kategori + Badge:**
  - Search instan (`x-model="stackSearch"`) berdasarkan nama, key, fungsi, atau kategori.
  - Chip kategori di atas grid (*Semua*, *Gratis*, *Berbayar BYOL*, *Dev & Proxy*, *CI/CD & Monitor*, *Backend & DB*, *AI & Automation*, *CMS & LMS*, *ERP & CRM*, *Docs & Produktivitas*, *Chat & Support*, *Storage & Media*, *VPN & Security*, *Game Server*).

#### B. Desain Kartu Stack & Perilaku Button Saat Diklik
Setiap kartu menampilkan:
- **Logo container kontras aman** (`40x40px`, tile terang/netral + `object-contain`) agar logo gelap maupun multicolor tetap tajam di tema gelap `#121212`.
- **Nama stack + deskripsi 1 baris + pill port/RAM**.
- **Badge Warning Kontekstual (bila ada):**
  - `requiresLicense = true` → Pill amber **`Berbayar · BYOL`** + label **`Belum Termasuk Lisensi`**.
  - `planRamGb < minRamGb` → Pill merah **`Min. {minRamGb} GB RAM`**.
  - `warningType = 'activation' | 'network'` → Pill biru/amber mis. **`Butuh Steam GSLT`** / **`Butuh Port UDP`**.
- **Aturan Wajib Interaksi Button:**
  - **JANGAN pernah disable kartu secara bisu!** Semua kartu/button **tetap dapat diklik** oleh user (`@click="handleStackClick(stack)"`).
  - **Alur Eksekusi `handleStackClick(stack)`:**
    1. Jika RAM paket sekarang `< stack.minRamGb`: buka popup **`ram`** (tombol klik tidak memilih kartu secara diam-diam, tetapi langsung menjelaskan kenapa tidak muat & menawarkan tombol CTA ganti paket).
    2. Else jika stack punya `requiresLicense` atau `warningType` (`license` / `activation` / `network`) **DAN** `!acknowledgedWarnings[stack.key]`: buka popup konfirmasi ringkas sebelum mengubah `controlPanel`.
    3. Jika tidak ada warning ATAU user sudah mengonfirmasi warning untuk stack tersebut di sesi ini: langsung pilih `controlPanel = stack.key`.

#### C. Spesifikasi Visual & Fungsional Popup Modal (`@include('components.stack-warning-modal')`)
Satu komponen Blade + Alpine partial dipakai bersama di **Slide Configuration Checkout** dan **Modal Reinstall Detail VPS**:

```text
+------------------------------------------------------------------+
|  [Logo 36px]  cPanel / WHM (BYOL)                           [X]  |
|               Badge: BELUM TERMASUK LISENSI (AMBER)              |
+------------------------------------------------------------------+
|  CravaCloud hanya menginstal installer resmi cPanel/WHM di VPS   |
|  Anda dan TIDAK menyediakan, menjual, atau menyertakan license   |
|  key berbayar.                                                   |
|                                                                  |
|  • Estimasi biaya lisensi vendor langsung: ~Rp250rb–Rp600rb/bln  |
|  • Wajib aktivasi trial / masukkan license key di WHM setelah    |
|    VPS aktif                                                     |
|  • Tagihan lisensi terpisah dari tagihan CravaCloud              |
|                                                                  |
|  [  Batal  ]            [  Saya Mengerti, Pilih Panel Ini  ]     |
+------------------------------------------------------------------+
```

- **3 Varian Skenario Popup:**
  1. **`type === 'license'` (Panel Berbayar BYOL — 5 panel):**
     - Aksen header amber `#F59E0B`.
     - Tombol primer: **`Saya Mengerti, Pilih {Nama}`** → set `acknowledgedWarnings[key] = true`, set `controlPanel = key`, tutup modal, munculkan tag hijau `✓ Lisensi BYOL Dipahami` di kartu.
     - Tombol sekunder: **`Batal`** → tutup modal tanpa mengubah stack aktif sebelumnya.
  2. **`type === 'ram'` (RAM Paket < `minRamGb`):**
     - Aksen header merah `#EF4444`.
     - Menampilkan perbandingan spesifik: *"Paket Anda saat ini: **{planName} ({planRamGb} GB)**. Stack **{stack.label}** membutuhkan minimal **{minRamGb} GB RAM** agar proses instalasi Docker/service tidak gagal OOM."*
     - Tombol di halaman Checkout: **`Tutup`** vs **`Pilih Paket ≥ {minRamGb} GB`** (redirect/scroll ke `/#pricing`).
     - Tombol di Modal Reinstall Detail VPS: **`Tutup`** vs **`Pilih Stack Lain`** (opsional link upgrade paket bila tersedia).
  3. **`type === 'activation' | 'network'` (Contoh: `cs2_server`, `fivem_txadmin`, `wireguard_easy`, `rustdesk_server`):**
     - Aksen header biru/cyan `#38BDF8` atau amber.
     - Poin praktis: mis. *"CS2 wajib mengisi `SRCDS_TOKEN` (Steam GSLT) gratis setelah install via SSH agar server muncul di daftar publik."*
     - Tombol primer: **`Mengerti, Pilih Aplikasi Ini`** → catat ke `acknowledgedWarnings[key] = true` dan pilih stack.
- **State Alpine.js & Penguncian Backend:**
  - Frontend mengirim input tersembunyi bersama form submit:
    - `warning_acknowledged`: `'1'` jika stack terpilih tidak punya warning ATAU key ada di `acknowledgedWarnings`.
    - `license_acknowledged`: `'1'` jika stack `requiresLicense` ada di `acknowledgedWarnings`.
  - Backend (`OrderController::store()` & `VpsActionController::reinstall()`) memverifikasi ulang kondisi di server (anti-bypass DevTools):
    - Jika `requiresLicense && !$request->boolean('license_acknowledged')` → HTTP `422`.
    - Jika `$spec->ram < $definition->minRamGb` → HTTP `422`.
    - Jika valid → simpan `orders.metadata`: `license_acknowledged`, `warning_acknowledged`, `acknowledged_stack`, `acknowledged_at`.

---

### 5.4 Kebijakan & Pipeline Logo Resmi (100% Real, Zero Fake/Placeholder)

Untuk mencegah terulangnya kasus SVG buatan/fake atau ikon yang tidak sesuai merek, CravaCloud memberlakukan aturan ketat aset logo:

1. **Larangan Mutlak:**
   - **DILARANG** membuat/menggambar path SVG merek secara manual oleh AI/LLM.
   - **DILARANG** memakai badge inisial huruf (`AA`, `GL`, `CD`), emoji, atau ikon generik sebagai pengganti logo stack.
   - Setiap stack aktif (selain `none`) **WAJIB** memiliki file aset resmi di `public/images/logos/{panels|apps}/{key}.{svg|png}`.
2. **Hierarki Sumber Logo Resmi (Hanya 3 Sumber Sah):**
   - **Tier 1 — [`homarr-labs/dashboard-icons`](https://github.com/homarr-labs/dashboard-icons):** Sumber utama untuk 90%+ self-hosted apps, container panel, DB, observability, dan game servers (format SVG multicolor asli & PNG HD terkurasi).
   - **Tier 2 — [`simple-icons/simple-icons`](https://github.com/simple-icons/simple-icons):** Untuk merek infrastruktur/enterprise/bahasa standar (`cpanel`, `plesk`, `docker`, `laravel`, `nginx`, `caddy`, `postgresql`, `mysql`, `redis`, `mongodb`, `gitlab`, `jenkins`, `python`, `nodedotjs`, `go`, `rust`, `wireguard`, `openvpn`, dll.) — wajib diberi hex warna resmi vendor agar tidak hitam polos.
   - **Tier 3 — Repo GitHub / Media Kit / Press Kit Resmi Vendor:** Khusus produk/panel baru atau lokal AI (`hermes_agent`, `openclaw`, `omniroute`, `9router`, `agent_zero`, `serverkit`, `aapanel`, `hestiacp`, `cyberpanel`, `directadmin`, `fastpanel`, `webuzo`, `enhance`, `fivem`).
3. **Audit 25 File Logo Existing (`public/images/logos/`):**
   - **Temuan audit ukuran & isi existing:**
     - `apps/netdata_beszel.svg` (`253 B`) terbukti hanya berisi 1 path glyph tunggal Netdata (mengabaikan Beszel).
     - Beberapa file lain berukuran sangat kecil atau berpotensi hasil render generik/parsial sehingga wajib verifikasi visual + hash terhadap Tier 1–3: `apps/prestashop_bagisto.svg` (`355 B`), `panels/coolify.svg` (`574 B`), `panels/docker.svg` (`608 B`), `apps/uptime_kuma.svg` (`630 B`), `apps/strapi_directus.svg` (`703 B`), `apps/anythingllm.svg` (`802 B`), serta logo 5 AI agent/router (`hermes_agent.svg`, `openclaw.svg`, `omniroute.svg`, `9router.svg`, `agent_zero.svg`).
   - **Solusi Stack Gabungan (Combo):** Jangan menggabungkan dua path secara manual! Gunakan `logoPath` + `secondaryLogoPath` di `StackDefinition` sehingga kedua file logo asli dari vendor ditampilkan berdampingan (`24px` + badge overlap `16px`).
4. **Standar Rendering UI (Anti-Gepeng & Anti-Hilang di Dark Mode):**
   - Bungkus logo dalam kotak netral seragam `w-10 h-10 rounded-xl bg-white/95 border border-white/10 p-1.5 shrink-0 flex items-center justify-center` — wajib memakai latar terang lembut karena banyak logo resmi (`Ollama`, `Nextcloud`, `Traefik`, `Ghost`, `Rust`, `Authentik`, `PocketBase`) aslinya bertinta gelap `#000`/`#111`.
   - Render via `<img src="{{ asset($stack->logoPath) }}" alt="{{ $stack->label }}" class="w-full h-full object-contain" loading="lazy">` (**bukan** inline `<svg>` massal) agar DOM checkout dengan 175+ kartu tetap ringan di bawah beberapa ratus KB HTML.
5. **Automated Logo Manifest & CI Test (`stack-logos.json` + `StackLogoIntegrityTest`):**
   - Simpan manifest `resources/data/stack-logos.json` berisi `{ key, source_tier, source_url, local_path, format, sha256 }` untuk audit jejak asal setiap logo.
   - Sediakan Artisan helper dev `php artisan stacks:sync-logos` untuk menarik & memverifikasi checksum logo dari sumber resmi sebelum commit.
   - **PHPUnit Guard (`tests/Unit/StackLogoIntegrityTest.php`):** Otomatis memvalidasi setiap entry `StackRegistry`:
     - File `public/{logoPath}` (+ `secondaryLogoPath` jika ada) eksis.
     - Ukuran `>= 200 B` dan `<= 250 KB` (menolak respons error kosong/404 HTML atau PNG raksasa).
     - File `.svg`: valid XML berakar `<svg>` dengan `viewBox`, tanpa `<html`/`<script`, dan menolak pola monogram placeholder (`<text>` huruf inisial di atas `<rect>`/`<circle>`).
     - File `.png`: valid PNG magic header `\x89PNG\r\n\x1a\n` dan dimensi minimum `64x64 px`.

---

### 5.5 Standar Kualitas, Keamanan (D6) & Verifikasi Script Installer (100% Real & Anti-Stub)

#### A. Temuan Audit Script Existing (`resources/provisioning/stacks/`)
Dari audit file `.sh` yang sudah ada saat ini:
- `agent_zero.sh` (`frdel/agent-zero-run:latest`) dan `dify_ollama.sh` (`github.com/langgenius/dify` + `ollama/ollama:latest`) sudah memakai upstream publik resmi.
- **4 script existing (`hermes_agent.sh`, `openclaw.sh`, `omniroute.sh`, `9router.sh`) masih mereferensikan registry/URL internal `https://registry.cravacloud.id/...`** (`HERMES_IMAGE=registry.cravacloud.id/hermes-agent:latest`, `OPENCLAW_IMAGE=registry.cravacloud.id/openclaw:latest`, `OMNIROUTE_URL=https://registry.cravacloud.id/releases/omniroute-linux-amd64`, `NINEROUTER_URL=https://registry.cravacloud.id/releases/9router-linux-amd64`), dan script existing belum memiliki auto-swap pre-flight maupun health-check liveness sebelum menulis `$MARKER`.
- **Perbaikan Wajib Batch 0:** Pastikan `registry.cravacloud.id` benar-benar live + SHA-256 terverifikasi, ATAU alihkan 4 script tersebut ke repo/image/release resmi GitHub/GHCR masing-masing agar tidak gagal 404 saat user menekan Deploy.

#### B. Blueprint Standar Setiap Script `.sh` Baru & Existing
Agar tidak mengulang boiler-plate di 175 file dan menjamin tingkat sukses provisioning maksimal, gunakan **`StackScriptBuilder`** yang menyuntikkan blok fungsi standar `_common.sh` saat mengirim script via Tencent TAT (`RunCommand`):
1. **Strict Shell & Lock Resilience:**
   - Wajib diawali `#!/usr/bin/env bash` dan `set -euo pipefail` + `DEBIAN_FRONTEND=noninteractive`.
   - Helper `crava_wait_pkg_lock` menunggu `apt-get`/`dpkg`/`dnf` lock selesai sampai maksimal 120 detik agar tidak bentrok dengan unattended-upgrades OS baru.
2. **OS Compatibility Guard:**
   - Cek `/etc/os-release` di awal script (Debian/Ubuntu `apt` vs Rocky/CentOS Stream `dnf`) sinkron 100% dengan `StackDefinition::$supportedOs` (hanya dari 10 distro OS + 3 Docker29 image CravaCloud yang sudah terverifikasi live di `ap-jakarta` & `ap-singapore`).
3. **Auto-Swap Helper (`crava_ensure_swap 2048`):**
   - Otomatis membuat `/swapfile` `2 GB` (`chmod 600`, `mkswap`, `swapon`, catat ke `/etc/fstab`) sebelum `docker pull`/`compose up`/`npm`/`apt` jika belum ada swap, sehingga VPS 2–4 GB stabil saat pull/booting container.
4. **Hanya Sumber Resmi & Terverifikasi (Anti-Stub / Anti-Placeholder):**
   - Gunakan resmi installer vendor (`get.coollabs.io`, `dokploy.com/install.sh`, `hestiacp.com`, `aapanel.com`, `get.casaos.io`, dll.) atau official container image (`docker.io`, `ghcr.io`, `quay.io`, repo compose resmi GitHub dengan release tag yang di-pin bila diperlukan).
   - **DILARANG** keras ada URL dummy, placeholder, `TODO`, atau Compose minimal buatan sendiri yang mengabaikan dependency resmi (seperti DB/Redis/migration container dari stack seperti Supabase, Immich, Appwrite, ERPNext, Chatwoot, Authentik).
5. **Post-Install Readiness Verification Gate (Wajib Sebelum `$MARKER`):**
   - Script **tidak boleh** langsung mencetak `CRAVA_INSTALL_OK` setelah `docker compose up -d` atau installer selesai!
   - Wajib panggil `crava_wait_port <primary_port> <timeout>` (`ss -tuln`) dan/atau `crava_wait_http <local_url>` / `systemctl is-active` / `docker inspect` (status `running`/`healthy`).
   - Jika timeout (mis. 120–180 detik) gagal: cetak 40 baris log terakhir service/container ke stderr dan **`exit 1` tanpa membuat `$MARKER`** sehingga job provisioning otomatis menandai failure dengan log diagnostik yang jelas.
6. **Keamanan Kredensial White-Label (D6) & Idempotensi:**
   - Password awal / `APP_KEY` / `JWT_SECRET` di-generate random (`openssl rand -hex 24`) dan **hanya** ditulis ke `.env` (`chmod 600`) atau `/root/.crava-credentials` (`chmod 600`), **tidak pernah** di-`echo` ke `stdout` (TAT TaskResult).
   - Cek `$MARKER` (`/var/lib/cravacloud/.installed-{{key}}`) di awal script **disertai verifikasi service/container aktif**; bila sehat → cetak `CRAVA_INSTALL_OK` dan `exit 0`.
7. **Automated CI & Sandbox Script Gates:**
   - **`tests/Unit/StackScriptSyntaxTest.php`:**
     - Cek 100% file `.sh` eksis sesuai `StackRegistry::all()`.
     - Jalankan `bash -n` pada semua 175+ script.
     - Static audit: wajib ada `set -euo pipefail`, `$MARKER`, `CRAVA_INSTALL_OK`, health verification call, **larangan** kata `example.com`/`TODO`/`FIXME`/echo password, dan validasi silang bahwa setiap port listen di script terdaftar di `StackDefinition::$ports` / `$udpPorts`.
   - **`php artisan stacks:audit-upstreams` (Dev/CI):** Verifikasi aksesibilitas HTTP HEAD / Docker registry manifest seluruh upstream URL & image sebelum setiap batch dipublikasikan (`visibleOnCheckout = true`).

---

## 6. Rencana Eksekusi Bertahap (Realistic Rollout)

> **Realita Operasional:** **150 stack baru** (`20` panel + `130` aplikasi) berarti **150 logo resmi terverifikasi SHA-256** + **150 script bash terverifikasi live-ready** + UX popup & firewall TCP/UDP. Eksekusi wajib dibagi dalam **4 Batch** berurutan dengan gerbang verifikasi test & dry-run sebelum dibuka ke pelanggan:

| Batch | Fokus Implementasi | Jumlah Tambahan | Kumulatif Katalog |
| --- | --- | :---: | :---: |
| **Batch 0 — Fondasi, Real Logo, Popup Warning & Script Audit** | 1) Refactor `StackRegistry` menjadi Single Source of Truth + dukungan `$udpPorts` & rentang port di `TencentLighthouseProvider::openPorts()`.<br>2) Bangun komponen popup konfirmasi `@include('components.stack-warning-modal')` (3 skenario: BYOL License, RAM Kurang, Aktivasi) + state Alpine + backend 422 lock di `OrderController` & Reinstall.<br>3) Audit & bersihkan 25 logo existing (`netdata_beszel.svg` dll.), siapkan `stack-logos.json` + `StackLogoIntegrityTest`.<br>4) Fix 4 script `registry.cravacloud.id` (`hermes_agent.sh`, `openclaw.sh`, `omniroute.sh`, `9router.sh`), terapkan `_common.sh` builder (auto-swap + health gate), serta siapkan `StackScriptSyntaxTest`.<br>5) Upgrade UI Checkout & Reinstall dengan Search Bar + Filter Chip Kategori. | **Pembenahan 27 stack existing** | **27 stack** *(25 di checkout, 100% logo real & script teraudit)* |
| **Batch 1 — Hostinger Core & High-Demand** | **Panels (Gratis):** `Portainer`, `Easypanel`, `1Panel`, `CapRover`, `CasaOS`, `ServerKit`, `Cosmos Cloud`, `FASTPANEL`, `Webmin/Virtualmin`<br>**Panels Berbayar BYOL (via Popup Konfirmasi):** `cPanel/WHM`, `DirectAdmin`, `Plesk`<br>**Apps Prioritas:** `LEMP Laravel`, `Nginx Proxy Manager`, `Grafana+Prometheus`, `Umami`, `Supabase`, `Appwrite`, `PocketBase`, `NocoDB`, `PostgreSQL+pgAdmin`, `MySQL+phpMyAdmin`, `MinIO`, `Open WebUI`, `Flowise`, `WireGuard`, `RustDesk`, `Nextcloud`, `Immich`, `Moodle`, `Odoo`, `Pterodactyl`, `FiveM txAdmin`, `Minecraft Paper` | **+34 stack** | **61 stack** |
| **Batch 2 — Business, Productivity, Comm & Auth** | `ERPNext`, `Dolibarr`, `Twenty CRM`, `Invoice Ninja`, `Akaunting`, `Paperless-ngx`, `Stirling PDF`, `BookStack`, `Outline`, `Wiki.js`, `Plane`, `Cal.com`, `Chatwoot`, `Typebot`, `Mattermost`, `Listmonk`, `Discourse`, `Flarum`, `Vaultwarden`, `AdGuard Home`, `Pi-hole`, `Authentik`, `Keycloak`, `Guacamole`, `OpenVPN`, `Headscale` + DB/BI (`Metabase`, `Plausible`, `Matomo`, `Meilisearch`, `Qdrant`, `Redis Insight`, `MongoDB`) | **+33 stack** | **94 stack** |
| **Batch 3 — Complete Hostinger Catalog+** | Sisa lengkap seluruh catalog (`ISPConfig`, `Froxlor`, `KeyHelp`, `Cockpit`, `YunoHost`, `Dockge`, `Webuzo` [BYOL], `Enhance` [BYOL], `Node/PM2`, `Python/FastAPI`, `Go/Caddy`, `Traefik`, `Caddy`, `HAProxy`, `Coder`, `IT-Tools`, `Hoppscotch`, `GitLab CE`, `Jenkins`, `Woodpecker`, `Dozzle`, `Watchtower`, `GlitchTip`, `Gatus`, `PostHog`, `Langfuse`, `LiteLLM`, `Activepieces`, `LocalAI`, `Whisper`, `LibreTranslate`, `Changedetection`, `SearXNG`, `Baserow`, `Grist`, `ClickHouse`, `RabbitMQ`, `InfluxDB`, `WooCommerce`, `Drupal`, `Joomla`, `Payload`, `MedusaJS`, `Magento`, `Owncast`, `EspoCRM`, `Firefly III`, `Kimai`, `Mautic`, `Snipe-IT`, `GLPI`, `Vikunja`, `Redmine`, `Memos`, `AFFiNE`, `CryptPad`, `Rocket.Chat`, `Zulip`, `Matrix`, `Mastodon`, `Ntfy`, `ownCloud`, `Seafile`, `FileBrowser`, `Syncthing`, `PhotoPrism`, `Jellyfin`, `Plex`, `Navidrome`, `Home Assistant`, `Linkwarden`, `FreshRSS`, `NetBird`, `Kasm`, `Homarr`, `OpenSpeedTest`, `Crafty`, `Minecraft Bedrock`, `Palworld`, `Valheim`, `Terraria`, `CS2`, `Rust`, dll.) | **+83 stack** | **177 stack** *(175 di tabel + `none` & `claude_opencode`)* |
