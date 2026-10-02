# Deploying the eBook Store to Hostinger

This guide moves the local site (`C:\projects\ebook-store`) to Hostinger shared / WordPress hosting.
Follow the steps in order. Where you see `yourstore.com`, use your real domain.
Where you see `u123456789`, use the account number shown in your hPanel file paths.

> ⚠️ **Do this only once (first launch).** After the shop is live and has real orders, **never** import your
> local database over the live one — you would delete real customers and orders. See section 11 for
> updating code later.

---

## 0. What you need before you start

| Item | Where |
|------|-------|
| A Hostinger plan with your domain connected | hPanel → Websites |
| SSH access (Premium/Business plans and above) — optional but makes step 6 easier | hPanel → Advanced → SSH Access |
| A mailbox such as `no-reply@yourstore.com` (+ SPF/DKIM/DMARC records) | hPanel → Emails (see section 8.3) |
| The local site running and working | `http://localhost:8080` |

---

## 1. Folder layout on Hostinger (important)

Our setup keeps secrets **one level above** the public web folder, so they can never be downloaded:

```
/home/u123456789/domains/yourstore.com/          ← "project folder" on Hostinger
├── .env                    ← production settings + secrets (from step 3c)
├── config/
│   └── env-loader.php      ← from C:\projects\ebook-store\config\
├── logs/                   ← created automatically (only if WP_DEBUG_LOG=true)
└── public_html/            ← contents of C:\projects\ebook-store\public\
    ├── wp-config.php
    ├── .htaccess
    ├── wp-admin/  wp-includes/  wp-content/ ...
```

**How to check the path:** hPanel → Files → **File Manager** → open `public_html`. The path shown at the top
(e.g. `/home/u123456789/domains/yourstore.com/public_html`) tells you where "one level above" is.
`wp-config.php` loads `../config/env-loader.php` and `../.env` from the folder **above** `public_html` —
if your path is different, that is fine as long as `.env` and `config/` sit next to `public_html`.

**Never upload:** `.git`, `sample-content/`, `backups/`, `logs/`, `bin/`, `PROGRESS.md`, `ebook_prompt.md`.

---

## 2. Prepare Hostinger (hPanel)

### 2.1 PHP version and settings
hPanel → Websites → **Manage** → Advanced → **PHP Configuration**:

1. **PHP version: 8.3** (same as local). Save.
2. Tab **PHP options** — set:
   `memory_limit = 256M`, `upload_max_filesize = 64M`, `post_max_size = 64M`,
   `max_execution_time = 120`, `max_input_vars = 3000`.
3. Tab **PHP extensions** — make sure these are ticked: `curl, fileinfo, gd (or imagick), intl, mbstring,
   mysqli, openssl, pdo_mysql, sodium, zip, exif`, and **OPcache** (on by default).

### 2.2 Database
hPanel → Databases → **Management** → *Create a new MySQL database and user*:

- Database name, username and a **strong password** (use the generator).
- Hostinger adds a prefix — note the **full** names, e.g. `u123456789_ebookstore` and `u123456789_shop`.
- The database host is usually **`localhost`** (shown in hPanel next to the database).

---

## 3. Prepare the files on your PC

Open **PowerShell** and run:

```powershell
cd C:\projects\ebook-store
```

### 3a. Export the database
```powershell
.\bin\export-db.ps1
```
This creates `backups\ebookstore-YYYYMMDD-HHMM.sql` in a format Hostinger's MariaDB accepts
(no MySQL-only GTID lines, `utf8mb4_unicode_520_ci` collation). The script prints two checks — both must be **0**.
The file contains customer data: keep it private and delete it after the import.

### 3b. Zip the website files
1. In File Explorer open `C:\projects\ebook-store\public`.
2. Select **everything** inside it (including `.htaccess`) → right-click → **Send to → Compressed (zipped) folder**.
3. Name it `public.zip`.

### 3c. Create the production `.env`
1. Copy `C:\projects\ebook-store\.env` to `C:\projects\ebook-store\.env.production`
   (any `.env.*` file is ignored by git, so it will never be committed).
