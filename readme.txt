=== LIKE-Json ===
Contributors: like
Tags: abschlussarbeiten, block, shortcode, university, research
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 2.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Displays student works (theses) from the LIKE database as a Gutenberg block or legacy shortcode.

== Description ==

LIKE-Json displays the student works of the Chair of Information Technology with
the focus on Communication Electronics (LIKE) from the LIKE database on a
WordPress website.

* Display as a Gutenberg block (recommended) or as a legacy shortcode.
* Supported categories: Bachelor theses, Master theses, research internships,
  project works, other works, and all categories combined.
* Display as a list or as an expandable accordion.
* Filter by ID, supervisor and assignment status.
* HTTPS data source with a bundled intermediate certificate (GEANT TLS ECC 1).

For the German end-user documentation see the README.md in the repository root.

== Installation ==

1. Download the plugin ZIP archive and extract it to `wp-content/plugins/like-json`.
2. Activate the plugin in the "Plugins" menu.
3. Add the "Studentische Arbeiten (LIKE)" block to a page or post, or use the
   `[like]` shortcode.

== Frequently Asked Questions ==

= Why do I not see any entries? =

Make sure the JSON source under Settings -> LIKE-Json is reachable. By default
`https://like35.like.e-technik.uni-erlangen.de/json/` is used.

= What does ressources="link" mean? =

With `ressources="link"` PDF files are provided as direct links instead of being
embedded as base64 data. This saves bandwidth and is the recommended setting.

== Changelog ==

= 2.0.0 =
* New: Gutenberg block "Studentische Arbeiten (LIKE)" (server-side rendered).
* New: Hardened remote requests (HTTP API, timeout, error handling, RRZE-compliant User-Agent).
* New: Full internationalization (en_US, de_DE, de_DE_formal).
* Security: Full escaping of all remote data output.
* Security: Validation and sanitization of the settings.
* Multisite: Clean separation of network-wide settings.
* Legacy `[like]` shortcode kept for backwards compatibility.

= 1.6.1 =
* HTTPS data source by default, configurable URL, bundled intermediate certificate.

= 1.6.0 =
* PHP 8.x compatibility (dynamic properties, stripos fix, JSON guards).
