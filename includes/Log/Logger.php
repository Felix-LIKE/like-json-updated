<?php
/**
 * Diagnostic logging for LIKE-Json.
 *
 * Emits structured log entries through the RRZE-Log hooks
 * (https://github.com/RRZE-Webteam/rrze-log) so support can trace the
 * plugin's connection attempts in WP_CONTENT/log/rrze-log/.
 *
 * Every entry carries a "plugin" context key (like-json) that can be used to
 * filter the log, and follows the RRZE-Log message/context convention
 * (do_action( 'rrze.log.info', $message, $context )).
 *
 * No personal data is ever logged: only URLs, HTTP status codes, byte counts
 * and internal error statuses.
 *
 * @package LIKE_Json
 */

namespace LIKE_Json\Log;

use LIKE_Json\Config\Settings;

/**
 * Thin wrapper around the RRZE-Log action hooks.
 */
class Logger {

	/**
	 * Emit an info-level log entry.
	 *
	 * @param string $message Human-readable message (may contain {placeholders}).
	 * @param array  $context Context values; a 'plugin' key is added automatically.
	 */
	public static function info( $message, $context = array() ) {
		if ( ! self::debug_enabled() ) {
			return;
		}

		self::log( 'info', $message, $context );
	}

	/**
	 * Emit a warning-level log entry.
	 *
	 * @param string $message Human-readable message (may contain {placeholders}).
	 * @param array  $context Context values; a 'plugin' key is added automatically.
	 */
	public static function warning( $message, $context = array() ) {
		if ( ! self::debug_enabled() ) {
			return;
		}

		self::log( 'warning', $message, $context );
	}

	/**
	 * Emit an error-level log entry.
	 *
	 * @param string $message Human-readable message (may contain {placeholders}).
	 * @param array  $context Context values; a 'plugin' key is added automatically.
	 */
	public static function error( $message, $context = array() ) {
		self::log( 'error', $message, $context );
	}

	/**
	 * Determine whether diagnostic log levels are enabled.
	 *
	 * @return bool
	 */
	private static function debug_enabled() {
		return true === Settings::get( 'debuglogs' );
	}

	/**
	 * Emit a log entry on the matching RRZE-Log hook.
	 *
	 * @param string $level   'info', 'warning' or 'error'.
	 * @param string $message Message.
	 * @param array  $context Context.
	 */
	private static function log( $level, $message, $context ) {
		$context = (array) $context;
		if ( ! isset( $context['plugin'] ) ) {
			$context['plugin'] = 'like-json';
		}

		/**
		 * Fires to write a log entry (RRZE-Log).
		 *
		 * The hook name uses dots (rrze.log.info) as required by the
		 * RRZE-Log plugin API (https://github.com/RRZE-Webteam/rrze-log).
		 *
		 * @param string $message Message.
		 * @param array  $context Context.
		 */
		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- RRZE-Log API mandates the rrze.log.* hook name.
		do_action( 'rrze.log.' . $level, $message, $context );
	}
}