2. Open `.env.production` in Notepad and change these values:

| Key | Production value |
|-----|------------------|
| `APP_ENV` | `production` |
| `WP_HOME` / `WP_SITEURL` | `https://yourstore.com` |
| `WP_DEBUG` | `false` |
| `WP_DEBUG_LOG` | `false` (set `true` only while troubleshooting — the log goes to `../logs/`, outside the web root) |
| `WP_DEBUG_DISPLAY` | `false` |
| `DISALLOW_FILE_EDIT` | `true` |
| `DB_NAME`, `DB_USER`, `DB_PASSWORD` | the full Hostinger names + password from step 2.2 |
| `DB_HOST` | `localhost` (or the host hPanel shows) |
| `DB_PREFIX`, `DB_CHARSET`, `DB_COLLATE` | keep `wp_`, `utf8mb4`, `utf8mb4_unicode_ci` |
| 8 salt keys (`AUTH_KEY` … `NONCE_SALT`) | **new** random values: open https://api.wordpress.org/secret-key/1.1/salt/ and copy each value (put it in single quotes `'...'` if it contains a `#` or spaces) |
| `STORE_SUPPORT_EMAIL`, `STORE_ADMIN_EMAIL` | your real addresses |
| `SMTP_HOST` / `SMTP_PORT` / `SMTP_ENCRYPTION` | `smtp.hostinger.com` / `465` / `ssl` |
| `SMTP_USER` / `SMTP_PASSWORD` | `no-reply@yourstore.com` / its mailbox password |
| `MAIL_FROM_EMAIL` / `MAIL_FROM_NAME` | `no-reply@yourstore.com` / your store name |
| `DUMMY_GATEWAY_ENABLED` | keep `true` for the first test purchase, then `false` **once a real gateway is installed** (with no gateway at all, customers cannot pay) |
| `LINK_FACEBOOK`, `LINK_INSTAGRAM`, `LINK_X` | full URLs, or empty to hide |

Everything else (`STORE_NAME`, currencies, download limit/expiry …) can stay as it is.

---

## 4. Upload

hPanel → Files → **File Manager**:

1. Open `public_html`. Delete Hostinger's placeholder files (e.g. `default.php`, an old `index.php`) —
   only if this is a new, empty site.
2. **Upload** `public.zip` into `public_html` → right-click it → **Extract** (into the current folder) →
   delete `public.zip` afterwards. You should now see `wp-config.php`, `.htaccess`, `wp-admin`… directly in `public_html`.
3. Go **one level up** (the folder that contains `public_html`).
4. Create a folder **`config`** and upload `C:\projects\ebook-store\config\env-loader.php` into it.
5. Upload `.env.production` here and **rename it to `.env`**.

---

## 5. Import the database

hPanel → Databases → **phpMyAdmin** → *Enter phpMyAdmin* next to your database:

1. Click the database name on the left (it must be empty).
2. Tab **Import** → *Choose file* → `backups\ebookstore-….sql` → **Import**.
3. You should see "Import has been successfully finished".

If you get *"Unknown collation: utf8mb4_0900_ai_ci"* (should not happen with `export-db.ps1`):
open the `.sql` file in Notepad, *Replace all* `utf8mb4_0900_ai_ci` → `utf8mb4_unicode_520_ci`, import again.

---

## 6. Replace the local address with your domain

The database still contains `http://localhost:8080` (product download links, images, settings).

### Option A — SSH (recommended)
hPanel → Advanced → SSH Access shows the command, e.g. `ssh -p 65002 u123456789@123.45.67.89`.
Run it in PowerShell, then:

```bash
cd domains/yourstore.com/public_html
wp search-replace 'http://localhost:8080' 'https://yourstore.com' --all-tables --precise --dry-run
wp search-replace 'http://localhost:8080' 'https://yourstore.com' --all-tables --precise
wp rewrite flush
wp cache flush
```
(The first command only shows what would change.)

