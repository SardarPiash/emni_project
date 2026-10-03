# eBook Store — Progress Log

Plan file: `ebook_prompt.md` (native Windows version — no Docker / WSL).
Project root: `C:\projects\ebook-store`

| Phase | Name | Status |
|-------|------|--------|
| 0 | Environment check and project setup | ✅ Complete (2026-10-03) |
| 1 | WordPress install + `.env` configuration | ✅ Complete (2026-10-03) |
| 2 | WooCommerce core setup + sample eBooks | ✅ Complete (2026-10-03) |
| 3 | Theme, design and responsive pages | ✅ Complete (2026-10-03) |
| 4 | Cart, checkout and currency | ✅ Complete (2026-10-03) |
| 5 | Dummy payment gateway | ✅ Complete (2026-10-03) |
| 6 | Email delivery via SMTP | ✅ Complete (2026-10-03) — real Gmail SMTP tested |
| 7 | QA, security and Hostinger deployment guide | ✅ Complete (2026-10-03) — post-deploy checks run on Hostinger (DEPLOY.md §9) |

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

---

## Phase 4 — Cart, checkout and currency (2026-10-03)

### User decisions (2026-10-03)
- **Reviews:** anyone can review (no purchase/login needed); every review is held for admin approval; the reviewer sees "Your review is awaiting approval". Settings: `woocommerce_review_rating_verification_required=no`, `comment_moderation=1`, `comment_previously_approved=0`. Tested full cycle (submit → hidden → approve → visible → delete).
- **Privacy Policy + Terms & Conditions:** published with sample text (visible "Sample text: replace…" note at the top of each) → footer links now show. Pages: `/privacy-policy/` (#3, set as WP privacy page), `/terms/` (#32). Terms page is NOT set as WooCommerce "terms" page (no checkbox at checkout).
- **OPcache:** proposed (pages ~1.2 s → ~0.2 s locally, measured) but NOT approved — php.ini unchanged.

### Done
- CURCY 2.2.17 installed (checksums verified, declares HPOS + blocks compatibility).
- Currencies USD (default, rate 1) + GBP (rate 0.79). The mu-plugin forces currencies and the rate from `.env` (`STORE_BASE_CURRENCY`, `STORE_SECONDARY_CURRENCY`, `STORE_GBP_EXCHANGE_RATE`) via CURCY's `wmc_settings_args` filter; the stored option holds the same values.
- **Pay in selected currency** (`enable_multi_payment`, free). Verified with two temporary orders via the built-in Check payments gateway (enabled only for the test, then disabled; orders deleted): GBP order saved as **GBP 6.31** (= 7.99 × 0.79), USD order as **USD 7.99**; no address/phone stored.
- **Auto-detect** (free): GB → GBP, US → USD, others → USD (default), using WooCommerce geolocation (`geo_api=0`). Tested by simulating CURCY's country cookie (localhost has no real location).
- Floating CURCY sidebar/price switchers off; theme shows an accessible **USD $ / GBP £** pill switcher in the header (CURCY API, `aria-current`, 44px targets, label hidden on phones).
- Checkout simplified (mu-plugin `woocommerce_billing_fields`): **First name, Last name, Email, Country** only; email labelled "Email address" + hint "Your eBook will be sent to this email."; order notes off; heading "Your details". Country kept for payment gateways / fraud checks / future tax. Same fields on My Account → Addresses.
- **"You will be charged in GBP: £15.79"** row in checkout order review (updates with AJAX) and cart totals.
- Cart: product, price, **quantity "1"** (WooCommerce only outputs a hidden field for sold-individually items → theme filter shows it), subtotal, total; "Update cart" hidden; coupon kept.
- Styled cart, checkout, tables, payment box, Place order / Proceed to checkout (accent buttons). Fixed Storefront borders that relied on removed Customizer colours (dark bar in cart, header cart colour on cart page).
- Screenshots: checkout 1440 + 500, cart 1440 + 500 (phone frame can't keep the cart cookie → 500px real window used).

### Open issues / decisions
1. **Auto-detect privacy:** without a MaxMind key, WooCommerce geolocation may look up the visitor IP with an external service. Mentioned in the sample privacy text only generally — the client should review. Can be switched off in **Multi Currency** (admin menu) → Location.
2. On Hostinger, geolocation needs either a free MaxMind license key (WooCommerce → Settings → Integration) or keeps using WooCommerce's fallback lookup.
3. Exchange rate is fixed (from `.env`); automatic rate updates are a CURCY Pro feature.
4. Checkout shows "no payment methods" until the dummy gateway (Phase 5).
5. OPcache still off locally (slow pages on this PC only).

### Phase 4 follow-ups (user requests, 2026-10-03)
- **Currency switchers everywhere:** main switcher moved into the **navigation bar** (menu | USD/GBP | cart); compact "Show price in" switcher under the price on product pages, "Pay in" switcher in the cart totals box and the checkout order box. Phones: switcher + Menu button in the nav row; cart in the bottom bar.
- **IP auto-detect + manual switch both active** (auto on first visit: GB → GBP, US → USD, others USD; the visitor can always switch).
- **Exchange rate (new admin page WooCommerce → Exchange Rate)** — custom module `mu-plugins/ebook-store-core/exchange-rate.php` (CURCY free has no auto-update; Pro is paid):
  - Google has no public exchange-rate API (scraping breaks its terms and is unreliable) → **European Central Bank** daily reference rates (official, free, no key): `https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml`; USD→GBP computed from EUR cross rates.
  - Modes: **Automatic** (WP-Cron daily + "Update from ECB now" button) or **Manual** (admin enters the rate).
  - Safety: rate change > 15% in one update is rejected; if automatic updates fail for 4 days → manual rate → `STORE_GBP_EXCHANGE_RATE` (.env, now fallback only).
  - CURCY's stored rate is kept in sync; CURCY's settings page shows a notice pointing to the new page.
  - Tested: ECB fetch (1 USD = 0.757532 GBP, ECB 2026-10-02), manual 0.80 → £6.39, invalid/empty input rejected, back to automatic → £6.05, logged-out POST 400, missing nonce 403.
- Cart totals recalculated on every page load (header cart showed totals from the old rate).
- Phone nav fixes (Menu button icon), checkout switcher placement, link underline in content.

---

## Phase 5 — Dummy payment gateway (2026-10-03)

### Done
- Standalone plugin `public/wp-content/plugins/ebook-dummy-gateway/` (`ebook-dummy-gateway.php`, `includes/class-wc-gateway-ebook-dummy.php`, `README.md`), activated.
- Name "eBook Dummy Payment Gateway (TEST ONLY)"; checkout title "Test payment (TEST ONLY — no real money)"; extends `WC_Payment_Gateway`; classic checkout; declares HPOS compatible (blocks: not integrated).
- Customer chooses **Simulate successful payment** / **Simulate failed payment** (validated).
- Success → `payment_complete('DUMMY-…')` → virtual/downloadable order **Completed**, download permission granted, cart emptied, redirect to order-received page.
- Failure → status **Failed**, error "Payment failed. Your card was not charged…", **no download permission**; next attempt creates a new order (failed one stays on record).
- `DUMMY_GATEWAY_ENABLED=false` → gateway not registered at all (not at checkout, not in settings). Admin notice "Dummy payment gateway is active — disable before going live." while enabled.
- mu-plugin: downloaded file name = eBook title ("Python in 30 Days.pdf") instead of the stored random name.
- Styling: gateway choices, downloads table (accent button), order overview cards, success message.

### Tests (all passed)
- Order #35: Buy Now → GBP → success → **completed, GBP 15.14**, 1 download permission (limit 5, expiry 30 days → Nov 1, 2026). Thank-you page shows Downloads table; download returns `application/pdf`, identical to the source PDF; tampered key → 404.
- Order #36: USD → fail → **failed, USD 7.99**, 0 download permissions, error notice shown.
- `.env` switch false → gateway gone from checkout, settings and admin notice; true → back.
- Plugin deactivated → home, shop, product, cart, account, order-received all 200, no PHP errors; reactivated.
- Test orders #35 (success.buyer@example.com) and #36 (failed.buyer@example.com) kept as examples — can be deleted in WooCommerce → Orders.

### Open issues / decisions
1. **Emails fail to send** ("Could not instantiate mail function") — no mail server on this PC yet. Phase 6 sets up SMTP. WooCommerce attempted: Completed order + New order (success), Failed order (failure).
2. WordPress automatic background updates are on (core minor/security updates ran once) — fine; noted for Phase 7.

---

## Phase 6 — Email delivery via SMTP (2026-10-03, in progress)

### Done
- WP Mail SMTP 4.10.0 (email log is Pro-only) + WP Mail Logging 1.17.0 installed, checksums verified.
- WP Mail SMTP is configured **only** through the `WPMS_*` constants in `wp-config.php` (all values from `.env`); verified every setting reports "from constant", **no password in the database**. Mailer SMTP, from address/name forced, return-path set.
- WooCommerce emails: brand colours (base #1F3A5F, background #FAF7F2, body #FFFFFF, text #1F2933, footer #6B7280), footer `{site_title}` + `{site_url}` + "Questions? Email us at <STORE_SUPPORT_EMAIL>".
- Completed order (the download email): subject "Your eBook from {site_title} is ready to download", heading "Your eBook is ready", Downloads table with link + expiry, closing text. Failed order (customer): "No money was taken…" text, no download links.
- mu-plugin: WooCommerce sender name/address follow `MAIL_FROM_NAME` / `MAIL_FROM_EMAIL`; `admin_email` follows the new **`STORE_ADMIN_EMAIL`** (store notifications); customer emails have `Reply-to: <STORE_SUPPORT_EMAIL>`.
- WP Mail Logging: logs auto-deleted after 30 days (they contain download links).
- Test purchases #38 (success, USD) and #39 (fail, GBP) → 4 emails generated and logged correctly: customer "ready to download" with 1 download link; admin "new order"; customer "unsuccessful" with 0 download links; admin "order failed". Rendered and checked visually. Sending failed with "SMTP Error: Could not authenticate" (placeholder credentials) — proves the SMTP path is used.
- Mailpit not needed: the email log shows every email locally.

### Waiting for the user
- Fill real SMTP values in `.env` (Gmail App Password now, or Hostinger email later) + real `STORE_ADMIN_EMAIL` / `STORE_SUPPORT_EMAIL`. Then: test email + real purchase with own address.

---

## Phase 7 — QA, security and Hostinger deployment guide (2026-10-03)

### End-to-end tests (all passed)
- Orders: USD success #38 (completed), USD fail #36 (failed), GBP success #35 (completed), GBP fail #39 (failed) — all guest checkout; #40 success **with "Create an account"** (customer logged in automatically, eBook listed in My Account → Downloads, billing form only 4 fields); #42 regression purchase after hardening.
- Downloads: 5 downloads OK, 6th → 403 "Sorry, you have reached your download limit for this file"; expired access → 403 "Sorry, this download has expired"; tampered key / wrong order key → 404. File served as "Python in 30 Days.pdf", byte-identical to the source.
- Responsive: home 375/768/1024/1440, shop 768, product 375/1440, cart 500/1440, checkout 500/1440, order received 500/1024, My Account 500, Terms 375. Legal page headings reduced.
- Language: rendered pages + database (posts, meta, options, comments, terms, email log) scanned — 0 Bengali characters, no lorem/placeholder/dummy text, `<html lang="en-US">`. Only intentional text: "Sample text: replace…" notes on Privacy/Terms (user decision) and "SAMPLE EBOOK" on sample covers.

### Security
- Admin login name no longer public: REST `/wp/v2/users` hidden for visitors (admins with nonce still 200), author archives + `?author=N` → 301 home, admin display name "Store Admin". Verified login name appears nowhere in public HTML.
- XML-RPC disabled (405 "services are disabled"), X-Pingback + RSD removed; WordPress/WooCommerce generator tags removed.
- Debug log moved **outside the web root**: `WP_DEBUG_LOG=true` now writes `../logs/debug.log` (`/logs/` git-ignored); old URL 404.
- `.htaccess` (Hostinger): `Options -Indexes`; deny `wp-config.php`, `xmlrpc.php`, `readme.html`, `license.txt`, `wp-config-sample.php`, `*.log`, `*.sql(.gz)`, `*.bak`, `*.swp`; existing `.env` deny rule.
- Avatars off (no Gravatar requests). WooCommerce **Order Attribution** off (it set `sbjs_*` tracking cookies on every visitor — consent issue under UK PECR/GDPR; privacy text says essential cookies only). New visitors now get only `wmc_current_currency`.
- `DISALLOW_FILE_EDIT=true`; `.env` outside web root (404); admin user not "admin"; strong generated passwords (admin 20, DB 32, salts 64 chars).
- Everything up to date: WordPress 7.1.2, WooCommerce 11.1.2, CURCY 2.2.17, WP Mail SMTP 4.10.0, WP Mail Logging 1.17.0, Storefront 4.6.2 (`wp core verify-checksums` + `wp plugin verify-checksums --all` pass).
- Local-only quirk: PHP's built-in server executes `/../config/env-loader.php` (no output, no secrets); Apache/LiteSpeed on Hostinger normalises `../` — in DEPLOY.md post-deploy checks.

### Performance
- Pages ≈ 490–580 KB text uncompressed (≈ ¼ with Hostinger gzip). Removed CURCY's unused switcher CSS + flag sprites and the Order Attribution script. Fonts self-hosted + preloaded. Images: 2:3 covers ~60 KB JPEG; WordPress generates sized versions. OPcache is on at Hostinger (off on this PC — user has not approved enabling it).

### Deployment
- `bin/export-db.ps1`: MariaDB-compatible dump to `backups/` (git-ignored): `--set-gtid-purged=OFF` (MySQL GTID line would fail on Hostinger), `--no-tablespaces`, utf8mb4, `--result-file` (PowerShell 5 piping would corrupt UTF-8/add BOM). Verified: 54 tables, 0 GTID lines, 0 `utf8mb4_0900`, all tables `utf8mb4_unicode_520_ci` (MariaDB-compatible), £ and — intact, no BOM. (Not test-imported into MariaDB — none installed locally.)
- `DEPLOY.md`: folder layout, PHP 8.3 + options, DB, export, zip, production `.env` table, upload, phpMyAdmin import, search-replace (SSH or plugin), approved download directory, SSL, first-login settings, email + DNS, MaxMind, LiteSpeed Cache + currency cookie, cron, pre-launch checklist, post-deploy checks, removing the dummy gateway, updating code later (never overwrite the live DB), rebuilding locally from git.
- Final `.gitignore` check: `.env`, `.env.production`, `backups/`, `logs/`, uploads, core and third-party plugins/themes ignored; no secret found in any tracked file.

### Open items for the user
1. ~~Phase 6 real send test~~ — done 2026-10-03 (Gmail SMTP).
2. Sample content (6 eBooks, test orders #35–#42, test customer `account.tester`) — keep for now or delete? (asked)
3. Before launch (DEPLOY.md §8.6): real Privacy/Terms text, real eBooks, taxes decision, store address, real payment gateway, then `DUMMY_GATEWAY_ENABLED=false`.
4. Optional: enable OPcache locally (pages ~1.2 s → ~0.2 s).

---

## Final summary (2026-10-03)

Working locally at `http://localhost:8080` (native Windows: PHP 8.3, MySQL 9.7, WP-CLI `wp server`):

- English eBook store: WordPress 7.1.2 + WooCommerce 11.1.2 (HPOS), Storefront + `ebookstore-child` design (brand tokens, self-hosted fonts, WCAG AA), responsive 1/2/3–4 columns.
- Products: Virtual + Downloadable + Sold individually PDFs; Buy Now → checkout; reviews (rating required, admin approval).
- USD/GBP via CURCY: switchers in nav bar, product page, cart, checkout; IP auto-detect; pay in selected currency; exchange rate from the ECB daily or manual (WooCommerce → Exchange Rate), `.env` fallback.
- Checkout: first name, last name, email, country; "You will be charged in …" notice.
- Delivery: download on thank-you page + My Account + email link; limit 5 / 30 days from `.env`; failed orders get nothing.
- Dummy gateway plugin (TEST ONLY, removable, `.env` switch).
- Email via WP Mail SMTP (constants from `.env`), WP Mail Logging, branded templates.
- All secrets/changeable values in `.env`; custom code only in the child theme, the mu-plugin (+ `ebook-store-core/exchange-rate.php`) and the dummy gateway.
- Ready for Hostinger via `DEPLOY.md`.

### Phase 6 — real SMTP test (2026-10-03)
- User added a Gmail App Password; Claude corrected the remaining `.env` lines for Gmail (`SMTP_HOST=smtp.gmail.com`, `SMTP_PORT=587`, `SMTP_ENCRYPTION=tls`, `MAIL_FROM_EMAIL`, `STORE_SUPPORT_EMAIL`, `STORE_ADMIN_EMAIL` = the Gmail address) and removed the spaces from the App Password (16 letters) — password never displayed.
- Sent via Gmail SMTP: test email; order #43 (GBP, success) → "Your eBook from eBook Store is ready to download" with 1 download link + admin "new order"; order #44 (USD, fail) → "Your order … was unsuccessful" (0 download links) + admin "order failed". All 5 logged as **sent**.
- For Hostinger later: switch the SMTP lines to the Hostinger mailbox (DEPLOY.md §3c).
- 2026-10-03 (user request): product images removed from all emails (`woocommerce_email_order_items_args` / `woocommerce_email_fulfillment_items_args` → `show_image=false` in the mu-plugin). Verified with order #45: 0 images, download link still present, sent via Gmail.
- 2026-10-03 (user request): shop toolbar — removed the duplicate sorting dropdown + result count below the grid (Storefront adds both above and below); redesigned the top toolbar (white bar, result count left, "Sort by" + styled dropdown right, stacks on phones); smaller gap under page titles on shop/cart/checkout/account. Pagination below the grid kept (shows only with > 12 eBooks).

---

# UI Redesign (ui_redesign_prompt.md — Phases 8–12)

| Phase | Name | Status |
|-------|------|--------|
| 8 | Impact analysis, research, design proposal | ✅ Complete (2026-10-03) — approved by user |
| 9 | Design refresh and homepage layout | ✅ Complete (2026-10-03) |
| 10 | Hero banner / carousel with admin | ✅ Complete (2026-10-03) |
| 11 | Demo catalog (50) + homepage sections | ✅ Complete (2026-10-03) |
| 12 | Regression test, polish, deploy notes | ✅ Complete (2026-10-03) |
| 13 | User feedback: side banners, carousel controls, shop page, category control | ✅ Complete (2026-10-03) — merged into main |
| 14 | Header and navigation reorganised | ✅ Complete (2026-10-03) — branch `feature/navigation`, waiting for review |

## Phase 8 — summary (2026-10-03)
- Verdict: safe if the safeguards are kept. `ebook-dummy-gateway` untouched; checkout/currency/env/email/download logic in the mu-plugin unchanged; redesign = child theme; new admin features = new files in `mu-plugins/ebook-store-core/`.
- **User decisions (all recommendations approved):** custom "Hero Slides" (not a slider plugin); keep palette + fonts, add `--color-primary-soft #E8EEF5` (10.1:1 with primary) and `--color-accent-soft #FBE9E3` (12.5:1 with primary-dark) + spacing scale + 2 shadow levels; the existing 6 sample eBooks become demo (`_ebookstore_demo`); homepage section settings on an admin page; search box back in the header; no carousel library (vanilla JS; Swiper heavy, Splide unmaintained since 2022; Embla only as fallback); visible pause button on the carousel (WCAG 2.2.2); Bestsellers on the live site must use real sales only.
- Design research: Stripe Press, Leanpub, Penguin UK, Payhip, bookstore UX case study (inspiration only).
- Images: Unsplash + Pexels licences verified 2026-10-03 (free commercial use, attribution optional; no unaltered resale, no implied endorsement, avoid identifiable people/brands) → `IMAGE_CREDITS.md`. Covers: generated originals only.
- **Live site finding:** ebookstore.tech runs a different build (fse-book-store theme + Elementor, 6 products, no currency switcher) — none of this project is live. Ask again before any deployment (Phase 12).
- Safety (done at Phase 9 start): tag `before-ui-redesign` (010c5af), branch `feature/ui-redesign`, DB backup `backups/before-ui-redesign.sql` (via `bin/export-db.ps1`; `wp db export` fails on this PC). Rollback: `git checkout main` + import that file.

## Phase 9 — Design refresh and homepage layout (2026-10-03)

### Done (child theme only — mu-plugin and dummy gateway unchanged)
- Safety first: tag `before-ui-redesign`, branch `feature/ui-redesign`, DB backup `backups/before-ui-redesign.sql`.
- Design tokens added to `main.css` `:root`: `--color-primary-soft`, `--color-accent-soft`, spacing scale `--space-1…8`, `--shadow-1/2`, `--ease`. Palette and fonts unchanged.
- New theme structure: `inc/icons.php` (inline SVG set), `inc/assets.php` (enqueue `assets/css/components.css` 9.6 KB + `assets/js/site.js` 2.4 KB, deferred, no library), `inc/header.php`, `inc/trust.php`, `inc/badges.php`, `template-parts/hero-default.php`, `woocommerce/product-searchform.php` (template override).
- **Header:** product search box (rounded, icon button; full width on phones), cart = bag icon + count badge + subtotal (replaces Storefront's pluggable `storefront_cart_link`, so AJAX fragments still update it); menu + USD/GBP switcher unchanged.
- **Hero:** two columns (text + stack of the 3 latest covers; covers hidden < 900px), "Browse eBooks" + "View all"; saved as `template-parts/hero-default.php` = Phase 10 fallback.
- **Trust elements:** icon row (Instant PDF download · Secure checkout · Delivered to your email · Read on any device) — band under the hero, compact on product page (replaces the text list), under "Proceed to checkout" and under "Place order"; footer trust line.
- **Product cards:** badge over the cover (Bestseller = top 3 by real WooCommerce sales count with ≥ 1 sale, cached 1 h, refreshed on completed/processing orders; New = < 30 days), stronger hover lift/shadow, 2-line title / 3-line excerpt clamp, button press feedback. Product page: badge above the title, sticky cover on desktop.
- **Animations:** reveal-on-scroll (IntersectionObserver; content visible without JS; 2.5 s safety net), card/button/hero-cover hovers, cart badge pulse after "added to cart"; all off with `prefers-reduced-motion`; nothing animated on cart/checkout.
- Screenshots: home 1440 + 375, product, cart, checkout 1440 (reduced-motion run to see final state).

### Regression (all passed)
- #49 GBP success → completed, PDF download 200 `application/pdf` "Mindful Mornings.pdf" identical to source, limit 5 / expiry 30 days; email "ready to download" with 1 link sent via Gmail + admin new order.
- #50 USD failure → failed, 0 download permissions; "unsuccessful" email (0 links) + admin failed order.
- Currency switch + "You will be charged in GBP: £6.05" on cart and checkout; checkout fields = first name, last name, email, country.
- `ebook-dummy-gateway`: 0 files changed. No PHP errors.

### Notes
- Old log lines (`get_order_currency` deprecated) came from a WP-CLI order listing on 2026-10-02, not from the site; log cleared.
- Rollback: `git checkout main` + import `backups/before-ui-redesign.sql`.

## Phase 10 — Hero banner / carousel with admin upload (2026-10-03)

### Done
- **Admin (mu-plugin `ebook-store-core/hero-slides.php` + `assets/hero-slides-admin.js`):** private post type `ebookstore_slide` (no public URL, not searchable, no REST) → menu **Hero Slides**. Edit screen "Slide content": desktop image (required, 1600×600 recommended), optional mobile image (800×1000), heading, subheading, button text, button link (`/path`, `#anchor` or https URL), "Show this slide on the website"; order = "Order" box; Media Library pickers; plain-English help on list + edit screens; warning when a shown slide has no image. List columns: image, name, heading, order, On website (Shown/Hidden); sorted like the site. Saving: nonce + `edit_post` capability, images validated as attachments, text sanitized and length-limited.
- **Settings** (Hero Slides → Settings): "Change slide every N seconds" (0–30, 0 = no autoplay); empty = `.env` **`HERO_AUTOPLAY_SECONDS`** (new key, default 6).
- **Front end (child theme):** `inc/hero.php`, `template-parts/hero-carousel.php`, `assets/css/carousel.css`, `assets/js/hero-carousel.js` (vanilla). Full-width hero; 1 shown slide = static banner (no JS); ≥ 2 = carousel with arrows, dots, swipe, keyboard (←/→), autoplay with **visible pause button**, pause on hover/focus and hidden tab, no autoplay with reduced motion; WAI-ARIA carousel roles, hidden slides `inert` + `aria-hidden`, live region only for user actions. No slides → `template-parts/hero-default.php` (Phase 9 hero). Store name stays the page H1 (visually hidden over the carousel).
- **Performance:** first slide eager + `fetchpriority="high"` + `<link rel=preload>` (separate mobile/desktop), other slides lazy; aspect-ratio reserves space (no layout shift); carousel CSS/JS load only on the home page and only when needed.
- **Starter slides:** `wp ebookstore seed-slides` (safe to run twice) → 3 slides (#53, #56, #59) from `sample-content/hero/*.webp` (desktop 15–127 KB, mobile 11–123 KB). Photos: Unsplash License (verified, not Unsplash+), no people/brands/readable real titles → **`IMAGE_CREDITS.md`**. Several candidates were rejected (Unsplash+ licence, readable "Harry Potter", religious titles, branded planner).

### Tests (all passed)
- No slides → default hero; 3 slides → carousel; seeding twice → no duplicates.
- Autoplay over simulated time: 2 s → slide 1, 7 s → 2, 13 s → 3, 19 s → 1; dots follow; pause button visible.
- Admin as `ebook_manager`: list/help/columns, edit screen with Media pickers; hide slide via edit form → 2 slides; hide another → 1 slide = static (no controls, no JS); show again → 3; settings 4 s → `data-autoplay="4"`, empty → 6; all fields preserved through saves. Security: no nonce 403, logged out 400, slide public URL 404.
- Regression: #60 USD success → completed + PDF + "ready to download" email (1 link); #61 GBP fail → failed, 0 downloads, "unsuccessful" email; dummy gateway 0 changes; no PHP errors.

### Notes
- Button links are stored as full URLs (`http://localhost:8080/shop/`) → fixed by the deploy search-replace (add to DEPLOY.md in Phase 12).

## Phase 11 — Demo catalog (50 products) and homepage sections (2026-10-03)

### Done
- **`wp ebookstore seed-demo`** (mu-plugin `ebook-store-core/demo-data.php`, loaded only in WP-CLI): 44 new fictional eBooks from `sample-content/demo-books.json` + the 6 original samples tagged as demo = **50 demo eBooks** in 8 categories (Business 6, Productivity 7, Technology 7, Fiction 7, Wellness 6, Finance 6, Cooking 6, Travel 5). Each: Virtual, Downloadable, Sold individually, $5.99–$21.99, short + long English description, generated original 2:3 WebP cover (3 layouts × category colours, avg 14 KB), own demo PDF in `woocommerce_uploads/demo/` (unguessable name), 9 Featured, publish dates spread over 240 days (11 "New"), demo `total_sales` 3–480. Everything tagged `_ebookstore_demo` (products, covers, categories); PDF paths stored in `_ebookstore_demo_files`. Safe to run twice; refuses on `APP_ENV=production` without `--force`. "The Art of Short Stories" moved Writing → Fiction; empty "Writing" category removed. Three titles renamed to avoid similarity with real books.
- **`wp ebookstore remove-demo [--yes] [--include-ordered]`**: deletes only demo-tagged eBooks, their demo covers, PDFs (only inside `woocommerce_uploads`) and empty demo categories; never touches orders; by default keeps demo eBooks that appear in **any** order.
  - **Bug found and fixed during testing:** the first version checked `wc_order_product_lookup`, which ignores failed/pending orders, so "Everyday Home Cooking" (only in a failed order) was deleted. Now checks order line items of all statuses. The book was recreated (`sample-content/create-products.php`, new ID #239) and re-tagged; orders and their line items were never affected (14 orders / 14 items before and after).
- **Home page sections** (child theme `inc/sections.php`, `template-parts/sections/{products,categories,promo}.php`): Bestsellers (`best_selling`), Editor's Picks (featured), Browse by Category (8 cards with newest cover + count), New Arrivals, promotional strip; each with "View all" (shop `?orderby=popularity`, `?ebook_list=featured`, `?orderby=date`, shop). Grids on desktop; product rows swipe horizontally on phones (CSS scroll-snap, no JS); category cards 2 per row on phones. First visible section gets `id="ebooks"` (hero button target). All sections off → fallback "Our eBooks" grid.
- **Admin: Home Page → Sections** (mu-plugin `ebook-store-core/homepage-sections.php`): show/hide, title, number of items (4/8/12) per section, promo title/text/button. Menu renamed **Home Page** (Hero Slides, Add New Slide, Slideshow Settings, Sections).
- **Shop for 50+ eBooks:** category filter dropdown in the toolbar (navigates on change; `<noscript>` Go button), "Showing 1–12 of 50 results", 5 pages, sorting; Editor's Picks list (`?ebook_list=featured`, title from the section setting).
- Badges moved to the top-right of the cover (no overlap with cover text); section focus ring removed; promo strip sits on the footer.

### Tests (all passed)
- Seed twice → 44 skipped. 50 published / 50 demo / 50 covers / 50 PDFs on disk.
- Home: 5 sections in order, correct products per section, category counts; shop page 1 = 12 cards, page 5 = 2; Travel = 5; Editor's Picks = 9; filter pre-selects the current category; no-JS `?product_cat=finance` = 6.
- Sections admin as `ebook_manager`: hide/rename/count/promo text (HTML stripped), all off → fallback grid, restore defaults; no nonce 403, logged out 400.
- remove-demo (with DB backup `backups/before-remove-demo-test.sql`) → see bug above; re-seed restored everything.
- Regression: #240 GBP success with a new demo eBook (Slow Travel Europe) → completed + PDF + email (1 link); #241 USD fail → failed, 0 downloads, "unsuccessful" email; checkout fields unchanged; dummy gateway 0 changes; no PHP errors.

### ⚠️ Before going live
Demo sales counts (and the "Bestseller" badges/section they drive) are **fake**. Run `wp ebookstore remove-demo` (or get the client's written approval) before deploying, and never import this local database into the live site with demo data in it.

## Phase 12 — Regression, performance, accessibility, deploy notes (2026-10-03)

### Full regression (all passed)
| Order | Currency | Payment | Result |
|-------|----------|---------|--------|
| #242 | USD | success | completed, PDF download 200, "ready to download" email (1 link) + admin email |
| #243 | USD | fail | failed, 0 download permissions, "unsuccessful" email |
| #244 | GBP (£5.30) | success | completed, "You will be charged in GBP" notice, download + email |
| #245 | GBP | fail | failed, 0 downloads |
- Checkout fields still first name, last name, email, country; `.env` settings and Gmail SMTP working; download limit 5 / expiry 30 days unchanged.
- `ebook-dummy-gateway`: 0 lines changed against `main`. PHP error log empty. Browser console: no errors (only jQuery Migrate's info line, from WordPress).

### Performance
- Our own front-end files total about 72 KB uncompressed (CSS + JS, no libraries). The carousel CSS/JS load only on the home page.
- **Fixed:** the WooCommerce product covers on the home page all loaded immediately. The new `inc/performance.php` makes covers lazy except the first row on shop/category/search pages. Result: home 1 eager image (hero slide) / 22 lazy; listings 4 eager; product page 1 eager (main cover), related eBooks lazy.

### Accessibility
- Static check script (lang, alt text, accessible names for links/buttons, form labels, duplicate ids, one h1, heading order) on home, shop, product, category, cart, checkout, my account and terms: all OK.
- **Fixed:** checkout heading jump h1 → h3. A visually hidden h2 "Checkout steps" was added (`functions.php`).
- Carousel: keyboard, visible pause button, reduced-motion support and `inert` hidden slides were already covered in Phase 10.

### Responsive check (375 / 768 / 1024 / 1440)
- **Fixed:** at 1024px product grids showed 3 cards with an orphan. They now show 4 per row from 1024px (`components.css`).
- **Fixed:** at 768px the hero banner was too short for its text. It is taller on tablets now (`carousel.css`).
- **Fixed:** at 768–1023px the two checkout columns were too narrow (the trust row wrapped word by word). They are stacked on tablets now (`main.css`).
- Shop, product page and checkout at 375px, and home at 768/1024/1440, are correct.

### Docs
- `DEPLOY.md`: new section 13. It covers what to deploy, the Home Page admin for the owner, removing the demo data before launch (fake sales!), slide links fixed by the search-replace, `HERO_AUTOPLAY_SECONDS`, the ebookstore.tech note and rollback. Section 8.6 checklist and section 11 table updated.
- `PLUGINS.md`: our own code list updated. No new third-party plugins or libraries.

### Open decisions for the user
- Merge `feature/ui-redesign` into `main`? (Not merged, not pushed.)
- Replace the current ebookstore.tech build with this project? (Not touched.)

## Phase 13 — Home banners, carousel controls, shop page, category control (2026-10-03, user feedback)

### Requested by the user
The home banner area should look more attractive, with 2–3 separate promotion banners. The carousel buttons should look better, and the pause/play button is not wanted. On All eBooks, pagination should be at the bottom and the page layout improved. The admin should be able to manage Browse by Category. Chosen options: "slider + side banners" and "admin picks categories".

### Done
- **Side promo banners** (new mu-plugin file `ebook-store-core/promo-banners.php`, admin **Home Page → Side Banners**). There are 2 banners. Each has: show/hide, small label, title, text, button text, link, colour (navy/coral/gold/cream, all ≥ 4.5:1 text contrast), and either 2 book covers from a list (Editor's Picks / Newest / Bestsellers / none) or an uploaded image. Links are stored relative (`/shop/…`), so no search-replace is needed after a domain move. Saving needs a nonce and `edit_pages`; text is sanitized and length-limited; `javascript:` links are dropped; unknown colours fall back to the default.
- **Home hero layout** (`inc/hero.php`, `template-parts/promo-banners.php`, `components.css`):
  - **Desktop:** slider (2/3) + 2 stacked banners (1/3), same height.
  - **Tablet:** slider, then 2 banners side by side.
  - **Phone:** stacked.
  - No slides → default hero with the banners in a row below it.
- **Carousel controls** (`hero-carousel.php/.js`, `carousel.css`): the slider is now contained with rounded corners (no longer full-bleed). It has round white arrows on the left/right edges (shown on hover/focus on desktop, hidden on touch screens, where you swipe) and small bar dots centred at the bottom. **Pause button removed** at the user's request. Autoplay still pauses on hover/keyboard focus/hidden tab, never runs with reduced motion, and can be set to 0 in Slideshow Settings. Note: WCAG 2.2.2 expects a pause control for auto-moving content; this is a deliberate client decision.
- **All eBooks / category / search pages** (new `inc/shop.php`):
  - **Layout:** desktop has a left sidebar card (Categories with counts, current one highlighted; Collections: Bestsellers, Editor's Picks, New Arrivals) and 3 eBooks per row. On phones/tablets the categories become a swipeable chip row, with the current chip scrolled into view.
  - **Toolbar:** result count + Sort by.
  - **Pagination:** removed above the grid (Storefront hook); below the grid it is centred, with square buttons and "← Previous / Next →" (arrows only on small phones).
  - The old category dropdown (and its JS) was removed, since the sidebar replaces it.
- **Browse by Category control** (Sections page): tick categories and give them positions 1, 2, 3 …; none ticked = automatic (the biggest categories). The card picture is the category's own image (Products → Categories → Thumbnail), else the newest cover. Empty categories are hidden.
- Slide image advice updated to 1600 × 900 (slider is narrower now).

### Tests (all passed)
- Admin as `ebook_manager`:
  - The Side Banners page has 2 boxes, image pickers and the menu order Hero Slides, Slideshow Settings, Side Banners, Sections.
  - Saves work: gold/bestsellers/relative link → correct class + full URL; HTML stripped from the title; hidden banner not shown.
  - Bad colour → default; `javascript:` link → banner without link; image → image variant; invalid image ID ignored.
  - Without a nonce the save returns 403; logged out it returns 400.
- Sections: Travel(1)/Fiction(2)/Cooking(3) → home shows exactly that order; all unticked → automatic; a category thumbnail is used when set. Test data reset afterwards.
- Screenshots at 1440/1024/768/375 (home, shop, shop page 5, category on phone).
- Regression:
  - #246 USD success and #247 GBP success: completed + PDF download + "ready to download" email (1 link each).
  - #248 USD fail and #249 GBP fail: failed, 0 downloads, "unsuccessful" emails.
  - Charge notices are correct. The accessibility script passes on all 8 pages. PHP log empty. `ebook-dummy-gateway`: 0 lines changed.

## Phase 14 — Header and navigation reorganised (2026-10-03, branch `feature/navigation`)

### Done
- **Desktop:**
  - The top bar has the store name on the left, the search centred, and on the right **Sign in / My Account** (person icon) and the cart (bag, count, subtotal).
  - Below it is a darker navigation strip: **Home · All eBooks ▾ · Bestsellers · New Arrivals** on the left and the compact currency pill on the right.
  - The active/hover link gets a gold bar on its bottom edge.
  - "All eBooks ▾" opens a white dropdown card with "All eBooks" plus every category in 2 columns. It is generated automatically (new categories appear by themselves). If the admin adds their own sub-items under All eBooks in Appearance → Menus, those are used instead.
- **Phones:**
  - The top row has the store name, an account icon and a cart icon with its count; search is full width below.
  - The strip has the **☰ Menu** button on the left and USD/GBP on the right. The menu opens full width below, with categories under All eBooks.
  - Storefront's bottom bar (account + cart) and its script are removed, because both are in the header now.
- **Menu (Appearance → Menus → Main Menu):** "My Account" removed (now the header icon). Added "Bestsellers" (`/shop/?orderby=popularity`) and "New Arrivals" (`/shop/?orderby=date`) as relative links, highlighted when that list is open (All eBooks is then not highlighted).
- **Code:**
  - New `assets/css/header.css`.
  - `inc/header.php`: account/cart group, category dropdown, menu highlight, script dequeue.
  - `user` icon added.
  - Old Phase 4 header/nav layout rules removed from `main.css`.

### Tests (all passed)
- Screenshots at 1440/1024/768/375, plus the dropdown open (desktop) and the menu open (phone).
- Label "Sign in" when logged out and "My Account" when logged in. The AJAX add-to-cart fragment updates the header count (1). On a category page the category is highlighted in the dropdown.
- Accessibility script OK on all 8 pages.
- Purchases: GBP success → completed + PDF; USD fail → failed. PHP log empty. Dummy gateway: 0 changes.

### Note for deployment
The menu items live in the database. On a first launch they come with the DB import. For a code-only update of a live site, edit **Appearance → Menus** by hand: remove My Account, add Bestsellers `/shop/?orderby=popularity` and New Arrivals `/shop/?orderby=date`.
