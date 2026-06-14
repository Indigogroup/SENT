<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registers the "Drukuj stany" submenu page under WooCommerce.
 */
class Woo_PSL_Admin_Menu {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Drukuj stany', 'woo-print-stock-lists' ),
			__( 'Drukuj stany', 'woo-print-stock-lists' ),
			'manage_woocommerce',
			'woo-print-stock-lists',
			[ $this, 'render_page' ]
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( 'woocommerce_page_woo-print-stock-lists' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'woo-psl-admin',
			WOO_PSL_PLUGIN_URL . 'admin/css/print-stock.css',
			[],
			WOO_PSL_VERSION
		);

		wp_enqueue_script(
			'woo-psl-admin',
			WOO_PSL_PLUGIN_URL . 'admin/js/print-stock.js',
			[ 'jquery' ],
			WOO_PSL_VERSION,
			true
		);

		wp_localize_script( 'woo-psl-admin', 'wooPSL', [
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'woo_psl_generate' ),
			'msgLoading'    => __( 'Generowanie…', 'woo-print-stock-lists' ),
			'msgError'      => __( 'Wystąpił błąd. Spróbuj ponownie.', 'woo-print-stock-lists' ),
			'msgConfirmDel' => __( 'Czy na pewno chcesz usunąć tę listę?', 'woo-print-stock-lists' ),
			'msgDeleting'   => __( 'Usuwanie…', 'woo-print-stock-lists' ),
		] );
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Brak uprawnień.', 'woo-print-stock-lists' ) );
		}

		require_once WOO_PSL_PLUGIN_DIR . 'admin/views/page-print-stock.php';
	}

	/**
	 * Renders category tree nodes recursively.
	 *
	 * @param array $nodes  Array of category node arrays.
	 * @param int   $depth  Current nesting depth (0 = top level).
	 */
	public function render_tree_nodes( array $nodes, int $depth ): void {
		$indent_class = $depth > 0 ? ' woo-psl-subtree' : ' woo-psl-root-tree';
		$hidden_attr  = $depth > 0 ? ' style="display:none;"' : '';

		echo '<ul class="woo-psl-cat-list' . esc_attr( $indent_class ) . '"' . $hidden_attr . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		foreach ( $nodes as $node ) {
			$has_children = ! empty( $node['children'] );
			echo '<li class="woo-psl-cat-item' . ( $has_children ? ' has-children' : '' ) . '">';
			echo '<label>';
			echo '<input type="checkbox" name="category_ids[]" '
				. 'value="' . esc_attr( (string) $node['id'] ) . '" '
				. 'data-id="' . esc_attr( (string) $node['id'] ) . '" '
				. 'data-has-children="' . ( $has_children ? '1' : '0' ) . '"> ';
			echo esc_html( $node['name'] );
			echo '</label>';

			if ( $has_children ) {
				$this->render_tree_nodes( $node['children'], $depth + 1 );
			}

			echo '</li>';
		}

		echo '</ul>';
	}
}
