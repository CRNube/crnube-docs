=== CRNube Docs ===
Contributors: crnube
Tags: documents, document library, pdf, downloads, file manager
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Document library with multi-level collapsible categories, search, year filter and a design you set per site.

== Description ==

CRNube Docs publishes documents (financial statements, reports, policies, forms) in an organized, accessible library that matches your brand.

* **Categories of any depth** that open and close, with search and a year filter.
* **Four layouts**: collapsible tree, sections, card grid and a filterable list.
* **Your design**: colors, font, corners, density and header style, with eight ready-made brand presets (all WCAG AA) and design export/import between sites.
* **Admin library app**: drag & drop upload of many files at once, inline editing, bulk actions, and drag to reorder documents and categories.
* **Stable links**: every file goes through /doc-download/{id}/, so replacing a file never breaks a link. Views and downloads are counted without storing anything about the visitor.
* **Shortcode, block and Elementor widget**, all with the same options.
* **Import from FIVO Docs**: categories, documents and the shortcodes on your pages, with undo.
* **Secure by default**: the real content of each upload is checked (not only its extension), and nothing loads from other sites.

= Add-on =

[CRNube Docs Pro](https://crnube.com) adds a protected area (access groups with expiry dates, a members' portal with emailed sign-in codes, private file storage) and detailed reports. CRNube Docs works fully without it.

== Installation ==

1. Install and activate the plugin.
2. Go to **Documents › Library**, create categories and drag your files in.
3. Add the "Document library" block, the Elementor widget or `[crnube_docs]` to a page.
4. Adjust colors and layout in **Documents › Settings**.

== Frequently Asked Questions ==

= Does it work with Elementor? =

Yes: there is a "Document library" widget in the CRNube Docs category of the panel.

= Where are the files stored? =

In the WordPress media library, as usual. Deleting the plugin does not delete them.

= Does it collect personal data? =

No. Counters only add one per view or download; bots and repeated clicks are left out using an anonymous key that changes every day and is kept for 30 seconds.

== Changelog ==

= 0.6.0 =
* The protected area and the reports moved to the CRNube Docs Pro add-on. The library keeps view and download counters.
* Extension points for add-ons (access policy, settings cards, library columns and actions).

= 0.5.0 =
* Import from FIVO Docs, block for the block editor and Elementor widget.
