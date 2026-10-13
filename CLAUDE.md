# CRNube Docs — notes for Claude

Commercial-grade WordPress document library for Costa Rican cooperatives (free core now, Pro add-on later). The user writes in Spanish; reply in Spanish.

## Conventions
- PHP 8.1+ (readonly, enums, `never`), `declare(strict_types=1)`, namespace `CRNube\Docs`, PSR-4 in `src/` loaded by the autoloader in `crnube-docs.php` (no Composer at runtime). 4-space indent.
- Prefix `crnd_` for options, meta (`_crnd_*`), hooks, handles, CSS classes (`.crnd-*`). Text domain `crnube-docs`.
- Source strings in English; every new string must be added to `languages/crnube-docs-es_CR.l10n.php`, then run `composer translations` (fails if one is missing).
- Never register `'intval'` (or other internal functions) as a meta sanitize callback: they receive 4 args. Use closures.
- Templates hold markup only; logic goes in `Frontend\View`. Escape everything on output.
- Run `composer check` (PHPCS security rules + PHPStan level 6) and `npm run lint:js` before finishing; all must pass. After JS changes run `npm run build` — `build/` is committed (sites have no Node).
- JS strings: `__( '…', 'crnube-docs' )` with single quotes (bin/translations.php extracts them by regex) and add them to the es_CR file like PHP ones.
- The user commits and pushes; never run git commit/push.
- Uploads must pass `FileTypes::contentMatches()` (real content vs extension); WordPress itself only inspects images.

## Architecture
- `Domain/`: CPT `crnd_document`, taxonomies `crnd_category` (hierarchical) and `crnd_year` (auto from `_crnd_year` meta or publication year), `DocumentRepository` builds the tree in 2 queries and caches it in a transient keyed by `Support\Cache::version()`.
- `Download/DownloadController`: all file links go through `/doc-download/{id}/{view|download}/`; asks `Access\AccessPolicy` (public for now; the protected area will plug in via the `crnd_access_policy` filter).
- `Frontend/`: shortcode `[crnube_docs]` → `Renderer` → `templates/*.php`; vanilla ES module `assets/js/frontend.js` (no jQuery), CSS custom properties.
- `Admin/`: `LibraryPage` owns the "Documents" menu (slug `crnd-library`; the CPT sits under it via `show_in_menu`), classic metaboxes (sites use Classic Editor), list columns/filters, category order + shortcode column, settings page with live preview.
- `Domain/CategoryOrder`: per-category ordering mode (term meta `crnd_sort`: '' inherit | date_desc | date_asc | title_asc | title_desc | manual) and manual order (term meta `crnd_doc_order`, list of IDs). Used by both the public tree and the admin list (`orderby=site`), so admin order = site order.
- `Rest/`: `crnd/v1` documents + categories controllers used by the library app (`assets/src/library`, React with `@wordpress/components`, built by `@wordpress/scripts`).

## Roadmap
1. ✅ Core. 2. ✅ React document library in admin + bulk drag & drop upload. 2.5 ✅ Drag to order documents (per-category mode) and categories (reorder/nest). 3. More layouts (sections, grid, list) + exportable presets. 4. Protected area (groups with expiry, OTP login, 2FA per group, download log, private storage). 5. FIVO Docs migrator, Gutenberg block, Elementor widget. 6. Free/Pro split + licensing.
