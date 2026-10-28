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

		return $data;
	}

	/**
	 * Fetch a raw body from a URL with caching and error handling.
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

		$body = $this->request( $url );

		if ( '' !== $body ) {
			set_transient( $cache_key, $body, self::CACHE_TTL );
		}

		return $body;
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
	 * @param string $url URL.
	 * @return string Body on success, empty string on failure.
	 */
	private function request( $url ) {
		$timeout = max( 1, min( 60, absint( Settings::get( 'timeout' ) ) ) );
		$args    = array(
			'timeout'     => $timeout,
			'user-agent'  => Settings::get( 'user_agent' ),
			'redirection' => 3,
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

		return $body;
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
