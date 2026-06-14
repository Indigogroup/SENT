<?php
defined( 'ABSPATH' ) || exit;

/**
 * Generates a proper .xlsx (Office Open XML) file without external dependencies.
 * Requires PHP's ZipArchive extension (bundled with PHP since 5.2).
 */
class Woo_PSL_Xlsx {

	/**
	 * Generates an .xlsx file and saves it to $file_path.
	 *
	 * @param array  $rows         [ ['sku'=>'', 'name'=>'', 'stock'=>0], ... ]
	 * @param float  $total_stock
	 * @param string $categories   Human-readable category label.
	 * @param string $file_path    Absolute path where the .xlsx should be saved.
	 * @return bool                True on success.
	 */
	public static function generate( array $rows, float $total_stock, string $categories, string $file_path ): bool {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return false;
		}

		$zip = new ZipArchive();
		if ( $zip->open( $file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
			return false;
		}

		// Collect shared strings.
		$strings  = [];
		$sheet_data = self::build_sheet_data( $rows, $total_stock, $categories, $strings );

		$zip->addFromString( '[Content_Types].xml',           self::content_types() );
		$zip->addFromString( '_rels/.rels',                   self::root_rels() );
		$zip->addFromString( 'xl/workbook.xml',               self::workbook() );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels',    self::workbook_rels() );
		$zip->addFromString( 'xl/styles.xml',                 self::styles() );
		$zip->addFromString( 'xl/sharedStrings.xml',          self::shared_strings( $strings ) );
		$zip->addFromString( 'xl/worksheets/sheet1.xml',      $sheet_data );

		$zip->close();

		return file_exists( $file_path );
	}

	// -----------------------------------------------------------------------
	// Sheet data builder
	// -----------------------------------------------------------------------

	private static function build_sheet_data( array $rows, float $total_stock, string $categories, array &$strings ): string {
		$si_map = []; // string → shared-string index

		$get_si = function ( string $val ) use ( &$strings, &$si_map ): int {
			if ( ! isset( $si_map[ $val ] ) ) {
				$si_map[ $val ] = count( $strings );
				$strings[]      = $val;
			}
			return $si_map[ $val ];
		};

		$xml_rows = '';
		$r        = 1; // current row number (explicit, never ambiguous)

		// Title row.
		$title     = sprintf( 'Stany magazynowe — %s — %s', $categories, wp_date( 'Y-m-d H:i' ) );
		$xml_rows .= self::xml_row( $r, [ self::xml_cell_s( 'A', $r, $get_si( $title ), 2 ) ] );
		$r++;

		// Empty spacer row.
		$xml_rows .= self::xml_row( $r, [] );
		$r++;

		// Header row.
		$xml_rows .= self::xml_row( $r, [
			self::xml_cell_s( 'A', $r, $get_si( 'SKU' ),   1 ),
			self::xml_cell_s( 'B', $r, $get_si( 'Nazwa' ), 1 ),
			self::xml_cell_s( 'C', $r, $get_si( 'Stan' ),  1 ),
		] );
		$r++;

		// Data rows.
		foreach ( $rows as $row ) {
			$sku   = (string) ( $row['sku']   ?? '—' );
			$name  = (string) ( $row['name']  ?? '' );
			$stock = (float)  ( $row['stock'] ?? 0 );

			$xml_rows .= self::xml_row( $r, [
				self::xml_cell_s( 'A', $r, $get_si( $sku ),  0 ),
				self::xml_cell_s( 'B', $r, $get_si( $name ), 0 ),
				self::xml_cell_n( 'C', $r, $stock ),
			] );
			$r++;
		}

		// Total row.
		$xml_rows .= self::xml_row( $r, [
			self::xml_cell_s( 'A', $r, $get_si( 'SUMA' ), 1 ),
			self::xml_cell_s( 'B', $r, $get_si( '' ),     1 ),
			self::xml_cell_n( 'C', $r, $total_stock,      1 ),
		] );

		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
			. ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
			. '<cols>'
			. '<col min="1" max="1" width="20" customWidth="1"/>'
			. '<col min="2" max="2" width="50" customWidth="1"/>'
			. '<col min="3" max="3" width="12" customWidth="1"/>'
			. '</cols>'
			. '<sheetData>' . $xml_rows . '</sheetData>'
			. '</worksheet>';
	}

	// -----------------------------------------------------------------------
	// XML helpers
	// -----------------------------------------------------------------------

	private static function xml_row( int $num, array $cells ): string {
		return '<row r="' . $num . '">' . implode( '', $cells ) . '</row>';
	}

	/** Cell with shared-string value. */
	private static function xml_cell_s( string $col, int $row, int $si_index, int $style = 0 ): string {
		$ref = $col . $row;
		return '<c r="' . $ref . '" t="s" s="' . $style . '"><v>' . $si_index . '</v></c>';
	}

	/** Cell with numeric value. */
	private static function xml_cell_n( string $col, int $row, float $value, int $style = 0 ): string {
		$ref = $col . $row;
		// Numeric values: no HTML escaping needed; cast to string for clean output.
		return '<c r="' . $ref . '" s="' . $style . '"><v>' . (string) $value . '</v></c>';
	}

	// -----------------------------------------------------------------------
	// Static XML parts
	// -----------------------------------------------------------------------

	private static function content_types(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
			. '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
			. '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
			. '</Types>';
	}

	private static function root_rels(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '</Relationships>';
	}

	private static function workbook(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
			. ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheets><sheet name="Stany" sheetId="1" r:id="rId1"/></sheets>'
			. '</workbook>';
	}

	private static function workbook_rels(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
			. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
			. '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
			. '</Relationships>';
	}

	private static function styles(): string {
		// Styles: index 0 = normal, 1 = bold, 2 = title bold.
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<fonts count="3">'
			. '<font><sz val="11"/><name val="Calibri"/></font>'
			. '<font><b/><sz val="11"/><name val="Calibri"/></font>'
			. '<font><b/><sz val="13"/><name val="Calibri"/></font>'
			. '</fonts>'
			. '<fills count="2">'
			. '<fill><patternFill patternType="none"/></fill>'
			. '<fill><patternFill patternType="gray125"/></fill>'
			. '</fills>'
			. '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="3">'
			. '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'  // 0 - normal
			. '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/>'  // 1 - bold
			. '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0"/>'  // 2 - title
			. '</cellXfs>'
			. '</styleSheet>';
	}

	private static function shared_strings( array $strings ): string {
		$count = count( $strings );
		$items = '';
		foreach ( $strings as $str ) {
			$items .= '<si><t xml:space="preserve">' . self::xml_escape( $str ) . '</t></si>';
		}
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
			. ' count="' . $count . '" uniqueCount="' . $count . '">'
			. $items
			. '</sst>';
	}

	private static function xml_escape( string $s ): string {
		return htmlspecialchars( $s, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}
}