### Option B — no SSH
Plugins → Add New → install **Better Search Replace** → Tools → Better Search Replace:
search `http://localhost:8080`, replace `https://yourstore.com`, select all tables,
run once with *dry run* ticked, then without. **Deactivate and delete** the plugin afterwards.
Then Settings → Permalinks → **Save Changes**.

### Approved download directory
WooCommerce only serves files from approved folders. Go to WooCommerce → Settings → **Products** → link
**Approved download directories** (at the top of the Products tab):

- Keep `https://yourstore.com/wp-content/uploads/woocommerce_uploads/` (updated by the replace above).
- **Add** `/home/u123456789/domains/yourstore.com/public_html/wp-content/uploads/woocommerce_uploads/`
- **Delete** the old `file://C:/projects/ebook-store/…` entry.

---

## 7. SSL / HTTPS

hPanel → Security → **SSL** → install the free SSL for your domain (can take a few minutes), then turn on
**Force HTTPS**. `WP_HOME` / `WP_SITEURL` in `.env` must start with `https://`.

---

## 8. First login and settings on the live site

Log in at `https://yourstore.com/wp-admin` with the **same admin user and password as locally**
(user `ebook_manager`). Then:

1. **Users → Profile:** change the password to a new strong one.
2. **Settings → Reading:** make sure *"Discourage search engines"* is **not** ticked.
3. **Settings → Permalinks:** click **Save Changes** once.
4. **WooCommerce → Exchange Rate:** click **Update from ECB now** — you should see today's rate.

### 8.1 Email
- **WP Mail SMTP → Tools → Email Test:** send a test to your own address (settings come from `.env`).
- **WP Mail Logging** (admin menu) shows every sent email.

### 8.2 Currency auto-detect (optional, recommended)
UK visitors get GBP, US visitors USD. For accurate detection without external lookup services, add a free
**MaxMind** license key: WooCommerce → Settings → **Integration → MaxMind Geolocation**.
(Without it WooCommerce may use an external IP-lookup service — mention this in your privacy policy, or
switch auto-detect off in **Multi Currency** (admin menu) → Location.)

### 8.3 Email deliverability (DNS)
hPanel → Emails → your domain: make sure **MX, SPF, DKIM and DMARC** show as configured. If the domain's DNS
is managed elsewhere (e.g. Cloudflare), copy the records hPanel shows into that DNS provider.

### 8.4 Page cache (LiteSpeed Cache) — only if you install it
Hostinger often recommends the **LiteSpeed Cache** plugin. If you use it:
- Cart, Checkout and My Account are excluded automatically for WooCommerce — keep it that way.
- Because prices change with the currency, enable **Multi Currency** (admin menu) → **"Use cache plugin"**
  (cache-compatible mode) **or** in LiteSpeed Cache → Cache → Advanced add the vary cookie
  `wmc_current_currency`. Then test switching USD/GBP on the home and product pages.

### 8.5 Scheduled tasks (optional)
Exchange-rate updates and WooCommerce jobs run via WordPress' built-in scheduler on page visits. For a
quiet shop you can add a real cron job: hPanel → Advanced → **Cron Jobs**, every 15 minutes:
`wget -q -O - https://yourstore.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1`

### 8.6 Before you take real orders
- [ ] Replace the **sample** Privacy Policy and Terms & Conditions text (Pages → edit).
- [ ] Replace or delete the **6 sample eBooks** and add your real ones (Products → Add New: tick
      *Virtual* + *Downloadable*, upload the PDF under *Downloadable files*, set *Sold individually*).
- [ ] Delete test orders (WooCommerce → Orders) and the test customer (Users).
- [ ] **Taxes:** UK VAT / US sales tax on digital goods may apply — decide with an accountant
      (WooCommerce → Settings → General → *Enable taxes*).
- [ ] Store address: WooCommerce → Settings → General.
- [ ] Install the **real payment gateway** (section 10).

---

## 9. Post-deploy checks (these cannot be tested on the local PC)

Open each URL in a private browser window:

| URL | Expected |
|-----|----------|
| `https://yourstore.com/.env` | 403 or 404 — **never** the file |
| `https://yourstore.com/../.env` | 404 / home page — never the file |
| `https://yourstore.com/wp-config.php` | 403 (blank/forbidden) |
| `https://yourstore.com/xmlrpc.php` | 403 |
| `https://yourstore.com/readme.html` | 403 |
| `https://yourstore.com/wp-content/uploads/woocommerce_uploads/` | 403 (no file list) |
| A direct PDF URL inside `wp-content/uploads/woocommerce_uploads/2026/…` | **403** — PDFs must only download through order links |
| `https://yourstore.com/wp-json/wp/v2/users` | 404 (`rest_no_route`) |
| `https://yourstore.com/?author=1` | redirects to the home page |
| `http://yourstore.com` | redirects to `https://` |

Then test the shop end-to-end:

1. Switch USD ⇄ GBP in the header, on a product page and in the cart.
2. Buy an eBook with your own email using **Simulate successful payment** → order *Completed*,
   download on the thank-you page works, email "Your eBook is ready" arrives with a working link.
3. Buy again with **Simulate failed payment** → order *Failed*, no download, no download email.
4. Check on a phone.

---

## 10. Removing the dummy gateway (when the real gateway is installed)

1. Install and configure the real gateway (e.g. WooPayments, Stripe or PayPal Payments) and make one real
   test purchase. Delivery works the same way: every mainstream gateway calls `payment_complete()`, which
   completes the order, grants the download and sends the email.
2. In `.env` on Hostinger set `DUMMY_GATEWAY_ENABLED=false` → the test gateway disappears immediately.
3. Plugins → *eBook Dummy Payment Gateway (TEST ONLY)* → **Deactivate → Delete**.
   Nothing else depends on it (details: `public/wp-content/plugins/ebook-dummy-gateway/README.md`).

---

## 11. Updating the live site later (code changes)

Only upload **our own code** — never the database:

| Changed locally | Upload to (on Hostinger) |
|-----------------|--------------------------|
| `public/wp-content/themes/ebookstore-child/` | `public_html/wp-content/themes/ebookstore-child/` |
| `public/wp-content/mu-plugins/ebook-store-core.php` and `ebook-store-core/` | `public_html/wp-content/mu-plugins/` |
| `public/wp-content/plugins/ebook-dummy-gateway/` | (only while still in use) |
| `config/env-loader.php` | `config/` (one level above `public_html`) |
| new `.env` keys (see `.env.example`) | add them to the live `.env` by hand |

WordPress core and plugins are updated from **Dashboard → Updates** on the live site (take a backup first:
hPanel → Files → **Backups**).

---

## 12. Rebuilding the local environment from git (new PC)

Requirements: Scoop, PHP 8.3, MySQL (LTS), WP-CLI, Git (see `ebook_prompt.md`, section 2).

```powershell
git clone git@github.com:SardarPiash/emni_project.git C:\projects\ebook-store
cd C:\projects\ebook-store
git config core.autocrlf input
copy .env.example .env      # then open .env in Notepad: DB password + 8 new salts
```

Create the database and user (replace `STRONG_PASSWORD` with the value you put in `.env`):
```powershell
mysql -u root -e "CREATE DATABASE ebookstore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER 'ebookstore_user'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD'; CREATE USER 'ebookstore_user'@'127.0.0.1' IDENTIFIED BY 'STRONG_PASSWORD'; GRANT ALL ON ebookstore.* TO 'ebookstore_user'@'localhost'; GRANT ALL ON ebookstore.* TO 'ebookstore_user'@'127.0.0.1';"
```

Download WordPress (on Windows use the zip — see the note in `PLUGINS.md`), then install the theme and
plugins listed in `PLUGINS.md`. Then either

- **import a database dump** (from Hostinger or `backups\`): `mysql -u root ebookstore < backups\file.sql`
  and run `wp search-replace 'https://yourstore.com' 'http://localhost:8080' --all-tables --precise`, **or**
- **start fresh**: `wp core install …`, then `php sample-content\generate.php` and
  `wp eval-file sample-content\create-products.php` for the sample eBooks.

Start the site (two PowerShell windows): `mysqld --console` and
`wp server --host=localhost --port=8080 --docroot=public`.
