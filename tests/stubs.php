<?php
/**
 * WordPress function stubs for running LIKE-Json outside a real WordPress
 * installation. Behavior-neutral for the unit tests in tests/run-tests.php.
 *
 * @package LIKE_Json_Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}

if ( ! defined( 'WP_Http' ) ) {
	define( 'WP_Http', 'WP_Http' );
}

/**
 * WordPress global option store.
 *
 * @var array
 */
$GLOBALS['__wp_options'] = array();

/**
 * WordPress network option store.
 *
 * @var array
 */
$GLOBALS['__wp_site_options'] = array();

/**
 * WordPress HTTP responses queue for tests.
 *
 * @var array
 */
$GLOBALS['__http_queue'] = array();

/**
 * WordPress filters registry.
 *
 * @var array
 */
$GLOBALS['__filters'] = array();

function add_filter( $tag, $callback ) {
	$GLOBALS['__filters'][ $tag ][] = $callback;
}

function remove_filter( $tag, $callback ) {
	if ( isset( $GLOBALS['__filters'][ $tag ] ) ) {
		foreach ( $GLOBALS['__filters'][ $tag ] as $k => $cb ) {
			if ( $cb === $callback ) {
				unset( $GLOBALS['__filters'][ $tag ][ $k ] );
			}
		}
	}
}

function apply_filters( $tag, $value ) {
	if ( isset( $GLOBALS['__filters'][ $tag ] ) ) {
		foreach ( $GLOBALS['__filters'][ $tag ] as $cb ) {
			$value = call_user_func( $cb, $value );
		}
	}
	return $value;
}

function get_option( $option, $default = false ) {
	return isset( $GLOBALS['__wp_options'][ $option ] ) ? $GLOBALS['__wp_options'][ $option ] : $default;
}

function update_option( $option, $value ) {
	$GLOBALS['__wp_options'][ $option ] = $value;
	return true;
}

function delete_option( $option ) {
	unset( $GLOBALS['__wp_options'][ $option ] );
	return true;
}

function get_site_option( $option, $default = false ) {
	return isset( $GLOBALS['__wp_site_options'][ $option ] ) ? $GLOBALS['__wp_site_options'][ $option ] : $default;
}

function update_site_option( $option, $value ) {
	$GLOBALS['__wp_site_options'][ $option ] = $value;
	return true;
}

function delete_site_option( $option ) {
	unset( $GLOBALS['__wp_site_options'][ $option ] );
	return true;
}

/**
 * HTTP response queue: each request pops the next queued response.
 * If none queued, returns an error (simulating network failure).
 *
 * @param string $url  URL.
 * @param array  $args Request args.
 * @return array|WP_Error
 */
function wp_safe_remote_get( $url, $args = array() ) {
	$GLOBALS['__last_request'] = array(
		'url'  => $url,
		'args' => $args,
	);
	if ( ! empty( $GLOBALS['__http_queue'] ) ) {
		$response = array_shift( $GLOBALS['__http_queue'] );
		return $response;
	}
	return new WP_Error( 'http_request_failed', 'No response queued.' );
}

function wp_remote_retrieve_body( $response ) {
	if ( is_wp_error( $response ) ) {
		return '';
	}
	if ( is_array( $response ) && isset( $response['body'] ) ) {
		return $response['body'];
	}
	return '';
}

function wp_remote_retrieve_response_code( $response ) {
	if ( is_wp_error( $response ) ) {
		return 500;
	}
	if ( is_array( $response ) && isset( $response['response']['code'] ) ) {
		return (int) $response['response']['code'];
	}
	return 200;
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

function get_transient( $key ) {
	return isset( $GLOBALS['__wp_transients'][ $key ] ) ? $GLOBALS['__wp_transients'][ $key ] : false;
}

function set_transient( $key, $value, $ttl ) {
	$GLOBALS['__wp_transients'][ $key ] = $value;
	return true;
}

function delete_transient( $key ) {
	unset( $GLOBALS['__wp_transients'][ $key ] );
	return true;
}

function is_multisite() {
	return ! empty( $GLOBALS['__is_multisite'] );
}

function wp_parse_url( $url, $component = -1 ) {
	// Mirror WordPress's wp_parse_url(): parse_url() is a core PHP function
	// and the URL is small/trusted (admin-configured or plugin-built).
	return parse_url( (string) $url, $component );
}

function trailingslashit( $string ) {
	return rtrim( (string) $string, '/\\' ) . '/';
}

function esc_url( $url ) {
	$url = (string) $url;
	// Reject javascript:, data: (except allowed), vbscript: etc.
	if ( preg_match( '#^(javascript|vbscript|data):#i', ltrim( $url ) ) ) {
		return '';
	}
	return filter_var( $url, FILTER_SANITIZE_URL );
}

function esc_url_raw( $url ) {
	$url = (string) $url;
	if ( preg_match( '#^(javascript|vbscript|data):#i', ltrim( $url ) ) ) {
		return '';
	}
	return filter_var( $url, FILTER_SANITIZE_URL );
}

function esc_attr( $text ) {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

function esc_html( $text ) {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

function wp_is_valid_utf8( $str ) {
	return 1 === preg_match( '//u', $str );
}

function sanitize_text_field( $text ) {
	return trim( wp_strip_all_tags( (string) $text ) );
}

function sanitize_key( $key ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $key ) );
}

function absint( $maybeint ) {
	return abs( (int) $maybeint );
}

function wp_strip_all_tags( $string ) {
	return strip_tags( $string );
}

function wp_parse_args( $args, $defaults = '' ) {
	if ( is_object( $args ) ) {
		$parsed_args = get_object_vars( $args );
	} elseif ( is_array( $args ) ) {
		$parsed_args =& $args;
	} else {
		wp_parse_str( $args, $parsed_args );
	}
	if ( is_array( $defaults ) ) {
		return array_merge( $defaults, $parsed_args );
	}
	return $parsed_args;
}

function wp_parse_str( $string, &$array ) {
	parse_str( $string, $array );
}

function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
	$atts = (array) $atts;
	$out  = array();
	foreach ( $pairs as $name => $default ) {
		if ( array_key_exists( $name, $atts ) ) {
			$out[ $name ] = $atts[ $name ];
		} else {
			$out[ $name ] = $default;
		}
	}
	return $out;
}

