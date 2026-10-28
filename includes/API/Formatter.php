<?php
/**
 * HTML formatter for student works.
 *
 * All remote data is treated as untrusted and escaped at output time with
 * esc_html() / esc_url(). Only PDF links are allowed (never HTML).
 *
 * @package LIKE_Json
 */

namespace LIKE_Json\API;

/**
 * Formats records as HTML list or accordion markup.
 */
class Formatter {

	/**
	 * Render records as a simple list.
	 *
	 * @param array  $data       Records.
	 * @param string $ressources 'link' or 'base64'.
	 * @return string
	 */
	public function list( $data, $ressources = 'link' ) {
		$output = '<ul class="like-json-list">';
		foreach ( $data as $record ) {
			$title = isset( $record['titel'] ) ? $record['titel'] : '';
			$id    = isset( $record['id'] ) ? $record['id'] : '';

			$output .= '<li class="like-json-item">';
			$output .= '<h2 class="like-json-title like-json-caption">'
				. esc_html( $title )
				. ( '' !== $id ? ' [ID: ' . esc_html( $id ) . ']' : '' )
				. '</h2>';
			$output .= $this->record_html( $record, $ressources );
			$output .= '</li>';
		}
		$output .= '</ul>';
		return $output;
	}

	/**
	 * Render records as accordions when RRZE Elements Blocks is available.
	 *
	 * Falls back to headings when the required RRZE blocks are not registered.
	 *
	 * @param array  $data       Records.
	 * @param string $ressources 'link' or 'base64'.
	 * @return string
	 */
	public function accordion( $data, $ressources = 'link' ) {
		$items = array();
		foreach ( $data as $record ) {
			$title   = isset( $record['titel'] ) ? $record['titel'] : '';
			$id      = isset( $record['id'] ) ? $record['id'] : '';
			$items[] = array(
				'title'   => $title . ' (ID: ' . $id . ')',
				'content' => $this->record_html( $record, $ressources ),
			);
		}

		if ( $this->has_rrze_accordion_blocks() ) {
			return $this->render_rrze_accordion_blocks( $items );
		}

		return $this->render_accordion_fallback( $items );
	}

	/**
	 * Determine whether the RRZE Elements accordion blocks are available.
	 *
	 * Checking registered block types also covers installations where the plugin
	 * is active but does not register its blocks for the active theme.
	 *
	 * @return bool
	 */
	private function has_rrze_accordion_blocks() {
		if ( ! class_exists( '\\WP_Block_Type_Registry' ) ) {
			return false;
		}

		$registry = \WP_Block_Type_Registry::get_instance();
		return $registry->is_registered( 'rrze-elements/collapsibles' )
			&& $registry->is_registered( 'rrze-elements/collapse' );
	}

	/**
	 * Render records through the RRZE Elements accordion blocks.
	 *
	 * The content is wrapped in core/html blocks so the dynamic RRZE block
	 * receives it as inner blocks and can generate its required markup.
	 *
	 * @param array $items Accordion titles and rendered content.
	 * @return string
	 */
	private function render_rrze_accordion_blocks( $items ) {
		$this->enqueue_rrze_accordion_assets();
		$accordion_id = wp_unique_id( 'like-json-accordion-' );
		$blocks       = '<!-- wp:rrze-elements/collapsibles -->';

		foreach ( $items as $index => $item ) {
			$jump_name  = $accordion_id . '-' . ( $index + 1 );
			$attributes = wp_json_encode(
				array(
					'title'    => $item['title'],
					'jumpName' => $jump_name,
				),
				JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
			);
			$blocks    .= '<!-- wp:rrze-elements/collapse ' . $attributes . ' -->';
			$blocks    .= '<!-- wp:html -->' . $item['content'] . '<!-- /wp:html -->';
			$blocks    .= '<!-- /wp:rrze-elements/collapse -->';
		}

		$blocks .= '<!-- /wp:rrze-elements/collapsibles -->';

		return do_blocks( $blocks );
	}

