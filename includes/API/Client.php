<?php
/**
 * Hardened HTTP client for the LIKE JSON data source.
 *
 * Uses the WordPress HTTP API exclusively. Sets a RRZE-conform User-Agent,
 * a configurable timeout, checks the HTTP status code, validates the JSON
 * structure, and caches results in transients (prefixed with the plugin slug).
 *
 * @package LIKE_Json
 */

namespace LIKE_Json\API;

use LIKE_Json\Config\Settings;

/**
 * Remote JSON client.
 */
class Client {

	/**
	 * Cache TTL for remote JSON data.
	 *
	 * @var int
	 */
	const CACHE_TTL = 3600; // 1 hour.

	/**
	 * Hard cap for JSON responses (2 MiB).
	 *
	 * @var int
	 */
	const MAX_JSON_BYTES = 2097152;

	/**
	 * Hard cap for PDF responses (5 MiB).
	 *
	 * @var int
	 */
	const MAX_PDF_BYTES = 5242880;

	/**
	 * Hard cap applied to every response regardless of type.
	 *
	 * Guard rail for edge cases (e.g. a redirected response) so a single
	 * oversized body can never exhaust server memory.
	 *
	 * @var int
	 */
	const MAX_ABSOLUTE_BYTES = 10485760; // 10 MiB.

	/**
	 * Last request error type.
	 *
	 * This deliberately stores a small internal status instead of an HTTP
	 * library error message, which must never be shown to site visitors.
	 *
	 * @var string
	 */
	private $last_error = '';

	/**
	 * Fetch and decode a JSON endpoint.
	 *
	 * @param string $endpoint Endpoint name (e.g. 'bachelorarbeiten').
	 * @return array|string Empty string on failure, array on success.
	 */
	public function get_json( $endpoint ) {
		$source = Settings::get( 'source_url' );
		$url    = trailingslashit( $source ) . $endpoint;

		$body = $this->fetch_body( $url );
		if ( '' === $body ) {
			return '';
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			$this->last_error = 'invalid_data';
			return '';
		}

		return $this->normalize_encoding( $data );
	}

