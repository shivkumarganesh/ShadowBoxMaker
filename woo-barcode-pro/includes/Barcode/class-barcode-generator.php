<?php
/**
 * Pure-PHP barcode SVG generator. No external dependencies.
 * Supports Code 128 B, EAN-13, UPC-A, ITF-14.
 *
 * @package WCBarcodePro\Barcode
 */

namespace WCBarcodePro\Barcode;

defined( 'ABSPATH' ) || exit;

class BarcodeGenerator {

	private static ?BarcodeGenerator $instance = null;

	// Code 128 patterns: index = symbol value, value = 6-element bar widths (bar-space-bar-space-bar-space, sum=11).
	private const CODE128_PATTERNS = [
		'212222','222122','222221','121223','121322','131222','122213',
		'122312','132212','221213','221312','231212','112232','122132',
		'122231','113222','123122','123221','223211','221132','221231',
		'213212','223112','312131','311222','321122','321221','312212',
		'322112','322211','212123','212321','232121','111323','131123',
		'131321','112313','132113','132311','211313','231113','231311',
		'112133','112331','132131','113123','113321','133121','313121',
		'211331','231131','213113','213311','213131','311123','311321',
		'331121','312113','312311','332111','314111','221411','431111',
		'111224','111422','121124','121421','141122','141221','112214',
		'112412','122114','122411','142112','142211','241211','221114',
		'413111','241112','134111','111242','121142','121241','114212',
		'124112','124211','411212','421112','421211','212141','214121',
		'412121','111143','111341','131141','114113','114311','411113',
		'411311','113141','114131','311141','411131',
		'211412','211214','211232', // 103=START_A, 104=START_B, 105=START_C
	];
	private const CODE128_STOP    = '2331112';
	private const CODE128_START_B = 104;

	// EAN-13 L-codes (odd parity, left side).
	private const EAN_L = [
		'0001101','0011001','0010011','0111101','0100011',
		'0110001','0101111','0111011','0110111','0001011',
	];
	// EAN-13 G-codes = NOT(reverse(L)).
	private const EAN_G = [
		'0100111','0110011','0011011','0100001','0011101',
		'0111001','0000101','0010001','0001001','0010111',
	];
	// EAN-13 R-codes = NOT(L).
	private const EAN_R = [
		'1110010','1100110','1101100','1000010','1011100',
		'1001110','1010000','1000100','1001000','1110100',
	];
	// First-digit parity pattern: 0=use L, 1=use G.
	private const EAN_PARITY = [
		'000000','001011','001101','001110','010011',
		'011001','011100','010101','010110','011010',
	];

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Generate an SVG string for a barcode.
	 *
	 * @param string $value     The value to encode.
	 * @param string $type      Symbology: code128, ean13, upca, itf14.
	 * @param array  $opts      Options: height, module_width, show_text, color, font_size.
	 * @return string           SVG markup.
	 */
	public function generate_svg( string $value, string $type = 'code128', array $opts = array() ): string {
		$opts = wp_parse_args( $opts, array(
			'height'       => 60,
			'module_width' => 2,
			'show_text'    => true,
			'color'        => '#000000',
			'font_size'    => 10,
		) );

		// Silently fall back to Code 128 for EAN-13 when value isn't numeric.
		if ( 'ean13' === $type && ! ctype_digit( preg_replace( '/\D/', '', $value ) ) ) {
			$type = 'code128';
		}

		return match ( strtolower( $type ) ) {
			'ean13'  => $this->svg_ean13( $value, $opts ),
			'upca'   => $this->svg_upca( $value, $opts ),
			'itf14'  => $this->svg_itf14( $value, $opts ),
			default  => $this->svg_code128( $value, $opts ),
		};
	}

