<?php
/**
 * config.php - Database Configuration
 * MyKasir POS System
 * 
 * Handles MySQL database connection with proper error handling.
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'mykasir');

// Enable mysqli error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Create connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Set charset to utf8mb4 for proper Unicode support
    $conn->set_charset("utf8mb4");
    
} catch (mysqli_sql_exception $e) {
    // Log error for debugging (in production, log to file instead)
    error_log("Database Connection Error: " . $e->getMessage());
    
    // Show user-friendly error and stop execution
    die("
        <div style='font-family: Arial, sans-serif; padding: 20px; text-align: center;'>
            <h2 style='color: #c62828;'>Koneksi Database Gagal</h2>
            <p>Tidak dapat terhubung ke database. Pastikan:</p>
            <ul style='list-style: none; padding: 0;'>
                <li>✖ XAMPP MySQL service sudah berjalan</li>
                <li>✖ Database 'mykasir' sudah dibuat</li>
                <li>✖ Kredensial database sudah benar</li>
            </ul>
            <p><small>Error Code: " . $e->getCode() . "</small></p>
        </div>
    ");
}
?>