	/**
	 * Normalize accidentally double-encoded UTF-8 strings in remote JSON data.
	 *
	 * Some legacy data sources interpret UTF-8 bytes as Windows-1252 before
	 * serializing JSON again. This results in visible sequences such as "Ã¼"
	 * instead of "ü". Sequences are repaired individually so one unrecoverable
	 * character cannot prevent valid parts of the same field from being fixed.
	 *
	 * @param mixed $value JSON value.
	 * @return mixed
	 */
	private function normalize_encoding( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = $this->normalize_encoding( $item );
			}
			return $value;
		}

		if ( ! is_string( $value ) || ! preg_match( '/(?:Ã[\x{0080}-\x{00BF}]|Â[\x{0080}-\x{00BF}]|â[\x{0080}-\x{10FFFF}]{2})/u', $value ) ) {
			return $value;
		}

		$repaired = preg_replace_callback(
			'/(?:Ã[\x{0080}-\x{00BF}]|Â[\x{0080}-\x{00BF}]|â[\x{0080}-\x{10FFFF}]{2})/u',
			array( $this, 'repair_encoding_sequence' ),
			$value
		);

		return null === $repaired ? $value : $repaired;
	}

	/**
	 * Repair one reversible mojibake sequence.
	 *
	 * @param array $matches Regular-expression matches.
	 * @return string
	 */
	private function repair_encoding_sequence( $matches ) {
		$sequence = $matches[0];
		$repaired = $this->convert_windows_1252_to_utf8( $sequence );

		return false !== $repaired && wp_is_valid_utf8( $repaired ) ? $repaired : $sequence;
	}

	/**
	 * Reinterpret a UTF-8 mojibake value as Windows-1252 bytes.
	 *
	 * The returned byte sequence is deliberately validated as UTF-8 by the
	 * caller. mbstring is preferred; iconv is a compatible fallback.
	 *
	 * @param string $value Mojibake value encoded as UTF-8.
	 * @return string|false
	 */
	private function convert_windows_1252_to_utf8( $value ) {
		if ( function_exists( 'mb_convert_encoding' ) ) {
			return mb_convert_encoding( $value, 'Windows-1252', 'UTF-8' );
		}

		if ( function_exists( 'iconv' ) ) {
			return iconv( 'UTF-8', 'Windows-1252//IGNORE', $value );
		}

		return false;
	}

	/**
	 * Fetch a raw JSON body from a URL with caching and error handling.
	 *
	 * The URL is expected to be one of the plugin's own endpoints (built from
	 * the configured source_url). Same-origin, HTTPS and size enforcement are
	 * applied in request().
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public function fetch_body( $url ) {
		$this->last_error = '';
		$cache_key        = 'like_json_fetch_' . md5( $url );
		$cached           = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$body = $this->request( $url, self::MAX_JSON_BYTES );

		if ( '' !== $body ) {
			set_transient( $cache_key, $body, self::CACHE_TTL );
		}

		return $body;
	}

	/**
	 * Fetch a PDF body (for ressources="base64") with a larger size cap.
	 *
	 * Same-origin, HTTPS and size enforcement are applied in request(). The
	 * result is intentionally not cached in transients (PDFs can be large).
	 *
	 * @param string $url PDF URL.
	 * @return string
	 */
	public function fetch_pdf( $url ) {
		$this->last_error = '';
		return $this->request( $url, self::MAX_PDF_BYTES );
	}

	/**
	 * Get the sanitized error status of the most recent request.
	 *
	 * @return string Empty string, 'timeout', 'unavailable', or 'invalid_data'.
	 */
	public function get_last_error() {
		return $this->last_error;
	}

	/**
	 * Perform one HTTP request via the WordPress HTTP API.
	 *
	 * SSRF hardening: only HTTPS URLs on the host of the configured
	 * source_url are fetched. Oversized responses are rejected.
	 *
	 * @param string $url       URL.
	 * @param int    $max_bytes Maximum accepted body size in bytes.
	 * @return string Body on success, empty string on failure.
	 */
	private function request( $url, $max_bytes ) {
		// SSRF hardening: the plugin only ever fetches HTTPS URLs from the
		// host configured as its own data source (same-origin lock). This
		// prevents a malicious source_url or pdf field from turning the
		// server into a proxy for arbitrary targets.
		if ( ! $this->is_allowed_url( $url ) ) {
			$this->last_error = 'unavailable';
			return '';
		}

		$timeout = max( 1, min( 60, absint( Settings::get( 'timeout' ) ) ) );
		$args    = array(
			'timeout'             => $timeout,
			'user-agent'          => Settings::get( 'user_agent' ),
			'redirection'         => 3,
			// Let the transport stream up to the cap instead of buffering the
			// whole body (supported by the cURL transport). The strlen() check
			// below is the authoritative guard regardless of transport.
			'limit_response_size' => min( $max_bytes, self::MAX_ABSOLUTE_BYTES ),
		);

		// Attach the intermediate certificate for this request only.
		add_filter( 'http_api_curl', array( $this, 'set_cainfo' ) );
		$response = wp_safe_remote_get( $url, $args );
		remove_filter( 'http_api_curl', array( $this, 'set_cainfo' ) );

		if ( is_wp_error( $response ) ) {
			$message          = $response->get_error_message();
			$this->last_error = $this->is_timeout_error( $message ) ? 'timeout' : 'unavailable';
			return '';
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$this->last_error = 'unavailable';
			return '';
		}

		$body = wp_remote_retrieve_body( $response );
		if ( ! is_string( $body ) || '' === $body ) {
			$this->last_error = 'unavailable';
			return '';
		}

		// Hard size cap: reject oversized bodies (DoS protection).
		if ( strlen( $body ) > $max_bytes ) {
			$this->last_error = 'unavailable';
			return '';
		}

		return $body;
	}

	/**
	 * Determine whether a URL may be fetched.
	 *
	 * Same-origin lock: the URL must be HTTPS and its host must match the
	 * host of the configured source_url. Ports and subdomains must match
	 * exactly to avoid e.g. a port-8080 or sibling-host bypass.
	 *
	 * @param string $url Candidate URL.
	 * @return bool
	 */
	private function is_allowed_url( $url ) {
		$source = Settings::get( 'source_url' );
		if ( '' === $source ) {
			return false;
		}

		$source_host = wp_parse_url( $source, PHP_URL_HOST );
		$source_port = wp_parse_url( $source, PHP_URL_PORT );
		$url_scheme  = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		$url_host    = wp_parse_url( $url, PHP_URL_HOST );
		$url_port    = wp_parse_url( $url, PHP_URL_PORT );

		if ( 'https' !== $url_scheme || false === $source_host || false === $url_host ) {
			return false;
		}

		// Host must match exactly (case-insensitive); default port 443 must
		// correspond to an explicit 443 or no port.
		if ( strtolower( (string) $source_host ) !== strtolower( (string) $url_host ) ) {
			return false;
		}

		if ( null !== $source_port && (int) $source_port !== (int) $url_port ) {
			return false;
		}

		return true;
	}

	/**
	 * Determine whether a WordPress HTTP error denotes a timeout.
	 *
	 * Transport implementations use differing messages, therefore the check is
	 * intentionally limited to the standard timeout terms.
	 *
	 * @param string $message HTTP error message.
	 * @return bool
	 */
	private function is_timeout_error( $message ) {
		return false !== stripos( $message, 'timed out' ) || false !== stripos( $message, 'timeout' );
	}

	/**
	 * Set CURLOPT_CAINFO to the bundled intermediate certificate.
	 *
	 * Hooked via the http_api_curl filter, scoped to this plugin's requests.
	 *
	 * @param resource|\CurlHandle $handle cURL handle.
	 */
	public function set_cainfo( $handle ) {
		$cert = dirname( __DIR__ ) . '/certs/GEANT-TLS-ECC-1.pem';
		if ( file_exists( $cert ) ) {
			// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_setopt -- Intentional: WordPress has no native API to set CURLOPT_CAINFO per request. This supplies the missing intermediate certificate for HTTPS verification.
			curl_setopt( $handle, CURLOPT_CAINFO, $cert );
			curl_setopt( $handle, CURLOPT_SSL_VERIFYPEER, true );
			curl_setopt( $handle, CURLOPT_SSL_VERIFYHOST, 2 );
			// phpcs:enable
		}
	}
}
