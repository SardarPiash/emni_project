# eBook Store — Implementation Plan for Claude Code

## 0. How to use this plan (READ FIRST)

You are Claude Code, building a WordPress + WooCommerce eBook store for local development on a Windows 10 machine using **Docker (WSL2)**. The site will later be deployed by the user to **Hostinger** (shared/WordPress hosting, no Docker on the server).

### Working rules

1. Work **one phase at a time**. Never start the next phase until the user types **"next"** (or clearly asks for it).
2. At the end of every phase:
   - Start (or restart) the local site so the user can test it (see Section 4 "Run the project").
   - Give a **phase report** using the template in Section 9.
   - Update `PROGRESS.md` (phase status, what was done, open issues).
   - Then STOP and wait.
3. Write phase reports and questions to the user in **Bangla**. Code, file names, comments and commit messages stay in English.
   **The website itself is 100% English**: all front-end text, product content, buttons, notices, checkout labels, emails and admin settings must be in English (US). Never put Bangla text on the website.
4. If anything is unclear or a decision would change the scope, ask the user before doing it.
5. If a session restarts, read `PLAN.md` and `PROGRESS.md` first and continue from the last unfinished phase.
6. Commit to git at the end of each phase: `git commit -m "Phase N: <summary>"`.

### Always use up-to-date versions

- Before installing anything, use web search / web fetch to confirm the **current stable** versions of: WordPress, WooCommerce, every plugin listed in this plan, and the Docker images used.
- **Match Hostinger's environment.** Research (and confirm with the user from their hPanel) which PHP versions Hostinger currently offers and which MySQL version its databases run. Pin those exact versions in `.env` (`PHP_VERSION`, `MYSQL_VERSION`) and use them in Docker. Choose the newest PHP version that is both offered by Hostinger and recommended by WordPress.
- Never use the `latest` tag for any Docker image. Always pin explicit versions.
- Check that every plugin is compatible with the current WooCommerce version, **HPOS** (High-Performance Order Storage), the current WordPress version and the chosen PHP version. If a plugin is abandoned, incompatible, or has known security issues, propose a maintained alternative to the user before using it.
- After install, run `wp core version`, `wp plugin list` and `wp theme list`, and include the versions in the phase report.

### Code rules (very important)

- **Never edit WordPress core, WooCommerce or any third-party plugin/theme files.** They are overwritten on update and cause conflicts.
- All customizations go only into:
  - the child theme: `public/wp-content/themes/ebookstore-child/`
  - the must-use plugin: `public/wp-content/mu-plugins/ebook-store-core.php` (plus a folder `mu-plugins/ebook-store-core/` if it grows)
  - the separate dummy gateway plugin: `public/wp-content/plugins/ebook-dummy-gateway/`
- Use WordPress hooks/filters and WooCommerce template overrides (copied into the child theme) only.
- **Prefer built-in WordPress/WooCommerce features and well-maintained free plugins.** Write custom code only when no suitable built-in feature or plugin exists, and explain why in the phase report.
- Follow WordPress coding standards, escape all output, sanitize all input, use nonces for any forms.

---

## 1. Project summary

A simple, responsive eBook store for customers in the **USA and UK**.

- WordPress (latest stable) + WooCommerce (latest stable)
- Database: **MySQL** (same version as Hostinger)
- Local development: **Docker Compose** (versions matched to Hostinger)
- Website language: **English (en_US)** for all pages, emails and notices
- eBooks are **Virtual + Downloadable** products (PDF)
- Prices shown in **USD and GBP**
- **Dummy payment gateway** for testing (success / fail), kept fully separate so it can be removed when the client installs a real gateway
- Download link emailed to the customer **via real SMTP email** after successful payment only
- Standard WordPress/WooCommerce admin (no custom admin dashboard)

### Out of scope

- Custom real payment gateway
- Installing/configuring a real payment gateway (client will do this later)
- Custom admin dashboard

---

## 2. Environment (Windows 10 + Docker)

### Host requirements

- Windows 10 version 2004 or later (build 19041+), virtualization enabled in BIOS
- At least 8 GB RAM recommended
- **WSL2** with an Ubuntu distribution (`wsl --install -d Ubuntu`, run by the user in an admin PowerShell)
- **Docker Desktop** with the WSL2 backend and WSL integration enabled for Ubuntu (`winget install Docker.DockerDesktop`, run by the user)
- **Git** inside Ubuntu (`sudo apt install git`)

