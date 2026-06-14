<?php
defined( 'ABSPATH' ) || exit;

/**
 * Handles all database operations for Woo Print Stock Lists.
 */
class Woo_PSL_DB {

	const TABLE = 'woo_psl_lists';

	/** Create the plugin table on activation. */
	public static function create_table(): void {
		global $wpdb;

		$table      = $wpdb->prefix . self::TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			date_generated datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			category_ids text NOT NULL,
			category_names text NOT NULL,
			xlsx_file varchar(500) NOT NULL DEFAULT '',
			pdf_file varchar(500) NOT NULL DEFAULT '',
			PRIMARY KEY (id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/** Insert a new list record. Returns inserted ID or false on failure. */
	public static function insert( array $data ) {
		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . self::TABLE,
			[
				'date_generated' => current_time( 'mysql' ),
				'category_ids'   => wp_json_encode( $data['category_ids'] ),
				'category_names' => sanitize_text_field( $data['category_names'] ),
				'xlsx_file'      => sanitize_text_field( $data['xlsx_file'] ),
				'pdf_file'       => sanitize_text_field( $data['pdf_file'] ),
			],
			[ '%s', '%s', '%s', '%s', '%s' ]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/** Fetch a single record by ID. */
	public static function get( int $id ): ?object {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM `' . $wpdb->prefix . self::TABLE . '` WHERE id = %d',
				$id
			)
		);
	}

	/** Fetch all records, newest first. */
	public static function get_all(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (array) $wpdb->get_results(
			'SELECT * FROM `' . $wpdb->prefix . self::TABLE . '` ORDER BY date_generated DESC'
		);
	}

	/** Delete a record. Returns true on success. */
	public static function delete( int $id ): bool {
		global $wpdb;

		$deleted = $wpdb->delete(
			$wpdb->prefix . self::TABLE,
			[ 'id' => $id ],
			[ '%d' ]
		);

		return (bool) $deleted;
	}
}
