<?php
/**
 * Settings page for LIKE-Json.
 *
 * Provides a site-level settings page under Settings -> LIKE-Json and, on
 * Multisite, a network settings page under Settings -> LIKE-Json. Capabilities
 * are enforced server-side (manage_options / manage_network_options). Forms use
 * WordPress nonces; input is validated and sanitized; output is escaped.
 *
 * @package LIKE_Json
 */

namespace LIKE_Json\Admin;

use LIKE_Json\Config\Settings;

/**
 * Admin settings page.
 */
class SettingsPage {

	/**
	 * Settings group slug for the site page.
	 *
	 * @var string
	 */
	private $page_hook;

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'network_admin_menu', array( $this, 'network_admin_menu' ) );
		add_action( 'network_admin_edit_like_json_network_settings', array( $this, 'network_save' ) );
	}

	/**
	 * Register the site settings page.
	 */
	public function admin_menu() {
		$this->page_hook = add_options_page(
			__( 'LIKE-Json', 'like-json' ),
			__( 'LIKE-Json', 'like-json' ),
			'manage_options',
			'like-json',
			array( $this, 'render_site_page' )
		);
	}

	/**
	 * Register settings fields.
	 */
	public function register_settings() {
		register_setting(
			'like_json_settings_group',
			Settings::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
			)
		);
	}

	/**
	 * Register the network settings page.
	 */
	public function network_admin_menu() {
		add_submenu_page(
			'settings.php',
			__( 'LIKE-Json', 'like-json' ),
			__( 'LIKE-Json', 'like-json' ),
			'manage_network_options',
			'like-json-network',
			array( $this, 'render_network_page' )
		);
	}

	/**
	 * Render the site settings page.
	 */
	public function render_site_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sie haben nicht die erforderliche Berechtigung.', 'like-json' ) );
		}
		$settings = Settings::all();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'LIKE-Json Einstellungen', 'like-json' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'like_json_settings_group' );
				$this->render_fields( $settings );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the network settings page.
	 */
	public function render_network_page() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'Sie haben nicht die erforderliche Berechtigung.', 'like-json' ) );
		}
		$settings = get_site_option( Settings::NETWORK_OPTION_NAME, array() );
		$settings = wp_parse_args( $settings, Settings::defaults() );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'LIKE-Json Netzwerkeinstellungen', 'like-json' ); ?></h1>
			<form method="post" action="edit.php?action=like_json_network_settings">
				<?php wp_nonce_field( 'like_json_network_settings' ); ?>
				<?php $this->render_fields( $settings ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the shared settings fields.
	 *
	 * @param array $settings Current settings values.
	 */
	private function render_fields( $settings ) {
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="like_json_source_url"><?php echo esc_html__( 'JSON-Quelle (URL)', 'like-json' ); ?></label></th>
				<td>
					<input type="url" id="like_json_source_url" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[source_url]"
						value="<?php echo esc_attr( $settings['source_url'] ); ?>" size="60" />
					<p class="description"><?php echo esc_html__( 'Basis-URL der JSON-Daten (ohne Endpunktnamen wie "bachelorarbeiten").', 'like-json' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="like_json_timeout"><?php echo esc_html__( 'Timeout (Sekunden)', 'like-json' ); ?></label></th>
				<td>
					<input type="number" id="like_json_timeout" min="1" max="60"
						name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[timeout]"
						value="<?php echo esc_attr( $settings['timeout'] ); ?>" />
					<p class="description"><?php echo esc_html__( 'Maximale Wartezeit für Remote-Anfragen.', 'like-json' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="like_json_user_agent"><?php echo esc_html__( 'User-Agent', 'like-json' ); ?></label></th>
				<td>
					<input type="text" id="like_json_user_agent" size="70"
						name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[user_agent]"
						value="<?php echo esc_attr( $settings['user_agent'] ); ?>" />
					<p class="description"><?php echo esc_html__( 'RRZE-konformer User-Agent für automatisierte Abrufe.', 'like-json' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save the network settings.
	 */
	public function network_save() {
		check_admin_referer( 'like_json_network_settings' );
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'Sie haben nicht die erforderliche Berechtigung.', 'like-json' ) );
		}
		$input = isset( $_POST[ Settings::NETWORK_OPTION_NAME ] ) ? (array) wp_unslash( $_POST[ Settings::NETWORK_OPTION_NAME ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below via Settings::sanitize().
		$clean = Settings::sanitize( $input );
		update_site_option( Settings::NETWORK_OPTION_NAME, $clean );
		wp_safe_redirect( add_query_arg( 'updated', 'true', network_admin_url( 'settings.php?page=like-json-network' ) ) );
		exit;
	}
}