Steps that need admin rights, a reboot or a GUI (installing WSL2, Docker Desktop) must be **explained to the user step by step** for them to run; do not try to force them.

### Where the project lives

For good file performance, the project must live in the **WSL2 Linux filesystem**, e.g. `~/projects/ebook-store` (accessible from Windows at `\\wsl$\Ubuntu\home\<user>\projects\ebook-store`), **not** on `C:\`. Run Claude Code and all commands from an Ubuntu (WSL2) terminal inside that folder.

### Containers (`docker-compose.yml`)

| Service | Image | Purpose |
|---------|-------|---------|
| `wordpress` | custom `Dockerfile` based on the official `wordpress:<ver>-php${PHP_VERSION}-apache` image | web server + PHP. Apache because Hostinger supports `.htaccess` rules (Hostinger uses LiteSpeed, which is Apache/.htaccess compatible). |
| `db` | `mysql:${MYSQL_VERSION}` | MySQL database, data in a named volume |
| `wpcli` | official `wordpress:cli-php${PHP_VERSION}` (pinned) | WP-CLI with the same mounts as `wordpress` |
| `mailpit` | `axllent/mailpit:<pinned version>` | catches emails locally for quick testing |
| `phpmyadmin` | `phpmyadmin:<pinned version>` | database GUI (Hostinger also uses phpMyAdmin) |

### Docker rules

- All versions, ports, and credentials come from `.env` (Docker Compose reads `.env` automatically). Nothing hardcoded in `docker-compose.yml`.
- `Dockerfile` (for `wordpress`):
  - `ARG PHP_VERSION` and pinned base image
  - custom `docker/php/php.ini` with values that match Hostinger's defaults as closely as possible: `upload_max_filesize = 64M`, `post_max_size = 64M`, `memory_limit = 256M`, `max_execution_time = 120`, OPcache enabled
  - verify required extensions exist: `curl, fileinfo, gd, intl, mbstring, mysqli, openssl, pdo_mysql, zip, exif, sodium, imagick` (install any missing ones)
  - Apache `mod_rewrite` enabled
- Mounts:
  - `./public` → `/var/www/html` (WordPress root)
  - `./config` → `/var/www/config` (read-only)
  - `./.env` → `/var/www/.env` (read-only)
  This mirrors Hostinger, where `config/` and `.env` sit one level above `public_html`, so the same `wp-config.php` path logic works locally and in production.
- Our own `wp-config.php` must exist before the first container start so the official image does not generate its own.
- `db` uses a named volume (`db_data`) and a healthcheck; `wordpress` and `wpcli` wait for it (`depends_on: condition: service_healthy`).
- Handle file ownership so files created by containers (uploads, plugin installs) stay editable by the WSL user (match UID/GID or document the fix).
- Create a helper script `bin/wp` so the user can run WP-CLI simply: `./bin/wp plugin list` (wraps `docker compose run --rm wpcli wp "$@"`).
- Add `.dockerignore`.

Docker files are for **local development only**. They are never uploaded to Hostinger.

---

## 3. Project structure

```
ebook-store/
├── .env                      # real values — NEVER commit
├── .env.example              # same keys, placeholder values — commit this
├── .gitignore
├── .dockerignore
├── docker-compose.yml
├── Dockerfile
├── docker/
│   └── php/php.ini           # PHP settings matching Hostinger
├── bin/
│   └── wp                    # WP-CLI helper script
├── PLAN.md                   # this file
├── PROGRESS.md               # phase status log
├── DEPLOY.md                 # Hostinger deployment guide (Phase 7)
├── config/
│   └── env-loader.php        # tiny .env parser, no Composer needed
├── sample-content/           # dummy PDFs + cover images for testing
└── public/                   # WordPress root (= public_html on Hostinger)
    ├── wp-config.php         # reads everything from .env
    ├── .htaccess
    └── wp-content/
        ├── themes/ebookstore-child/
        ├── mu-plugins/ebook-store-core.php
        └── plugins/ebook-dummy-gateway/   # SEPARATE, REMOVABLE
