# Blueprint Katalog Control Panel & 1-Click Applications CravaCloud (Hostinger Parity)

> **Tujuan Dokumen:** Menjadi cetak biru katalog lengkap *Control Panel* dan *1-Click Applications* agar fitur provisioning CravaCloud setara dengan katalog **Hostinger VPS (`OS & Panel` + `Docker Manager Catalog`)**, sekaligus memetakan aturan **lisensi berbayar**, **batas minimum RAM**, **protokol firewall TCP/UDP**, dan **arsitektur Single Source of Truth**.

---

## 1. Posisi CravaCloud Saat Ini vs Hostinger VPS

### 1.1 Kondisi CravaCloud Saat Ini
- **Sistem Operasi (terverifikasi live `DescribeBlueprints` di `ap-jakarta` & `ap-singapore`):** 10 distro Linux non-EOL aktif (`ubuntu2404`, `ubuntu2204`, `ubuntu2604`, `ubuntu2004`, `debian13`, `debian12`, `debian11`, `rocky10`, `rocky94`, `centos_stream9`) + 3 image Docker29 (`ubuntu2404_docker`, `debian13_docker`, `centos_stream9_docker`). Semua bundle CravaCloud (`_lin_`) adalah Linux-only (`SupportWindowsPlatform=false`).
- **Control Panel saat ini (7):** `Coolify`, `Dokploy`, `aaPanel`, `CloudPanel`, `Docker Engine`, `CyberPanel`, `HestiaCP`.
- **1-Click Apps saat ini (18 checkout + 1 AI CLI):** `Hermes Agent`, `OpenClaw`, `OmniRoute`, `9router`, `Agent Zero`, `n8n`, `Ollama`, `Dify + Ollama`, `AnythingLLM`, `LibreChat`, `VS Code Server`, `Gitea / Forgejo`, `Uptime Kuma`, `Netdata / Beszel`, `WordPress`, `Ghost`, `Strapi / Directus`, `PrestaShop / Bagisto`, dan `Claude Code & OpenCode`.

### 1.2 Benchmark Resmi Katalog Hostinger VPS
Berdasarkan dokumentasi resmi Hostinger (`support.hostinger.com`, `hostinger.com/tutorials`, dan `Docker Manager Catalog`):
1. **OS Templates dengan Control Panel (`OS & Panel`):**
   - *Gratis / Open-Source:* **CloudPanel**, **CyberPanel**, **aaPanel**, **HestiaCP**, **Coolify**, **Dokploy**, **Easypanel**, **ServerKit**, **Cosmos Cloud**, **FASTPANEL**, **Webmin / Virtualmin**, **ISPConfig**, **CapRover**.
   - *Komersial / Berbayar (BYOL — Bring Your Own License):* **cPanel / WHM**, **DirectAdmin**, **Plesk**.
2. **Framework & 1-Click App / Docker Manager Catalog (130+ templates):**
   - *Container & Proxy:* Docker + Compose, Portainer, Nginx Proxy Manager, Traefik.
   - *Backend, BaaS & Database:* Supabase, Appwrite, PocketBase, NocoDB, PostgreSQL (+ pgAdmin), MySQL/MariaDB (+ phpMyAdmin), MongoDB, Redis, MinIO, Meilisearch, Qdrant.
   - *AI & Automation:* Open WebUI + Ollama, n8n, Flowise, Dify, AnythingLLM, LibreChat, Langfuse.
   - *Dev, CI/CD & Monitoring:* Laravel / LEMP Stack (Ubuntu + CloudPanel/Nginx+PHP), Grafana + Prometheus, Umami Analytics, Plausible Analytics, Metabase, GitLab CE, Jenkins, Gitea, Redmine, Uptime Kuma, Netdata.
   - *CMS, LMS & Bisnis:* WordPress (+ WooCommerce), Ghost, Strapi, Directus, Drupal, Joomla, Moodle, Odoo, BookStack, Outline, AFFiNE.
   - *VPN, Privacy, Auth & Remote Access:* WireGuard (`wg-easy`), OpenVPN, RustDesk Server, Apache Guacamole, AdGuard Home, Pi-hole, Vaultwarden, Authentik, Keycloak.
   - *Storage, Media, Chat & Gaming:* Nextcloud, ownCloud, Immich, Jellyfin, Plex, Mattermost, Rocket.Chat, Pterodactyl Panel / Game Server.

