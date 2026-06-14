<?php
defined( 'ABSPATH' ) || exit;

/**
 * Orchestrates stock list generation: queries products, saves files, stores DB record.
 */
class Woo_PSL_Generator {

	/**
	 * Generates the stock list based on selected category IDs.
	 *
	 * @param int[] $selected_cat_ids Checked category IDs from the form.
	 * @return array|WP_Error On success: ['id'=>int, 'row_html'=>string]. On failure: WP_Error.
	 */
	public static function generate( array $selected_cat_ids ) {
		if ( empty( $selected_cat_ids ) ) {
			return new WP_Error( 'no_categories', __( 'Nie wybrano żadnych kategorii.', 'woo-print-stock-lists' ) );
		}

		// Resolve effective categories.
		$children_map     = Woo_PSL_Category_Tree::get_children_map();
		$effective_cats   = Woo_PSL_Category_Tree::get_effective_categories( $selected_cat_ids, $children_map );
		$all_cat_ids      = Woo_PSL_Category_Tree::expand_to_descendants( $effective_cats, $children_map );

		// Fetch products.
		$rows        = Woo_PSL_Product_Query::get_rows( $all_cat_ids );
		$total_stock = (float) array_sum( array_column( $rows, 'stock' ) );

		// Determine category names for display (use the effective selection, not the expanded set).
		$cat_names  = Woo_PSL_Category_Tree::get_names( $selected_cat_ids );
		$cat_label  = implode( ', ', $cat_names );

		// Prepare upload directory.
		$upload    = wp_upload_dir();
		$dir       = trailingslashit( $upload['basedir'] ) . 'woo-psl';
		$dir_url   = trailingslashit( $upload['baseurl'] ) . 'woo-psl';

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
			// Protect directory from direct HTTP access.
			// Supports both Apache 2.2 and Apache 2.4+ syntax.
			$htaccess = "# Apache 2.4+\n"
				. "<IfModule mod_authz_core.c>\n"
				. "    Require all denied\n"
				. "</IfModule>\n"
				. "# Apache 2.2\n"
				. "<IfModule !mod_authz_core.c>\n"
				. "    Order Deny,Allow\n"
				. "    Deny from all\n"
				. "</IfModule>\n";
			// phpcs:ignore WordPress.WP.AlternativeFunctions
			file_put_contents( $dir . '/.htaccess', $htaccess );
			// phpcs:ignore WordPress.WP.AlternativeFunctions
			file_put_contents( $dir . '/index.php', '<?php // Silence is golden.' );
		}

		$slug      = sanitize_file_name( wp_date( 'Y-m-d_His' ) );
		$xlsx_name = 'stock-list-' . $slug . '.xlsx';
		$pdf_name  = 'stock-list-' . $slug . '.pdf';
		$xlsx_path = $dir . '/' . $xlsx_name;
		$pdf_path  = $dir . '/' . $pdf_name;

		// Generate files.
		$xlsx_ok = Woo_PSL_Xlsx::generate( $rows, $total_stock, $cat_label, $xlsx_path );
		$pdf_ok  = Woo_PSL_Pdf::generate(  $rows, $total_stock, $cat_label, $pdf_path );

		if ( ! $xlsx_ok || ! $pdf_ok ) {
			// Clean up partial files.
			@unlink( $xlsx_path ); // phpcs:ignore
			@unlink( $pdf_path );  // phpcs:ignore
			return new WP_Error( 'file_error', __( 'Nie udało się zapisać plików.', 'woo-print-stock-lists' ) );
		}

		// Save DB record.
		$record_id = Woo_PSL_DB::insert( [
			'category_ids'   => $selected_cat_ids,
			'category_names' => $cat_label,
			'xlsx_file'      => $xlsx_name,
			'pdf_file'        => $pdf_name,
		] );

		if ( ! $record_id ) {
			@unlink( $xlsx_path ); // phpcs:ignore
			@unlink( $pdf_path );  // phpcs:ignore
			return new WP_Error( 'db_error', __( 'Nie udało się zapisać rekordu w bazie danych.', 'woo-print-stock-lists' ) );
		}

		$record = Woo_PSL_DB::get( $record_id );

		return [
			'id'       => $record_id,
			'row_html' => self::build_table_row_html( $record ),
		];
	}

	/**
	 * Deletes a generated list record and its associated files.
	 *
	 * @param int $record_id
	 * @return true|WP_Error
	 */
	public static function delete( int $record_id ) {
		$record = Woo_PSL_DB::get( $record_id );

		if ( ! $record ) {
			return new WP_Error( 'not_found', __( 'Rekord nie istnieje.', 'woo-print-stock-lists' ) );
		}

		$upload = wp_upload_dir();
		$dir    = trailingslashit( $upload['basedir'] ) . 'woo-psl/';

		if ( $record->xlsx_file ) {
			@unlink( $dir . basename( $record->xlsx_file ) ); // phpcs:ignore
		}
		if ( $record->pdf_file ) {
			@unlink( $dir . basename( $record->pdf_file ) ); // phpcs:ignore
		}

		Woo_PSL_DB::delete( $record_id );

		return true;
	}

	/**
	 * Builds the HTML for a single history table row.
	 */
	public static function build_table_row_html( object $record ): string {
		$date      = esc_html( wp_date( 'Y-m-d H:i', strtotime( $record->date_generated ) ) );
		$cats      = esc_html( $record->category_names );
		$nonce_del = wp_create_nonce( 'woo_psl_delete_' . $record->id );
		$nonce_dl  = wp_create_nonce( 'woo_psl_download_' . $record->id );

		$xlsx_url = add_query_arg( [
			'action'   => 'woo_psl_download',
			'id'       => $record->id,
			'type'     => 'xlsx',
			'_wpnonce' => $nonce_dl,
		], admin_url( 'admin-ajax.php' ) );

		$pdf_url = add_query_arg( [
			'action'   => 'woo_psl_download',
			'id'       => $record->id,
			'type'     => 'pdf',
			'_wpnonce' => $nonce_dl,
		], admin_url( 'admin-ajax.php' ) );

		return '<tr data-id="' . (int) $record->id . '">'
			. '<td>' . $date . '</td>'
			. '<td>' . $cats . '</td>'
			. '<td>'
			. '<a href="' . esc_url( $xlsx_url ) . '" class="button button-small">XLSX</a> '
			. '<a href="' . esc_url( $pdf_url ) . '" class="button button-small">PDF</a>'
			. '</td>'
			. '<td>'
			. '<button type="button" class="button button-small woo-psl-delete" '
			. 'data-id="' . (int) $record->id . '" '
			. 'data-nonce="' . esc_attr( $nonce_del ) . '">'
			. esc_html__( 'Usuń', 'woo-print-stock-lists' )
			. '</button>'
			. '</td>'
			. '</tr>';
	}
}
