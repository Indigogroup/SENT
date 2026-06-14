<?php
defined( 'ABSPATH' ) || exit;

/**
 * Handles AJAX requests for Woo Print Stock Lists.
 */
class Woo_PSL_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_woo_psl_generate',  [ $this, 'handle_generate' ] );
		add_action( 'wp_ajax_woo_psl_delete',    [ $this, 'handle_delete' ] );
		add_action( 'wp_ajax_woo_psl_download',  [ $this, 'handle_download' ] );
	}

	// -----------------------------------------------------------------------
	// Generate
	// -----------------------------------------------------------------------

	public function handle_generate(): void {
		check_ajax_referer( 'woo_psl_generate', '_wpnonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Brak uprawnień.', 'woo-print-stock-lists' ) ], 403 );
		}

		$raw_ids      = isset( $_POST['category_ids'] ) ? (array) $_POST['category_ids'] : [];
		$category_ids = array_map( 'absint', $raw_ids );
		$category_ids = array_filter( $category_ids );
		$category_ids = array_values( $category_ids );

		if ( empty( $category_ids ) ) {
			wp_send_json_error( [ 'message' => __( 'Nie wybrano żadnych kategorii.', 'woo-print-stock-lists' ) ] );
		}

		$result = Woo_PSL_Generator::generate( $category_ids );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( $result );
	}

	// -----------------------------------------------------------------------
	// Delete
	// -----------------------------------------------------------------------

	public function handle_delete(): void {
		$record_id = absint( $_POST['id'] ?? 0 );

		check_ajax_referer( 'woo_psl_delete_' . $record_id, '_wpnonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Brak uprawnień.', 'woo-print-stock-lists' ) ], 403 );
		}

		if ( ! $record_id ) {
			wp_send_json_error( [ 'message' => __( 'Nieprawidłowy identyfikator.', 'woo-print-stock-lists' ) ] );
		}

		$result = Woo_PSL_Generator::delete( $record_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success();
	}

	// -----------------------------------------------------------------------
	// Download
	// -----------------------------------------------------------------------

	public function handle_download(): void {
		$record_id = absint( $_GET['id'] ?? 0 );
		$type      = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : '';

		check_ajax_referer( 'woo_psl_download_' . $record_id, '_wpnonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Brak uprawnień.', 'woo-print-stock-lists' ), 403 );
		}

		if ( ! $record_id || ! in_array( $type, [ 'xlsx', 'pdf' ], true ) ) {
			wp_die( esc_html__( 'Nieprawidłowe żądanie.', 'woo-print-stock-lists' ), 400 );
		}

		$record = Woo_PSL_DB::get( $record_id );
		if ( ! $record ) {
			wp_die( esc_html__( 'Plik nie istnieje.', 'woo-print-stock-lists' ), 404 );
		}

		$upload    = wp_upload_dir();
		$dir       = trailingslashit( $upload['basedir'] ) . 'woo-psl/';
		$file_name = $type === 'xlsx' ? $record->xlsx_file : $record->pdf_file;
		$file_path = $dir . basename( $file_name );

		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			wp_die( esc_html__( 'Plik nie istnieje lub jest niedostępny.', 'woo-print-stock-lists' ), 404 );
		}

		$mime = $type === 'xlsx'
			? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
			: 'application/pdf';

		// Serve file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions
		$file_size = filesize( $file_path );
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: attachment; filename="' . basename( $file_name ) . '"' );
		header( 'Content-Length: ' . $file_size );
		header( 'Cache-Control: no-store' );
		header( 'Pragma: no-cache' );

		// Flush output buffers.
		if ( ob_get_level() ) {
			ob_end_clean();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions
		readfile( $file_path );
		exit;
	}
}
