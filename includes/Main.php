<?php
/**
 * Main bootstrap for LIKE-Json.
 *
 * @package LIKE_Json
 */

namespace LIKE_Json;

use LIKE_Json\Admin\SettingsPage;
use LIKE_Json\Blocks\Blocks;
use LIKE_Json\Config\Settings;

/**
 * Plugin bootstrap and lifecycle.
 */
class Main {

	/**
	 * Single instance.
	 *
	 * @var Main|null
	 */
	private static $instance = null;

	/**
	 * Get the single instance.
	 *
	 * @return Main
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'maybe_migrate' ), 5 );
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Run upgrade migrations once per plugin version.
	 *
	 * Runs early on plugins_loaded (priority 5) so any data repair is complete
	 * before the blocks/shortcode render their first output on the same request.
	 * The version option acts as a guard: each migration runs exactly once per
	 * recorded version.
	 */
	public function maybe_migrate() {
		$version = get_option( Settings::VERSION_OPTION_NAME, '' );

		if ( '' === $version || version_compare( $version, '2.1.0', '<' ) ) {
			$this->migrate_210_force_https_source();
		}

		// Record the current version so migrations do not re-run.
		update_option( Settings::VERSION_OPTION_NAME, LIKE_JSON_VERSION );
	}

	/**
	 * Force an HTTPS source_url (v2.1.0 migration).
	 *
	 * Versions prior to v2.1.0 (including the legacy 1.6.x line, whose default
	 * was http://like35...) could leave a plain-HTTP URL stored in the
	 * like_json_settings option. Since v2.1.0 the same-origin lock rejects
	 * non-HTTPS requests, which would otherwise make the plugin silently show
	 * no data after an update. Repair the scheme in place so the upgrade is
	 * seamless and requires no manual re-save of the settings.
	 */
	private function migrate_210_force_https_source() {
		$options = get_option( Settings::OPTION_NAME, array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$url = isset( $options['source_url'] ) ? $options['source_url'] : '';
		if ( is_string( $url ) && '' !== $url && 0 === stripos( $url, 'http://' ) ) {
			$options['source_url'] = 'https://' . substr( $url, strlen( 'http://' ) );
			update_option( Settings::OPTION_NAME, $options );
		}
	}

	/**
	 * Initialize components.
	 */
	public function init() {
		( new Blocks() )->register();
		( new Shortcode() )->register();
		if ( is_admin() ) {
			( new SettingsPage() )->register();
		}
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'like-json',
			false,
			dirname( plugin_basename( LIKE_JSON_FILE ) ) . '/languages'
		);
	}

	/**
	 * Activation hook.
	 */
	public static function activate() {
		// No data migration required yet. Version is recorded for future updates.
		update_option( Settings::VERSION_OPTION_NAME, LIKE_JSON_VERSION );
	}

	/**
	 * Deactivation hook.
	 */
	public static function deactivate() {
		// No cleanup on deactivation (data is retained by design).
	}

	/**
	 * Uninstall hook: delete plugin-owned options.
	 */
	public static function uninstall() {
		delete_option( Settings::OPTION_NAME );
		delete_option( Settings::VERSION_OPTION_NAME );
		// Remove the obsolete network option left by versions prior to 2.0.1.
		delete_site_option( 'like_json_network_settings' );
	}
}