	/**
	 * Return a base64 SVG data URI (usable in img src or email).
	 */
	public function generate_data_uri( string $value, string $type = 'code128', array $opts = array() ): string {
		$svg = $this->generate_svg( $value, $type, $opts );
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	/**
	 * Compute the EAN-13 check digit from 12 numeric digits.
	 */
	public static function ean13_checksum( string $twelve_digits ): int {
		$sum = 0;
		for ( $i = 0; $i < 12; $i++ ) {
			$sum += (int) $twelve_digits[ $i ] * ( ( $i % 2 === 0 ) ? 1 : 3 );
		}
		return ( 10 - ( $sum % 10 ) ) % 10;
	}

	// ── Private generators ────────────────────────────────────────────────────

	private function svg_code128( string $text, array $opts ): string {
		return $this->render_linear_svg( $this->code128_pattern( $text ), $text, $opts, 10 );
	}

	/**
	 * Code 128 bar pattern. Uses set B, switching to set C (two digits per symbol)
	 * for digit runs where that makes the barcode shorter.
	 */
	private function code128_pattern( string $text ): string {
		$s = '';
		foreach ( str_split( $text ) as $ch ) {
			if ( ord( $ch ) >= 32 && ord( $ch ) <= 127 ) {
				$s .= $ch; // skip non-Code-128-B characters.
			}
		}
		$n         = strlen( $s );
		$digit_run = static function ( int $i ) use ( $s, $n ): int {
			$j = $i;
			while ( $j < $n && ctype_digit( $s[ $j ] ) ) {
				$j++;
			}
			return $j - $i;
		};

		$lead = $digit_run( 0 );
		$set  = ( $lead >= 4 && 0 === $lead % 2 ) ? 'C' : 'B';
		$vals = array( 'C' === $set ? 105 : self::CODE128_START_B );

		$i = 0;
		while ( $i < $n ) {
			if ( 'C' === $set ) {
				if ( $i + 1 < $n && ctype_digit( $s[ $i ] ) && ctype_digit( $s[ $i + 1 ] ) ) {
					$vals[] = (int) substr( $s, $i, 2 );
					$i     += 2;
					continue;
				}
				$vals[] = 100; // CODE B.
				$set    = 'B';
				continue;
			}
			$run = $digit_run( $i );
			if ( $run >= 6 || ( $run >= 4 && $i + $run === $n ) ) {
				if ( $run % 2 ) {
					$vals[] = ord( $s[ $i ] ) - 32;
					$i++;
				}
				$vals[] = 99; // CODE C.
				$set    = 'C';
				continue;
			}
			$vals[] = ord( $s[ $i ] ) - 32;
			$i++;
		}

		// Checksum: start value + sum of position × value.
		$check = $vals[0];
		foreach ( $vals as $pos => $v ) {
			$check += $pos * $v;
		}
		$vals[] = $check % 103;

		$pattern = '';
		foreach ( $vals as $v ) {
			$pattern .= self::CODE128_PATTERNS[ $v ];
		}
		return $pattern . self::CODE128_STOP;
	}

	/**
	 * Width of the barcode in modules, including quiet zones (matches the generated SVG viewBox / module_width).
	 */
	public function module_count( string $value, string $type ): int {
		$type = strtolower( $type );
		if ( 'ean13' === $type && ! ctype_digit( preg_replace( '/\D/', '', $value ) ) ) {
			$type = 'code128';
		}
		if ( in_array( $type, array( 'ean13', 'upca' ), true ) ) {
			return 11 + 95 + 7;
		}
		$pattern = 'itf14' === $type ? $this->itf14_pattern( $value ) : $this->code128_pattern( $value );
		return array_sum( array_map( 'intval', str_split( $pattern ) ) ) + 20;
	}

	private function svg_ean13( string $value, array $opts ): string {
		// Normalise to 12 digits (pad left).
		$digits_str = preg_replace( '/\D/', '', $value );
		$digits_str = substr( str_pad( $digits_str, 12, '0', STR_PAD_LEFT ), 0, 12 );
		$check      = self::ean13_checksum( $digits_str );
		$all        = $digits_str . $check;

		$first  = (int) $all[0];
		$parity = self::EAN_PARITY[ $first ];

		// Build 95-bit string.
		$bits  = '101'; // Left guard.
		for ( $i = 1; $i <= 6; $i++ ) {
			$d     = (int) $all[ $i ];
			$bits .= $parity[ $i - 1 ] === '1' ? self::EAN_G[ $d ] : self::EAN_L[ $d ];
		}
		$bits .= '01010'; // Centre guard.
		for ( $i = 7; $i <= 12; $i++ ) {
			$d     = (int) $all[ $i ];
			$bits .= self::EAN_R[ $d ];
		}
		$bits .= '101'; // Right guard.

		$h   = (int) $opts['height'];
		$mw  = (int) $opts['module_width'];
		$col = $opts['color'];
		$fs  = (int) $opts['font_size'];
		$txt = (bool) $opts['show_text'];

		$guard_extra  = $txt ? 5 : 0; // Guard bars drop below digit area.
		$txt_h        = $txt ? $fs + 6 : 0;
		$pad_l        = 11 * $mw;  // Left quiet + first digit space.
		$pad_r        = 7 * $mw;
		$bar_w        = 95 * $mw;
		$total_w      = $pad_l + $bar_w + $pad_r;
		$total_h      = $h + $guard_extra + $txt_h;

		$svg  = $this->svg_open( $total_w, $total_h, $mw, $opts, $txt );

		// Guard bar positions (extend guard_extra below).
		$guard_positions = array_merge(
			range( 0, 2 ),              // Left guard bits 0-2.
			range( 45, 49 ),            // Centre guard bits 45-49.
			range( 92, 94 )             // Right guard bits 92-94.
		);

		$x = $pad_l;
		foreach ( str_split( $bits ) as $pos => $bit ) {
			if ( '1' === $bit ) {
				$extra = in_array( $pos, $guard_positions, true ) ? $guard_extra : 0;
				$svg  .= sprintf( '<rect x="%d" y="0" width="%d" height="%d" fill="%s"/>',
					$x, $mw, $h + $extra, esc_attr( $col ) );
			}
			$x += $mw;
		}

		if ( $txt ) {
			$ty = $h + $guard_extra + $fs;
			// First digit (left of left guard).
			$svg .= sprintf( '<text x="%d" y="%d" font-family="monospace" font-size="%d" text-anchor="middle" fill="%s">%s</text>',
				(int) ( $pad_l / 2 ), $ty, $fs, esc_attr( $col ), esc_html( $all[0] ) );
			// Digits 1-6.
			$svg .= sprintf( '<text x="%d" y="%d" font-family="monospace" font-size="%d" text-anchor="middle" fill="%s">%s</text>',
				$pad_l + (int) ( ( 3 + 21 ) * $mw ), $ty, $fs, esc_attr( $col ), esc_html( substr( $all, 1, 6 ) ) );
			// Digits 7-12.
			$svg .= sprintf( '<text x="%d" y="%d" font-family="monospace" font-size="%d" text-anchor="middle" fill="%s">%s</text>',
				$pad_l + (int) ( ( 3 + 42 + 5 + 21 ) * $mw ), $ty, $fs, esc_attr( $col ), esc_html( substr( $all, 7, 6 ) ) );
		}

		$svg .= '</svg>';
		return $svg;
	}

	private function svg_upca( string $value, array $opts ): string {
		// UPC-A is EAN-13 with a leading 0.
		$digits = preg_replace( '/\D/', '', $value );
		$digits = substr( str_pad( $digits, 11, '0', STR_PAD_LEFT ), 0, 11 );
		return $this->svg_ean13( '0' . $digits, $opts );
	}

	private function svg_itf14( string $value, array $opts ): string {
		$digits = substr( str_pad( preg_replace( '/\D/', '', $value ), 14, '0', STR_PAD_LEFT ), 0, 14 );
		return $this->render_linear_svg( $this->itf14_pattern( $value ), $digits, $opts, 10 );
	}

	private function itf14_pattern( string $value ): string {
		// ITF-14: Interleaved 2 of 5 — 14 digits.
		$digits = preg_replace( '/\D/', '', $value );
		$digits = substr( str_pad( $digits, 14, '0', STR_PAD_LEFT ), 0, 14 );

		// ITF narrow/wide bar encoding.
		$narrow_bars = [
			'00110','10001','01001','11000','00101',
			'10100','01100','00011','10010','01010',
		];

		$pattern = '1111'; // Start: narrow bar, space, bar, space.
		for ( $i = 0; $i < 14; $i += 2 ) {
			$d1 = (int) $digits[ $i ];
			$d2 = (int) $digits[ $i + 1 ];
			$b1 = $narrow_bars[ $d1 ];
			$b2 = $narrow_bars[ $d2 ];
			// Interleave: b1 gives bars, b2 gives spaces (0=narrow=1, 1=wide=3).
			for ( $j = 0; $j < 5; $j++ ) {
				$pattern .= $b1[ $j ] === '1' ? '3' : '1'; // bar width.
				$pattern .= $b2[ $j ] === '1' ? '3' : '1'; // space width.
			}
		}
		return $pattern . '311'; // Stop: wide bar, narrow space, narrow bar.
	}

	/**
	 * Generic linear barcode SVG renderer.
	 *
	 * @param string $pattern  String of digits, each digit = width of alternating bar/space.
	 * @param string $label    Human-readable text.
	 * @param array  $opts     Render options.
	 * @param int    $quiet    Quiet zone in modules.
	 */
	private function render_linear_svg( string $pattern, string $label, array $opts, int $quiet = 10 ): string {
		$h   = (int) $opts['height'];
		$mw  = (int) $opts['module_width'];
		$col = $opts['color'];
		$fs  = (int) $opts['font_size'];
		$txt = (bool) $opts['show_text'];

		// Compute total module count.
		$total_modules = 0;
		foreach ( str_split( $pattern ) as $w ) {
			$total_modules += (int) $w;
		}

		$pad      = $quiet * $mw;
		$bar_w    = $total_modules * $mw;
		$total_w  = $bar_w + 2 * $pad;
		$txt_h    = $txt ? $fs + 4 : 0;
		$total_h  = $h + $txt_h;

		$svg = $this->svg_open( $total_w, $total_h, $mw, $opts, $txt );

		$x      = $pad;
		$is_bar = true;
		foreach ( str_split( $pattern ) as $w_str ) {
			$w = (int) $w_str * $mw;
			if ( $is_bar ) {
				$svg .= sprintf( '<rect x="%d" y="0" width="%d" height="%d" fill="%s"/>',
					$x, $w, $h, esc_attr( $col ) );
			}
			$x     += $w;
			$is_bar = ! $is_bar;
		}

		if ( $txt ) {
			$svg .= sprintf(
				'<text x="%d" y="%d" font-family="monospace" font-size="%d" text-anchor="middle" fill="%s">%s</text>',
				(int) ( $total_w / 2 ), $h + $fs + 2, $fs, esc_attr( $col ), esc_html( $label )
			);
		}

		$svg .= '</svg>';
		return $svg;
	}

	/**
	 * Opening <svg> tag. With print_width_in set, the barcode gets a physical size whose
	 * narrowest bar lands on a whole number of printer dots instead of being squeezed to fit.
	 */
	private function svg_open( int $w, int $h, int $mw, array $opts, bool $has_text ): string {
		$avail = (float) ( $opts['print_width_in'] ?? 0 );
		if ( $avail <= 0 ) {
			return sprintf(
				'<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" shape-rendering="crispEdges">',
				$w, $h, $w, $h
			);
		}

		$module_in = self::print_module_in( $w / max( 1, $mw ), $avail );
		$width_in  = ( $w / max( 1, $mw ) ) * $module_in;

		// Bars-only barcodes stretch vertically to fill the box; 1D scanners only read bar widths.
		if ( $has_text ) {
			$size = sprintf( 'width="%.4fin" height="%.4fin"', $width_in, $width_in * $h / $w );
		} else {
			$size = sprintf( 'width="%.4fin" height="100%%" preserveAspectRatio="none"', $width_in );
		}

		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" %s viewBox="0 0 %d %d" shape-rendering="crispEdges" style="display:block;flex-shrink:0">',
			$size, $w, $h
		);
	}

	/**
	 * Narrowest bar width (inches) for a barcode of $modules modules in $avail inches.
	 * 0.02in / 0.01in are whole dots at 203, 300 and 600 dpi; 1/150in is whole dots at 300 and 600 dpi.
	 */
	public static function print_module_in( float $modules, float $avail ): float {
		foreach ( array( 0.02, 0.01, 1 / 150 ) as $m ) {
			if ( $modules * $m <= $avail ) {
				return $m;
			}
		}
		return $avail / max( 1, $modules );
	}
}