---

## 2. Kebijakan Wajib: Panel Berbayar & Perlindungan Konsumen

CravaCloud mengizinkan template panel komersial (**cPanel / WHM**, **DirectAdmin**, **Plesk**) masuk katalog agar sebanding dengan Hostinger, namun **wajib** menerapkan 4 pengaman UI berikut sebelum order dibuat:

1. **Badge Status Lisensi di Kartu Katalog:**
   - Panel gratis: `Gratis / Open-Source`
   - Panel komersial: **`Belum Termasuk Lisensi`** (warna peringatan amber/merah tegas).
2. **Kotak Peringatan Eksplisit Saat Panel Berbayar Dipilih:**
   > **Peringatan Lisensi Terpisah:** CravaCloud **hanya menginstal perangkat lunak/installer panel** di VPS Anda dan **tidak menjual atau menyertakan lisensi berbayar**. Anda wajib membeli atau mengaktifkan lisensi resmi langsung dari penyedia panel (*cPanel / DirectAdmin / Plesk*) agar panel dapat beroperasi setelah masa trial berakhir.
3. **Checkbox Konfirmasi Wajib Sebelum Lanjut (`requires_license`):**
   - Saat user memilih stack dengan `requiresLicense = true`, tombol `Lanjutkan →` terkunci sampai customer mencentang pernyataan bahwa lisensi berbayar ditanggung sendiri di luar tagihan CravaCloud.
4. **Simpan Flag di `orders.metadata` & `vps_instances`:**
   - Mencatat bukti bahwa customer menyetujui ketentuan BYOL (*Bring Your Own License*) pada saat checkout.

---

## 3. Katalog Lengkap Control Panel (Target Parity Hostinger)

### 3.1 Control Panel Gratis / Open-Source (0 Biaya Lisensi)
| Key | Nama Panel | Kategori UI | Status | Port Firewall | URL Akses Default | Min RAM | OS Didukung (`StackRegistry`) |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `coolify` | Coolify | PaaS & Container | **Sudah Ada** | `22, 80, 443, 8000` (TCP) | `http://{ip}:8000` | 2 GB | Ubuntu 20/22/24, Debian 11/12/13 |
| `dokploy` | Dokploy | PaaS & Container | **Sudah Ada** | `22, 80, 443, 3000` (TCP) | `http://{ip}:3000` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 |
| `docker` | Docker Engine | PaaS & Container | **Sudah Ada** | `22` (TCP) | — (CLI / SSH) | 2 GB | Ubuntu 22/24/26 |
| `cloudpanel` | CloudPanel | Web Hosting Panel | **Sudah Ada** | `22, 80, 443, 8443` (TCP) | `https://{ip}:8443` | 2 GB | Ubuntu 22/24, Debian 12 |
| `aapanel` | aaPanel | Web Hosting Panel | **Sudah Ada** | `22, 80, 443, 8888, 888, 20, 21` (TCP) | `http://{ip}:8888` | 2 GB | Ubuntu 20/22/24, Debian 11/12/13, Rocky 9/10, CentOS Stream 9 |
| `cyberpanel` | CyberPanel | Web Hosting Panel | **Sudah Ada** | `22, 80, 443, 8090, 7080` (TCP) | `https://{ip}:8090` | 2 GB | Ubuntu 22/24, Rocky 9.4, CentOS Stream 9 |
| `hestiacp` | HestiaCP | Web Hosting Panel | **Sudah Ada** | `22, 80, 443, 8083` (TCP) | `https://{ip}:8083` | 2 GB | Ubuntu 20/22/24, Debian 11/12/13 |
| `easypanel` | Easypanel | PaaS & Container | **BARU** | `22, 80, 443, 3000` (TCP) | `http://{ip}:3000` | 2 GB | Ubuntu 22/24, Debian 11/12/13 |
| `onepanel` | 1Panel | Web & Container Panel | **BARU** | `22, 80, 443, 10086` (TCP, fixed via CLI) | `http://{ip}:10086` | 2 GB | Ubuntu 22/24, Debian 11/12/13, Rocky 9.4, CentOS Stream 9 |
| `portainer` | Portainer CE | Container GUI | **BARU** | `22, 80, 443, 9443` (TCP) | `https://{ip}:9443` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 |
| `caprover` | CapRover | PaaS & Container | **BARU** | `22, 80, 443, 3000` (TCP) | `http://{ip}:3000` | 2 GB | Ubuntu 22/24, Debian 11/12/13 |
| `casaos` | CasaOS | Personal Cloud Panel | **BARU** | `22, 80, 443, 8080` (TCP) | `http://{ip}:8080` | 2 GB | Ubuntu 22/24, Debian 11/12 |
| `cosmos_cloud` | Cosmos Cloud | Container & Proxy Panel | **BARU** | `22, 80, 443` (TCP) | `http://{ip}` | 2 GB | Ubuntu 22/24, Debian 11/12/13 |
| `serverkit` | ServerKit | Web & Docker Panel | **BARU** | `22, 80, 443, 8443` (TCP) | `https://{ip}:8443` | 2 GB | Ubuntu 22/24/26 |
| `fastpanel` | FASTPANEL | Web Hosting Panel | **BARU** | `22, 80, 443, 8888` (TCP) | `https://{ip}:8888` | 2 GB | Ubuntu 22/24, Debian 11/12 |
| `webmin_virtualmin` | Webmin / Virtualmin | Web & SysAdmin Panel | **BARU** | `22, 80, 443, 10000` (TCP) | `https://{ip}:10000` | 2 GB | Ubuntu 22/24, Debian 11/12/13, Rocky 9.4 |
| `ispconfig` | ISPConfig | Web Hosting Panel | **BARU** | `22, 80, 443, 8080, 8081` (TCP) | `https://{ip}:8080` | 2 GB | Ubuntu 22/24, Debian 11/12 |

