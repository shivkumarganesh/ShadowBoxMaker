<?php
/**
 * Unit tests for WSBG_Barcode (no WP / WC runtime needed).
 *
 * Run with:  php tests/test-barcode.php
 */

require_once __DIR__ . '/../includes/class-wsbg-barcode.php';

// Minimal stubs so the class file doesn't crash outside WP
if (!defined('ABSPATH')) define('ABSPATH', '/');
if (!function_exists('WSBG_Settings')) {
    // Settings::get() not called in barcode class directly, no stub needed
}

class BarcodeTest {
    private int $pass = 0;
    private int $fail = 0;

    private function assert_true(bool $cond, string $label): void {
        if ($cond) {
            echo "  [PASS] $label\n";
            $this->pass++;
        } else {
            echo "  [FAIL] $label\n";
            $this->fail++;
        }
    }

    // ── EAN-13 normalisation ──────────────────────────────────────────────────

    public function test_ean13_checkdigit(): void {
        echo "\n== EAN-13 normalisation ==\n";
        // 590123412345 → check digit 7
        $result = WSBG_Barcode::normalize_ean13('590123412345');
        $this->assert_true($result === '5901234123457', "Correct check digit appended: got '$result'");

        // Too short → empty
        $result = WSBG_Barcode::normalize_ean13('123');
        $this->assert_true($result === '', "Short input returns empty: got '$result'");

        // Non-numeric stripped
        $result = WSBG_Barcode::normalize_ean13('590-123-412345');
        $this->assert_true($result === '5901234123457', "Hyphens stripped correctly: got '$result'");

        // Pre-supplied 13 digits
        $result = WSBG_Barcode::normalize_ean13('5901234123457');
        $this->assert_true($result === '5901234123457', "13-digit passthrough correct: got '$result'");
    }

    // ── EAN-13 SVG output ─────────────────────────────────────────────────────

    public function test_ean13_svg(): void {
        echo "\n== EAN-13 SVG ==\n";
        $svg = WSBG_Barcode::ean13_svg('590123412345');
        $this->assert_true(strpos($svg, '<svg') !== false, 'SVG tag present');
        $this->assert_true(strpos($svg, '<rect') !== false, 'Rect elements present');
        $this->assert_true(strpos($svg, 'xmlns') !== false, 'xmlns attribute present');

        // Invalid input returns empty
        $empty = WSBG_Barcode::ean13_svg('abc');
        $this->assert_true($empty === '', "Invalid EAN returns empty: got '$empty'");
    }

    // ── Code 128 SVG output ───────────────────────────────────────────────────

    public function test_code128_svg(): void {
        echo "\n== Code 128 SVG ==\n";
        $svg = WSBG_Barcode::code128_svg('SKU-00001');
        $this->assert_true(strpos($svg, '<svg') !== false, 'SVG tag present');
        $this->assert_true(strpos($svg, '<rect') !== false, 'Rect elements present');

        // Width scales with module size
        $svg1 = WSBG_Barcode::code128_svg('A', 60, 1);
        $svg2 = WSBG_Barcode::code128_svg('A', 60, 2);
        preg_match('/width="(\d+)"/', $svg1, $m1);
        preg_match('/width="(\d+)"/', $svg2, $m2);
        $this->assert_true(
            isset($m1[1], $m2[1]) && (int)$m2[1] === (int)$m1[1] * 2,
            "Module size doubles width ({$m1[1]} → {$m2[1]})"
        );
    }

    // ── SKU generator (standalone) ────────────────────────────────────────────

    public function test_sku_format(): void {
        echo "\n== SKU format simulation ==\n";
        $prefix  = 'SKU-';
        $padding = 5;
        $id      = 42;
        $sku     = $prefix . str_pad((string)$id, $padding, '0', STR_PAD_LEFT);
        $this->assert_true($sku === 'SKU-00042', "ID-based SKU correct: got '$sku'");

        $sku2 = 'SKU-' . str_pad('1', 5, '0', STR_PAD_LEFT);
        $this->assert_true($sku2 === 'SKU-00001', "Sequential SKU correct: got '$sku2'");

        // Variation suffix
        $variation_sku = $sku . '-V' . 99;
        $this->assert_true($variation_sku === 'SKU-00042-V99', "Variation SKU correct: got '$variation_sku'");
    }

    public function run(): void {
        echo "Running WSBG tests...\n";
        $this->test_ean13_checkdigit();
        $this->test_ean13_svg();
        $this->test_code128_svg();
        $this->test_sku_format();

        echo "\n=========================================\n";
        echo "Results: {$this->pass} passed, {$this->fail} failed.\n";
        if ($this->fail > 0) {
            exit(1);
        }
    }
}

(new BarcodeTest())->run();
