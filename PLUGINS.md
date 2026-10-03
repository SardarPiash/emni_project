# Third-party software (not in git)

These are reinstalled with WP-CLI when rebuilding the environment. Run all commands
from `C:\projects\ebook-store` in PowerShell (`wp-cli.yml` points WP-CLI to `public/`).

## WordPress core

| Package | Version | Rebuild command |
|---------|---------|-----------------|
| WordPress (en_US) | 7.1.2 | see note below |

> **Windows note:** `wp core download` fails on Windows for WordPress 7.x (PHP's tar reader
> truncates a long file name, and `tar` is not on WP-CLI's PATH). Download the official
> zip instead, verify its SHA1 (`https://wordpress.org/wordpress-<ver>.zip.sha1`), extract the
> `wordpress/` folder contents into `public/` without overwriting `wp-config.php`, then run
> `wp core verify-checksums`. On Hostinger/Linux, `wp core download --version=<ver>` works normally.

## Themes

| Slug | Version | Status | Install command |
|------|---------|--------|-----------------|
| storefront | 4.6.2 | parent of ebookstore-child | `wp theme install storefront --version=4.6.2` |
| twentytwentyfive | 1.5 | inactive fallback (WordPress default) | bundled with WordPress |

## Plugins

| Slug | Version | Install command |
|------|---------|-----------------|
| woocommerce | 11.1.2 | `wp plugin install woocommerce --version=11.1.2 --activate` |
| woo-multi-currency (CURCY) | 2.2.18 | `wp plugin install woo-multi-currency --version=2.2.18 --activate` — currencies + rate are forced from `.env` by the mu-plugin; other settings in Multi Currency (admin menu) |
| wp-mail-smtp | 4.10.0 | `wp plugin install wp-mail-smtp --version=4.10.0 --activate` — configured only by WPMS_* constants in wp-config.php (from .env); no secrets in the database |
| wp-mail-logging | 1.17.0 | `wp plugin install wp-mail-logging --version=1.17.0 --activate` — email log (admin → WP Mail Logging); logs auto-deleted after 30 days |

## Our own code (in git)

| Path | Type |
|------|------|
| `public/wp-content/mu-plugins/ebook-store-core.php` + `ebook-store-core/` (`exchange-rate.php`, `hero-slides.php`, `homepage-sections.php`, `promo-banners.php`, `demo-data.php`, `security/*.php`, `assets/hero-slides-admin.js`) | must-use plugin |
| `public/wp-content/themes/ebookstore-child/` | child theme (active) — the UI redesign (hero carousel, sections, badges, icons) is plain PHP/CSS/vanilla JS: **no front-end libraries** (no slider, no animation, no icon font) |
| `public/wp-content/plugins/ebook-dummy-gateway/` | test-only gateway — active while `DUMMY_GATEWAY_ENABLED=true`; see its README.md to remove |

## Sample content (in git, local testing only)

```powershell
php sample-content/generate.php              # PDFs + 2:3 covers from books.json
wp eval-file sample-content/create-products.php   # creates the 6 sample products (skips existing)
```

## WP-CLI commands (our mu-plugin)

| Command | What it does |
|---------|--------------|
| `wp ebookstore seed-slides` | Creates the 3 starter Hero Slides from `sample-content/hero/` (safe to run twice) |
| `wp ebookstore seed-demo` | Creates the ~50-eBook demo catalogue (safe to run twice; refuses on `APP_ENV=production` without `--force`) |
| `wp ebookstore remove-demo [--yes] [--include-ordered]` | Deletes only demo eBooks, their covers/PDFs and empty demo categories; never touches orders |
| `wp ebookstore security list-blocked \| unblock <ip> \| unblock-all \| allow <ip> \| disallow <ip>` | Admin-login lockout recovery (see DEPLOY.md → "If you are locked out") |
