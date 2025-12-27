<?php
/**
 * functions.php - Helper Functions
 * MyKasir POS System
 * 
 * Contains utility functions for formatting and calculations.
 */

/**
 * Format number to Indonesian Rupiah currency format
 * 
 * @param float|int $angka The number to format
 * @param bool $withSymbol Whether to include 'Rp' symbol
 * @return string Formatted currency string
 */
function formatRupiah($angka, $withSymbol = true) {
    // Ensure we're working with a numeric value
    $angka = floatval($angka);
    
    // Format with thousand separators (Indonesian style: dot separator)
    $formatted = number_format($angka, 0, ',', '.');
    
    return $withSymbol ? "Rp " . $formatted : $formatted;
}

/**
 * Calculate total from shopping cart items
 * 
 * @param array $keranjang Array of cart items with 'subtotal' key
 * @return float Total amount
 */
function hitungTotal($keranjang) {
    // Return 0 if cart is empty or not an array
    if (!is_array($keranjang) || empty($keranjang)) {
        return 0;
    }
    
    $total = 0;
    foreach ($keranjang as $item) {
        // Ensure subtotal exists and is numeric
        if (isset($item['subtotal']) && is_numeric($item['subtotal'])) {
            $total += floatval($item['subtotal']);
        }
    }
    
    return $total;
}

/**
 * Sanitize output for HTML display to prevent XSS
 * 
 * @param string $string The string to sanitize
 * @return string Sanitized string
 */
function sanitizeOutput($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Validate quantity input
 * 
 * @param mixed $jumlah The quantity to validate
 * @param int $maxStock Maximum allowed stock
 * @return array ['valid' => bool, 'value' => int, 'message' => string]
 */
function validateQuantity($jumlah, $maxStock) {
    // Check if numeric
    if (!is_numeric($jumlah)) {
        return [
            'valid' => false,
            'value' => 0,
            'message' => 'Jumlah harus berupa angka!'
        ];
    }
    
    $jumlah = intval($jumlah);
    
    // Check for negative or zero
    if ($jumlah <= 0) {
        return [
            'valid' => false,
            'value' => 0,
            'message' => 'Jumlah harus lebih dari 0!'
        ];
    }
    
    // Check against stock
    if ($jumlah > $maxStock) {
        return [
            'valid' => false,
            'value' => 0,
            'message' => 'Jumlah melebihi stok tersedia (Stok: ' . $maxStock . ')!'
        ];
    }
    
    return [
        'valid' => true,
        'value' => $jumlah,
        'message' => ''
    ];
}
?>