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
			$output .= $this->record_html( $record, $ressources, false );
		}
		$output .= '</ul>';
		return $output;
	}

	/**
	 * Render records as an accordion (RRZE-Elements compatible shortcode).
	 *
	 * @param array  $data       Records.
	 * @param string $ressources 'link' or 'base64'.
	 * @return string
	 */
	public function accordion( $data, $ressources = 'link' ) {
		$output = '[collapsibles]';
		foreach ( $data as $record ) {
			$title = isset( $record['titel'] ) ? $record['titel'] : '';
			$id    = isset( $record['id'] ) ? $record['id'] : '';
			// Title is inserted into a shortcode attribute, so it MUST be escaped
			// for the attribute context AND have brackets stripped to avoid
			// shortcode-breaking.
			$title_attr = esc_attr( $title . ' (ID: ' . $id . ')' );
			$title_attr = str_replace( array( '[', ']' ), '', $title_attr );

			$output .= '[collapse title="' . $title_attr . '"]';
			$output .= $this->record_html( $record, $ressources, true );
			$output .= '[/collapse]';
		}
		$output .= '[/collapsibles]';
		return do_shortcode( $output );
	}

	/**
	 * Build the inner HTML of a single record.
	 *
	 * @param array  $record     Record data.
	 * @param string $ressources 'link' or 'base64'.
	 * @param bool   $accordion  Whether inside an accordion (affects header).
	 * @return string
	 */
	private function record_html( $record, $ressources, $accordion ) {
		$fields = array(
			'beschreibung'  => __( 'Themenbeschreibung', 'like-json' ),
			'kategorien'    => __( 'Themengebiete', 'like-json' ),
			'voraussetzung' => __( 'Voraussetzungen', 'like-json' ),
			'betreuer'      => __( 'Betreuer', 'like-json' ),
			'hs_lehrer'     => __( 'Hochschullehrer', 'like-json' ),
		);

		$html = '<ul class="like-json-record">';

		// Title as list caption (not in accordion; accordion uses the collapse title).
		if ( ! $accordion && isset( $record['titel'] ) ) {
			$html .= '<li class="like-json-title"><span class="like-json-caption">'
				. esc_html( $record['titel'] )
				. ( isset( $record['id'] ) ? ' [ID: ' . esc_html( $record['id'] ) . ']' : '' )
				. '</span></li>';
		}

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
	 * @param string $pdf_url    PDF URL.
	 * @param string $ressources 'link' or 'base64'.
	 * @return string
	 */
	private function pdf_html( $pdf_url, $ressources ) {
		$label = esc_html__( 'PDF', 'like-json' );

		if ( 'base64' === $ressources ) {
			$client = new Client();
			$body   = $client->fetch_body( esc_url_raw( $pdf_url ) );
			if ( ! empty( $body ) ) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Intentional: optional PDF embedding as base64 data URI (feature requested by the plugin's ressources="base64" mode).
				$href = 'data:application/pdf;base64,' . chunk_split( base64_encode( $body ) );
				return '<li><b>' . $label . ': </b><a class="like-json-pdf" href="' . $href . '" download="Aushang.pdf">' . esc_html__( 'Aushang', 'like-json' ) . '</a></li>';
			}
		}

		$href = esc_url( $pdf_url );
		return '<li><b>' . $label . ': </b><a class="like-json-pdf" href="' . $href . '" download="Aushang.pdf">' . esc_html__( 'Aushang', 'like-json' ) . '</a></li>';
	}
}
