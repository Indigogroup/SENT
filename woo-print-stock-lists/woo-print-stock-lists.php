<?php
/**
 * Plugin Name:       Woo Print Stock Lists
 * Plugin URI:        https://github.com/Indigogroup/SENT
 * Description:       Adds a "Drukuj stany" page in WooCommerce admin for generating printable stock lists by product category tree.
 * Version:           1.0.1
 * Author:            Indigogroup
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 7.0
 * WC tested up to:   9.9
 * Text Domain:       woo-print-stock-lists
 * License:           GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'WOO_PSL_VERSION',     '1.0.1' );
define( 'WOO_PSL_PLUGIN_FILE', __FILE__ );
define( 'WOO_PSL_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'WOO_PSL_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

// Declare WooCommerce feature compatibility before WC initialises.
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'product_block_editor', __FILE__, true );
	}
} );

// Activation: create DB table.
register_activation_hook( __FILE__, function () {
	require_once WOO_PSL_PLUGIN_DIR . 'includes/class-db.php';
	Woo_PSL_DB::create_table();
} );

// Bootstrap the plugin after all plugins are loaded.
add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'Woo Print Stock Lists wymaga aktywnego WooCommerce.', 'woo-print-stock-lists' )
				. '</p></div>';
		} );
		return;
	}

	require_once WOO_PSL_PLUGIN_DIR . 'includes/class-db.php';
	require_once WOO_PSL_PLUGIN_DIR . 'includes/class-category-tree.php';
	require_once WOO_PSL_PLUGIN_DIR . 'includes/class-product-query.php';
	require_once WOO_PSL_PLUGIN_DIR . 'includes/class-xlsx.php';
	require_once WOO_PSL_PLUGIN_DIR . 'includes/class-pdf.php';
	require_once WOO_PSL_PLUGIN_DIR . 'includes/class-generator.php';
	require_once WOO_PSL_PLUGIN_DIR . 'includes/class-ajax.php';
	require_once WOO_PSL_PLUGIN_DIR . 'includes/class-admin-menu.php';

	new Woo_PSL_Admin_Menu();
	new Woo_PSL_Ajax();
} );
