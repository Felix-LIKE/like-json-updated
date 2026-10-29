=== LIKE-Json ===
Contributors: like
Tags: abschlussarbeiten, block, shortcode, university, research
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 2.1.0
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

= 2.1.0 =
* Security: SSRF hardening - the plugin now only ever fetches HTTPS URLs from
  the host configured in "source_url" (same-origin lock). This applies to the
  JSON endpoints and to base64 PDF embeds alike, and prevents a malicious
  source_url or pdf field from turning the server into a proxy for arbitrary
  targets. The host stays freely configurable, so pointing the plugin at a
  new or additional LIKE server requires no code change - just the setting.
* Security: The "source_url" setting now rejects non-HTTPS URLs (https only).
* Security: On update, a stale "http://" source_url stored by older versions is
  automatically rewritten to "https://" (one-time migration), so upgrading never
  silently breaks data collection.
* Security: base64 PDFs are only embedded when the fetched body actually starts
  with the PDF magic bytes ("%PDF-"); anything else falls back to a plain link.
* Security: Hard response size caps (JSON 2 MiB, PDFs 5 MiB, absolute guard
  rail 10 MiB) are enforced at request time; oversized bodies are rejected.

= 2.0.1 =
* Security: HTTPS failure no longer falls back to plain HTTP (removes a downgrade risk).
* Change: Settings are now strictly site-local; the network-wide settings page and
  the network option were removed (the plugin is used on individual sites).
  This is a deliberate deviation from the RRZE SPEC "Multisite is mandatory"
  (section 1.2.1 / section 14) - see README.md for the rationale.
* Change: Remote request timeout is capped at 1-60 seconds (cannot be raised
  beyond 60s via a filter or a legacy option).
* New: Specific visitor-safe error messages for timeouts and unreachable sources
  (with role="alert"), instead of a generic empty message.
* Hardening: Unknown or non-string task names are rejected before any request.
* Fix: Reverted an accidental edit in the readme.txt header.
* Translations: Regenerated .po/.mo for the new strings (en_US, de_DE, de_DE_formal).

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