### 3.2 Control Panel Komersial / Berbayar (`requiresLicense = true` — Wajib Warning)
| Key | Nama Panel | Kategori UI | Status | Port Firewall | URL Akses Default | Min RAM | OS Didukung | Catatan Lisensi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `cpanel_whm` | cPanel / WHM | Web Panel (Berbayar) | **BARU** | `22, 80, 443, 2083, 2087` (TCP) | `https://{ip}:2087` | **4 GB** (`Starter S+`) | `ubuntu2204`, `rocky94` | **Belum Termasuk Lisensi.** Perlu lisensi resmi cPanel (~$20–$65/bln di vendor). |
| `directadmin` | DirectAdmin | Web Panel (Berbayar) | **BARU** | `22, 80, 443, 2222` (TCP) | `https://{ip}:2222` | 2 GB | `ubuntu2204`, `ubuntu2404`, `debian12`, `rocky94` | **Belum Termasuk Lisensi.** Perlu license key DirectAdmin dari vendor/partner. |
| `plesk` | Plesk Obsidian | Web Panel (Berbayar) | **BARU** | `22, 80, 443, 8443, 8880` (TCP) | `https://{ip}:8443` | 2 GB | `ubuntu2204`, `ubuntu2404`, `debian12` | **Belum Termasuk Lisensi.** Hanya versi Linux (bundle tidak mendukung Windows). |

> **Catatan keamanan D6 (no plaintext secret via TAT):** Semua panel yang menghasilkan password awal saat install (mis. `FASTPANEL`, `1Panel`, `cPanel`, `DirectAdmin`) tidak boleh mencetak password ke stdout/TaskResult. Customer mengakses wizard first-run lewat URL panel atau reset password CLI via SSH/VNC sesuai mekanisme resmi masing-masing panel.

---

## 4. Katalog Lengkap 1-Click Applications (Terintegrasi & Terkategori)

