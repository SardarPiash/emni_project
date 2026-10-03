# eBook Store — Security Hardening Plan (Phases 13–15)

## 0. Context and rules (READ FIRST)

This plan extends `ebook_prompt.md` (and `ui_redesign_prompt.md` if it exists). If any phase of another plan is unfinished, tell the user and ask which to do first.

All existing rules still apply: one phase at a time, stop after each phase with a report, wait for "next", English communication, never edit WordPress core or third-party plugin/theme files, everything changeable in `.env` (or admin settings), commit after each phase.

**Goal:** protect the admin area properly with only the security measures that are really needed, while customers notice nothing. No extra steps, no CAPTCHAs, no blocks and no new friction for customers.

### The most important rule: do not break existing work

Security changes must not change the design or any current functionality. These must keep working and must be re-tested at the end of every phase:
- Purchase flow: cart → checkout → dummy payment success → order completed → download email with working PDF link
- Dummy payment failure → order Failed → no download link
- USD/GBP currency switching and the "you will be charged in" notice
- Simplified checkout, homepage, carousel/banners and their admin screen (if built), product pages, My Account login and downloads
- Admin login for the real admin, `.env` loading, SMTP email
- The dummy gateway stays separate; do not modify it unless a real security bug is found, and then only after asking the user

### Decisions already made (do not re-ask)

1. **Customers are never blocked in any case.** Not their accounts, not their IPs, not their logins, not their password resets. They never see a lockout message.
2. **Lockout protects admin access only.**
   - **Protected accounts:** roles in `SECURITY_PROTECTED_ROLES` (default `administrator,shop_manager`).
   - **Counted failures:** a wrong password for a protected account, or a login with a username/email that does not exist. A wrong password for an existing customer account is **never counted**.
   - **After 5 counted failures from one IP → that IP is blocked for 2 hours** from: `/wp-admin/` pages (except `admin-ajax.php` and `admin-post.php`) and logging in to protected accounts on any route. While blocked, protected-account login attempts are refused **without checking the password**.
   - **Everything else stays open for that IP:** browsing, cart, checkout, payment, downloads, customer login, customer password reset, registration, `admin-ajax.php`, `admin-post.php`, `?wc-ajax=`, `?wc-api=` and payment webhooks, the public Store API, static files.
3. **Blocking is per IP, never per account**, so an attacker cannot lock out the real admin. A correct admin login from any non-blocked IP always works and resets that IP's counter.
4. **Admin recovery options:** another device/network, **Security** admin screen (unblock), IP allowlist, one-time unlock link by email, WP-CLI.
5. **Implementation:** a custom module in our mu-plugin. Generic lockout plugins usually block every login from an IP, which conflicts with decision 1.
6. **Not included (unnecessary or risky for this store):** CAPTCHA anywhere, stricter customer password rules than the WooCommerce default, Content-Security-Policy, Permissions-Policy, security-alert emails to customers, heavy security suites (e.g. Wordfence). **Two-factor login for admins** is recommended in the report but only added if the user asks.
7. **Password reset anti-flood (invisible to customers):** the same email address receives at most one reset email per 2 minutes; the user always sees the normal success message.

### Where code goes

- Security features → `mu-plugins/ebook-store-core/security/` (one file per feature, easy to find and disable)
- Front-end validation → child theme JS (small, no libraries)
- `.htaccess` rules → `public/.htaccess` (only effective on Hostinger)

### Local testing limitation

The local server (`wp server`) does **not** read `.htaccess`. PHP-level protections are tested locally; `.htaccess` rules go into `DEPLOY.md` under "verify on Hostinger" with exact test commands.

### Safety setup (before any code change in Phase 14)

Create git branch `feature/security-hardening`, tag `before-security`, and a DB backup `wp db export backups/before-security.sql` (ignored by git). Mention the rollback steps in every report.

---

## Phase 13 — Quick audit (NO code changes)

Only investigate. Do not change any project file except `PROGRESS.md`. Keep the report short.

1. **Versions and known vulnerabilities:** list WordPress, WooCommerce, PHP and plugin versions; use web search (e.g. WPScan, Patchstack, changelogs) to check for known vulnerabilities; list anything that needs updating.
2. **Code audit** of our own code (child theme, `ebook-store-core`, dummy gateway — audit only): missing sanitization, escaping, nonces, capability checks, unprepared SQL, unsafe file handling, debug output. List findings with file and line.
3. **Front-end route check:** list which features use `admin-ajax.php`, `admin-post.php`, `wc-ajax` or the REST/Store API, and confirm none will be blocked.
4. **Real visitor IP on Hostinger:** explain how it will be detected. Headers like `X-Forwarded-For` can be faked, so trust one only when the site is behind a known proxy/CDN (`SECURITY_TRUSTED_PROXY_HEADER`); otherwise use `REMOTE_ADDR`.
5. **Anything in this plan that could still affect customers or current features** — if you find a problem, say so and propose a fix.
6. **STOP** and ask: "Is it okay to continue?" Only ask about decisions if the audit found a real problem with them.

