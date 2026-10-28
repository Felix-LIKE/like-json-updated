<?php
/**
 * Plugin Name:       LIKE-Json
 * Plugin URI:        https://github.com/Felix-LIKE/like-json-updated
 * Description:       Stellt studentische Arbeiten (Abschlussarbeiten) aus der LIKE-Datenbank als Gutenberg-Block und als Legacy-Shortcode dar.
 * Version:           2.0.1
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            LIKE
 * Author URI:        https://www.like.tf.fau.de/
 * License:           GPLv3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       like-json
 * Domain Path:       /languages
 *
 * Distribution and updates: this plugin is distributed via its public GitHub
 * repository and installed/updated with the RRZE Updater. It is not hosted on
 * WordPress.org, so no "Update URI" header is used (Plugin Check rejects it).
 *
 * @package LIKE_Json
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Direct access not allowed.
}

define( 'LIKE_JSON_VERSION', '2.0.1' );
define( 'LIKE_JSON_FILE', __FILE__ );
define( 'LIKE_JSON_DIR', plugin_dir_path( __FILE__ ) );
define( 'LIKE_JSON_URL', plugin_dir_url( __FILE__ ) );

require_once LIKE_JSON_DIR . 'includes/autoload.php';

register_activation_hook( __FILE__, array( 'LIKE_Json\Main', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'LIKE_Json\Main', 'deactivate' ) );
register_uninstall_hook( __FILE__, array( 'LIKE_Json\Main', 'uninstall' ) );

\LIKE_Json\Main::instance();
