<?php
/**
 * Data model for student works (Abschlussarbeiten).
 *
 * @package LIKE_Json
 */

namespace LIKE_Json\API;

/**
 * Repository for student works, with filtering and safe HTML output.
 */
class Works {

	/**
	 * HTTP client.
	 *
	 * @var Client
	 */
	private $client;

	/**
	 * Constructor.
	 *
	 * @param Client $client HTTP client.
	 */
	public function __construct( Client $client ) {
		$this->client = $client;
	}

	/**
	 * List of valid task endpoints.
	 *
	 * @return array
	 */
	public static function tasks() {
		return array(
			'bachelorarbeiten'   => __( 'Bachelorarbeiten', 'like-json' ),
			'masterarbeiten'     => __( 'Masterarbeiten', 'like-json' ),
			'forschungspraktika' => __( 'Forschungspraktika', 'like-json' ),
			'projektarbeiten'    => __( 'Projektarbeiten', 'like-json' ),
			'sonstiges'          => __( 'Sonstige Arbeiten', 'like-json' ),
		);
	}

	/**
	 * Get data for a task.
	 *
	 * @param string $task    Task endpoint.
	 * @param string $id      Optional ID filter.
	 * @param string $advisor Optional advisor filter.
	 * @param string $status  Optional status filter.
	 * @return array
	 */
	public function get( $task, $id = '', $advisor = '', $status = '' ) {
		if ( ! is_string( $task ) || ! array_key_exists( $task, self::tasks() ) ) {
			return array();
		}

		$data = $this->client->get_json( $task );
		if ( ! is_array( $data ) ) {
			return array();
		}

		if ( '' !== $id ) {
			$data = $this->filter_by_id( $data, $id );
		}
		if ( '' !== $advisor ) {
			$data = $this->filter_by_advisor( $data, $advisor );
		}
		if ( '' !== $status ) {
			$data = $this->filter_by_status( $data, $status );
		}

		return $data;
	}

	/**
	 * Get all tasks combined, keyed by task name.
	 *
	 * @return array
	 */
	public function get_all() {
		$all = array();
		foreach ( array_keys( self::tasks() ) as $task ) {
			$data = $this->client->get_json( $task );
			if ( is_array( $data ) && ! empty( $data ) ) {
				$all[ $task ] = $data;
			}
		}
		return $all;
	}

	/**
	 * Get the sanitized error status of the most recent data request.
	 *
	 * @return string
	 */
	public function get_last_error() {
		return $this->client->get_last_error();
	}

	/**
	 * Filter records by ID.
	 *
	 * @param array  $data Records.
	 * @param string $id   ID.
	 * @return array
	 */
	private function filter_by_id( $data, $id ) {
		foreach ( $data as $record ) {
			if ( isset( $record['id'] ) && (string) $record['id'] === (string) $id ) {
				return array( $record );
			}
		}
		return array();
	}

	/**
	 * Filter records by advisor (substring, case-insensitive).
	 *
	 * @param array  $data    Records.
	 * @param string $advisor Advisor.
	 * @return array
	 */
	private function filter_by_advisor( $data, $advisor ) {
		$result = array();
		foreach ( $data as $record ) {
			if ( isset( $record['betreuer'] ) && false !== stripos( $record['betreuer'], $advisor ) ) {
				$result[] = $record;
			}
		}
		return $result;
	}

	/**
	 * Filter records by status (substring match).
	 *
	 * @param array  $data   Records.
	 * @param string $status Status.
	 * @return array
	 */
	private function filter_by_status( $data, $status ) {
		$result = array();
		foreach ( $data as $record ) {
			if ( isset( $record['status'] ) && false !== stripos( $record['status'], $status ) ) {
				$result[] = $record;
			}
		}
		return $result;
	}

	/**
	 * Render records as a list or accordion.
	 *
	 * @param array  $data       Records.
	 * @param string $format     'accordion' or 'liste'.
	 * @param string $ressources 'link' or 'base64'.
	 * @return string
	 */
	public function render( $data, $format = 'liste', $ressources = 'link' ) {
		if ( empty( $data ) || ! is_array( $data ) ) {
			return '';
		}

		$formatter = new Formatter();
		if ( 'accordion' === $format ) {
			return $formatter->accordion( $data, $ressources );
		}
		return $formatter->list( $data, $ressources );
	}
}
