<?php
defined( 'ABSPATH' ) || exit;

/**
 * Minimal self-contained PDF generator for Woo Print Stock Lists.
 *
 * Generates a valid PDF 1.4 file with:
 * – A4 portrait (595 × 842 pt)
 * – Helvetica font with a custom encoding that covers Polish diacritics
 *   (ISO-8859-2 / Windows-1250 characters mapped via /Differences)
 * – Automatic pagination
 * – Simple table layout for the stock list
 *
 * No external libraries or PHP extensions required.
 */
class Woo_PSL_Pdf {

	// Page dimensions (A4 in points, 1 pt = 1/72 inch).
	const PAGE_W  = 595;
	const PAGE_H  = 842;
	const MARGIN  = 40;

	// Font metrics for Helvetica at 1pt (×width_multiplier = actual width).
	// Source: Adobe Core 14 Fonts AFM data.
	private static $char_widths = [
		' ' => 278, '!' => 278, '"' => 355, '#' => 556, '$' => 556, '%' => 889,
		'&' => 667, "'" => 191, '(' => 333, ')' => 333, '*' => 389, '+' => 584,
		',' => 278, '-' => 333, '.' => 278, '/' => 278, '0' => 556, '1' => 556,
		'2' => 556, '3' => 556, '4' => 556, '5' => 556, '6' => 556, '7' => 556,
		'8' => 556, '9' => 556, ':' => 278, ';' => 278, '<' => 584, '=' => 584,
		'>' => 584, '?' => 556, '@' => 1015, 'A' => 667, 'B' => 667, 'C' => 722,
		'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778, 'H' => 722, 'I' => 278,
		'J' => 500, 'K' => 667, 'L' => 556, 'M' => 833, 'N' => 722, 'O' => 778,
		'P' => 667, 'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611, 'U' => 722,
		'V' => 667, 'W' => 944, 'X' => 667, 'Y' => 667, 'Z' => 611, '[' => 278,
		'\\' => 278, ']' => 278, '^' => 469, '_' => 556, '`' => 333, 'a' => 556,
		'b' => 556, 'c' => 500, 'd' => 556, 'e' => 556, 'f' => 278, 'g' => 556,
		'h' => 556, 'i' => 222, 'j' => 222, 'k' => 500, 'l' => 222, 'm' => 833,
		'n' => 556, 'o' => 556, 'p' => 556, 'q' => 556, 'r' => 333, 's' => 500,
		't' => 278, 'u' => 556, 'v' => 500, 'w' => 722, 'x' => 500, 'y' => 500,
		'z' => 500, '{' => 334, '|' => 260, '}' => 334, '~' => 584,
		// Extended Latin (approximate, similar to ASCII counterparts).
		'Ą' => 667, 'ą' => 556, 'Ć' => 722, 'ć' => 500, 'Ę' => 667, 'ę' => 556,
		'Ł' => 556, 'ł' => 222, 'Ń' => 722, 'ń' => 556, 'Ó' => 778, 'ó' => 556,
		'Ś' => 667, 'ś' => 500, 'Ź' => 611, 'ź' => 500, 'Ż' => 611, 'ż' => 500,
		'–' => 556, '—' => 889,
	];

	// Polish characters UTF-8 → ISO-8859-2 byte map.
	private static $polish_map = [
		'Ą' => "\xA1", 'ą' => "\xB1", 'Ć' => "\xC6", 'ć' => "\xE6",
		'Ę' => "\xCA", 'ę' => "\xEA", 'Ł' => "\xA3", 'ł' => "\xB3",
		'Ń' => "\xD1", 'ń' => "\xF1", 'Ó' => "\xD3", 'ó' => "\xF3",
		'Ś' => "\xA6", 'ś' => "\xB6", 'Ź' => "\xAC", 'ź' => "\xBC",
		'Ż' => "\xAF", 'ż' => "\xBF",
	];

	// -----------------------------------------------------------------------
	// Public API
	// -----------------------------------------------------------------------

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
		$pdf = new self();
		$pdf->build( $rows, $total_stock, $categories );
		$bytes = $pdf->get_bytes();

