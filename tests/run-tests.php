<?php
/**
 * Automated test runner for LIKE-Json.
 *
 * Runs without a full WordPress install using the WP stubs in tests/stubs.php.
 * Usage: php tests/run-tests.php
 *
 * Covers:
 *   - Escaping of remote data (XSS prevention)
 *   - JSON validation / empty data handling
 *   - HTTP error handling (non-2xx, network error)
 *   - Advisor filter (position-0 fix)
 *   - Status filter
 *   - ID filter
 *   - Format rendering (list + accordion)
 *   - PDF link mode vs base64 mode
 *   - Settings sanitization
 *   - User-Agent is set on requests
 *   - Timeout is set on requests
 *
 * @package LIKE_Json_Tests
 */

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

require_once __DIR__ . '/stubs.php';

// Constants normally defined in like-json.php; define them here for the test harness.
if ( ! defined( 'LIKE_JSON_VERSION' ) ) {
	define( 'LIKE_JSON_VERSION', '2.1.0' );
}
if ( ! defined( 'LIKE_JSON_DIR' ) ) {
	define( 'LIKE_JSON_DIR', dirname( __DIR__ ) . '/' );
}

require_once dirname( __DIR__ ) . '/includes/autoload.php';

use LIKE_Json\API\Client;
use LIKE_Json\API\Formatter;
use LIKE_Json\API\Works;
use LIKE_Json\Config\Settings;
use LIKE_Json\Shortcode;

$GLOBALS['__tests_run']     = 0;
$GLOBALS['__tests_failed']  = 0;
$GLOBALS['__tests_passed']  = 0;

/**
 * Assert a condition.
 *
 * @param mixed  $condition Condition.
 * @param string $message   Message.
 */
function t_assert( $condition, $message ) {
	$GLOBALS['__tests_run']++;
	if ( $condition ) {
		$GLOBALS['__tests_passed']++;
		echo "  [OK] $message\n";
	} else {
		$GLOBALS['__tests_failed']++;
		echo "  [FAIL] $message\n";
	}
}

/**
 * Assert equality (strict).
 *
 * @param mixed  $expected Expected.
 * @param mixed  $actual   Actual.
 * @param string $message  Message.
 */
function t_eq( $expected, $actual, $message ) {
	$ok = ( $expected === $actual );
	$GLOBALS['__tests_run']++;
	if ( $ok ) {
		$GLOBALS['__tests_passed']++;
		echo "  [OK] $message\n";
	} else {
		$GLOBALS['__tests_failed']++;
		echo "  [FAIL] $message\n";
		echo "        expected: " . var_export( $expected, true ) . "\n";
		echo "        actual:   " . var_export( $actual, true ) . "\n";
	}
}

/**
 * A sample malicious record for XSS testing.
 *
 * @return array
 */
function sample_record_malicious() {
	return array(
		'id'          => '999',
		'titel'       => '<script>alert(1)</script>Titel',
		'beschreibung'=> 'Beschreibung <img src=x onerror=alert(1)>',
		'kategorien'  => 'Kat <b>fett</b>',
		'voraussetzung'=> 'Vor <a href="javascript:alert(1)">link</a>',
		'betreuer'    => 'Betreuer "quoted"',
		'hs_lehrer'   => 'Prof <i>X</i>',
		'aufgaben'    => 'Aufgabe1/Aufgabe2',
		'pdf'         => 'https://example.test/x.pdf',
	);
}

// ---------------------------------------------------------------- Settings.
echo "== Settings ==\n";

t_assert( Settings::defaults()['timeout'] > 0, 'default timeout is positive' );
t_eq( 'https://like35.like.e-technik.uni-erlangen.de/json/', Settings::get( 'source_url' ), 'default source_url' );
t_eq( 10, Settings::get( 'timeout' ), 'default timeout is 10' );

$clean = Settings::sanitize(
	array(
		'source_url' => 'javascript:alert(1)',
		'timeout'    => '99',
		'crawl_limit'=> '500',
	)
);
t_eq( '', $clean['source_url'], 'sanitize strips javascript: URL (esc_url_raw returns empty)' );
t_eq( 60, $clean['timeout'], 'sanitize clamps timeout 99 to max 60' );
t_eq( 10, $clean['crawl_limit'], 'sanitize clamps crawl_limit 500 to max 10' );