---

## Phase 14 — Implementation

Start only after the user approves Phase 13. First do the safety setup and confirm it in the report.

### 14.1 Admin login lockout

- Apply decisions 1–3 on every login route, including requests made with curl or scripts: `wp-login.php` (form and direct POST), WooCommerce My Account login form, REST API requests with credentials (Basic Auth / Application Passwords).
- **XML-RPC:** disable completely (`SECURITY_DISABLE_XMLRPC=true`); nothing in this project needs it.
- **Generic login error** for everyone: "Invalid username or password." Never reveal whether a username exists.
- **Blocked response** (only for blocked `/wp-admin/` pages and protected-account login attempts): HTTP `429`, `Cache-Control: no-store`, a short neutral message with the time to try again, and the emergency unlock link (14.3). Do not reveal that the username belongs to an admin.
- **Storage:** small custom table or transients; expired entries cleaned automatically. Block checks run only on login and admin routes, never on store pages.
- **Log:** counted failures and lockouts (IP, time, route, username tried, user agent). Never log passwords. Kept for `SECURITY_LOG_RETENTION_DAYS`.
- **IP detection safety check:** if the detected IP equals the server/proxy IP, or one IP shows an unusual number of lockouts, show an admin notice (this would mean IP detection is wrong on the server).

### 14.2 Admin screen: Security

A **Security** menu in WP admin, administrators only, standard WordPress admin styling, plain-English labels.

- **Blocked IPs:** table (IP, last username tried, attempts, route, blocked at, time left), **Unblock** per row, **Unblock selected**, **Unblock all** (with confirmation), **Block an IP manually** (IP + duration), search and pagination. Note at the top: "Blocked IPs cannot open the admin area or log in to admin accounts. Customers on the same IP are not affected."
- **Allowlist:** add/remove IPs that are never blocked, an **"Add my current IP"** button; IPs from `SECURITY_IP_ALLOWLIST` in `.env` shown read-only.
- **Activity log:** recent counted failures and lockouts, filter by IP/date, pagination, **Clear log** (with confirmation).
- **Dashboard widget:** number of blocked IPs and any IP-detection warning, linking to the Security screen.
- Capability checks and nonces on every action, valid IPv4/IPv6 only, all output escaped. Highlight the admin's own IP and warn before they block it or remove it from the allowlist.

### 14.3 Emergency unlock by email

- The blocked page shows: **"Are you the site owner? Send an unlock link."** The admin enters their email.
- If it belongs to a protected account, send a **one-time link** (random token stored hashed, valid 15 minutes, single use) that unblocks only the requesting IP.
- Always show the same neutral reply ("If this email belongs to an administrator, an unlock link has been sent.").
- Max 3 unlock requests per IP per hour; every request and use is logged.

### 14.4 WP-CLI backup tools

`wp ebookstore security list-blocked`, `unblock <ip>`, `unblock-all`, `allow <ip>`, `disallow <ip>`. Document all recovery options in `DEPLOY.md` under "If you are locked out".

### 14.5 Essential hardening

