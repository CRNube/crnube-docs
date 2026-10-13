# CRNube Docs — notes for Claude

Commercial-grade WordPress document library for Costa Rican cooperatives (free core now, Pro add-on later). The user writes in Spanish; reply in Spanish.

## Conventions
- PHP 8.1+ (readonly, enums, `never`), `declare(strict_types=1)`, namespace `CRNube\Docs`, PSR-4 in `src/` loaded by the autoloader in `crnube-docs.php` (no Composer at runtime). 4-space indent.
- Prefix `crnd_` for options, meta (`_crnd_*`), hooks, handles, CSS classes (`.crnd-*`). Text domain `crnube-docs`.
- Source strings in English; every new string must be added to `languages/crnube-docs-es_CR.l10n.php`, then run `composer translations` (fails if one is missing).
- Never register `'intval'` (or other internal functions) as a meta sanitize callback: they receive 4 args. Use closures.
- Templates hold markup only; logic goes in `Frontend\View`. Escape everything on output.
- Run `composer check` (PHPCS security rules + PHPStan level 6) before finishing; both must pass.

## Architecture
- `Domain/`: CPT `crnd_document`, taxonomies `crnd_category` (hierarchical) and `crnd_year` (auto from `_crnd_year` meta or publication year), `DocumentRepository` builds the tree in 2 queries and caches it in a transient keyed by `Support\Cache::version()`.
- `Download/DownloadController`: all file links go through `/doc-download/{id}/{view|download}/`; asks `Access\AccessPolicy` (public for now; the protected area will plug in via the `crnd_access_policy` filter).
- `Frontend/`: shortcode `[crnube_docs]` → `Renderer` → `templates/*.php`; vanilla ES module `assets/js/frontend.js` (no jQuery), CSS custom properties.
- `Admin/`: classic metaboxes (sites use Classic Editor), list columns/filters, category order + shortcode column, settings page with live preview.

## Roadmap
1. ✅ Core. 2. React document library in admin + bulk drag & drop upload. 3. More layouts (sections, grid, list) + exportable presets. 4. Protected area (groups with expiry, OTP login, 2FA per group, download log, private storage). 5. FIVO Docs migrator, Gutenberg block, Elementor widget. 6. Free/Pro split + licensing.
