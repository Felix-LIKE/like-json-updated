<?php
/**
 * Project-local PSR-4 autoloader for LIKE-Json.
 *
 * The plugin does not require Composer at runtime. This small autoloader maps
 * the LIKE_Json namespace to the includes/ directory (PSR-4 compatible).
 *
 * @package LIKE_Json
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Direct access not allowed.
}

/**
 * Register the PSR-4 autoloader.
 *
 * @param string $class Fully qualified class name.
 */
function like_json_autoload( $class ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.classFound -- Required signature for spl_autoload_register.
	$prefix = 'LIKE_Json\\';
	if ( 0 !== strncmp( $class, $prefix, strlen( $prefix ) ) ) {
		return;
	}

	$relative = substr( $class, strlen( $prefix ) );
	$path     = LIKE_JSON_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
	if ( file_exists( $path ) ) {
		require_once $path;
	}
}
spl_autoload_register( 'like_json_autoload' );