// Trailing/leading whitespace in source_url must be trimmed, not URL-encoded.
$clean_ws = Settings::sanitize(
	array(
		'source_url' => '  https://like35.like.e-technik.uni-erlangen.de/json/  ',
	)
);
t_eq( 'https://like35.like.e-technik.uni-erlangen.de/json/', $clean_ws['source_url'], 'sanitize trims whitespace around source_url' );
t_assert( false === strpos( $clean_ws['source_url'], '%20' ), 'sanitize does not URL-encode whitespace into source_url' );

// SSRF hardening: only https source URLs are accepted (host stays free).
$clean_http = Settings::sanitize( array( 'source_url' => 'http://like35.like.e-technik.uni-erlangen.de/json/' ) );
t_eq( '', $clean_http['source_url'], 'sanitize rejects http:// source_url (https only)' );
$clean_https = Settings::sanitize( array( 'source_url' => 'https://neuer-server.example.org/json/' ) );
t_eq( 'https://neuer-server.example.org/json/', $clean_https['source_url'], 'sanitize accepts any https host (new servers need no code change)' );

$ua = Settings::default_user_agent();
t_assert( false !== stripos( $ua, 'FAU-LIKE-like-json/' ), 'User-Agent starts with FAU-LIKE-like-json/' );
t_assert( false !== strpos( $ua, 'mailto:like-admin@fau.de' ), 'User-Agent contains the contact mailto' );
t_assert( false !== strpos( $ua, 'https://www.like.tf.fau.de/' ), 'User-Agent contains the info URL' );

// Network options never influence site-local plugin settings.
test_reset();
$GLOBALS['__is_multisite'] = true;
update_site_option( 'like_json_network_settings', array( 'timeout' => 5 ) );
t_eq( 10, Settings::get( 'timeout' ), 'multisite: network option is ignored' );
update_option( Settings::OPTION_NAME, array( 'timeout' => 25 ) );
t_eq( 25, Settings::get( 'timeout' ), 'multisite: site-local option is used' );
test_reset();

// ---------------------------------------------------------------- Migration.
echo "\n== Migration (v2.1.0 https source_url repair) ==\n";

// A stale http:// source_url (e.g. left over from the 1.6.x default) is
// repaired to https:// when the plugin is updated to v2.1.0+.
test_reset();
update_option( Settings::VERSION_OPTION_NAME, '2.0.1' ); // pre-2.1.0.
update_option( Settings::OPTION_NAME, array( 'source_url' => 'http://like35.like.e-technik.uni-erlangen.de/json/' ) );
\LIKE_Json\Main::instance()->maybe_migrate();
t_eq( 'https://like35.like.e-technik.uni-erlangen.de/json/', Settings::get( 'source_url' ), 'migration rewrites stale http:// source_url to https://' );
t_eq( LIKE_JSON_VERSION, get_option( Settings::VERSION_OPTION_NAME ), 'migration records current version' );

// An already-correct https source_url is left untouched.
test_reset();
update_option( Settings::VERSION_OPTION_NAME, '2.0.1' );
update_option( Settings::OPTION_NAME, array( 'source_url' => 'https://like35.like.e-technik.uni-erlangen.de/json/' ) );
\LIKE_Json\Main::instance()->maybe_migrate();
t_eq( 'https://like35.like.e-technik.uni-erlangen.de/json/', Settings::get( 'source_url' ), 'migration leaves an https source_url untouched' );

// A custom https host (any future server) is preserved.
test_reset();
update_option( Settings::VERSION_OPTION_NAME, '2.0.1' );
update_option( Settings::OPTION_NAME, array( 'source_url' => 'https://server-b.example.org/json/' ) );
\LIKE_Json\Main::instance()->maybe_migrate();
t_eq( 'https://server-b.example.org/json/', Settings::get( 'source_url' ), 'migration preserves a custom https host (no code change per server)' );

// http:// on a custom host is also repaired (https-only is universal).
test_reset();
update_option( Settings::VERSION_OPTION_NAME, '2.0.1' );
update_option( Settings::OPTION_NAME, array( 'source_url' => 'http://server-b.example.org/json/' ) );
\LIKE_Json\Main::instance()->maybe_migrate();
t_eq( 'https://server-b.example.org/json/', Settings::get( 'source_url' ), 'migration rewrites http:// to https:// on any host' );