### Kategori A — Dev Stack, CI/CD & Observability (Termasuk Permintaan Ryan + Hostinger)
| Key | Nama | Status | Port Firewall | URL Akses | Min RAM | OS Didukung | Deskripsi Singkat |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `lemp_laravel` | **LEMP / Laravel Optimized** | **BARU** | `22, 80, 443` (TCP) | `http://{ip}` | 2 GB | Ubuntu 22/24, Debian 12/13 | Nginx + PHP 8.3 FPM + MariaDB + Redis + Composer + Node LTS + Supervisor siap deploy Laravel |
| `grafana_prometheus` | **Grafana + Prometheus** | **BARU** | `22, 3000, 9090` (TCP) | `http://{ip}:3000` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Monitoring infrastruktur, dashboard metrik server (Node Exporter) & alert real-time |
| `umami` | **Umami Analytics** | **BARU** | `22, 3000` (TCP) | `http://{ip}:3000` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Alternatif Google Analytics ringan, privacy-first, + PostgreSQL terintegrasi |
| `plausible` | **Plausible Analytics** | **BARU** | `22, 8000` (TCP) | `http://{ip}:8000` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | Web analytics bebas cookie (Postgres + ClickHouse) |
| `metabase` | **Metabase BI** | **BARU** | `22, 3000` (TCP) | `http://{ip}:3000` | **4 GB** (`Starter S+`) | Ubuntu 22/24/26, Debian 11/12/13 | Business Intelligence & visualisasi SQL dashboard untuk tim bisnis |
| `nginx_proxy_manager` | **Nginx Proxy Manager** | **BARU** | `22, 80, 443, 81` (TCP) | `http://{ip}:81` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Reverse proxy GUI + auto SSL Let's Encrypt |
| `traefik` | **Traefik Proxy** | **BARU** | `22, 80, 443, 8080` (TCP) | `http://{ip}:8080` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Edge router & load balancer Docker modern + auto TLS |
| `gitlab_ce` | **GitLab CE** | **BARU** | `22, 80, 443, 2222` (TCP) | `http://{ip}` | **8 GB** (`Starter M+`) | Ubuntu 22/24, Debian 12 | Self-hosted Git + CI/CD Enterprise lengkap (butuh RAM besar) |
| `jenkins` | **Jenkins CI/CD** | **BARU** | `22, 8080` (TCP) | `http://{ip}:8080` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Automation server untuk pipeline build, test, & deploy |
| `vscode_server` | VS Code Server | **Sudah Ada** | `22, 8080` (TCP) | `http://{ip}:8080` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | Cloud IDE di browser |
| `gitea_forgejo` | Gitea / Forgejo | **Sudah Ada** | `22, 3000, 2222` (TCP) | `http://{ip}:3000` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | Self-hosted Git server ringan |
| `uptime_kuma` | Uptime Kuma | **Sudah Ada** | `22, 3001` (TCP) | `http://{ip}:3001` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | Status page & uptime monitor |
| `netdata_beszel` | Netdata / Beszel | **Sudah Ada** | `22, 19999, 8090` (TCP) | `http://{ip}:19999` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | Telemetry & system metrics ringan |
| `redmine` | **Redmine** | **BARU** | `22, 3000` (TCP) | `http://{ip}:3000` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Issue tracking & manajemen proyek open-source |

### Kategori B — Backend, BaaS & Database
| Key | Nama | Status | Port Firewall | URL Akses | Min RAM | OS Didukung | Deskripsi Singkat |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `appwrite` | **Appwrite** | **BARU** | `22, 80, 443` (TCP) | `http://{ip}` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | BaaS open-source (Auth, DB, Storage, Functions) |
| `supabase` | **Supabase (Self-Hosted)** | **BARU** | `22, 80, 443, 8000` (TCP) | `http://{ip}:8000` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | Alternatif Firebase full stack (Postgres, Auth, Realtime, Studio) |
| `pocketbase` | **PocketBase** | **BARU** | `22, 8090` (TCP) | `http://{ip}:8090/_/` | 2 GB | All Linux (`apt`/`dnf`) | BaaS super ringan 1 binary (SQLite + Realtime + Auth) |
| `nocodb` | **NocoDB** | **BARU** | `22, 8080` (TCP) | `http://{ip}:8080` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Alternatif Airtable open-source berbasis spreadsheet/SQL |
| `postgresql_pgadmin` | **PostgreSQL + pgAdmin** | **BARU** | `22, 5432, 5050` (TCP) | `http://{ip}:5050` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | PostgreSQL 16 + GUI pgAdmin 4 siap pakai |
| `mysql_phpmyadmin` | **MySQL / MariaDB + phpMyAdmin** | **BARU** | `22, 3306, 8080` (TCP) | `http://{ip}:8080` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Database MariaDB/MySQL + UI web phpMyAdmin |
| `meilisearch` | **Meilisearch** | **BARU** | `22, 7700` (TCP) | `http://{ip}:7700` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Search engine full-text cepat untuk aplikasi web/e-commerce |
| `qdrant` | **Qdrant Vector DB** | **BARU** | `22, 6333, 6334` (TCP) | `http://{ip}:6333/dashboard` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Vector database performa tinggi untuk aplikasi AI & RAG |

