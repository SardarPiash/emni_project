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
| twentytwentyfive | 1.5 | active (temporary — replaced by Storefront in Phase 3) | bundled with WordPress |

## Plugins

| Slug | Version | Install command |
|------|---------|-----------------|
| _(none yet — WooCommerce comes in Phase 2)_ | | |

## Our own code (in git)

| Path | Type |
|------|------|
| `public/wp-content/mu-plugins/ebook-store-core.php` | must-use plugin |
| `public/wp-content/themes/ebookstore-child/` | child theme (Phase 3) |
| `public/wp-content/plugins/ebook-dummy-gateway/` | test-only gateway (Phase 5) |