// The migration does not re-run once the version is current.
test_reset();
update_option( Settings::VERSION_OPTION_NAME, LIKE_JSON_VERSION );
update_option( Settings::OPTION_NAME, array( 'source_url' => 'http://like35.like.e-technik.uni-erlangen.de/json/' ) );
\LIKE_Json\Main::instance()->maybe_migrate();
t_eq( 'http://like35.like.e-technik.uni-erlangen.de/json/', Settings::get( 'source_url' ), 'migration does not re-run at the current version' );

// A fresh install (no version option) also gets the repair.
test_reset();
update_option( Settings::OPTION_NAME, array( 'source_url' => 'http://like35.like.e-technik.uni-erlangen.de/json/' ) );
\LIKE_Json\Main::instance()->maybe_migrate();
t_eq( 'https://like35.like.e-technik.uni-erlangen.de/json/', Settings::get( 'source_url' ), 'migration repairs source_url on a fresh install with no version marker' );

// After migration, the plugin's Client builds https URLs that pass the
// same-origin lock and fetch data (no regressions in the fetch path).
test_reset();
update_option( Settings::VERSION_OPTION_NAME, '2.0.1' );
update_option( Settings::OPTION_NAME, array( 'source_url' => 'http://like35.like.e-technik.uni-erlangen.de/json/' ) );
\LIKE_Json\Main::instance()->maybe_migrate();
$client = new Client();
test_queue_response( '[{"id":"1"}]', 200 );
t_eq( array( array( 'id' => '1' ) ), $client->get_json( 'bachelorarbeiten' ), 'post-migration get_json builds a valid https URL' );
test_reset();


// --------------------------------------------------------------- HTTP / Client.
echo "\n== Client (HTTP) ==\n";

test_reset();
$client = new Client();

// Network error -> empty.
test_queue_response_error();
t_eq( '', $client->fetch_body( 'https://example.test/json/sonstiges' ), 'network error -> empty string' );

// Non-2xx -> empty.
test_reset();
$client = new Client();
test_queue_response( '{}', 500 );
t_eq( '', $client->fetch_body( 'https://example.test/json/sonstiges' ), 'HTTP 500 -> empty string' );

// Valid JSON, 200.
test_reset();
$client = new Client();
test_queue_response( '[{"id":"1"}]', 200 );
$data = $client->get_json( 'bachelorarbeiten' );
t_eq( array( array( 'id' => '1' ) ), $data, 'get_json returns decoded array' );

// Double-encoded UTF-8 from the remote source is repaired while valid UTF-8 remains intact.
test_reset();
$client = new Client();
test_queue_response( '[{"titel":"FÃ¼r â€žTelemetrieâ€œ","betreuer":"Müller"}]', 200 );
$data = $client->get_json( 'bachelorarbeiten' );
t_eq( 'Für „Telemetrie“', $data[0]['titel'], 'repairs double-encoded UTF-8 in remote JSON' );
t_eq( 'Müller', $data[0]['betreuer'], 'leaves valid UTF-8 unchanged' );

// Repair valid sequences even when another part of the same field is not reversible.
test_reset();
$client = new Client();
test_queue_response( '[{"beschreibung":"MÃ¤use âBATSa"}]', 200 );
$data = $client->get_json( 'bachelorarbeiten' );
t_eq( 'Mäuse âBATSa', $data[0]['beschreibung'], 'repairs valid sequences in partially corrupt text' );

// Invalid JSON -> empty.
test_reset();
$client = new Client();
test_queue_response( 'not-json', 200 );
t_eq( '', $client->get_json( 'bachelorarbeiten' ), 'invalid JSON -> empty string' );

// HTTPS failure must not downgrade to HTTP.
test_reset();
$client = new Client();
test_queue_response_error();
$data = $client->get_json( 'bachelorarbeiten' );
t_eq( '', $data, 'HTTPS failure does not downgrade to HTTP' );
t_eq( 'unavailable', $client->get_last_error(), 'network error is reported as unavailable' );
t_eq( 0, count( $GLOBALS['__http_queue'] ), 'HTTPS failure makes no second HTTP request' );

