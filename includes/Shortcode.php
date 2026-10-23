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
			$html = '<p class="like-json-empty">' . esc_html__( 'Keine Einträge gefunden.', 'like-json' ) . '</p>';
		}

		return $html;
	}
}
