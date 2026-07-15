<?php
/**
 * Persistent print submission archive.
 *
 * @package PridgeWPEndpoint
 */

namespace Pridge;

defined( 'ABSPATH' ) || exit;

final class ArchiveRepository {
	/** @var \wpdb */
	private $db;

	/**
	 * @param \wpdb $db WordPress database connection.
	 */
	public function __construct( \wpdb $db ) {
		$this->db = $db;
	}

	/**
	 * @return string
	 */
	public function table_name() {
		return $this->db->prefix . 'pridge_archive';
	}

	/**
	 * Store a binary-safe snapshot of one submission attempt.
	 *
	 * @param array<string, mixed> $entry Archive values.
	 * @return int
	 */
	public function record( array $entry ) {
		$metadata = isset( $entry['metadata'] ) && is_array( $entry['metadata'] ) ? $entry['metadata'] : array();
		$payload  = isset( $entry['payload'] ) && is_string( $entry['payload'] ) ? $entry['payload'] : '';

		$inserted = $this->db->insert(
			$this->table_name(),
			array(
				'job_id'       => ! empty( $entry['job_id'] ) ? (int) $entry['job_id'] : null,
				'endpoint_id'  => sanitize_key( $entry['endpoint_id'] ?? '' ),
				'endpoint_name'=> sanitize_text_field( $entry['endpoint_name'] ?? '' ),
				'document_type'=> sanitize_key( $entry['document_type'] ?? 'custom' ),
				'source'       => sanitize_key( $entry['source'] ?? 'wordpress' ),
				'order_id'     => ! empty( $entry['order_id'] ) ? (int) $entry['order_id'] : null,
				'status'       => sanitize_key( $entry['status'] ?? 'failed' ),
				'content_type' => sanitize_text_field( $entry['content_type'] ?? 'application/octet-stream' ),
				'payload'      => base64_encode( $payload ),
				'metadata'     => wp_json_encode( $metadata ),
				'error_code'   => sanitize_key( $entry['error_code'] ?? '' ),
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return false === $inserted ? 0 : (int) $this->db->insert_id;
	}

	/**
	 * @param int $archive_id Archive row ID.
	 * @return array<string, mixed>|null
	 */
	public function find( $archive_id ) {
		$table = $this->table_name();
		$row   = $this->db->get_row(
			$this->db->prepare( "SELECT * FROM {$table} WHERE id = %d", $archive_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param int $page     One-based page.
	 * @param int $per_page Rows per page.
	 * @return array<int, array<string, mixed>>
	 */
	public function fetch_page( $page = 1, $per_page = 20 ) {
		$table  = $this->table_name();
		$limit  = max( 1, min( 100, (int) $per_page ) );
		$offset = ( max( 1, (int) $page ) - 1 ) * $limit;

		return (array) $this->db->get_results(
			$this->db->prepare( "SELECT id, job_id, endpoint_id, endpoint_name, document_type, source, order_id, status, content_type, error_code, created_at FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
	}

	/**
	 * @return int
	 */
	public function count() {
		$table = $this->table_name();

		return (int) $this->db->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * @param array<string, mixed> $row Archive row.
	 * @return string
	 */
	public function decode_payload( array $row ) {
		$decoded = base64_decode( (string) ( $row['payload'] ?? '' ), true );

		return false === $decoded ? '' : $decoded;
	}
}
