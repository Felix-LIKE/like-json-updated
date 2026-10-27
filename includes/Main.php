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
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
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