// Timeout is exposed as a distinct, safe error status.
test_reset();
$client = new Client();
test_queue_response_timeout();
t_eq( '', $client->get_json( 'bachelorarbeiten' ), 'timeout returns an empty result' );
t_eq( 'timeout', $client->get_last_error(), 'timeout is reported separately' );

// SSRF hardening: the same-origin lock rejects off-host and non-HTTPS URLs.
test_reset();
$client = new Client();
test_queue_response( '[{"id":"1"}]', 200 );
t_eq( '', $client->fetch_body( 'https://evil.example.net/json/bachelorarbeiten' ), 'SSRF: off-host URL is rejected (same-origin lock)' );
t_eq( 'unavailable', $client->get_last_error(), 'SSRF: rejected off-host URL reports unavailable' );
t_eq( 1, count( $GLOBALS['__http_queue'] ), 'SSRF: rejected URL consumes no queued response (no HTTP request)' );

test_reset();
$client = new Client();
test_queue_response( '[{"id":"1"}]', 200 );
t_eq( '', $client->fetch_body( 'http://example.test/json/bachelorarbeiten' ), 'SSRF: plain http URL is rejected (https only)' );
t_eq( 1, count( $GLOBALS['__http_queue'] ), 'SSRF: rejected http URL consumes no queued response (no HTTP request)' );

test_reset();
$client = new Client();
test_queue_response( '[{"id":"1"}]', 200 );
t_eq( array( array( 'id' => '1' ) ), $client->get_json( 'bachelorarbeiten' ), 'same-origin https URL is fetched normally' );

// DoS protection: an oversized JSON response is rejected, not buffered.
test_reset();
$client = new Client();
test_queue_response( str_repeat( 'x', Client::MAX_JSON_BYTES + 1 ), 200 );
t_eq( '', $client->fetch_body( 'https://example.test/json/bachelorarbeiten' ), 'oversized JSON response is rejected (size cap)' );
t_eq( 'unavailable', $client->get_last_error(), 'oversized response reports unavailable' );

// The size cap is applied at request time (transport-level streaming hint).
test_reset();
$client = new Client();
test_queue_response( '[]', 200 );
$client->fetch_body( 'https://example.test/json/bachelorarbeiten' );
t_eq( Client::MAX_JSON_BYTES, $GLOBALS['__last_request']['args']['limit_response_size'], 'limit_response_size is set on requests' );

// User-Agent and timeout are set on the request.
test_reset();
$client = new Client();
test_queue_response( '[]', 200 );
$client->get_json( 'bachelorarbeiten' );
$last = $GLOBALS['__last_request'];
t_assert( isset( $last['args']['user-agent'] ) && false !== strpos( $last['args']['user-agent'], 'FAU-LIKE-like-json/' ), 'User-Agent is set on requests' );
t_eq( 10, $last['args']['timeout'], 'timeout is set on requests' );

// A runtime filter or legacy option cannot disable the timeout cap.
test_reset();
update_option( Settings::OPTION_NAME, array( 'timeout' => 90 ) );
$client = new Client();
test_queue_response( '[]', 200 );
$client->get_json( 'bachelorarbeiten' );
t_eq( 60, $GLOBALS['__last_request']['args']['timeout'], 'timeout is capped at 60 seconds at request time' );

// Caching: two calls only one HTTP request.
test_reset();
$client = new Client();
test_queue_response( '[{"id":"3"}]', 200 );
$client->get_json( 'bachelorarbeiten' );
$count_after_first = count( $GLOBALS['__http_queue'] );
$client->get_json( 'bachelorarbeiten' );
$count_after_second = count( $GLOBALS['__http_queue'] );
t_eq( $count_after_first, $count_after_second, 'cached: second call does not re-request (transient)' );

// ------------------------------------------------------------------- Works.
echo "\n== Works (filters) ==\n";

test_reset();
$works = new Works( new Client() );

$records = array(
	array( 'id' => '1', 'betreuer' => 'Adam Kalisz', 'status' => 'zu vergeben', 'titel' => 'A' ),
	array( 'id' => '2', 'betreuer' => 'Bob Fischer', 'status' => 'in Bearbeitung', 'titel' => 'B' ),
	array( 'id' => '3', 'betreuer' => 'Adam Kalisz', 'status' => 'zu vergeben', 'titel' => 'C' ),
);
test_queue_response( json_encode( $records ), 200 );