function add_shortcode( $tag, $callback ) {
	$GLOBALS['__shortcodes'][ $tag ] = $callback;
}

function do_shortcode( $content ) {
	// Minimal shortcode processing for [collapsibles]/[collapse] used by accordion.
	$content = preg_replace( '/\[collapsibles\]/', '<div class="accordion">', $content );
	$content = preg_replace( '/\[collapse title="([^"]*)"\]/', '<details><summary>$1</summary>', $content );
	$content = preg_replace( '/\[\/collapse\]/', '</details>', $content );
	$content = preg_replace( '/\[\/collapsibles\]/', '</div>', $content );
	return $content;
}

function do_blocks( $content ) {
	$GLOBALS['__last_do_blocks'] = $content;
	return $content;
}

function wp_json_encode( $data, $flags = 0, $depth = 512 ) {
	return json_encode( $data, $flags, $depth );
}

function wp_unique_id( $prefix = '' ) {
	$GLOBALS['__wp_unique_id'] = isset( $GLOBALS['__wp_unique_id'] )
		? $GLOBALS['__wp_unique_id'] + 1
		: 1;
	return $prefix . $GLOBALS['__wp_unique_id'];
}

/**
 * Minimal registry stub for testing optional block integrations.
 */
class WP_Block_Type_Registry {
	public static function get_instance() {
		return new self();
	}

	public function is_registered( $block_name ) {
		return ! empty( $GLOBALS['__registered_blocks'][ $block_name ] );
	}
}

function load_plugin_textdomain() {}

function plugin_dir_path( $file ) {
	return trailingslashit( dirname( $file ) );
}

function plugin_dir_url( $file ) {
	return 'https://example.test/wp-content/plugins/like-json/';
}

function plugin_basename( $file ) {
	return basename( $file );
}

function add_action() {}
function add_options_page() { return 'toplevel_page_like-json'; }
function add_submenu_page() { return 'settings_page_like-json'; }
function register_setting() {}
function settings_fields() {}
function submit_button() {}
function wp_nonce_field() {}
function check_admin_referer() {}
function current_user_can( $cap ) { return true; }
function wp_die( $message ) { echo $message; exit; }
function register_activation_hook() {}
function register_deactivation_hook() {}
function register_uninstall_hook() {}
function register_block_type() {}

function esc_html__( $text, $domain = null ) { return esc_html( $text ); }
function esc_attr__( $text, $domain = null ) { return esc_attr( $text ); }
function esc_html_e( $text, $domain = null ) { echo esc_html( $text ); }

function __( $text, $domain = null ) { return $text; }

function wp_safe_redirect( $location ) {
	$GLOBALS['__redirect'] = $location;
}

function network_admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/network/' . ltrim( $path, '/' );
}

function wp_unslash( $value ) {
	return $value;
}

/**
 * Minimal WP_Error class.
 */
class WP_Error {
	public $errors = array();

	public function __construct( $code = '', $message = '' ) {
		if ( '' !== $code ) {
			$this->errors[ $code ] = array( $message );
		}
	}

	public function get_error_message() {
		$first = reset( $this->errors );
		return is_array( $first ) ? $first[0] : '';
	}

}

/**
 * Queue a successful HTTP response.
 *
 * @param string $body     Body.
 * @param int    $code     HTTP status.
 * @param array  $response Response headers.
 */
function test_queue_response( $body, $code = 200, $response = array() ) {
	$GLOBALS['__http_queue'][] = array(
		'body'     => $body,
		'response' => array_merge(
			array( 'code' => $code, 'message' => 'OK' ),
			$response
		),
	);
}

/**
 * Queue a network-error response (WP_Error).
 */
function test_queue_response_error() {
	$GLOBALS['__http_queue'][] = new WP_Error( 'http_request_failed', 'Simulated network error.' );
}

/**
 * Queue a timeout response.
 */
function test_queue_response_timeout() {
	$GLOBALS['__http_queue'][] = new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out.' );
}

/**
 * Reset the test state.
 */
function test_reset() {
	$GLOBALS['__wp_options']    = array();
	$GLOBALS['__wp_site_options'] = array();
	$GLOBALS['__http_queue']    = array();
	$GLOBALS['__wp_transients'] = array();
	$GLOBALS['__is_multisite']  = false;
	$GLOBALS['__filters']       = array();
	$GLOBALS['__registered_blocks'] = array();
	$GLOBALS['__last_do_blocks'] = '';
	$GLOBALS['__wp_unique_id']   = 0;

	// Default test data source: same host as the example.test URLs used in
	// the HTTP tests, so the same-origin lock accepts them.
	update_option( \LIKE_Json\Config\Settings::OPTION_NAME, array( 'source_url' => 'https://example.test/json/' ) );
}