```

### `.gitignore` (must be complete and correct)

Git tracks **only our own code and config templates**, never secrets, uploads, databases, WordPress core, or third-party plugins/themes (those are reinstalled with WP-CLI). It must cover at least:

- Secrets: `.env`, `.env.*` (but keep `!.env.example`)
- WordPress core: everything in `public/` **except** `public/wp-config.php`, `public/.htaccess`, and our own code. Use an "ignore all, then un-ignore" pattern so only these are tracked:
  - `public/wp-content/themes/ebookstore-child/`
  - `public/wp-content/mu-plugins/ebook-store-core.php` and `public/wp-content/mu-plugins/ebook-store-core/`
  - `public/wp-content/plugins/ebook-dummy-gateway/`
- Uploads, cache, backups, upgrade folders: `public/wp-content/uploads/`, `cache/`, `upgrade/`, `backup*/`, `wflogs/`
- Database dumps and logs: `*.sql`, `*.sql.gz`, `*.log`, `debug.log`
- OS/editor files: `.DS_Store`, `Thumbs.db`, `desktop.ini`, `.vscode/`, `.idea/`, `*.swp`
- Node/build output if ever used: `node_modules/`, `dist/`
- Docker local overrides: `docker-compose.override.yml`

After creating it, verify with `git status` and `git check-ignore -v` that `.env` and core files are ignored and our own code is tracked. Show the result in the report.

Write a `PLUGINS.md` (or a section in `DEPLOY.md`) listing every third-party plugin/theme slug and version, so the environment can be rebuilt with WP-CLI.

---

## 4. Configuration via `.env`

### Rules

- **All secrets** (DB passwords, salts, SMTP password, any API keys) live only in `.env`.
- **All changeable values** (URLs, emails, store name, site language, currencies, feature flags, download limits, social/footer links, Docker versions and ports) also live in `.env`.
- Nothing environment-specific is hardcoded in PHP, CSS, Docker files or the database where it can be avoided.
- `config/env-loader.php` parses `.env` (supports comments `#`, quoted values, empty values) and provides a helper `env( 'KEY', 'default' )`. It must not overwrite variables already set by the server.
- `wp-config.php` loads `dirname( __DIR__ ) . '/config/env-loader.php'` and defines every constant from `.env`.
- Add a fallback `.htaccess` rule in `public/` that denies access to any `.env` file, in case it is ever placed in the web root by mistake.
- Keep `.env.example` in sync with `.env` at all times (same keys, safe placeholder values, a comment explaining each key).

### Required keys (`.env.example`)

```dotenv
# ---------- Docker (local only) ----------
COMPOSE_PROJECT_NAME=ebookstore
PHP_VERSION=8.3                    # set to the version chosen in Hostinger hPanel
MYSQL_VERSION=8.0                  # set to Hostinger's MySQL version
MYSQL_ROOT_PASSWORD=change_me
APP_PORT=8080
PMA_PORT=8081
MAILPIT_UI_PORT=8025

# ---------- App ----------
APP_ENV=local                      # local | production
WP_HOME=http://localhost:8080
WP_SITEURL=http://localhost:8080
WP_DEBUG=true
WP_DEBUG_LOG=true
WP_DEBUG_DISPLAY=false
DISALLOW_FILE_EDIT=true

# ---------- Database (MySQL) ----------
DB_NAME=ebookstore
DB_USER=ebookstore_user
DB_PASSWORD=change_me
DB_HOST=db                         # "db" in Docker; Hostinger value in production
DB_PREFIX=wp_
DB_CHARSET=utf8mb4

# ---------- Security salts (generate unique random 64-char values) ----------
AUTH_KEY=
SECURE_AUTH_KEY=
LOGGED_IN_KEY=
NONCE_KEY=
AUTH_SALT=
SECURE_AUTH_SALT=
LOGGED_IN_SALT=
NONCE_SALT=

# ---------- Store ----------
STORE_NAME="eBook Store"
STORE_SUPPORT_EMAIL=support@example.com
SITE_LOCALE=en_US                  # WordPress site language
STORE_BASE_CURRENCY=USD
STORE_SECONDARY_CURRENCY=GBP
STORE_GBP_EXCHANGE_RATE=0.79
STORE_DOWNLOAD_LIMIT=5             # downloads per purchase (empty = unlimited)
STORE_DOWNLOAD_EXPIRY_DAYS=30      # days (empty = never)

# ---------- Email (SMTP) ----------
# Local quick testing: SMTP_HOST=mailpit, SMTP_PORT=1025, SMTP_ENCRYPTION=none, SMTP_AUTH=false
# Real delivery (Phase 6 + production): Hostinger email values below
SMTP_HOST=smtp.hostinger.com
SMTP_PORT=465
SMTP_ENCRYPTION=ssl                # ssl | tls | none
SMTP_AUTH=true
SMTP_USER=no-reply@example.com
SMTP_PASSWORD=change_me
MAIL_FROM_EMAIL=no-reply@example.com
MAIL_FROM_NAME="eBook Store"

# ---------- Dummy payment gateway ----------
DUMMY_GATEWAY_ENABLED=true         # set false in production

# ---------- Footer / social links ----------
LINK_FACEBOOK=
LINK_INSTAGRAM=
LINK_X=
LINK_PRIVACY_POLICY=/privacy-policy
LINK_TERMS=/terms
```