### Kategori C — AI, LLM & Automation
| Key | Nama | Status | Port Firewall | URL Akses | Min RAM | OS Didukung | Deskripsi Singkat |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `flowise` | **Flowise** | **BARU** | `22, 3000` (TCP) | `http://{ip}:3000` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Visual drag-and-drop builder untuk AI agents & LangChain workflows |
| `open_webui` | **Open WebUI + Ollama** | **BARU** | `22, 3000, 11434` (TCP) | `http://{ip}:3000` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | Antarmuka mirip ChatGPT self-hosted + RAG dokumen lokal |
| `n8n` | n8n | **Sudah Ada** | `22, 5678` (TCP) | `http://{ip}:5678` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | Workflow automation |
| `ollama` | Ollama | **Sudah Ada** | `22, 11434` (TCP) | `http://{ip}:11434` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | Local LLM runtime API |
| `dify_ollama` | Dify + Ollama | **Sudah Ada** | `22, 80, 443` (TCP) | `http://{ip}` | **4 GB** (`Starter S+`) | Ubuntu 22/24/26, Debian 12/13 | Studio orkestrasi AI & RAG |
| `anythingllm` | AnythingLLM | **Sudah Ada** | `22, 3001` (TCP) | `http://{ip}:3001` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | Private document RAG workspace |
| `librechat` | LibreChat | **Sudah Ada** | `22, 3080` (TCP) | `http://{ip}:3080` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | Multi-model AI chat UI |
| `hermes_agent` | Hermes Agent | **Sudah Ada** | `22, 8000` (TCP) | `http://{ip}:8000` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | Autonomous agent runtime |
| `openclaw` | OpenClaw | **Sudah Ada** | `22, 8080` (TCP) | `http://{ip}:8080` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | AI Agent gateway |
| `agent_zero` | Agent Zero | **Sudah Ada** | `22, 50001` (TCP) | `http://{ip}:50001` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | Dynamic AI framework |
| `omniroute` | OmniRoute | **Sudah Ada** | `22` (TCP) | — (outbound) | 2 GB | All 10 verified Linux | Smart LLM outbound proxy |
| `9router` | 9router | **Sudah Ada** | `22` (TCP) | — (outbound) | 2 GB | All 10 verified Linux | Lightweight AI outbound router |

