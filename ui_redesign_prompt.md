# eBook Store — UI Redesign Plan (Phases 8–12)

## 0. Context and rules (READ FIRST)

This plan extends `ebook_prompt.md`. Phases 0–7 are complete and working (WooCommerce store, USD/GBP currency, dummy payment gateway, SMTP email delivery, `.env` configuration). An initial version is deployed on Hostinger at ebookstore.tech; all work here is done **locally** and deployed later.

All rules from `ebook_prompt.md` still apply: one phase at a time, stop after each phase with a report, wait for "next", English communication, English-only website, never edit WordPress core or third-party plugins/themes, everything changeable in `.env` or the admin, commit after each phase.

### The most important rule: do not break existing work

These must keep working exactly as before, and must be re-tested at the end of every phase:
- Purchase flow: add to cart → checkout → dummy payment success → order completed → download email with working PDF link
- Dummy payment failure → order Failed → no download link
- USD/GBP currency switching and the "you will be charged in" notice at checkout
- Simplified checkout fields
- `.env` loading, SMTP settings, download limits/expiry
- The dummy gateway plugin stays fully separate and **must not be modified**

### Where code goes

- Visual design, templates, CSS, JS, animations → child theme `ebookstore-child`
- Banner/carousel admin, homepage section logic, demo data seeding → our own mu-plugin `ebook-store-core` (organized in separate files inside `mu-plugins/ebook-store-core/`)
- `ebook-dummy-gateway` → no changes at all
- Third-party plugins → configuration only, never file edits

---

## Phase 8 — Impact analysis, research and design proposal (NO code changes)

This phase only investigates and proposes. Do not change any project file except `PROGRESS.md`.

1. **Read the current code:** `ebook_prompt.md`, `PROGRESS.md`, the child theme, `ebook-store-core`, and the dummy gateway. Understand how the homepage, product grid, "Buy Now" flow, checkout customizations and currency switcher currently work.
2. **Impact report.** For each planned change (design refresh, carousel, homepage sections, banner admin, 50 demo products, animations), state:
   - which files will be created or changed (child theme / mu-plugin)
   - whether any existing custom code must change, and why
   - whether the dummy gateway is affected (expected: no)
   - risks to existing features (checkout styling, JS conflicts with WooCommerce or the currency plugin, demo data mixing with real data, performance) and how each risk will be prevented
   - an honest verdict: is this safe to do? Are there any changes you recommend against?
3. **Design research.** Use web search to study 5–8 well-designed modern online bookstores and eBook/digital product stores. Summarize in a short list what makes them look professional (layout, hero, typography, card design, spacing, trust signals, micro-interactions). Take inspiration only; never copy layouts, text, logos or images.
4. **Design proposal**, written for a non-designer:
   - homepage structure from top to bottom (see Phase 9–11 for required sections)
   - whether the current color palette and fonts should stay or be refined (show old vs. new hex values if changing; keep the brand feel)
   - the animation list (see Phase 9 rules) with one sentence each
   - **banner admin approach:** compare (a) a lightweight custom "Hero Slides" feature in `ebook-store-core` using the built-in Media Library, vs. (b) a maintained free slider plugin. Recommend one with reasons (performance, matching the design, ease of use for the client, maintenance)
   - **image sources:** where free, commercially usable images will come from (e.g. Unsplash, Pexels — verify their current license), and the plan for book covers (generated original cover designs, no real book covers)
5. **Safety setup plan:** confirm you will create a git branch `feature/ui-redesign`, a git tag `before-ui-redesign`, and a database backup (`wp db export backups/before-ui-redesign.sql`, ignored by git) before any code changes in Phase 9.
6. **STOP.** Give the report and ask the user clearly: "Is it okay to continue with this plan?" plus any decisions you need (banner approach, palette changes). Do not continue until the user confirms.

---

## Phase 9 — Design refresh and homepage layout

Start only after the user approves Phase 8. First: create the branch, tag and DB backup from Phase 8 step 5, and confirm them in the report.

1. Apply the approved design system (colors, typography, spacing scale, shadows, radius) as CSS custom properties in the child theme.
2. Redesign the **header** (logo/store name, navigation, currency switcher, cart icon with item count, mobile menu) and **footer** (links and support email from `.env`, social links, short trust text).
3. Redesign **product cards**: cover, title, author, price, short description, "View Details" and "Buy Now", optional badge ("Bestseller", "New").
4. Redesign the **product details page**, cart and checkout to match. Checkout must stay simple and clearly show the payment currency.
5. Add **trust elements**, e.g. "Instant PDF download", "Secure checkout", "Delivered to your email", in a clean icon row.

