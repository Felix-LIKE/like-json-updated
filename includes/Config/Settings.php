<?php
/**
 * Configuration and settings for LIKE-Json.
 *
 * Centralizes site-specific option storage. On WordPress Multisite each site
 * has its own option and there are no network-wide settings.
 *
 * @package LIKE_Json
 */

namespace LIKE_Json\Config;

/**
 * Central configuration resolver.
 */
class Settings {

	/**
	 * Option name for site settings.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'like_json_settings';

	/**
	 * Version option name.
	 *
	 * @var string
	 */
	const VERSION_OPTION_NAME = 'like_json_version';

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'source_url'  => 'https://like35.like.e-technik.uni-erlangen.de/json/',
			'timeout'     => 10,
			'user_agent'  => self::default_user_agent(),
			'crawl_limit' => 3,
		);
	}

	/**
	 * The RRZE-conform default User-Agent.
	 *
	 * Schema: FAU-<ORG>-<BOT>/<VERSION> (+<INFO-URL>;mailto:<CONTACT>)
	 * See https://www.wp.rrze.fau.de/entwicklung/vorgaben/crawler/
	 *
	 * @return string
	 */
	public static function default_user_agent() {
		$contact = apply_filters( 'like_json_contact_email', 'like-admin@fau.de' );
		return sprintf(
			'FAU-LIKE-like-json/%s (+https://www.like.tf.fau.de/;mailto:%s)',
			LIKE_JSON_VERSION,
			$contact
		);
	}

	/**
	 * Get a single setting value.
	 *
	 * Precedence: filter override > site option > default.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $fallback Optional fallback.
	 * @return mixed
	 */
	public static function get( $key, $fallback = null ) {
		$defaults = self::defaults();
		$default  = isset( $defaults[ $key ] ) ? $defaults[ $key ] : $fallback;

		$value = apply_filters( 'like_json_setting_' . $key, null );
		if ( null !== $value ) {
			return $value;
		}

		// Settings are always local to the current site.
		$site = get_option( self::OPTION_NAME, array() );
		if ( is_array( $site ) && isset( $site[ $key ] ) && '' !== $site[ $key ] ) {
			return $site[ $key ];
		}

		return $default;
	}

	/**
	 * Get all settings as an array.
	 *
	 * @return array
	 */
	public static function all() {
		$defaults = self::defaults();
		$result   = array();
		foreach ( array_keys( $defaults ) as $key ) {
			$result[ $key ] = self::get( $key );
		}
		return $result;
	}

	/**
	 * Sanitize and validate the site settings input.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$clean    = array();

		foreach ( $defaults as $key => $default_value ) {
			$value = isset( $input[ $key ] ) ? $input[ $key ] : $default_value;
			switch ( $key ) {
				case 'source_url':
					// Trim first: esc_url_raw() does not strip surrounding
					// whitespace and would otherwise URL-encode it into the
					// saved value (breaking the endpoint URL).
					$url = esc_url_raw( trim( (string) $value ) );

					// SSRF hardening: the data source must be HTTPS. The host
					// remains freely configurable so the plugin can be pointed
					// at any (future) LIKE server without a code change; only
					// the transport is restricted.
					if ( '' !== $url && 0 !== stripos( $url, 'https://' ) ) {
						$url = '';
					}
					$clean[ $key ] = $url;
					break;
				case 'timeout':
					$clean[ $key ] = max( 1, min( 60, absint( $value ) ) );
					break;
				case 'crawl_limit':
					$clean[ $key ] = max( 1, min( 10, absint( $value ) ) );
					break;
				case 'user_agent':
					$clean[ $key ] = sanitize_text_field( $value );
					break;
				default:
					$clean[ $key ] = sanitize_text_field( $value );
			}
		}
		return $clean;
	}
}