### Kategori D — Home, Self-Host, Storage, Media & LMS (Termasuk Permintaan Ryan + Hostinger)
| Key | Nama | Status | Port Firewall | URL Akses | Min RAM | OS Didukung | Deskripsi Singkat |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `minio` | **MinIO (S3 Storage)** | **BARU** | `22, 9000, 9001` (TCP) | `http://{ip}:9001` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Object storage kompatibel Amazon S3 self-hosted |
| `immich` | **Immich** | **BARU** | `22, 2283` (TCP) | `http://{ip}:2283` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | Alternatif Google Photos (backup foto/video HP + AI search) |
| `moodle` | **Moodle LMS** | **BARU** | `22, 80, 443` (TCP) | `http://{ip}` | 2 GB (rek. 4 GB) | Ubuntu 22/24, Debian 12/13 | Platform e-learning untuk sekolah, kampus, & kursus |
| `nextcloud` | **Nextcloud** | **BARU** | `22, 80, 443, 8080` (TCP) | `http://{ip}:8080` | 2 GB (rek. 4 GB) | Ubuntu 22/24/26, Debian 11/12/13 | Alternatif Google Drive & kolaborasi file tim |
| `jellyfin` | **Jellyfin Media Server** | **BARU** | `22, 8096` (TCP) | `http://{ip}:8096` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Streaming film, musik, & media pribadi bebas lisensi |
| `bookstack` | **BookStack** | **BARU** | `22, 80, 443, 6875` (TCP) | `http://{ip}:6875` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Dokumentasi, SOP, & wiki organisasi |
| `outline` | **Outline Wiki** | **BARU** | `22, 3000` (TCP) | `http://{ip}:3000` | 2 GB | Ubuntu 22/24, Debian 12/13 | Knowledge base modern ala Notion untuk tim |
| `mattermost` | **Mattermost / Rocket.Chat** | **BARU** | `22, 8065` (TCP) | `http://{ip}:8065` | 2 GB (rek. 4 GB) | Ubuntu 22/24, Debian 12/13 | Chat internal tim alternatif Slack self-hosted |
| `odoo` | **Odoo Community ERP** | **BARU** | `22, 8069` (TCP) | `http://{ip}:8069` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | ERP open-source (Akuntansi, Stok, POS, CRM) |
| `pterodactyl` | **Pterodactyl Panel** | **BARU** | `22, 80, 443, 8080, 2022` (TCP) | `http://{ip}` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12 | Panel server game populer (Minecraft, FiveM, dll) |

### Kategori E — Web CMS & E-Commerce
| Key | Nama | Status | Port Firewall | URL Akses | Min RAM | OS Didukung | Deskripsi Singkat |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `wordpress` | WordPress | **Sudah Ada** | `22, 80, 443` (TCP) | `http://{ip}` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | WordPress + Nginx/Docker |
| `ghost` | Ghost CMS | **Sudah Ada** | `22, 2368` (TCP) | `http://{ip}:2368` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | Platform publikasi modern |
| `strapi_directus` | Strapi / Directus | **Sudah Ada** | `22, 8055` (TCP) | `http://{ip}:8055` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | Headless CMS |
| `prestashop_bagisto` | PrestaShop / Bagisto | **Sudah Ada** | `22, 80, 443` (TCP) | `http://{ip}` | 2 GB | Ubuntu 22/24/26, Debian 12/13 | E-commerce platform |
| `drupal` | **Drupal CMS** | **BARU** | `22, 80, 443` (TCP) | `http://{ip}` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | CMS enterprise fleksibel (+ MariaDB) |
| `joomla` | **Joomla! CMS** | **BARU** | `22, 80, 443` (TCP) | `http://{ip}` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | CMS website komunitas & portal |

### Kategori F — VPN, Remote Access, Security & Auth *(Sebagian Butuh Port UDP!)*
| Key | Nama | Status | Port Firewall | URL Akses | Min RAM | OS Didukung | Deskripsi Singkat |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `rustdesk` | **RustDesk Server** | **BARU** | `22, 21114-21119` (TCP) + **`21116` (UDP)** | `http://{ip}:21114` (Info ID/Relay) | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Remote desktop self-hosted alternatif TeamViewer / AnyDesk |
| `wireguard` | **WireGuard (`wg-easy`)** | **BARU** | `22, 51821` (TCP) + **`51820` (UDP)** | `http://{ip}:51821` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | VPN super cepat + web UI kelola peer & QR code |
| `openvpn` | **OpenVPN Server** | **BARU** | `22, 943, 443` (TCP) + **`1194` (UDP)** | `https://{ip}:943` | 2 GB | Ubuntu 22/24, Debian 11/12 | VPN server klasik siap ekspor `.ovpn` |
| `guacamole` | **Apache Guacamole** | **BARU** | `22, 8080` (TCP) | `http://{ip}:8080/guacamole` | 2 GB | Ubuntu 22/24, Debian 11/12 | Gateway RDP / VNC / SSH clientless di browser |
| `vaultwarden` | **Vaultwarden** | **BARU** | `22, 80, 443, 8080` (TCP) | `http://{ip}:8080` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | Password manager self-hosted kompatibel Bitwarden |
| `adguard_home` | **AdGuard Home** | **BARU** | `22, 80, 443, 3000, 53` (TCP) + **`53` (UDP)** | `http://{ip}:3000` | 2 GB | Ubuntu 22/24/26, Debian 11/12/13 | DNS server & pemblokir iklan jaringan |
| `pihole` | **Pi-hole** | **BARU** | `22, 80, 53` (TCP) + **`53` (UDP)** | `http://{ip}/admin` | 2 GB | Ubuntu 22/24, Debian 11/12 | Network-wide ad & tracker blocker |
| `authentik` | **Authentik SSO / IAM** | **BARU** | `22, 9000, 9443` (TCP) | `http://{ip}:9000` | **4 GB** (`Starter S+`) | Ubuntu 22/24, Debian 12/13 | Single Sign-On (OAuth2/OIDC/SAML/LDAP) |

