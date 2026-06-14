<?php
defined( 'ABSPATH' ) || exit;

/**
 * PDF generator for Woo Print Stock Lists.
 *
 * Uses FPDF 1.9 (bundled in vendor/fpdf/) together with LiberationSans
 * (ISO-8859-2 encoded font files in vendor/fpdf/font/) so that Polish
 * diacritics render correctly without any additional PHP extensions.
 *
 * Requires: iconv (bundled with PHP since 5.0, always available in WP).
 */
class Woo_PSL_Pdf {

/**
 * Generates a PDF file and saves it to $file_path.
 *
 * @param array  $rows        [ ['sku'=>'', 'name'=>'', 'stock'=>0], ... ]
 * @param float  $total_stock
 * @param string $categories  Human-readable category label (UTF-8).
 * @param string $file_path   Absolute path where the PDF should be saved.
 * @return bool
 */
public static function generate( array $rows, float $total_stock, string $categories, string $file_path ): bool {
require_once WOO_PSL_PLUGIN_DIR . 'includes/vendor/fpdf/fpdf.php';

$font_dir = WOO_PSL_PLUGIN_DIR . 'includes/vendor/fpdf/font/';

$pdf = new FPDF( 'P', 'pt', 'A4' );
$pdf->SetAutoPageBreak( true, 40 );
$pdf->SetMargins( 40, 40, 40 );

// Register LiberationSans in two weights (ISO-8859-2 encoded).
$pdf->AddFont( 'LiberationSans', '',  'LiberationSans-Regular.json', $font_dir );
$pdf->AddFont( 'LiberationSans', 'B', 'LiberationSans-Bold.json',    $font_dir );

$pdf->AddPage();

$content_w = $pdf->GetPageWidth() - 80; // left+right margin = 80 pt
$col_sku   = 80;
$col_stock = 60;
$col_name  = $content_w - $col_sku - $col_stock;
$row_h     = 14;

// ── Title ────────────────────────────────────────────────────────────
$pdf->SetFont( 'LiberationSans', 'B', 13 );
$pdf->Cell( $content_w, 18, self::enc( 'Stany magazynowe' ), 0, 1 );

// ── Subtitle ─────────────────────────────────────────────────────────
$pdf->SetFont( 'LiberationSans', '', 9 );
$pdf->Cell( $content_w, 13, self::enc( 'Kategorie: ' . $categories ), 0, 1 );
$pdf->Cell( $content_w, 13, self::enc( 'Data: ' . date_i18n( 'Y-m-d H:i' ) ), 0, 1 );

// ── Separator ────────────────────────────────────────────────────────
$pdf->Ln( 2 );
$pdf->Line( 40, $pdf->GetY(), $pdf->GetPageWidth() - 40, $pdf->GetY() );
$pdf->Ln( 4 );

// ── Table header ─────────────────────────────────────────────────────
$pdf->SetFont( 'LiberationSans', 'B', 10 );
$pdf->Cell( $col_sku,   $row_h, self::enc( 'SKU' ),   0, 0 );
$pdf->Cell( $col_name,  $row_h, self::enc( 'Nazwa' ), 0, 0 );
$pdf->Cell( $col_stock, $row_h, self::enc( 'Stan' ),  0, 1, 'R' );

$pdf->Line( 40, $pdf->GetY(), $pdf->GetPageWidth() - 40, $pdf->GetY() );
$pdf->Ln( 2 );

// ── Data rows ────────────────────────────────────────────────────────
$pdf->SetFont( 'LiberationSans', '', 10 );

foreach ( $rows as $row ) {
$sku   = (string) ( $row['sku']   ?? '—' );
$name  = (string) ( $row['name']  ?? '' );
$stock = self::format_stock( (float) ( $row['stock'] ?? 0 ) );

// Encode once here; split_to_lines works with already-encoded bytes.
$sku_enc   = self::enc( $sku );
$name_enc  = self::enc( $name );
$stock_enc = self::enc( $stock );

// Split the encoded name into lines that fit the column.
$name_lines = self::split_to_lines( $pdf, $name_enc, $col_name );
$lines      = max( 1, count( $name_lines ) );
$cell_h     = $lines * $row_h;

// Page-break guard.
if ( $pdf->GetY() + $cell_h > $pdf->GetPageHeight() - 40 ) {
$pdf->AddPage();
$pdf->SetFont( 'LiberationSans', 'B', 10 );
$pdf->Cell( $col_sku,   $row_h, self::enc( 'SKU' ),   0, 0 );
$pdf->Cell( $col_name,  $row_h, self::enc( 'Nazwa' ), 0, 0 );
$pdf->Cell( $col_stock, $row_h, self::enc( 'Stan' ),  0, 1, 'R' );
$pdf->Line( 40, $pdf->GetY(), $pdf->GetPageWidth() - 40, $pdf->GetY() );
$pdf->Ln( 2 );
$pdf->SetFont( 'LiberationSans', '', 10 );
}

$y_before = $pdf->GetY();

// SKU cell (single line, vertically centred for multi-line name rows).
$pdf->SetXY( 40, $y_before + ( $cell_h / 2 ) - ( $row_h / 2 ) );
$pdf->Cell( $col_sku, $row_h, $sku_enc, 0, 0 );

// Name cell (possibly multi-line) — already encoded, do NOT call enc() again.
$pdf->SetXY( 40 + $col_sku, $y_before );
foreach ( $name_lines as $idx => $line ) {
if ( $idx > 0 ) {
$pdf->SetX( 40 + $col_sku );
}
$pdf->Cell( $col_name, $row_h, $line, 0, 1 );
}

// Stock cell (single line, vertically centred).
$pdf->SetXY( 40 + $col_sku + $col_name, $y_before + ( $cell_h / 2 ) - ( $row_h / 2 ) );
$pdf->Cell( $col_stock, $row_h, $stock_enc, 0, 0, 'R' );

$pdf->SetXY( 40, $y_before + $cell_h + 2 );
}

// ── Total row ────────────────────────────────────────────────────────
$pdf->Line( 40, $pdf->GetY(), $pdf->GetPageWidth() - 40, $pdf->GetY() );
$pdf->Ln( 3 );
$pdf->SetFont( 'LiberationSans', 'B', 10 );
$pdf->Cell( $col_sku,   $row_h, self::enc( 'SUMA' ),                               0, 0 );
$pdf->Cell( $col_name,  $row_h, '',                                                 0, 0 );
$pdf->Cell( $col_stock, $row_h, self::enc( self::format_stock( $total_stock ) ),    0, 1, 'R' );

// ── Save file ────────────────────────────────────────────────────────
$bytes = $pdf->Output( 'S' );
// phpcs:ignore WordPress.WP.AlternativeFunctions
return (bool) file_put_contents( $file_path, $bytes );
}

// ── Helpers ──────────────────────────────────────────────────────────────

/**
 * Converts a UTF-8 string to ISO-8859-2 for FPDF.
 * Characters outside ISO-8859-2 are replaced by '?'.
 * Must be called exactly once per string before passing to FPDF.
 */
private static function enc( string $utf8 ): string {
if ( function_exists( 'iconv' ) ) {
$result = iconv( 'UTF-8', 'ISO-8859-2//TRANSLIT', $utf8 );
if ( false !== $result ) {
return $result;
}
}
// Fallback: strip non-ASCII.
return preg_replace( '/[^\x00-\x7F]/', '?', $utf8 );
}

/** Formats a stock quantity: integer → no decimals, float → 2 decimals. */
private static function format_stock( float $v ): string {
return fmod( $v, 1.0 ) === 0.0
? (string) (int) $v
: number_format( $v, 2, ',', ' ' );
}

/**
 * Wraps an already ISO-8859-2-encoded string to fit $max_width pts.
 * Uses FPDF's own GetStringWidth for accurate measurements.
 * Returns an array of ISO-8859-2 encoded line strings ready for Cell().
 *
 * @param FPDF   $pdf       The FPDF instance (font must already be set).
 * @param string $encoded   ISO-8859-2 encoded text.
 * @param float  $max_width Maximum column width in points.
 * @return string[]
 */
private static function split_to_lines( FPDF $pdf, string $encoded, float $max_width ): array {
if ( '' === $encoded ) {
return [ '' ];
}

$words = explode( ' ', $encoded );
$lines = [];
$line  = '';

foreach ( $words as $word ) {
$test = $line === '' ? $word : $line . ' ' . $word;
if ( $pdf->GetStringWidth( $test ) <= $max_width ) {
$line = $test;
} else {
if ( $line !== '' ) {
$lines[] = $line;
}
// If a single word is wider than the column, truncate it to fit.
if ( $pdf->GetStringWidth( $word ) > $max_width ) {
while ( strlen( $word ) > 1 && $pdf->GetStringWidth( $word . '...' ) > $max_width ) {
	$word = substr( $word, 0, -1 );
}
$word .= '...';
}
$line = $word;
}
}
if ( $line !== '' ) {
$lines[] = $line;
}

return $lines ?: [ '' ];
}
}
