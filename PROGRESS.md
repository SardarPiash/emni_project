# eBook Store — Progress Log

Plan file: `ebook_prompt.md` (native Windows version — no Docker / WSL).
Project root: `C:\projects\ebook-store`

| Phase | Name | Status |
|-------|------|--------|
| 0 | Environment check and project setup | ✅ Complete (2026-10-03) |
| 1 | WordPress install + `.env` configuration | ✅ Complete (2026-10-03) |
| 2 | WooCommerce core setup + sample eBooks | ✅ Complete (2026-10-03) |
| 3 | Theme, design and responsive pages | ✅ Complete (2026-10-03) — waiting for "next" |
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
1. ~~MySQL listens on all network interfaces~~ — **fixed in Phase 1** (user approved): added `bind-address=127.0.0.1` and `mysqlx-bind-address=127.0.0.1` to `C:\Users\ASUS\scoop\persist\mysql-lts\my.ini` (backup `my.ini.bak`). Verified ports 3306/33060 listen on 127.0.0.1 only.
2. **Hostinger database is MariaDB, not MySQL** (on shared/cloud hosting). Local is MySQL 9.7. Using `utf8mb4_unicode_ci` everywhere (plan rule) keeps the export importable. Exact PHP/MariaDB versions to be confirmed once the user has a Hostinger account (no account yet). Hostinger offers PHP 8.3, so local PHP 8.3 matches.
3. MySQL server default collation is `utf8mb4_0900_ai_ci` — the project database will be created explicitly with `utf8mb4_unicode_ci` in Phase 1.
4. The old folder `C:\New folder\emni_project` still exists; it is no longer used. The user can delete it once Phase 0 is committed.
5. Plan Section 3 mentions `PLAN.md`; the user decided the plan file stays as `ebook_prompt.md`.

---

## Phase 1 — WordPress install + `.env` configuration (2026-10-03)

### Done
- MySQL bound to localhost (see Phase 0 issue 1).
- Database `ebookstore` (utf8mb4 / utf8mb4_unicode_ci) and dedicated user `ebookstore_user` (@localhost and @127.0.0.1, ALL on `ebookstore.*` only) with a generated 32-char password.
- `config/env-loader.php`: `.env` parser + `env()` helper (comments, quotes, inline comments, empty values, CRLF/LF, `export` prefix; never overwrites server variables). Tested with a fixture file — all cases pass.
- `.env` generated from `.env.example` with random DB password and 8 random 64-char salts (secrets never printed).
- `.env.example` — every key commented. New key: `SITE_TIMEZONE`.
- `public/wp-config.php` reads everything from `../.env`: DB (incl. `DB_COLLATE`), salts, `WP_HOME`/`WP_SITEURL`, `WP_ENVIRONMENT_TYPE` from `APP_ENV`, debug flags, `DISALLOW_FILE_EDIT`, WP Mail SMTP constants (names verified in plugin source `src/Options.php`).
- WordPress 7.1.2 (en_US) installed — from the official zip (SHA1 verified) because `wp core download` fails on Windows (see PLUGINS.md); `wp core verify-checksums` passes.
- Admin user `ebook_manager` (not "admin"), generated password given to the user in the Phase 1 report; admin email `support@example.com` (placeholder).
- Settings: language en_US, date `F j, Y`, time `g:i a`, week starts Sunday, timezone America/New_York, permalinks `/%postname%/`, comments + pingbacks closed on new posts.
- Removed sample post, sample page, Hello Dolly, Akismet, Twenty Twenty-Three/Four. Kept: Privacy Policy draft page (footer link later), Twenty Twenty-Five (active until Storefront in Phase 3).
- `mu-plugins/ebook-store-core.php`: site title follows `STORE_NAME` and timezone follows `SITE_TIMEZONE` live from `.env`; those fields are disabled in Settings → General with a note.
- `public/.htaccess`: WordPress rewrite rules + `.env` deny rule (effective on Hostinger only).
- `PLUGINS.md` created.
- Tests: home, wp-login, REST API return 200; pretty permalinks work; title changes when `STORE_NAME` changes; no PHP errors in debug.log; git tracks only our 5 new files.

