<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'FPDF' ) ) {
require_once __DIR__ . '/vendor/fpdf/fpdf.php';
}

/**
 * Internal FPDF subclass that repeats the table header on each new page.
 *
 * @internal
 */
class Woo_PSL_Pdf_Doc extends FPDF {

/** @var float Column width: SKU (mm). */
public float $col_sku   = 30;
/** @var float Column width: Name (mm). */
public float $col_name  = 0;
/** @var float Column width: Stock (mm). */
public float $col_stock = 22;

public function Header(): void {
// Page 1 has a full title + subtitle drawn manually; skip the auto-header there.
if ( $this->PageNo() === 1 ) {
return;
}
$this->SetFont( 'LiberationSans', 'B', 10 );
$this->Cell( $this->col_sku,   6, Woo_PSL_Pdf::enc( 'SKU' ),   0, 0 );
$this->Cell( $this->col_name,  6, Woo_PSL_Pdf::enc( 'Nazwa' ), 0, 0 );
$this->Cell( $this->col_stock, 6, Woo_PSL_Pdf::enc( 'Stan' ),  0, 1, 'R' );
$this->Line( $this->lMargin, $this->GetY(), $this->w - $this->rMargin, $this->GetY() );
$this->Ln( 1 );
}
}

/**
 * PDF generator for Woo Print Stock Lists.
 *
 * Uses FPDF 1.9 with bundled LiberationSans (ISO-8859-2) to correctly
 * render Polish diacritics. Requires only the iconv PHP extension (standard).
 */
class Woo_PSL_Pdf {

// Column widths (mm).
const COL_SKU   = 30;
const COL_STOCK = 22;
const MARGIN    = 15;
const PAGE_W    = 210; // A4 width in mm

/**
 * Generates a PDF file and saves it to $file_path.
 *
 * @param array  $rows        [ ['sku'=>'', 'name'=>'', 'stock'=>0], ... ]
 * @param float  $total_stock
 * @param string $categories  Human-readable category label.
 * @param string $file_path   Absolute path where the PDF should be saved.
 * @return bool
 */
public static function generate( array $rows, float $total_stock, string $categories, string $file_path ): bool {
$font_dir = __DIR__ . '/vendor/fpdf/font/';
$col_name = self::PAGE_W - 2 * self::MARGIN - self::COL_SKU - self::COL_STOCK;
$lx1      = self::MARGIN;
$lx2      = self::PAGE_W - self::MARGIN;

$pdf            = new Woo_PSL_Pdf_Doc( 'P', 'mm', 'A4' );
$pdf->col_sku   = self::COL_SKU;
$pdf->col_name  = $col_name;
$pdf->col_stock = self::COL_STOCK;
$pdf->SetMargins( self::MARGIN, self::MARGIN, self::MARGIN );
$pdf->SetAutoPageBreak( true, self::MARGIN );
$pdf->AddFont( 'LiberationSans', '',  'LiberationSans-Regular.json', $font_dir );
$pdf->AddFont( 'LiberationSans', 'B', 'LiberationSans-Bold.json',    $font_dir );
$pdf->AddPage();

// Title.
$pdf->SetFont( 'LiberationSans', 'B', 13 );
$pdf->Cell( 0, 8, self::enc( 'Stany magazynowe' ), 0, 1 );

// Subtitle.
$pdf->SetFont( 'LiberationSans', '', 9 );
$pdf->Cell( 0, 5, self::enc( 'Kategorie: ' . $categories ), 0, 1 );
$pdf->Cell( 0, 5, self::enc( 'Data: ' . date_i18n( 'Y-m-d H:i' ) ), 0, 1 );
$pdf->Ln( 2 );

// Separator line.
$pdf->Line( $lx1, $pdf->GetY(), $lx2, $pdf->GetY() );
$pdf->Ln( 2 );

// Table header (also repeated on each new page via Woo_PSL_Pdf_Doc::Header()).
$pdf->SetFont( 'LiberationSans', 'B', 10 );
$pdf->Cell( self::COL_SKU,   6, self::enc( 'SKU' ),   0, 0 );
$pdf->Cell( $col_name,       6, self::enc( 'Nazwa' ), 0, 0 );
$pdf->Cell( self::COL_STOCK, 6, self::enc( 'Stan' ),  0, 1, 'R' );
$pdf->Line( $lx1, $pdf->GetY(), $lx2, $pdf->GetY() );
$pdf->Ln( 1 );

// Data rows.
$pdf->SetFont( 'LiberationSans', '', 10 );
foreach ( $rows as $row ) {
$sku   = (string) ( $row['sku']   ?? '' );
$name  = (string) ( $row['name']  ?? '' );
$stock = self::format_stock( (float) ( $row['stock'] ?? 0 ) );

$row_y  = $pdf->GetY();
$name_x = self::MARGIN + self::COL_SKU;

// SKU cell (single line, no auto-break).
$pdf->Cell( self::COL_SKU, 6, self::enc( $sku ), 0, 0 );

// Name cell (may wrap to multiple lines).
$pdf->MultiCell( $col_name, 6, self::enc( $name ), 0, 'L' );
$after_y = $pdf->GetY();

// Stock cell: placed at the same y as the row start, right-aligned.
$pdf->SetXY( $name_x + $col_name, $row_y );
$pdf->Cell( self::COL_STOCK, 6, self::enc( $stock ), 0, 0, 'R' );

// Advance past the tallest cell.
$pdf->SetY( $after_y );
}

// Total row.
$pdf->Line( $lx1, $pdf->GetY(), $lx2, $pdf->GetY() );
$pdf->Ln( 1 );
$pdf->SetFont( 'LiberationSans', 'B', 10 );
$pdf->Cell( self::COL_SKU,   6, self::enc( 'SUMA' ), 0, 0 );
$pdf->Cell( $col_name,       6, '',                  0, 0 );
$pdf->Cell( self::COL_STOCK, 6, self::enc( self::format_stock( $total_stock ) ), 0, 1, 'R' );

$pdf->Output( 'F', $file_path );

return file_exists( $file_path );
}

/**
 * Convert a UTF-8 string to ISO-8859-2 for FPDF.
 * Characters not representable in ISO-8859-2 are silently dropped.
 *
 * Declared public so that Woo_PSL_Pdf_Doc::Header() can call it.
 */
public static function enc( string $text ): string {
return (string) iconv( 'UTF-8', 'ISO-8859-2//IGNORE', $text );
}

private static function format_stock( float $v ): string {
return fmod( $v, 1.0 ) === 0.0 ? (string) (int) $v : number_format( $v, 2, ',', ' ' );
}
}
