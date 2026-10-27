<?php
/**
 * Gutenberg block registration for LIKE-Json.
 *
 * Registers a dynamic (server-side rendered) block so the plugin integrates
 * with the Block Editor as required by the RRZE SPEC. The shortcode is kept
 * as a legacy interface for backwards compatibility.
 *
 * @package LIKE_Json
 */

namespace LIKE_Json\Blocks;

use LIKE_Json\API\Client;
use LIKE_Json\API\Works;

/**
 * Block registration and rendering.
 */
class Blocks {

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Register the block type.
	 */
	public function register_block() {
		register_block_type(
			__DIR__ . '/../../build/studentische-arbeiten-block',
			array(
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Server-side render callback.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Saved content (ignored, dynamic block).
	 * @param WP_Block $block      Block instance.
	 * @return string
	 */
	public function render( $attributes, $content, $block ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required render_callback signature for register_block_type().
		$attributes = wp_parse_args(
			$attributes,
			array(
				'task'       => 'bachelorarbeiten',
				'format'     => 'liste',
				'id'         => '',
				'advisor'    => '',
				'status'     => '',
				'ressources' => 'link',
			)
		);

		$client = new Client();
		$works  = new Works( $client );

		$format = ( 'accordion' === $attributes['format'] ) ? 'accordion' : 'liste';
		$res    = ( 'base64' === $attributes['ressources'] ) ? 'base64' : 'link';

		$html = '';
		if ( 'arbeiten-alle' === $attributes['task'] ) {
			$all = $works->get_all();
			foreach ( $all as $task => $records ) {
				$html .= '<div class="like-json-group like-json-group-' . esc_attr( $task ) . '">';
				$html .= '<h2 class="like-json-heading">' . esc_html( Works::tasks()[ $task ] ) . '</h2>';
				$html .= $works->render( $records, $format, $res );
				$html .= '</div>';
			}
		} else {
			$data  = $works->get(
				$attributes['task'],
				$attributes['id'],
				$attributes['advisor'],
				$attributes['status']
			);
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