Add any new changeable value to both `.env` and `.env.example` and mention it in the phase report.

### Run the project (local)

From the Ubuntu (WSL2) terminal in the project folder:

```bash
docker compose up -d --build      # start everything
docker compose ps                 # check all services are healthy
./bin/wp plugin list              # run WP-CLI
docker compose logs -f wordpress  # view logs
docker compose down               # stop (data is kept in the db volume)
```

At the end of each phase, confirm all containers are running and give the user the URLs:
- Site: `http://localhost:8080`
- Admin: `http://localhost:8080/wp-admin`
- phpMyAdmin: `http://localhost:8081`
- Mailpit (test inbox): `http://localhost:8025`

---

## 5. Design system

### Color palette (professional, book-store feel)

Define as CSS custom properties in the child theme (`:root`) and use them everywhere. Never hardcode colors elsewhere.

| Token | Hex | Use |
|-------|-----|-----|
| `--color-primary` | `#1F3A5F` | header, footer, headings, primary buttons |
| `--color-primary-dark` | `#152842` | button hover, active states |
| `--color-accent` | `#E07A5F` | "Buy Now" buttons, highlights, prices on hover |
| `--color-accent-dark` | `#C4614A` | accent hover |
| `--color-gold` | `#C9A227` | badges, small decorative details |
| `--color-bg` | `#FAF7F2` | page background (warm paper) |
| `--color-surface` | `#FFFFFF` | cards, forms |
| `--color-text` | `#1F2933` | body text |
| `--color-muted` | `#6B7280` | secondary text, meta info |
| `--color-border` | `#E5E1DA` | card and input borders |
| `--color-success` | `#2F855A` | success notices |
| `--color-error` | `#C53030` | error notices |

All text/background pairs must meet **WCAG AA contrast** (check them).

### Typography

- Headings: **Merriweather** (serif, book feel)
- Body/UI: **Inter** (sans-serif)
- Load from Google Fonts with `display=swap`, or self-host if better for performance. Provide system font fallbacks.

### Responsive breakpoints (mobile first)

- Mobile: `< 600px` → 1 product per row
- Tablet: `600px – 1023px` → 2 products per row
- Desktop: `≥ 1024px` → 3–4 products per row
- Test at widths 375, 768, 1024, 1440. No horizontal scrolling, tap targets at least 44×44px, readable font sizes (base 16px).

### Style

Clean, lots of white space, rounded cards (8–12px radius), soft shadows, cover images in a consistent aspect ratio (2:3, `object-fit: cover`), clear price display, visible focus states for keyboard users.

### Content language

All website copy (hero text, taglines, button labels, notices, product sample content, email text, footer) is written in clear, professional **English (US)**.

---

## 6. Plugins and theme

Verify the current version and compatibility of each before installing (see Section 0).

| Purpose | Choice | Notes |
|---------|--------|-------|
| Store | **WooCommerce** | core of the project |
| Theme | **Storefront** (parent) + `ebookstore-child` | WooCommerce's official theme, best compatibility. If research shows a better maintained free WooCommerce theme, propose it to the user first. |
| Currency USD/GBP | **CURCY – Multi Currency for WooCommerce** (`woo-multi-currency`) | verify; if unsuitable, propose an alternative (e.g. WOOCS) |
| SMTP email | **WP Mail SMTP** (`wp-mail-smtp`) | configured via wp-config constants from `.env` (`WPMS_ON`, `WPMS_MAILER`, `WPMS_SMTP_HOST`, `WPMS_SMTP_PORT`, `WPMS_SSL`, `WPMS_SMTP_AUTH`, `WPMS_SMTP_USER`, `WPMS_SMTP_PASS`, `WPMS_MAIL_FROM`, `WPMS_MAIL_FROM_NAME`, force from email/name). Verify the current constant names in its docs. |
| Email log (testing) | **WP Mail Logging** or the logging in WP Mail SMTP if available free | so the user can see every sent email in admin |
| Dummy payment | **custom plugin** `ebook-dummy-gateway` | see Phase 5 |