		// phpcs:ignore WordPress.WP.AlternativeFunctions
		return (bool) file_put_contents( $file_path, $bytes );
	}

	// -----------------------------------------------------------------------
	// Internal state
	// -----------------------------------------------------------------------

	private array  $objects  = [];
	private array  $pages    = [];
	private string $stream   = '';
	private float  $y        = 0;
	private int    $cur_page = 0;
	private float  $font_sz  = 10;

	// Column widths (in pts).
	private float $col_sku;
	private float $col_name;
	private float $col_stock;
	private float $row_h    = 14;
	private float $content_w;

	private function __construct() {
		$this->content_w  = self::PAGE_W - 2 * self::MARGIN;
		$this->col_sku    = 80;
		$this->col_stock  = 60;
		$this->col_name   = $this->content_w - $this->col_sku - $this->col_stock;
	}

	// -----------------------------------------------------------------------
	// PDF building
	// -----------------------------------------------------------------------

	private function build( array $rows, float $total_stock, string $categories ): void {
		$this->add_page();

		// Title.
		$this->set_font( 13, true );
		$this->write_text(
			self::MARGIN,
			$this->y,
			'Stany magazynowe',
			self::PAGE_W - 2 * self::MARGIN
		);
		$this->y += 16;

		// Subtitle.
		$this->set_font( 9, false );
		$this->write_text( self::MARGIN, $this->y, 'Kategorie: ' . $categories, self::PAGE_W - 2 * self::MARGIN );
		$this->y += 12;
		$this->write_text( self::MARGIN, $this->y, 'Data: ' . date_i18n( 'Y-m-d H:i' ), self::PAGE_W - 2 * self::MARGIN );
		$this->y += 16;

		// Draw separator line.
		$this->draw_hline( $this->y );
		$this->y += 6;

		// Table header.
		$this->draw_table_row( 'SKU', 'Nazwa', 'Stan', true );

		$this->draw_hline( $this->y );
		$this->y += 2;

		// Data rows.
		$this->set_font( 10, false );
		foreach ( $rows as $row ) {
			if ( $this->y > self::PAGE_H - self::MARGIN - 30 ) {
				$this->add_page();
				$this->draw_table_row( 'SKU', 'Nazwa', 'Stan', true );
				$this->draw_hline( $this->y );
				$this->y += 2;
				$this->set_font( 10, false );
			}

			$sku   = (string) ( $row['sku']   ?? '—' );
			$name  = (string) ( $row['name']  ?? '' );
			$stock = self::format_stock( (float) ( $row['stock'] ?? 0 ) );

			$this->draw_table_row( $sku, $name, $stock, false );
		}

		// Total.
		$this->draw_hline( $this->y );
		$this->y += 3;
		$this->draw_table_row( 'SUMA', '', self::format_stock( $total_stock ), true );

		$this->finalize_page();
	}

	private static function format_stock( float $v ): string {
		return fmod( $v, 1.0 ) === 0.0 ? (string) (int) $v : number_format( $v, 2, ',', ' ' );
	}

	// -----------------------------------------------------------------------
	// Drawing helpers
	// -----------------------------------------------------------------------

	private bool $is_bold = false;

	private function set_font( float $size, bool $bold ): void {
		$this->font_sz = $size;
		$this->is_bold = $bold;
	}

	/**
	 * Renders a single line of text at the given page coordinates (top-origin).
	 * Long text is silently truncated to fit $max_width (0 = no limit).
	 */
	private function write_text( float $x, float $y, string $utf8_text, float $max_width = 0 ): void {
		$text = $utf8_text;
		if ( $max_width > 0 && $this->text_width( $text ) > $max_width ) {
			$text = $this->truncate_to_width( $text, $max_width );
		}
		$this->emit_text( $this->encode( $text ), $x, $y, $this->is_bold );
	}

	private function draw_table_row( string $sku, string $name, string $stock, bool $bold ): void {
		$this->set_font( $this->font_sz, $bold );

		$x = (float) self::MARGIN;

		// Wrap name to fit column width.
		$name_lines = $this->wrap_text( $name, $this->col_name );
		$line_count = max( 1, count( $name_lines ) );
		$row_h      = $line_count * $this->row_h;

		// Vertically center single-line cells.
		$base_y = $this->y + ( $row_h / 2 ) - ( $this->font_sz / 2 );

		$this->emit_text( $this->encode( $sku ),   $x,                                    $base_y, $bold );
		$this->emit_text( $this->encode( $stock ),  $x + $this->col_sku + $this->col_name, $base_y, $bold );

		$ly = $this->y + ( $this->font_sz / 2 );
		foreach ( $name_lines as $line ) {
			$this->emit_text( $this->encode( $line ), $x + $this->col_sku, $ly, $bold );
			$ly += $this->row_h;
		}

		$this->y += $row_h + 2;
	}

	private function draw_hline( float $y ): void {
		$x1 = self::MARGIN;
		$x2 = self::PAGE_W - self::MARGIN;
		// Convert y from top-origin to PDF bottom-origin.
		$py = self::PAGE_H - $y;
		$this->stream .= sprintf( "%.2f %.2f m %.2f %.2f l S\n", $x1, $py, $x2, $py );
	}

	private function emit_text( string $encoded_text, float $x, float $y, bool $bold ): void {
		// PDF y is from bottom; our y is from top.
		$pdf_y = self::PAGE_H - $y - $this->font_sz;
		$font  = $bold ? '/F2' : '/F1';
		$sz    = $this->font_sz;

		$this->stream .= "BT\n";
		$this->stream .= sprintf( "%s %.2f Tf\n", $font, $sz );
		$this->stream .= sprintf( "%.2f %.2f Td\n", $x, $pdf_y );
		$this->stream .= '(' . $this->escape_pdf_string( $encoded_text ) . ") Tj\n";
		$this->stream .= "ET\n";
	}

	// -----------------------------------------------------------------------
	// Text wrapping
	// -----------------------------------------------------------------------

	private function wrap_text( string $text, float $max_width ): array {
		if ( '' === $text ) {
			return [ '' ];
		}

		$words = explode( ' ', $text );
		$lines = [];
		$line  = '';

		foreach ( $words as $word ) {
			$test = $line === '' ? $word : $line . ' ' . $word;
			if ( $this->text_width( $test ) <= $max_width ) {
				$line = $test;
			} else {
				if ( $line !== '' ) {
					$lines[] = $line;
				}
				// If a single word is too wide, truncate with ellipsis.
				if ( $this->text_width( $word ) > $max_width ) {
					$word = $this->truncate_to_width( $word, $max_width );
				}
				$line = $word;
			}
		}
		if ( $line !== '' ) {
			$lines[] = $line;
		}

		return $lines ?: [ '' ];
	}

	private function text_width( string $text ): float {
		$w    = 0;
		$chars = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
		foreach ( $chars as $ch ) {
			$w += self::$char_widths[ $ch ] ?? 500; // 500 = fallback avg width
		}
		return $w * $this->font_sz / 1000;
	}

	private function truncate_to_width( string $text, float $max_width ): string {
		$result = '';
		$chars  = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
		$ew     = $this->text_width( '…' );
		$used   = 0;
		foreach ( $chars as $ch ) {
			$cw = ( self::$char_widths[ $ch ] ?? 500 ) * $this->font_sz / 1000;
			if ( $used + $cw + $ew > $max_width ) {
				break;
			}
			$result .= $ch;
			$used   += $cw;
		}
		return $result . '…';
	}

	// -----------------------------------------------------------------------
	// Encoding: UTF-8 → ISO-8859-2
	// -----------------------------------------------------------------------

	private function encode( string $utf8 ): string {
		// Replace known Polish characters first.
		$encoded = str_replace(
			array_keys( self::$polish_map ),
			array_values( self::$polish_map ),
			$utf8
		);
		// Drop any remaining non-ASCII multi-byte sequences (safe fallback).
		return preg_replace( '/[^\x00-\xFF]/', '?', $encoded );
	}

	private function escape_pdf_string( string $s ): string {
		// Escape special PDF string characters.
		return str_replace(
			[ '\\', '(', ')', "\r", "\n" ],
			[ '\\\\', '\(', '\)', '\r', '\n' ],
			$s
		);
	}

	// -----------------------------------------------------------------------
	// Page management
	// -----------------------------------------------------------------------

	private function add_page(): void {
		if ( $this->cur_page > 0 ) {
			$this->finalize_page();
		}
		$this->cur_page++;
		$this->stream = '';
		$this->y      = (float) self::MARGIN;

		// Set line width.
		$this->stream .= "0.5 w\n";
	}

	private function finalize_page(): void {
		$this->pages[] = $this->stream;
	}

	// -----------------------------------------------------------------------
	// PDF assembly
	// -----------------------------------------------------------------------

	private function get_bytes(): string {
		// Force finalize last page.
		if ( count( $this->pages ) < $this->cur_page ) {
			$this->finalize_page();
		}

		$objects = [];
		$offsets = [];
		$out     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"; // Header + binary comment.

		$n = 1;

		// 1 - Catalog.
		$objects[ $n ] = "<< /Type /Catalog /Pages 2 0 R >>\n";
		$n++;

		// 2 - Pages (placeholder, filled later).
		$page_count   = count( $this->pages );
		$page_ids     = [];
		$first_content = $n + 1; // After pages object.
		// We'll compute page object IDs below.

		// Plan: obj 2 = Pages, then for each page: obj (3+2i) = Page, obj (4+2i) = Stream.
		$pages_obj_id = 2;
		$page_tree_ids = [];

		for ( $i = 0; $i < $page_count; $i++ ) {
			$page_tree_ids[] = ( $n + 1 + $i * 2 ); // page dict object IDs
		}

		$objects[ $pages_obj_id ] = "<< /Type /Pages /Kids ["
			. implode( ' ', array_map( fn( $id ) => "{$id} 0 R", $page_tree_ids ) )
			. "] /Count {$page_count} >>\n";

		$n = 3; // Start page objects at 3.

		// Font objects.
		// Encoding dictionary with ISO-8859-2 differences.
		$enc_obj_id = $n + $page_count * 2;
		$font_n_id  = $enc_obj_id + 1;
		$font_b_id  = $enc_obj_id + 2;

		// Page objects + content streams.
		$resource_str = "<< /Font << /F1 {$font_n_id} 0 R /F2 {$font_b_id} 0 R >> >>";

		for ( $i = 0; $i < $page_count; $i++ ) {
			$page_id    = $n + $i * 2;
			$content_id = $page_id + 1;

			$objects[ $page_id ] = "<< /Type /Page /Parent {$pages_obj_id} 0 R "
				. "/MediaBox [0 0 " . self::PAGE_W . " " . self::PAGE_H . "] "
				. "/Contents {$content_id} 0 R "
				. "/Resources {$resource_str} >>\n";

			$stream   = $this->pages[ $i ];
			$len      = strlen( $stream );
			$objects[ $content_id ] = "<< /Length {$len} >>\nstream\n{$stream}\nendstream\n";
		}

		// Encoding object (ISO-8859-2 differences).
		$differences = $this->build_encoding_differences();
		$objects[ $enc_obj_id ] = "<< /Type /Encoding /BaseEncoding /WinAnsiEncoding\n"
			. "/Differences [{$differences}] >>\n";

		// Font normal.
		$objects[ $font_n_id ] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica "
			. "/Encoding {$enc_obj_id} 0 R >>\n";

		// Font bold.
		$objects[ $font_b_id ] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold "
			. "/Encoding {$enc_obj_id} 0 R >>\n";

		// Write all objects in order.
		ksort( $objects );
		foreach ( $objects as $obj_id => $body ) {
			$offsets[ $obj_id ] = strlen( $out );
			$out .= "{$obj_id} 0 obj\n{$body}endobj\n";
		}

		// Cross-reference table.
		$xref_offset = strlen( $out );
		$obj_count   = max( array_keys( $objects ) ) + 1;
		$out .= "xref\n0 {$obj_count}\n";
		$out .= sprintf( "%010d %05d f \n", 0, 65535 );
		for ( $i = 1; $i < $obj_count; $i++ ) {
			if ( isset( $offsets[ $i ] ) ) {
				$out .= sprintf( "%010d %05d n \n", $offsets[ $i ], 0 );
			} else {
				$out .= sprintf( "%010d %05d f \n", 0, 65535 );
			}
		}

		// Trailer.
		$out .= "trailer\n<< /Size {$obj_count} /Root 1 0 R >>\n";
		$out .= "startxref\n{$xref_offset}\n%%EOF\n";

		return $out;
	}

	/**
	 * Returns the /Differences array string for ISO-8859-2 characters
	 * that differ from WinAnsiEncoding. We only map the Polish characters
	 * (and a few nearby Central-European ones) that we actually need.
	 */
	private function build_encoding_differences(): string {
		// Maps byte position (ISO-8859-2 value) → PDF glyph name.
		$diff = [
			0xA1 => 'Aogonek',    // Ą
			0xA3 => 'Lstroke',    // Ł
			0xA6 => 'Sacute',     // Ś
			0xAC => 'Zacute',     // Ź
			0xAF => 'Zdotaccent', // Ż
			0xB1 => 'aogonek',    // ą
			0xB3 => 'lstroke',    // ł
			0xB6 => 'sacute',     // ś
			0xBC => 'zacute',     // ź
			0xBF => 'zdotaccent', // ż
			0xC6 => 'Cacute',     // Ć
			0xCA => 'Eogonek',    // Ę
			0xD1 => 'Nacute',     // Ń
			0xD3 => 'Oacute',     // Ó
			0xE6 => 'cacute',     // ć
			0xEA => 'eogonek',    // ę
			0xF1 => 'nacute',     // ń
			0xF3 => 'oacute',     // ó
		];

		ksort( $diff );

		$parts = [];
		$prev  = -2;

		foreach ( $diff as $byte => $glyph ) {
			if ( $byte !== $prev + 1 ) {
				$parts[] = (string) $byte;
			}
			$parts[] = '/' . $glyph;
			$prev    = $byte;
		}

		return implode( ' ', $parts );
	}
}
