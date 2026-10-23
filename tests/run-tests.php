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
	define( 'LIKE_JSON_VERSION', '2.0.0' );
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
t_eq( 99, $clean['timeout'], 'sanitize keeps timeout 99' );
t_eq( 10, $clean['crawl_limit'], 'sanitize clamps crawl_limit 500 to max 10' );

// Trailing/leading whitespace in source_url must be trimmed, not URL-encoded.
$clean_ws = Settings::sanitize(
	array(
		'source_url' => '  https://like35.like.e-technik.uni-erlangen.de/json/  ',
	)
);
t_eq( 'https://like35.like.e-technik.uni-erlangen.de/json/', $clean_ws['source_url'], 'sanitize trims whitespace around source_url' );
t_assert( false === strpos( $clean_ws['source_url'], '%20' ), 'sanitize does not URL-encode whitespace into source_url' );

$ua = Settings::default_user_agent();
t_assert( false !== stripos( $ua, 'FAU-LIKE-like-json/' ), 'User-Agent starts with FAU-LIKE-like-json/' );
t_assert( false !== strpos( $ua, 'mailto:like-admin@fau.de' ), 'User-Agent contains the contact mailto' );
t_assert( false !== strpos( $ua, 'https://www.like.tf.fau.de/' ), 'User-Agent contains the info URL' );

// Multisite precedence: site option overrides network option.
test_reset();
$GLOBALS['__is_multisite'] = true;
t_eq( 10, Settings::get( 'timeout' ), 'multisite: default timeout when no options set' );
update_site_option( Settings::NETWORK_OPTION_NAME, array( 'timeout' => 5 ) );
t_eq( 5, Settings::get( 'timeout' ), 'multisite: network-only value is used' );
update_option( Settings::OPTION_NAME, array( 'timeout' => 25 ) );
t_eq( 25, Settings::get( 'timeout' ), 'multisite: site value overrides network value' );
delete_option( Settings::OPTION_NAME );
t_eq( 5, Settings::get( 'timeout' ), 'multisite: falls back to network after site deleted' );
$GLOBALS['__is_multisite'] = false;
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

// Invalid JSON -> empty.
test_reset();
$client = new Client();
test_queue_response( 'not-json', 200 );
t_eq( '', $client->get_json( 'bachelorarbeiten' ), 'invalid JSON -> empty string' );

// HTTPS fallback to HTTP: first request fails, second succeeds.
test_reset();
$client = new Client();
test_queue_response_error(); // HTTPS fails.
test_queue_response( '[{"id":"2"}]', 200 ); // HTTP succeeds.
$data = $client->get_json( 'bachelorarbeiten' );
t_eq( array( array( 'id' => '2' ) ), $data, 'HTTPS fails, falls back to HTTP' );

// User-Agent and timeout are set on the request.
test_reset();
$client = new Client();
test_queue_response( '[]', 200 );
$client->get_json( 'bachelorarbeiten' );
$last = $GLOBALS['__last_request'];
t_assert( isset( $last['args']['user-agent'] ) && false !== strpos( $last['args']['user-agent'], 'FAU-LIKE-like-json/' ), 'User-Agent is set on requests' );
t_eq( 10, $last['args']['timeout'], 'timeout is set on requests' );

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

$acc_html = $formatter->accordion( array( $record ), 'link' );
t_assert( false === strpos( $acc_html, '<script>' ), 'accordion: no raw script' );
t_assert( false !== strpos( $acc_html, '&lt;script&gt;' ), 'accordion: script escaped as entity' );
t_assert( false !== strpos( $acc_html, '<details>' ), 'accordion: emits accordion structure (via [collapse] shortcode)' );

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