Do not install anything else without asking the user.

---

## 7. Phases

### Phase 0 — Environment check and Docker setup

1. Check the host: Windows build, RAM, WSL2 (`wsl -l -v`), Docker (`docker --version`, `docker compose version`), Git. Guide the user through installing anything missing (Section 2).
2. Confirm the project folder is inside the WSL2 filesystem.
3. Research current stable versions (WordPress, WooCommerce, Docker images) and **Hostinger's PHP and MySQL versions**. Ask the user to confirm the versions shown in their hPanel (PHP Configuration and phpMyAdmin server info) if they already have the hosting account.
4. Create the project structure (Section 3), `.gitignore`, `.dockerignore`, `PROGRESS.md`, `git init`.
5. Create `.env` and `.env.example` with Docker keys and versions, `Dockerfile`, `docker/php/php.ini`, `docker-compose.yml`, `bin/wp`.
6. Build and start the containers; verify PHP version and extensions (`docker compose exec wordpress php -v`, `php -m`) and MySQL version.

**Deliverable:** Docker environment running with versions matching Hostinger; correct `.gitignore`.
**User test:** run `docker compose ps`, open phpMyAdmin and Mailpit; check the versions listed in the report.

### Phase 1 — WordPress install + `.env` configuration

1. Create the database and dedicated user (utf8mb4) via environment variables or an init script.
2. Create `config/env-loader.php`, and fill `.env` with freshly generated random salts and the local DB credentials.
3. Write `public/wp-config.php` that reads **everything** from `.env` (DB, salts, URLs, debug flags, `DISALLOW_FILE_EDIT`, SMTP constants) **before** WordPress core is placed, so the Docker image does not generate its own.
4. Download the latest stable WordPress (English) into `public/` with WP-CLI.
5. Install WordPress via WP-CLI (admin credentials: ask the user or generate and tell them in the report; the admin username must not be "admin").
6. Settings: site title from `STORE_NAME`, site language from `SITE_LOCALE` (`wp site switch-language en_US`), date/time format in US style, timezone, permalinks `/%postname%/`, disable comments/pingbacks on new posts, remove default sample content.
7. Add `.htaccess` deny rule for `.env`.
8. Verify with `git status` that WordPress core files are ignored and only our files are tracked.

**Deliverable:** WordPress running at `http://localhost:8080` in English, configured only from `.env`.
**User test:** open the site and admin; change `STORE_NAME` in `.env`, restart, and see the site title follow (if wired that way). Explain exactly what to try.

### Phase 2 — WooCommerce core setup + sample eBooks

