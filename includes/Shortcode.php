<?php
/**
 * Legacy shortcode for LIKE-Json.
 *
 * Kept for backwards compatibility only. New content should use the Gutenberg
 * block "Studentische Arbeiten (LIKE)". See README.md for the legacy strategy.
 *
 * @package LIKE_Json
 */

namespace LIKE_Json;

use LIKE_Json\API\Client;
use LIKE_Json\API\Works;

/**
 * Legacy shortcode handler.
 */
class Shortcode {

	/**
	 * Default shortcode attributes.
	 *
	 * @return array
	 */
	private static function defaults() {
		return array(
			'task'       => 'bachelorarbeiten',
			'id'         => '',
			'format'     => 'liste',
			'advisor'    => '',
			'status'     => '',
			'ressources' => 'link',
		);
	}

	/**
	 * Register the shortcode.
	 */
	public function register() {
		add_shortcode( 'like', array( $this, 'render' ) );
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts( self::defaults(), $atts, 'like' );

		$client = new Client();
		$works  = new Works( $client );

		$format = ( 'accordion' === $atts['format'] ) ? 'accordion' : 'liste';
		$res    = ( 'base64' === $atts['ressources'] ) ? 'base64' : 'link';

		$html = '';
		if ( 'arbeiten-alle' === $atts['task'] ) {
			$all = $works->get_all();
			foreach ( $all as $task => $records ) {
				$html .= '<div class="like-json-group like-json-group-' . esc_attr( $task ) . '">';
				$html .= '<h2 class="like-json-heading">' . esc_html( Works::tasks()[ $task ] ) . '</h2>';
				$html .= $works->render( $records, $format, $res );
				$html .= '</div>';
			}
		} else {
			$data  = $works->get( $atts['task'], $atts['id'], $atts['advisor'], $atts['status'] );
			$html .= $works->render( $data, $format, $res );
		}

		if ( '' === trim( $html ) ) {
			$html = $this->error_html( $works->get_last_error() );
		}

		return $html;
	}

	/**
	 * Render a visitor-safe message for a remote data error.
	 *
	 * @param string $error Sanitized client error status.
	 * @return string
	 */
	private function error_html( $error ) {
		if ( 'timeout' === $error ) {
			$message = __( 'Der Abruf der Datenquelle hat das Zeitlimit überschritten. Bitte versuchen Sie es später erneut.', 'like-json' );
		} elseif ( 'unavailable' === $error || 'invalid_data' === $error ) {
			$message = __( 'Die Datenquelle ist derzeit nicht erreichbar. Bitte versuchen Sie es später erneut.', 'like-json' );
		} else {
			$message = __( 'Keine Einträge gefunden.', 'like-json' );
		}

		return '<p class="like-json-error" role="alert">' . esc_html( $message ) . '</p>';
	}
}