### Animation rules

- Subtle and purposeful only: fade/slide-in of sections on scroll, gentle hover lift and shadow on cards and buttons, smooth carousel transitions, a small "added to cart" feedback.
- No flashing, bouncing, autoplaying video, parallax overload, or anything that delays content or checkout.
- CSS transitions and a small vanilla JS (`IntersectionObserver`) are preferred. Any library must be small, maintained, served locally from the child theme, and approved in Phase 8.
- Respect `prefers-reduced-motion` (disable animations for users who ask for it).
- No layout shift: reserve space for images; lazy-load images below the fold.

**Deliverable:** new look across all pages; all existing features re-tested.

---

## Phase 10 — Hero banner / carousel with admin upload

1. Build the approved banner approach from Phase 8.
2. Admin side (if custom): a "Hero Slides" menu in WP admin where the client can add, edit, reorder, enable/disable and delete slides. Each slide has: desktop image, optional mobile image, heading, subheading, button text, button link. Images are chosen from the WordPress Media Library. Add simple help text in the admin screen, in plain English.
3. Front end: full-width hero at the top of the homepage. With one active slide it shows a static banner; with several it becomes a carousel with arrows, dots, swipe on mobile, keyboard support, and pause on hover. Autoplay interval is configurable (store it in `.env` or a simple admin setting).
4. If no slides exist, show a good-looking default hero so the homepage never looks broken.
5. Seed 3 attractive starter slides with suitable free images (optimized, WebP where possible) so the client sees a finished result and can replace them later.
6. Record every external image source, author and license in `IMAGE_CREDITS.md`.

**Deliverable:** working carousel, manageable from admin.

---

## Phase 11 — Demo catalog (50 products) and homepage sections

1. **Seed about 50 demo eBooks** via a WP-CLI command in `ebook-store-core` (e.g. `wp ebookstore seed-demo`):
   - realistic but **fictional** English titles, authors and descriptions, across 6–8 categories (e.g. Business, Self-Improvement, Technology, Fiction, Health & Fitness, Finance, Cooking, Travel)
   - generated original cover images (consistent, professional style, 2:3, optimized)
   - prices in a realistic range; Virtual, Downloadable, Sold individually, with a demo PDF attached
   - some marked as Featured, varied publish dates for "New Arrivals", and demo sales counts so "Bestsellers" has data
   - every demo item tagged with meta `_ebookstore_demo = 1`
   - the command is safe to run twice (no duplicates)
   - add `wp ebookstore remove-demo` that deletes **only** demo products, their images and files — never real products or orders
2. **Homepage sections** (below the hero), each a reusable block in the child theme, each with a "View all" link:
   - Bestsellers (by WooCommerce sales count)
   - Featured / Editor's Picks (WooCommerce featured flag)
   - New Arrivals (latest products)
   - Browse by Category (category cards)
   - optionally one promotional strip (e.g. "Instant download, read on any device")
   Use horizontal sliders on mobile and grids on desktop. Section titles and on/off switches should be manageable from admin or `.env`.
3. Make sure shop and category pages handle 50+ products well (pagination, sorting, category filter).
4. **Important:** demo sales numbers are for local testing and client preview only. In the report, remind the user that demo data must be removed (or approved by the client) before deploying to the live site, because fake sales counts on a real store are misleading.

**Deliverable:** rich homepage with real-looking content; demo data removable with one command.

---

## Phase 12 — Full regression test, polish and deploy notes

1. Re-test everything listed in "The most important rule" above, in both currencies, on mobile, tablet and desktop.
2. Check performance: image sizes, number of scripts/styles loaded, no console errors, no PHP warnings in `debug.log`.
3. Accessibility basics: contrast, focus states, alt text, keyboard use of the carousel.
4. Update `DEPLOY.md` with what is new: how to deploy the redesign, how the client manages slides, how to remove demo data, and `IMAGE_CREDITS.md`.
5. Update `PLUGINS.md` if any plugin or library was added.
6. Report and ask the user whether to merge `feature/ui-redesign` into `main`. Merge only after confirmation.

**Rollback (tell the user in every report):** to undo everything, `git checkout main` and import `backups/before-ui-redesign.sql`.