1. Install and activate WooCommerce. Skip/complete the onboarding wizard via CLI where possible.
2. Enable HPOS if compatible with all chosen plugins.
3. Settings:
   - Selling locations: United States and United Kingdom
   - Base currency from `STORE_BASE_CURRENCY`
   - Taxes: disabled for now (mention to the user that UK VAT / US sales tax on digital goods may need setup later — that is the client's business decision)
   - Shipping: disabled (digital only)
   - Guest checkout: enabled; account creation: optional
   - Downloads: method **Force downloads**, "Grant access after payment" ON, append unique string to filename ON, approved download directories configured
   - Default download limit and expiry from `.env` (applied via `ebook-store-core` mu-plugin when products are saved, or as documented defaults)
4. Ensure WooCommerce pages exist: Shop, Cart, Checkout, My Account. Use the **classic** cart and checkout (`[woocommerce_cart]`, `[woocommerce_checkout]`) for best compatibility with the currency plugin and the dummy gateway. Explain this choice in the report; ask the user if they prefer block checkout.
5. Generate 4–6 sample eBooks in `sample-content/` (simple dummy PDFs and placeholder cover images, e.g. created with PHP GD inside the container) and create products via WP-CLI: Virtual, Downloadable, Sold individually, price, short + long description in English, cover image, PDF attached.

**Deliverable:** WooCommerce configured with sample eBooks.
**User test:** see products in admin and on the default shop page; check a product has its PDF attached.

### Phase 3 — Theme, design and responsive pages

1. Install Storefront (parent) and create `ebookstore-child` (style.css, functions.php, assets/css, assets/js if needed).
2. Implement the design system (Section 5): CSS variables, fonts, header, footer (footer links and support email from `.env`), buttons, cards, notices, forms.
3. **Home page:** hero section (store name, short English tagline, call-to-action) and a responsive grid of eBooks showing cover, title, price, short description, **"View Details"** button (→ product page) and **"Buy Now"** button (→ adds to cart and goes straight to checkout). Use WooCommerce hooks/shortcodes or a template in the child theme, not page builders.
4. **eBook details page:** cover, title, price, full description, "Buy Now" button, clear notes ("Instant PDF download after payment", format, delivered by email).
5. Hide irrelevant WooCommerce elements for digital goods (shipping notes, reviews if not wanted — ask), and remove the Storefront default credit.
6. Test responsiveness at 375 / 768 / 1024 / 1440 px.

**Deliverable:** finished look of home and product pages on all devices.
**User test:** open the site on desktop and resize the browser; use the browser's device toolbar (F12) for phone/tablet sizes. If the user wants to test on a real phone, explain how (PC's local IP, Windows firewall, and temporarily setting `WP_HOME`/`WP_SITEURL` to that IP).

### Phase 4 — Cart, checkout and currency

1. Style cart and checkout to match the design.
2. Simplify checkout for digital products with hooks in `ebook-store-core`: keep **first name, last name, email** (and country if required by the currency/payment logic); remove address, phone, company, order notes. Make email clearly labeled ("Your eBook will be sent to this email").
3. Cart shows product, quantity (1, sold individually) and total.
4. Install and configure the currency plugin:
   - USD (default) and GBP
   - currency switcher visible in the header (mobile-friendly)
   - optional auto-detect by visitor country (US → USD, UK → GBP) if the plugin supports it without paid add-ons
   - exchange rate from `STORE_GBP_EXCHANGE_RATE` if the plugin allows setting it programmatically; otherwise document where to change it in admin
5. Make the **actual payment currency clearly visible** at checkout (e.g. notice "You will be charged in GBP £X.XX"), and verify the order is saved in the currency the customer paid in.

**Deliverable:** working cart and checkout in USD and GBP.
**User test:** add an eBook, switch currency, go to checkout, check prices and the currency notice. (Payment itself comes in Phase 5.)

### Phase 5 — Dummy payment gateway (separate, removable)

Create a **standalone plugin** `public/wp-content/plugins/ebook-dummy-gateway/`:

```
ebook-dummy-gateway/
├── ebook-dummy-gateway.php       # plugin header, bootstrap
├── includes/class-wc-gateway-ebook-dummy.php
└── README.md                     # what it is + how to remove it
```

Requirements:
- Plugin name: **"eBook Dummy Payment Gateway (TEST ONLY)"**, clearly marked as test-only in the admin and at checkout.
- Extends `WC_Payment_Gateway`. Works with the classic checkout (and declare HPOS compatibility; add Blocks support only if the project uses block checkout).
- At checkout the customer chooses **"Simulate successful payment"** or **"Simulate failed payment"**.
- Success → `$order->payment_complete()` → order completes (virtual + downloadable), download access is granted, emails are sent.
- Failure → order marked **Failed**, error notice shown, **no download access and no download link**.
- Only active when `DUMMY_GATEWAY_ENABLED=true` in `.env`; when false, it must not appear at checkout at all.
- Add an admin notice "Dummy payment gateway is active — disable before going live" while enabled.
- No other part of the project may depend on this plugin. Removing it (deactivate + delete folder) must not break anything.
- `README.md` explains how to remove it and how the real gateway will take over (a real gateway that calls `payment_complete()` keeps the same delivery flow).

**Deliverable:** full test purchase flow (success and failure).
**User test:** buy once with success and once with failure; check order statuses in admin, that the downloads appear only for the successful order (My Account / order received page), and that the emails appear in Mailpit.

### Phase 6 — Email delivery via SMTP

