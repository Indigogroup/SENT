<?php
/**
 * Admin page template: "Drukuj stany"
 *
 * Available variables:
 *   $this  – instance of Woo_PSL_Admin_Menu (provides render_tree_nodes())
 */
defined( 'ABSPATH' ) || exit;

$category_tree = Woo_PSL_Category_Tree::get_tree();
$history       = Woo_PSL_DB::get_all();
?>
<div class="wrap woo-psl-wrap">
<h1><?php esc_html_e( 'Drukuj stany', 'woo-print-stock-lists' ); ?></h1>

<div class="woo-psl-panel">
<h2><?php esc_html_e( 'Wybierz kategorie', 'woo-print-stock-lists' ); ?></h2>

<?php if ( empty( $category_tree ) ) : ?>
<p class="woo-psl-notice"><?php esc_html_e( 'Brak kategorii produktów WooCommerce.', 'woo-print-stock-lists' ); ?></p>
<?php else : ?>

<form id="woo-psl-form">
<div class="woo-psl-tree" id="woo-psl-tree">
<?php $this->render_tree_nodes( $category_tree, 0 ); ?>
</div>

<p class="submit">
<button type="submit" class="button button-primary" id="woo-psl-generate-btn">
<?php esc_html_e( 'Generuj listę', 'woo-print-stock-lists' ); ?>
</button>
</p>
</form>

<?php endif; ?>

<div id="woo-psl-messages"></div>
</div>

<div class="woo-psl-panel" id="woo-psl-history-panel">
<h2><?php esc_html_e( 'Wygenerowane listy', 'woo-print-stock-lists' ); ?></h2>

<p class="woo-psl-notice" id="woo-psl-empty-history"<?php echo empty( $history ) ? '' : ' style="display:none;"'; ?>>
<?php esc_html_e( 'Brak wygenerowanych list.', 'woo-print-stock-lists' ); ?>
</p>

<table class="widefat striped woo-psl-history-table" id="woo-psl-history-table"<?php echo empty( $history ) ? ' style="display:none;"' : ''; ?>>
<thead>
<tr>
<th><?php esc_html_e( 'Data', 'woo-print-stock-lists' ); ?></th>
<th><?php esc_html_e( 'Kategorie', 'woo-print-stock-lists' ); ?></th>
<th><?php esc_html_e( 'Pobierz', 'woo-print-stock-lists' ); ?></th>
<th><?php esc_html_e( 'Działanie', 'woo-print-stock-lists' ); ?></th>
</tr>
</thead>
<tbody id="woo-psl-history-body">
<?php foreach ( $history as $record ) : ?>
<?php echo Woo_PSL_Generator::build_table_row_html( $record ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
