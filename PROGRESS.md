# eBook Store — Progress Log

Plan file: `ebook_prompt.md` (native Windows version — no Docker / WSL).
Project root: `C:\projects\ebook-store`

| Phase | Name | Status |
|-------|------|--------|
| 0 | Environment check and project setup | ✅ Complete (2026-10-03) — waiting for "next" |
| 1 | WordPress install + `.env` configuration | ⏳ Not started |
| 2 | WooCommerce core setup + sample eBooks | ⏳ Not started |
| 3 | Theme, design and responsive pages | ⏳ Not started |
| 4 | Cart, checkout and currency | ⏳ Not started |
| 5 | Dummy payment gateway | ⏳ Not started |
| 6 | Email delivery via SMTP | ⏳ Not started |
| 7 | QA, security and Hostinger deployment guide | ⏳ Not started |

---

## Phase 0 — Environment check and project setup (2026-10-03)

### Done
- Plan changed from Docker/WSL to **native Windows** (user decision: PC has 8 GB RAM). An earlier Docker-based Phase 0 attempt was abandoned before anything was built.
- Verified tools (nothing reinstalled):
  - Windows 10 build 19045, ~8 GB RAM
  - Git 2.55.0.windows.5
  - PHP 8.3.33 (CLI, ZTS, x64) via Scoop — php.ini at `C:\Users\ASUS\scoop\apps\php83\current\cli\php.ini`
  - PHP extensions present: curl, fileinfo, gd, intl, mbstring, exif, mysqli, openssl, pdo_mysql, sodium, zip (+ bcmath, dom, xml, zlib, etc.)
  - php.ini values: memory_limit 256M, upload_max_filesize 64M, post_max_size 64M, max_execution_time 120 (CLI always reports 0 — normal), max_input_vars 3000, date.timezone UTC
  - MySQL 9.7.2 (LTS) via Scoop — running; root without password
  - WP-CLI 2.12.0 (latest release) at `C:\tools\wp-cli\wp.bat`
  - Node.js v24.19.0
- Researched current stable versions (2026-10-03):
  - WordPress **7.1.2**
  - WooCommerce **11.1.2** (requires WP 7.0+, tested up to 7.1.2, PHP 7.4+)
  - WP-CLI **2.12.0**
- Project created at `C:\projects\ebook-store` by cloning the existing repo (keeps history and GitHub remote `git@github.com:SardarPiash/emni_project.git`). Updated `ebook_prompt.md` copied in.
- `git config core.autocrlf input` set for the repo.
- Created folder structure (Section 3) with `.gitkeep` placeholders, `.gitignore`, `wp-cli.yml` (`path: public`), `PROGRESS.md`.
- Verified `.gitignore` with temporary dummy files (then deleted):
  - Ignored: `.env`, `.env.local`, WordPress core (`public/index.php`, `wp-admin/`, `wp-includes/`, `wp-content/index.php`), third-party themes/plugins (storefront, woocommerce), other mu-plugins, `uploads/`, `debug.log`, `*.sql`
  - Tracked: `.env.example`, `public/wp-config.php`, `public/.htaccess`, `ebookstore-child/`, `ebook-store-core.php` + `ebook-store-core/`, `ebook-dummy-gateway/`
- WP-CLI picks up the project config (`wp --info` → `C:\projects\ebook-store\wp-cli.yml`).

### Open issues / decisions
1. **MySQL listens on all network interfaces** (`bind_address = *`) while root has no password. The plan requires localhost only. Fix proposed to the user (add `bind-address=127.0.0.1` to MySQL's `my.ini`) — waiting for approval.
2. **Hostinger database is MariaDB, not MySQL** (on shared/cloud hosting). Local is MySQL 9.7. Using `utf8mb4_unicode_ci` everywhere (plan rule) keeps the export importable. Exact PHP/MariaDB versions to be confirmed once the user has a Hostinger account (no account yet). Hostinger offers PHP 8.3, so local PHP 8.3 matches.
3. MySQL server default collation is `utf8mb4_0900_ai_ci` — the project database will be created explicitly with `utf8mb4_unicode_ci` in Phase 1.
4. The old folder `C:\New folder\emni_project` still exists; it is no longer used. The user can delete it once Phase 0 is committed.
5. Plan Section 3 mentions `PLAN.md`; the user decided the plan file stays as `ebook_prompt.md`.