1. Install WP Mail SMTP, configure it fully through constants from `.env` (no secrets in the database).
2. Install email logging for testing.
3. Style WooCommerce emails with the brand colors (WooCommerce → Settings → Emails: header color, base color, footer text, and logo if provided) using built-in settings; use template overrides in the child theme only if needed. All email text in English.
4. Make sure the customer email for a successful order contains the **download link(s)** and a clear message; failed orders send no download link.
5. First verify with Mailpit (local SMTP values). Then ask the user to put real SMTP credentials (e.g. a Hostinger email account or a Gmail App Password) into `.env` themselves. Never ask them to paste the password into the chat; tell them which keys to fill in.
6. Send a test email (WP Mail SMTP test tool or `./bin/wp eval "wp_mail(...)"`), then run a full purchase with real SMTP.

**Deliverable:** real emails with download links after successful payment.
**User test:** do a successful purchase with your own email address, receive the email, click the download link, get the PDF. Do a failed purchase and confirm no download email arrives.

### Phase 7 — QA, security and Hostinger deployment guide

1. End-to-end tests: both currencies × success/fail, guest checkout, download limit/expiry, broken/expired link behavior, direct access to PDF file URL (must be blocked).
2. Responsive check of every page (home, product, cart, checkout, order received, My Account) at all breakpoints.
3. Language check: confirm no Bangla or placeholder text appears anywhere on the website or in emails.
4. Security: `DISALLOW_FILE_EDIT`, debug off in production settings, `.env` not web-accessible, uploads directory protection for downloads, admin username not "admin", strong passwords, latest versions of everything.
5. Performance basics: optimized images, no unused plugins, fonts loaded efficiently.
6. Final `.gitignore` check: `git status` clean, no secrets, uploads or core files tracked.
7. Clean up sample content only if the user wants (ask).
8. Write `DEPLOY.md` for Hostinger, including:
   - selecting in hPanel the same PHP version used in Docker (`PHP_VERSION`)
   - creating the MySQL database and user in hPanel
   - uploading files: `public/` contents → `public_html/`, and `config/` + `.env` **one level above** `public_html` (adjust the path in `wp-config.php` if Hostinger's folder layout differs — explain how to check). Docker files, `bin/`, `sample-content/` and `.git` are **not** uploaded.
   - exporting the local DB (`./bin/wp db export`) and importing in phpMyAdmin
   - `wp search-replace 'http://localhost:8080' 'https://yourdomain.com' --all-tables` (via SSH), or a migration plugin alternative
   - the full list of `.env` keys to change for production (`APP_ENV`, URLs, `DB_HOST` and other DB values, salts — regenerate, SMTP, `WP_DEBUG=false`, `DUMMY_GATEWAY_ENABLED=false` once the real gateway is installed); Docker-only keys are ignored in production
   - enabling SSL and forcing HTTPS
   - how to remove the dummy gateway when the client installs the real one
   - how to rebuild the local environment from git (`git clone`, copy `.env.example` to `.env`, `docker compose up -d --build`, install plugins from `PLUGINS.md`, import a DB dump)
9. Final `PROGRESS.md` summary.

**Deliverable:** tested project + `DEPLOY.md`.
**User test:** follow the final checklist in the report.

---

## 8. Definition of done (whole project)

- All features in Sections 1 and 7 work locally in Docker.
- Docker PHP and MySQL versions match Hostinger; all image versions pinned.
- The whole website and all emails are in English (en_US).
- No core/plugin/theme files edited; all custom code in child theme, mu-plugin, or dummy gateway plugin.
- Every secret and changeable value is in `.env`; `.env.example` is complete.
- `.gitignore` is correct: no secrets, uploads, DB dumps or WordPress core in git.
- Dummy gateway is isolated and removable without side effects.
- Responsive on mobile, tablet, desktop.
- `DEPLOY.md` lets the user deploy to Hostinger without help.

---

## 9. Phase report template

Use these headings; write the content in Bangla (Section 0, rule 3).

```
## Phase N complete: <phase name>

### What was done in this phase
- ...

### Files created / changed
- ...

### Installed versions (if relevant)
- WordPress x.x.x, WooCommerce x.x.x, PHP x.x, MySQL x.x, ...

### New / changed .env variables
- ...

### How to test
1. ...
2. ...
(URLs, login details, step by step)

### Known issues / decisions you should know about
- ...

Next phase: <name> — type "next" to start.
```