---

## 5. Prasyarat Teknis Implementasi Sebelum Script Ditulis

Agar penambahan ±37 stack baru ini bersih, anti-bug, dan tidak mengulang duplikasi kode di masa depan, ada **4 pekerjaan arsitektur** yang harus dikerjakan lebih dulu:

### 5.1 Dukungan Firewall Port UDP di `StackDefinition` & `TencentLighthouseProvider`
- **Gap saat ini:** `TencentLighthouseProvider::openPorts()` (baris 928–946) men-set `'Protocol' => 'TCP'` untuk semua item di `StackDefinition::$ports`.
- **Dampak:** Stack seperti **WireGuard (`51820/UDP`)**, **OpenVPN (`1194/UDP`)**, **RustDesk (`21116/UDP`)**, dan **AdGuard/Pi-hole (`53/UDP`)** akan gagal berfungsi jika hanya port TCP yang dibuka di firewall Tencent Lighthouse.
- **Solusi:** Tambahkan properti optional `public readonly array $udpPorts = []` pada `StackDefinition` (tetap mempertahankan `ports` untuk TCP sehingga 100% backward-compatible dengan 27 stack & test existing), dan perbarui `openPorts()` untuk menyusun rule baik TCP maupun UDP sebelum diff ke `DescribeFirewallRules`.

### 5.2 Jadikan `StackRegistry` Single Source of Truth
- Saat ini label, validasi controller, tab/filter UI checkout, logo, dan map URL terpencar di `StackRegistry.php`, `Order.php`, `OrderController.php`, `slide-configuration.blade.php`, dan `checkout.blade.php`.
- **Solusi:** Tambahkan field metadata terstruktur di `StackDefinition`:
  - `uiGroup`: `'none' | 'panel' | 'app'`
  - `category`: string (e.g. `'PaaS & Container'`, `'Web Panel'`, `'Backend & DB'`, `'AI & Automation'`, `'Dev & Monitoring'`, `'Web & CMS'`, `'VPN & Remote'`, `'Storage & Gaming'`)
  - `description`: string ringkas 1 baris
  - `logo`: path relatif ikon SVG/PNG
  - `minRamGb`: int (`2` default, `4` atau `8` untuk app berat)
  - `requiresLicense`: bool (`false` default, `true` untuk `cpanel_whm`, `directadmin`, `plesk`)
  - `licenseNotice`: `?string` (pesan peringatan resmi BYOL)
- Turunkan otomatis:
  - `Order::stackLabels()` membaca dari `StackRegistry` (+ label legacy `managed_database` & `hermes_omniroute`).
  - `OrderController::store()` memvalidasi `control_panel` terhadap daftar key aktif dari `StackRegistry` + cek `minRamGb` terhadap `$spec->ram` + cek persetujuan `license_acknowledged` jika `requiresLicense === true`.