$filtered = $works->get( 'bachelorarbeiten', '', 'Adam Kalisz' );
t_eq( 2, count( $filtered ), 'advisor filter: "Adam Kalisz" (position-0 match) returns 2' );

test_reset();
$works = new Works( new Client() );
test_queue_response( json_encode( $records ), 200 );
$filtered = $works->get( 'bachelorarbeiten', '', '', 'zu vergeben' );
t_eq( 2, count( $filtered ), 'status filter "zu vergeben" returns 2' );

test_reset();
$works = new Works( new Client() );
test_queue_response( json_encode( $records ), 200 );
$filtered = $works->get( 'bachelorarbeiten', '2' );
t_eq( 1, count( $filtered ), 'id filter "2" returns 1' );
t_eq( '2', $filtered[0]['id'], 'id filter returns the right record' );

// Empty data handling.
test_reset();
$works = new Works( new Client() );
test_queue_response_error();
$data = $works->get( 'bachelorarbeiten' );
t_eq( array(), $data, 'network error -> empty array (no crash)' );

// ---------------------------------------------------------------- Formatting.
echo "\n== Formatting (escaping + output) ==\n";

$formatter = new Formatter();
$record    = sample_record_malicious();

$list_html = $formatter->list( array( $record ), 'link' );
t_assert( false === strpos( $list_html, '<script>' ), 'list: <script> is escaped (no raw script tag)' );
t_assert( false === strpos( $list_html, '<img' ), 'list: <img> tag is escaped (no raw image tag)' );
t_assert( false === strpos( $list_html, 'href="javascript:' ), 'list: javascript: not present as a real href attribute' );
t_assert( false !== strpos( $list_html, '&lt;script&gt;' ), 'list: script escaped as entity' );
t_assert( false !== strpos( $list_html, '<li class="like-json-item"><h2 class="like-json-title like-json-caption">' ), 'list: title is a heading in an outer list item' );
t_assert( false !== strpos( $list_html, '</h2><ul class="like-json-record">' ), 'list: record details are nested below the title' );

$acc_html = $formatter->accordion( array( $record ), 'link' );
t_assert( false === strpos( $acc_html, '<script>' ), 'accordion: no raw script' );
t_assert( false !== strpos( $acc_html, '&lt;script&gt;' ), 'accordion: script escaped as entity' );
t_assert( false !== strpos( $acc_html, '<h2 class="like-json-heading">' ), 'accordion fallback: emits headings' );
t_assert( false === strpos( $acc_html, '[collapse' ), 'accordion fallback: no legacy shortcode is emitted' );

// Active RRZE Elements Blocks uses the current Accordion block structure.
test_reset();
$GLOBALS['__registered_blocks'] = array(
	'rrze-elements/collapsibles' => true,
	'rrze-elements/collapse'     => true,
);
$acc_html = $formatter->accordion( array( $record ), 'link' );
t_assert( false !== strpos( $acc_html, '<!-- wp:rrze-elements/collapsibles -->' ), 'accordion: uses RRZE accordion container block' );
t_assert( false !== strpos( $acc_html, '<!-- wp:rrze-elements/collapse {"title":' ), 'accordion: uses RRZE accordion item block' );
t_assert( false !== strpos( $acc_html, '"jumpName":"like-json-accordion-1-1"' ), 'accordion: provides a unique target ID for each item' );

// PDF link mode.
$pdf_link = $formatter->list( array( $record ), 'link' );
t_assert( false !== strpos( $pdf_link, 'href="https://example.test/x.pdf"' ), 'link mode: direct PDF href' );
t_assert( false === strpos( $pdf_link, 'data:application/pdf' ), 'link mode: no base64 data URI' );

// base64 mode with a successful fetch.
test_reset();
// The pdf URL will be fetched. Queue a PDF response.
test_queue_response( '%PDF-1.4 fake pdf content', 200 );
$base64_html = $formatter->list( array( $record ), 'base64' );
t_assert( false !== strpos( $base64_html, 'data:application/pdf;base64,' ), 'base64 mode: emits data URI' );