- **Hide usernames:** `?author=N` redirects to the homepage; `/wp-json/wp/v2/users` hidden from visitors who are not logged in (WooCommerce, Store API and the block editor must keep working).
- **Hide version info:** remove the WordPress version from page source and feeds; block public access to `readme.html` and `license.txt`.
- **Safe security headers** (sent via PHP so they work locally too): `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, and `Strict-Transport-Security` only when `SECURITY_HSTS=true`.
- **Production only** (driven by `APP_ENV=production`): `FORCE_SSL_ADMIN`, secure cookies.
- **`.htaccess` for Hostinger:** deny `.env`, `wp-config.php`, `debug.log`, `*.sql`, `*.log`; disable directory listing; block PHP execution in `wp-content/uploads/`; keep WooCommerce download protection intact.
- **Caching:** document in `DEPLOY.md` that login, My Account, cart and checkout pages must be excluded from LiteSpeed Cache on Hostinger.
- **Password reset anti-flood:** decision 7.

### 14.6 Form validation and injection protection

Server-side checks are the real protection; client-side checks only make errors appear faster. Do both, without making forms harder to use.

- **Checkout and My Account:** keep WooCommerce's built-in validation. For our customized fields add server-side checks: names accept letters from all languages (Unicode `\p{L}`), accents, apostrophes, hyphens, spaces and dots (José, Zoë, O'Brien, Nguyễn, Anne-Marie must pass), with a sensible max length; emails checked with WordPress `is_email()`. Friendly English messages in the existing design.
- **Our admin forms** (Hero Slides, Security screen) and the unlock form: capability checks, nonces, sanitizing (`sanitize_text_field`, `sanitize_email`, `esc_url_raw`, `absint`, IP validation) and escaping on output (`esc_html`, `esc_attr`, `esc_url`).
- **All database queries** in our code use `$wpdb->prepare()` or WordPress APIs.
- **Client-side:** HTML5 attributes (`required`, `type="email"`, `maxlength`) plus a few lines of JS for clear inline messages. No extra fields or steps.
- Fix every issue found in the Phase 13 audit.

### New `.env` keys (add to `.env` and `.env.example`)

```dotenv
# ---------- Security ----------
SECURITY_PROTECTED_ROLES=administrator,shop_manager   # only these accounts trigger lockouts
SECURITY_MAX_LOGIN_ATTEMPTS=5
SECURITY_LOCKOUT_MINUTES=120             # blocks admin area + admin-account logins only; customers never blocked
SECURITY_IP_ALLOWLIST=127.0.0.1,::1      # never blocked; more can be added in admin
SECURITY_TRUSTED_PROXY_HEADER=           # empty = use REMOTE_ADDR; set only if behind a known proxy/CDN
SECURITY_DISABLE_XMLRPC=true
SECURITY_UNLOCK_LINK_MINUTES=15
SECURITY_RESET_EMAIL_COOLDOWN_SECONDS=120
SECURITY_LOG_RETENTION_DAYS=30
SECURITY_HSTS=false                      # true only in production with working HTTPS
```

**Deliverable:** admin area protected; customers unaffected; design and current features unchanged.

---

## Phase 15 — Testing and short report

Run real tests and show the commands and results (PowerShell, `curl.exe` where helpful). To simulate different IPs locally, use a test-only IP override that works **only when `APP_ENV=local`** and is disabled afterwards. Create a test customer and a test shop manager, and delete them afterwards.

**Admin protection:**
1. 6 wrong passwords for the admin username via `wp-login.php` (browser and `curl.exe`) → the 6th is blocked with 429; `/wp-admin/` pages are blocked for that IP.
2. Same via My Account login and REST API with wrong credentials; XML-RPC is disabled.
3. Non-existent usernames are counted and lead to a block.
4. From a blocked IP, the correct admin password is still refused; from another IP, the real admin logs in normally.
5. Security screen: unblock, bulk unblock, unblock all, manual block, allowlist and "Add my current IP" work.
6. Emergency unlock link: works once, only for the requesting IP, expires after 15 minutes; non-admin emails get the same neutral reply and no email.
7. Block expires on time (test with 1 minute, then restore 120); WP-CLI commands work.

**Customers are never affected:**
8. 20+ wrong passwords on a customer account → only the normal error message, never a block.
9. From a blocked IP, a customer can log in, reset their password, register, browse, add to cart, switch currency, check out and download. `admin-ajax.php`, `?wc-ajax=`, `?wc-api=` and the Store API work.
10. Password reset: the customer always sees the normal message; a second reset email within 2 minutes is not sent.
11. Checkout accepts José, Zoë, O'Brien, Nguyễn, Anne-Marie.

**Hardening and injection:**
12. `?author=1` and `/wp-json/wp/v2/users` reveal no usernames; generic login error for wrong username and wrong password.
13. Headers present (`curl.exe -I http://localhost:8080`); blocked response has `Cache-Control: no-store`.
14. Injection attempts (`<script>alert(1)</script>`, `' OR '1'='1`, very long strings, invalid IPs) in checkout, admin forms and the unlock form are rejected or safely escaped, never executed or stored raw.

**Regression** (everything in "The most important rule"), on mobile, tablet and desktop, in both currencies.

**Report (keep it short):**

```
## Security update — test report

| Test | Result |
|------|--------|
| Admin lockout after 5 attempts | ✅ / ❌ |
| Real admin not locked out by attacker | ✅ / ❌ |
| Customers never blocked | ✅ / ❌ |
| Customer can log in and buy from a blocked IP | ✅ / ❌ |
| Admin panel unblock / allowlist | ✅ / ❌ |
| Emergency unlock email | ✅ / ❌ |
| Hardening + injection tests | ✅ / ❌ |

Regression: purchase ✅ | failed payment ✅ | email ✅ | currency ✅ | design unchanged ✅

Recommended next (optional): two-factor login for admins
To verify on Hostinger after deploy: <short list>
If locked out: <short list of recovery options>
Rollback: git checkout main + import backups/before-security.sql
```

Then ask the user whether to merge `feature/security-hardening` into `main`. Merge only after confirmation.