### 5.3 UI Checkout & Reinstall yang Nyaman untuk 60+ Pilihan
- **Search Box + Filter Sub-Kategori:** Di dalam tab **Control Panel** dan **AI & Aplikasi** pada `slide-configuration.blade.php`, sediakan kolom cari cepat (`x-model="stackSearch"`) dan chip kategori supaya halaman tidak terlalu panjang dan user menemukan aplikasi dalam 2 detik.
- **Proteksi RAM (`minRamGb`) Otomatis:** Jika customer memilih `Starter XS` (2 GB RAM) lalu melihat `Supabase`, `Appwrite`, `Immich`, `Odoo`, `Pterodactyl`, `cPanel`, atau `GitLab CE`, tampilkan badge **`Min. 4 GB / 8 GB RAM`** + status non-aktif dengan tombol pintar *"Perlu Upgrade ke Starter S/M/L"* agar server tidak langsung mati kehabisan RAM (*OOM kill*) saat deploy.
- **Warning Lisensi Berbayar:** Kartu `cPanel / WHM`, `DirectAdmin`, dan `Plesk` menampilkan badge mencolok **`Belum Termasuk Lisensi`**, banner merah/amber saat diklik, serta checkbox konfirmasi eksplisit sebelum lanjut ke pembayaran.

### 5.4 Integritas Script Bash Installer (D6 & Kebersihan Root)
- Setiap file `.sh` di `resources/provisioning/stacks/` harus memakai pola standar:
  1. `set -euo pipefail` + marker `/var/lib/cravacloud/.installed-{{stack}}` (idempoten aman di-retry TAT).
  2. Cek / buat swap file otomatis 1–2 GB bila belum ada pada VPS 2–4 GB sebelum menarik image Docker besar.
  3. **Dilarang mencetak password/token ke stdout** (`TaskResult` TAT terbaca log internal — kredensial awal dibuat via wizard first-run web UI atau tersimpan di file root-only `/root/...` atau `/var/lib/cravacloud/...` dengan izin `600`).
  4. Selalu emit `CRAVA_INSTALL_OK {{stack}}` di baris akhir.

---

## 6. Rencana Eksekusi Bertahap (Recommended Rollout)

| Tahap | Cakupan Pekerjaan | Jumlah Stack Baru | Estimasi Dampak |
| --- | --- | --- | --- |
| **Tahap 1 — Fondasi** | Refactor `StackRegistry` (Single Source of Truth + `udpPorts` + `minRamGb` + `requiresLicense`), search/filter UI checkout & modal reinstall, checkbox warning panel berbayar, auto-swap helper script | — | Menghilangkan bottleneck duplikasi 6 file & mencegah salah pilih RAM/lisensi |
| **Tahap 2 — Batch Prioritas Utama** | **Panels:** `Portainer`, `Easypanel`, `1Panel`, `CapRover`, `CasaOS`, `Webmin/Virtualmin`<br>**Dev & Observability:** `LEMP / Laravel Optimized`, `Grafana + Prometheus`, `Umami Analytics`, `Nginx Proxy Manager`<br>**Backend & Storage:** `Supabase`, `Appwrite`, `PocketBase`, `NocoDB`, `PostgreSQL + pgAdmin`, `MinIO`<br>**VPN, AI & Apps:** `WireGuard (wg-easy)`, `RustDesk Server`, `Open WebUI + Ollama`, `Flowise`, `Nextcloud`, `Immich`, `Moodle`, `Odoo`, `Pterodactyl` | **+25 stack** *(total ~51 stack)* | Menutup seluruh gap paling laris vs Hostinger sekaligus memasukkan seluruh stack yang Anda setujui |
| **Tahap 3 — Panel Berbayar & Pelengkap Penuh Hostinger** | **Panel Komersial (w/ Warning Lisensi):** `cPanel / WHM`, `DirectAdmin`, `Plesk`<br>**Hostinger Extras:** `ServerKit`, `Cosmos Cloud`, `FASTPANEL`, `ISPConfig`, `MySQL + phpMyAdmin`, `Meilisearch`, `Qdrant`, `Plausible`, `Metabase`, `GitLab CE`, `Jenkins`, `Traefik`, `Redmine`, `BookStack`, `Outline`, `Mattermost`, `Drupal`, `Joomla`, `Jellyfin`, `OpenVPN`, `Guacamole`, `Vaultwarden`, `AdGuard Home`, `Pi-hole`, `Authentik` | **+28 stack** *(total ~79 stack)* | Paritas penuh dengan ekosistem panel & catalog Hostinger VPS |