	/**
	 * Enqueue the assets required by the RRZE accordion blocks.
	 *
	 * The dynamically rendered blocks do not declare the legacy accordion
	 * script in their block metadata. RRZE Elements Blocks exposes this method
	 * specifically for requesting the associated frontend assets.
	 *
	 * @return void
	 */
	private function enqueue_rrze_accordion_assets() {
		if ( is_callable( array( '\\RRZE\\ElementsBlocks\\FrontendAssets', 'enqueueAccordion' ) ) ) {
			call_user_func( array( '\\RRZE\\ElementsBlocks\\FrontendAssets', 'enqueueAccordion' ) );
		}
	}

	/**
	 * Render an accessible non-accordion fallback.
	 *
	 * @param array $items Accordion titles and rendered content.
	 * @return string
	 */
	private function render_accordion_fallback( $items ) {
		$output = '';
		foreach ( $items as $item ) {
			$output .= '<h2 class="like-json-heading">' . esc_html( $item['title'] ) . '</h2>';
			$output .= $item['content'];
		}

		return $output;
	}

	/**
	 * Build the inner HTML of a single record.
	 *
	 * @param array  $record     Record data.
	 * @param string $ressources 'link' or 'base64'.
	 * @return string
	 */
	private function record_html( $record, $ressources ) {
		$fields = array(
			'beschreibung'  => __( 'Themenbeschreibung', 'like-json' ),
			'kategorien'    => __( 'Themengebiete', 'like-json' ),
			'voraussetzung' => __( 'Voraussetzungen', 'like-json' ),
			'betreuer'      => __( 'Betreuer', 'like-json' ),
			'hs_lehrer'     => __( 'Hochschullehrer', 'like-json' ),
		);

		$html = '<ul class="like-json-record">';

		foreach ( $fields as $key => $label ) {
			if ( ! empty( $record[ $key ] ) ) {
				$html .= '<li><b>' . esc_html( $label ) . ': </b>' . esc_html( $record[ $key ] ) . '</li>';
			}
		}

		// Aufgaben (tasks) split by "/".
		if ( ! empty( $record['aufgaben'] ) ) {
			$html .= '<li><b>' . esc_html__( 'Aufgaben', 'like-json' ) . ': </b><ul>';
			foreach ( explode( '/', $record['aufgaben'] ) as $aufgabe ) {
				$html .= '<li>' . esc_html( $aufgabe ) . '</li>';
			}
			$html .= '</ul></li>';
		}

		// PDF link.
		if ( ! empty( $record['pdf'] ) ) {
			$html .= $this->pdf_html( $record['pdf'], $ressources );
		}

		$html .= '</ul>';
		return $html;
	}

	/**
	 * Build the PDF link HTML.
	 *
	 * With $ressources='base64' the PDF is embedded as a base64 data URI when
	 * the fetch succeeds, otherwise it falls back to a plain link. With
	 * 'link' the PDF is always a direct link (recommended).
	 *
	 * SSRF hardening: the server-side fetch (base64 mode) only happens for
	 * HTTPS URLs on the same host as the configured source_url, for bodies
	 * that actually start with the PDF magic bytes and are within the size
	 * cap. Everything else degrades to a plain link that the visitor's
	 * browser opens directly.
	 *
	 * @param string $pdf_url    PDF URL.
	 * @param string $ressources 'link' or 'base64'.
	 * @return string
	 */
	private function pdf_html( $pdf_url, $ressources ) {
		$label = esc_html__( 'PDF', 'like-json' );

		if ( 'base64' === $ressources ) {
			$client = new Client();
			$body   = $client->fetch_pdf( esc_url_raw( $pdf_url ) );
			if ( ! empty( $body ) && 0 === strpos( $body, '%PDF-' ) ) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Intentional: optional PDF embedding as base64 data URI (feature requested by the plugin's ressources="base64" mode).
				$href = 'data:application/pdf;base64,' . chunk_split( base64_encode( $body ) );
				return '<li><b>' . $label . ': </b><a class="like-json-pdf" href="' . $href . '" download="Aushang.pdf">' . esc_html__( 'Aushang', 'like-json' ) . '</a></li>';
			}
		}

		$href = esc_url( $pdf_url );
		return '<li><b>' . $label . ': </b><a class="like-json-pdf" href="' . $href . '" download="Aushang.pdf">' . esc_html__( 'Aushang', 'like-json' ) . '</a></li>';
	}
}