### Open issues / decisions
1. **Collation:** WordPress automatically upgrades `utf8mb4_unicode_ci` to `utf8mb4_unicode_520_ci` for its tables when the server supports it. MariaDB (Hostinger) supports this collation, so the export stays portable. The problematic `utf8mb4_0900_ai_ci` is not used. Re-check in Phase 7 before export.
2. Admin email is a placeholder (`support@example.com`) — user should change it to a real address in Settings → General (or tell Claude).
3. `.env` deny rule and other `.htaccess` rules can only be verified on Hostinger (PHP built-in server ignores `.htaccess`).

---

## Phase 2 — WooCommerce core setup + sample eBooks (2026-10-03)

### Done
- Compatibility research (2026-10-03): WooCommerce 11.1.2; CURCY 2.2.17 (tested WP 7.1.2, HPOS conflict fixed in 2.2.11, free auto-detect by IP, recent XSS fix); WP Mail SMTP 4.10.0 (email log is Pro-only → use WP Mail Logging 1.17.0 in Phase 6); Storefront 4.6.2.
- WooCommerce 11.1.2 installed + activated, `wp plugin verify-checksums` passes.
- HPOS enabled (`wp wc hpos enable`, compatibility/sync mode off, 0 orders).
- Settings: store base US:NY; sell to US + GB only; shipping disabled; base currency USD (follows `STORE_BASE_CURRENCY` via mu-plugin filter); taxes off; guest checkout on; account creation optional at checkout (not on My Account page); downloads = Force downloads, grant access after payment, hash in filename, no redirect fallback, no login required; onboarding wizard/task lists hidden; tracking + marketplace suggestions off.
- Approved download directories: mode enabled, only `uploads/woocommerce_uploads/` (file:// path + URL). WooCommerce's `.htaccess` + `index.html` exist in that folder.
- Cart and Checkout pages switched from blocks to classic shortcodes `[woocommerce_cart]` / `[woocommerce_checkout]`.
- mu-plugin: `woocommerce_currency` follows `.env`; new hook applies `STORE_DOWNLOAD_LIMIT` / `STORE_DOWNLOAD_EXPIRY_DAYS` the first time a product is saved as downloadable with empty limit/expiry (meta flag `_ebookstore_download_defaults` so later manual edits are kept).
- Sample content: `sample-content/books.json`, `generate.php` (PDF + 800×1200 cover via GD), `create-products.php` (idempotent). 6 products created (IDs 14–24), each Virtual + Downloadable + Sold individually, price, category, cover, PDF with unguessable name in `woocommerce_uploads/2026/10/`, download limit 5, expiry 30 days. PDFs validated (xref + 5 pages).
- Tests: shop lists 6 products; product page, category page, cart, checkout, my-account return 200; add to cart works; quantity input hidden; classic cart + classic checkout render; no shipping section; only US/GB countries.

### Open issues / decisions
1. **Classic vs block checkout** — classic chosen per plan (best compatibility with CURCY + dummy gateway). User asked to confirm.
2. **Taxes are off.** UK VAT / US sales tax on digital goods may be required — the client's business decision; can be enabled later in WooCommerce → Settings → Tax.
3. **Store address** set to US:NY (matches timezone) — only matters for tax; change in WooCommerce → Settings → General if needed.
4. **Deploy note:** the approved directory `file://C:/projects/...` is local only; on Hostinger the URL rule is fixed by search-replace, and the file:// rule must be re-added (WooCommerce → Settings → Products → Approved download directories) — add to DEPLOY.md.
5. Download protection of `woocommerce_uploads/` relies on `.htaccess` → only verifiable on Hostinger (PHP built-in server serves the folder). Force downloads + unguessable file names still apply locally.
6. WooCommerce created a "Refund and Returns Policy" draft page — kept for the client.

---

## Phase 3 — Theme, design and responsive pages (2026-10-03)

### User decisions (2026-10-03)
- Keep the **classic** cart/checkout.
- **Reviews on:** star rating (required) + written review; admin deletes reviews in Products → Reviews (built-in).
- From now on Claude only **commits**; the user pushes to GitHub.

### Done
- Storefront 4.6.2 installed as parent (still WooCommerce's official theme; "tested up to WP 6.7" in its header but updated Dec 2025 and works on WP 7.1.2 — no errors).
- Child theme `ebookstore-child` (style.css header, functions.php, front-page.php, assets/css/main.css, assets/fonts/) — activated. Storefront files untouched; everything via hooks/filters.
- Design system: all 12 color tokens as CSS variables in `:root`; Storefront Customizer CSS and Google Fonts removed.
- Fonts: Merriweather (headings) + Inter (body) **self-hosted** (variable woff2, Latin subset, preloaded, `font-display: swap`, system fallbacks) — no Google requests (privacy/UK GDPR + speed). OFL license note in `assets/fonts/LICENSE.txt`.
- WCAG AA contrast check (all pairs computed):
  - text on bg 13.8, text on surface 14.8, muted on bg 4.52 / surface 4.83, white on primary 11.5, white on primary-dark 14.9, gold on primary 4.75, primary-dark on gold 6.1, success/error on white 4.5/5.5 — all pass.
  - **White on accent fails (2.95)** → "Buy Now" uses accent background with **primary-dark text (5.03)**; hover = primary-dark background + white text (14.85) because accent-dark fails with both dark (3.65) and white (4.07) text.
  - Accent is never used as text color (2.95 on white). Prices use primary.
  - Form inputs use the `muted` border (4.8:1 ≥ 3:1 for UI controls); the `border` token (1.3:1) only on decorative card edges. Stars use accent-dark (4.07:1 ≥ 3:1); gold is too light for stars on white (2.4).
  - Visible focus ring everywhere (primary-dark on light, gold on dark areas).
- Header: store name, menu (Home / All eBooks / My Account — primary + mobile), cart; product search removed.
- Footer: name + tagline + support email (from `.env`), Shop links, Information links (Privacy/Terms — shown only when a **published** page exists at `LINK_PRIVACY_POLICY` / `LINK_TERMS`), social links (only if set in `.env`), © year + store name. Storefront credit removed.
- Home page (`front-page.php`, static page "Home"): hero (store name, `STORE_TAGLINE`, "Browse eBooks", 3 feature ticks) + responsive grid via `[products]`.
- Product cards everywhere (home/shop/category/related): 2:3 cover, title, author, price, short description, **View Details** + **Buy Now**.
- Product page: cover, title, author, price, short description, **Buy Now** (primary) + Add to Cart (secondary), delivery notes (instant PDF download, format, emailed link, secure checkout), Description + Reviews tabs. Sticky add-to-cart bar and prev/next arrows removed.
- **Buy Now** (mu-plugin, theme-independent): `?ebook-buy-now=<ID>` → adds to cart once (sold individually — no duplicate error), redirects to checkout; invalid ID → shop with English error notice. Tested.
- Reviews: enabled, rating required, "verified owner" label, **only verified buyers** can review.
- Images: catalog thumbnails cropped 2:3 (324×486), regenerated.
- mu-plugin: `blogdescription` follows new `STORE_TAGLINE`; WordPress emoji script disabled (no requests to s.w.org).
- Pages renamed: Shop → "All eBooks", My account → "My Account". Default sidebar widgets cleared.
- **Fixed:** WooCommerce had switched on **"Coming soon" mode** for store pages (visitors saw a placeholder instead of the shop) → `woocommerce_coming_soon=no`, `woocommerce_store_pages_only=no`.
- Responsive check with headless Edge at 375 / 768 / 1024 / 1440 (375 via exact-width iframe because desktop Edge windows can't go below ~500px): home, shop, product, my account. No horizontal scrolling, tap targets ≥ 44px, base font 16px. Fixed: Storefront header gap, flex offset in product form, breadcrumb spacing, menu alignment, card button wrapping.
- No external requests on the front end; no PHP errors.

### Open issues / decisions
1. **Reviews and guest buyers:** "verified buyers only" requires the buyer to be **logged in** — guest-checkout buyers cannot review unless they create an account at checkout. Option: allow anyone to review (with admin approval). Asked the user.
2. Privacy Policy / Terms links are hidden until those pages are written and published (legal text = client's job). Privacy Policy draft exists; no Terms page yet.
3. Product page cover fades in (WooCommerce gallery, 0.25s) — headless screenshots catch it mid-fade; real browsers are fine (verified with reduced-motion screenshot).
4. Twenty Twenty-Five kept as inactive fallback theme (WordPress recommends one default theme); can be removed in Phase 7.
5. New `.env` key: `STORE_TAGLINE`.
