<?php
defined( 'ABSPATH' ) || exit;

/**
 * SVG barcode renderer — Code 128 B and EAN-13.
 * No external library required; pure PHP SVG output.
 */
class WSBG_Barcode {

	// ── Code 128 B ─────────────────────────────────────────────────────────────

	// Each character maps to a pattern of 11 bits (bar / space widths 1-4).
	// Source: ISO/IEC 15417 Code 128 specification.
	private static array $CODE128_TABLE = [
		' ' => '11011001100', '!' => '11001101100', '"' => '11001100110',
		'#' => '10010011000', '$' => '10010001100', '%' => '10001001100',
		'&' => '10011001000', "'" => '10011000100', '(' => '10001100100',
		')' => '11001001000', '*' => '11001000100', '+' => '11000100100',
		',' => '10110011100', '-' => '10011011100', '.' => '10011001110',
		'/' => '10111001100', '0' => '10011101100', '1' => '10011100110',
		'2' => '11001110010', '3' => '11001011100', '4' => '11001001110',
		'5' => '11011100100', '6' => '11001110100', '7' => '11101101110',
		'8' => '11101001100', '9' => '11100101100', ':' => '11100100110',
		';' => '11101100100', '<' => '11100110100', '=' => '11100110010',
		'>' => '11011011000', '?' => '11011000110', '@' => '11000110110',
		'A' => '10100011000', 'B' => '10001011000', 'C' => '10001000110',
		'D' => '10110001000', 'E' => '10001101000', 'F' => '10001100010',
		'G' => '11010001000', 'H' => '11000101000', 'I' => '11000100010',
		'J' => '10110111000', 'K' => '10110001110', 'L' => '10001101110',
		'M' => '10111011000', 'N' => '10111000110', 'O' => '10001110110',
		'P' => '11101110110', 'Q' => '11010001110', 'R' => '11000101110',
		'S' => '11011101000', 'T' => '11011100010', 'U' => '11011101110',
		'V' => '11101011000', 'W' => '11101000110', 'X' => '11100010110',
		'Y' => '11101101000', 'Z' => '11101100010', '[' => '11100011010',
		'\\' => '11101111010', ']' => '11001000010', '^' => '11110001010',
		'_' => '10100110000', '`' => '10100001100', 'a' => '10010110000',
		'b' => '10010000110', 'c' => '10000101100', 'd' => '10000100110',
		'e' => '10110010000', 'f' => '10110000100', 'g' => '10011010000',
		'h' => '10011000010', 'i' => '10000110100', 'j' => '10000110010',
		'k' => '11000010010', 'l' => '11001010000', 'm' => '11110111010',
		'n' => '11000010100', 'o' => '10001111010', 'p' => '10100111100',
		'q' => '10010111100', 'r' => '10010011110', 's' => '10111100100',
		't' => '10011110100', 'u' => '10011110010', 'v' => '11110100100',
		'w' => '11110010100', 'x' => '11110010010', 'y' => '11011011110',
		'z' => '11011110110', '{' => '11110110110', '|' => '10101111000',
		'}' => '10100011110', '~' => '10001111100',
	];

	// Code 128 special symbols (values 0–2 map to START B, STOP, etc.)
	private const C128_START_B = '11010010000';
	private const C128_STOP    = '11000111010';
	private const C128_TERM    = '11';

	// Code 128 value table (for checksum); value 0 = space, 1 = '!', …
	private static function c128_value( string $char ): int {
		return ord( $char ) - 32;
	}

	public static function code128_svg( string $data, int $height = 60, int $module = 2 ): string {
		// Build bit-string
		$bits = self::C128_START_B;
		$checksum = 104; // START B value

		$chars = str_split( $data );
		foreach ( $chars as $i => $ch ) {
			$val   = self::c128_value( $ch );
			$checksum += ( $i + 1 ) * $val;
			$bits  .= ( self::$CODE128_TABLE[ $ch ] ?? '10110011100' ); // fallback = '-'
		}

		// Checksum character (value → pattern)
		$cs_val  = $checksum % 103;
		$cs_char = chr( $cs_val + 32 );
		$bits   .= ( self::$CODE128_TABLE[ $cs_char ] ?? '10110011100' );

		$bits .= self::C128_STOP . self::C128_TERM;

		return self::bits_to_svg( $bits, $height, $module );
	}