// base64 mode with failed fetch -> falls back to plain link.
test_reset();
$base64_fail = $formatter->list( array( $record ), 'base64' );
t_assert( false !== strpos( $base64_fail, 'href="https://example.test/x.pdf"' ), 'base64 mode fetch fail -> falls back to link' );

// SSRF hardening: base64 mode must not fetch off-host or non-HTTPS PDFs.
$record_offhost = array_merge(
	sample_record_malicious(),
	array( 'pdf' => 'https://evil.example.net/x.pdf' )
);
test_reset();
test_queue_response( '%PDF-1.4 attacker', 200 );
$base64_offhost = $formatter->list( array( $record_offhost ), 'base64' );
t_assert( false !== strpos( $base64_offhost, 'href="https://evil.example.net/x.pdf"' ), 'base64: off-host PDF is not fetched, stays a plain link' );
t_assert( false === strpos( $base64_offhost, 'data:application/pdf' ), 'base64: off-host PDF is not embedded (no data URI)' );

$record_http = array_merge(
	sample_record_malicious(),
	array( 'pdf' => 'http://example.test/x.pdf' )
);
test_reset();
test_queue_response( '%PDF-1.4 attacker', 200 );
$base64_http = $formatter->list( array( $record_http ), 'base64' );
t_assert( false !== strpos( $base64_http, 'href="http://example.test/x.pdf"' ), 'base64: http PDF is not fetched, stays a plain link' );
t_assert( false === strpos( $base64_http, 'data:application/pdf' ), 'base64: http PDF is not embedded (no data URI)' );

// The fetched body must actually be a PDF (magic bytes).
$record_htmlbody = array_merge(
	sample_record_malicious(),
	array( 'pdf' => 'https://example.test/not-a-pdf.txt' )
);
test_reset();
test_queue_response( '<html><body>not a pdf</body></html>', 200 );
$base64_htmlbody = $formatter->list( array( $record_htmlbody ), 'base64' );
t_assert( false !== strpos( $base64_htmlbody, 'href="https://example.test/not-a-pdf.txt"' ), 'base64: non-PDF body is not embedded, falls back to link' );
t_assert( false === strpos( $base64_htmlbody, 'data:application/pdf' ), 'base64: non-PDF body produces no data URI' );

// --------------------------------------------------------------- Shortcode.
echo "\n== Shortcode ==\n";

test_reset();
$shortcode = new Shortcode();
$records   = array( array( 'id' => '1', 'titel' => 'Titel', 'beschreibung' => 'Beschr', 'betreuer' => 'X', 'hs_lehrer' => 'Y', 'status' => 'zu vergeben' ) );
test_queue_response( json_encode( $records ), 200 );
$html = $shortcode->render( array( 'task' => 'bachelorarbeiten' ) );
t_assert( false !== strpos( $html, 'Titel' ), 'shortcode renders title' );
t_assert( false !== strpos( $html, 'like-json-list' ), 'shortcode default format is list' );

// Unknown task -> empty message.
test_reset();
$html_empty = $shortcode->render( array( 'task' => 'gibtsnicht' ) );
t_assert( false !== strpos( $html_empty, 'Keine Einträge gefunden' ), 'unknown task -> friendly empty message' );

// Remote errors render a useful, safe visitor message.
test_reset();
test_queue_response_timeout();
$html_timeout = $shortcode->render( array( 'task' => 'bachelorarbeiten' ) );
t_assert( false !== strpos( $html_timeout, 'Zeitlimit überschritten' ), 'timeout -> helpful error message' );
t_assert( false !== strpos( $html_timeout, 'role="alert"' ), 'timeout message is announced to assistive technology' );

// ------------------------------------------------------------------ Summary.
echo "\n========================================\n";
echo 'TESTS RUN:   ' . $GLOBALS['__tests_run'] . "\n";
echo 'PASSED:      ' . $GLOBALS['__tests_passed'] . "\n";
echo 'FAILED:      ' . $GLOBALS['__tests_failed'] . "\n";
echo "========================================\n";

if ( $GLOBALS['__tests_failed'] > 0 ) {
	exit( 1 );
}
echo "ALL TESTS PASSED\n";
exit( 0 );