	private static function bits_to_svg( string $bits, int $height, int $module ): string {
		$width  = strlen( $bits ) * $module;
		$rects  = '';
		$x      = 0;

		$i = 0;
		while ( $i < strlen( $bits ) ) {
			$bit   = $bits[ $i ];
			$run   = 0;
			while ( $i < strlen( $bits ) && $bits[ $i ] === $bit ) {
				$run++;
				$i++;
			}
			if ( $bit === '1' ) {
				$rects .= sprintf(
					'<rect x="%d" y="0" width="%d" height="%d"/>',
					$x * $module,
					$run * $module,
					$height
				);
			}
			$x += $run;
		}

		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" fill="#000">%s</svg>',
			$width, $height, $width, $height, $rects
		);
	}

	// ── EAN-13 ────────────────────────────────────────────────────────────────

	private static array $EAN13_L = [
		'0' => '0001101', '1' => '0011001', '2' => '0010011',
		'3' => '0111101', '4' => '0100011', '5' => '0110001',
		'6' => '0101111', '7' => '0111011', '8' => '0110111',
		'9' => '0001011',
	];

	private static array $EAN13_G = [
		'0' => '0100111', '1' => '0110011', '2' => '0011011',
		'3' => '0100001', '4' => '0011101', '5' => '0111001',
		'6' => '0000101', '7' => '0010001', '8' => '0001001',
		'9' => '0010111',
	];

	private static array $EAN13_R = [
		'0' => '1110010', '1' => '1100110', '2' => '1101100',
		'3' => '1000010', '4' => '1011100', '5' => '1001110',
		'6' => '1010000', '7' => '1000100', '8' => '1001000',
		'9' => '1110100',
	];

	// First-digit parity structure (L=0, G=1)
	private static array $EAN13_PARITY = [
		'0' => [0,0,0,0,0,0], '1' => [0,0,1,0,1,1], '2' => [0,0,1,1,0,1],
		'3' => [0,0,1,1,1,0], '4' => [0,1,0,0,1,1], '5' => [0,1,1,0,0,1],
		'6' => [0,1,1,1,0,0], '7' => [0,1,0,1,0,1], '8' => [0,1,0,1,1,0],
		'9' => [0,1,1,0,1,0],
	];

	public static function ean13_svg( string $raw, int $height = 60, int $module = 2 ): string {
		$data = self::normalize_ean13( $raw );
		if ( $data === '' ) {
			return '';
		}

		$first  = $data[0];
		$left   = str_split( substr( $data, 1, 6 ) );
		$right  = str_split( substr( $data, 7, 6 ) );
		$parity = self::$EAN13_PARITY[ $first ] ?? array_fill( 0, 6, 0 );

		$bits  = '101'; // start guard
		foreach ( $left as $k => $d ) {
			$bits .= $parity[ $k ] ? self::$EAN13_G[ $d ] : self::$EAN13_L[ $d ];
		}
		$bits .= '01010'; // centre guard
		foreach ( $right as $d ) {
			$bits .= self::$EAN13_R[ $d ];
		}
		$bits .= '101'; // end guard

		return self::bits_to_svg( $bits, $height, $module );
	}

	public static function normalize_ean13( string $raw ): string {
		$digits = preg_replace( '/\D/', '', $raw );
		if ( strlen( $digits ) < 12 ) {
			return '';
		}
		$digits = substr( $digits, 0, 12 );
		// Calculate check digit
		$sum = 0;
		for ( $i = 0; $i < 12; $i++ ) {
			$sum += (int) $digits[ $i ] * ( $i % 2 === 0 ? 1 : 3 );
		}
		$check  = ( 10 - ( $sum % 10 ) ) % 10;
		return $digits . $check;
	}

	// ── Unified entry ─────────────────────────────────────────────────────────

	public static function svg_for_product( \WC_Product $product ): string {
		$format = WSBG_Settings::get( 'barcode_format' );
		$value  = $product->get_sku();

		if ( $value === '' ) {
			$value = (string) $product->get_id();
		}

		if ( $format === 'ean13' ) {
			$svg = self::ean13_svg( $value );
			if ( $svg !== '' ) {
				return $svg;
			}
			// Fallback to Code 128 when value is not a valid EAN
		}

		return self::code128_svg( $value );
	}
}
