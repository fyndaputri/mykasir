<?php
/**
 * dashboard.php - Main POS Dashboard
 * MyKasir POS System
 * 
 * Handles product search, cart management, and transactions.
 * All database queries use prepared statements to prevent SQL injection.
 */

// ============================================
// INITIALIZATION
// ============================================

// Start session first (before any output)
session_start();

// Include configuration and helper functions
require_once 'config.php';
require_once 'functions.php';

// Initialize cart if not exists
if (!isset($_SESSION['keranjang'])) {
    $_SESSION['keranjang'] = [];
}

// Initialize message variable
$pesan = '';
$pesan_type = ''; // 'success' or 'error'

// ============================================
// PROCESS: SEARCH PRODUCTS
// ============================================

if (isset($_POST['cari_produk'])) {
    $nama_produk = trim($_POST['nama_produk']);
    
    if (!empty($nama_produk)) {
        // SECURE: Using prepared statement with LIKE
        $stmt = $conn->prepare("SELECT * FROM produk WHERE Nama LIKE ? AND Stok > 0");
        $searchTerm = "%" . $nama_produk . "%";
        $stmt->bind_param("s", $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } else {
        // Show all products if search is empty
        $result = $conn->query("SELECT * FROM produk WHERE Stok > 0");
    }
} else {
    // Default: show all products with stock > 0
    $result = $conn->query("SELECT * FROM produk WHERE Stok > 0");
}

// ============================================
// PROCESS: ADD TO CART
// ============================================

if (isset($_POST['tambah_keranjang'])) {
    $id_produk = filter_input(INPUT_POST, 'id_produk', FILTER_VALIDATE_INT);
    $jumlah = filter_input(INPUT_POST, 'jumlah', FILTER_VALIDATE_INT);
    
    if ($id_produk && $jumlah !== false) {
        // SECURE: Using prepared statement to fetch product
        $stmt = $conn->prepare("SELECT id, Nama, Harga, Stok FROM produk WHERE id = ?");
        $stmt->bind_param("i", $id_produk);
        $stmt->execute();
        $produk_result = $stmt->get_result();
        $produk = $produk_result->fetch_assoc();
        $stmt->close();
        
        if ($produk) {
            // Validate quantity using helper function
            $validation = validateQuantity($jumlah, $produk['Stok']);
            
            if ($validation['valid']) {
                // Check if product already in cart
                $existingIndex = null;
                foreach ($_SESSION['keranjang'] as $index => $item) {
                    if ($item['id'] == $produk['id']) {
                        $existingIndex = $index;
                        break;
                    }
                }
                
                if ($existingIndex !== null) {
                    // Update existing item quantity
                    $newQty = $_SESSION['keranjang'][$existingIndex]['jumlah'] + $validation['value'];
                    
                    // Re-validate combined quantity against stock
                    $combinedValidation = validateQuantity($newQty, $produk['Stok']);
                    
                    if ($combinedValidation['valid']) {
                        $_SESSION['keranjang'][$existingIndex]['jumlah'] = $newQty;
                        $_SESSION['keranjang'][$existingIndex]['subtotal'] = $produk['Harga'] * $newQty;
                        $pesan = "Jumlah produk '" . sanitizeOutput($produk['Nama']) . "' diperbarui!";
                        $pesan_type = 'success';
                    } else {
                        $pesan = $combinedValidation['message'];
                        $pesan_type = 'error';
                    }
                } else {
                    // Add new item to cart
                    $_SESSION['keranjang'][] = [
                        'id' => $produk['id'],
                        'Nama' => $produk['Nama'],
                        'Harga' => floatval($produk['Harga']),
                        'jumlah' => $validation['value'],
                        'subtotal' => floatval($produk['Harga']) * $validation['value']
                    ];
                    $pesan = "Produk '" . sanitizeOutput($produk['Nama']) . "' ditambahkan ke keranjang!";
                    $pesan_type = 'success';
                }
            } else {
                $pesan = $validation['message'];
                $pesan_type = 'error';
            }
        } else {
            $pesan = "Produk tidak ditemukan!";
            $pesan_type = 'error';
        }
    } else {
        $pesan = "Data input tidak valid!";
        $pesan_type = 'error';
    }
}

// ============================================
// PROCESS: REMOVE FROM CART
// ============================================

if (isset($_GET['hapus'])) {
    $index = filter_input(INPUT_GET, 'hapus', FILTER_VALIDATE_INT);
    
    if ($index !== false && $index >= 0 && isset($_SESSION['keranjang'][$index])) {
        $removed_item = $_SESSION['keranjang'][$index]['Nama'];
        unset($_SESSION['keranjang'][$index]);
        // Re-index array
        $_SESSION['keranjang'] = array_values($_SESSION['keranjang']);
        $pesan = "Produk '" . sanitizeOutput($removed_item) . "' dihapus dari keranjang!";
        $pesan_type = 'success';
    }
}

// ============================================
// CALCULATE TOTAL
// ============================================

$total_belanja = hitungTotal($_SESSION['keranjang']);

// ============================================
// PROCESS: COMPLETE TRANSACTION
// ============================================

if (isset($_POST['selesai'])) {
    $uang_diterima = filter_input(INPUT_POST, 'uang_diterima', FILTER_VALIDATE_FLOAT);
    
    if ($uang_diterima === false || $uang_diterima === null) {
        $pesan = "Jumlah uang tidak valid!";
        $pesan_type = 'error';
    } elseif ($uang_diterima <= 0) {
        $pesan = "Jumlah uang harus lebih dari 0!";
        $pesan_type = 'error';
    } elseif (empty($_SESSION['keranjang'])) {
        $pesan = "Keranjang kosong! Tambahkan produk terlebih dahulu.";
        $pesan_type = 'error';
    } else {
        $kembalian = $uang_diterima - $total_belanja;
        
        if ($kembalian >= 0) {
            // Start transaction for data integrity
            $conn->begin_transaction();
            
            try {
                // SECURE: Insert transaction record with prepared statement
                $stmt = $conn->prepare("INSERT INTO transaksi (total_belanja, uang_diterima, kembalian) VALUES (?, ?, ?)");
                $stmt->bind_param("ddd", $total_belanja, $uang_diterima, $kembalian);
                $stmt->execute();
                $stmt->close();
                
                // SECURE: Update stock for each item with prepared statement
                $stmt = $conn->prepare("UPDATE produk SET Stok = Stok - ? WHERE id = ? AND Stok >= ?");
                
                foreach ($_SESSION['keranjang'] as $item) {
                    $stmt->bind_param("iii", $item['jumlah'], $item['id'], $item['jumlah']);
                    $stmt->execute();
                    
                    // Check if update affected any rows (stock was sufficient)
                    if ($stmt->affected_rows === 0) {
                        throw new Exception("Stok tidak mencukupi untuk produk: " . $item['Nama']);
                    }
                }
                $stmt->close();
                
                // Commit transaction
                $conn->commit();
                
                // Reset cart
                $_SESSION['keranjang'] = [];
                $total_belanja = 0;
                
                $pesan = "Transaksi berhasil! Kembalian: " . formatRupiah($kembalian);
                $pesan_type = 'success';
                
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                $pesan = "Transaksi gagal: " . sanitizeOutput($e->getMessage());
                $pesan_type = 'error';
            }
        } else {
            $pesan = "Uang tidak cukup! Kurang: " . formatRupiah(abs($kembalian));
            $pesan_type = 'error';
        }
    }
}

// Recalculate total after any changes
$total_belanja = hitungTotal($_SESSION['keranjang']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="MyKasir - Sistem Point of Sales sederhana">
    <title>MyKasir - Dashboard Kasir</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <h1>🛒 MyKasir</h1>
            <p class="subtitle">Sistem Point of Sales</p>
            <div class="header-actions">
                <a href="manage_products.php" class="btn btn-primary btn-sm">📦 Kelola Produk (Admin)</a>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </header>

        <!-- Message Display -->
        <?php if (!empty($pesan)): ?>
            <div class="alert alert-<?php echo $pesan_type; ?>">
                <?php echo $pesan; ?>
            </div>
        <?php endif; ?>

        <!-- Search Section -->
        <section class="section">
            <h2>🔍 Cari Produk</h2>
            <form method="POST" class="search-form">
                <input type="text" 
                       name="nama_produk" 
                       placeholder="Ketik nama produk..." 
                       value="<?php echo isset($_POST['nama_produk']) ? sanitizeOutput($_POST['nama_produk']) : ''; ?>"
                       autocomplete="off">
                <button type="submit" name="cari_produk" class="btn btn-primary">Cari</button>
                <a href="dashboard.php" class="btn btn-secondary">Reset</a>
            </form>
        </section>

        <!-- Product List -->
        <section class="section">
            <h2>📦 Daftar Produk</h2>
            <?php if ($result && $result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Nama Produk</th>
                                <th>Harga</th>
                                <th>Stok</th>
                                <th>Jumlah</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td data-label="Nama"><?php echo sanitizeOutput($row['Nama']); ?></td>
                                    <td data-label="Harga"><?php echo formatRupiah($row['Harga']); ?></td>
                                    <td data-label="Stok">
                                        <span class="stock-badge <?php echo $row['Stok'] <= 5 ? 'stock-low' : 'stock-ok'; ?>">
                                            <?php echo $row['Stok']; ?>
                                        </span>
                                    </td>
                                    <td data-label="Jumlah">
                                        <form method="POST" class="inline-form" id="form-<?php echo $row['id']; ?>">
                                            <input type="hidden" name="id_produk" value="<?php echo $row['id']; ?>">
                                            <input type="number" 
                                                   name="jumlah" 
                                                   min="1" 
                                                   max="<?php echo $row['Stok']; ?>" 
                                                   value="1"
                                                   class="input-qty"
                                                   required>
                                    </td>
                                    <td data-label="Aksi">
                                            <button type="submit" name="tambah_keranjang" class="btn btn-success btn-sm">
                                                + Keranjang
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>📭 Tidak ada produk tersedia.</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Shopping Cart -->
        <section class="section cart-section">
            <h2>🛒 Keranjang Belanja</h2>
            <?php if (!empty($_SESSION['keranjang'])): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Nama Produk</th>
                                <th>Harga</th>
                                <th>Jumlah</th>
                                <th>Subtotal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($_SESSION['keranjang'] as $index => $item): ?>
                                <tr>
                                    <td data-label="Nama"><?php echo sanitizeOutput($item['Nama']); ?></td>
                                    <td data-label="Harga"><?php echo formatRupiah($item['Harga']); ?></td>
                                    <td data-label="Jumlah"><?php echo $item['jumlah']; ?></td>
                                    <td data-label="Subtotal"><?php echo formatRupiah($item['subtotal']); ?></td>
                                    <td data-label="Aksi">
                                        <a href="?hapus=<?php echo $index; ?>" 
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Hapus produk ini dari keranjang?');">
                                            Hapus
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="3"><strong>Total Belanja</strong></td>
                                <td colspan="2"><strong><?php echo formatRupiah($total_belanja); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Payment Form -->
                <div class="payment-section">
                    <h3>💳 Pembayaran</h3>
                    <form method="POST" class="payment-form">
                        <div class="form-group">
                            <label for="uang_diterima">Uang Diterima:</label>
                            <input type="number" 
                                   name="uang_diterima" 
                                   id="uang_diterima"
                                   min="1" 
                                   step="100"
                                   placeholder="Masukkan jumlah uang..."
                                   required>
                        </div>
                        <div class="form-group">
                            <button type="submit" name="selesai" class="btn btn-primary btn-lg">
                                ✅ Selesaikan Transaksi
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>🛒 Keranjang kosong. Silakan tambahkan produk.</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Footer -->
        <footer class="footer">
            <p>&copy; <?php echo date('Y'); ?> MyKasir - Simple POS System</p>
        </footer>
    </div>

    <script>
        // Client-side validation for quantity
        document.querySelectorAll('input[name="jumlah"]').forEach(function(input) {
            input.addEventListener('change', function() {
                var max = parseInt(this.getAttribute('max'));
                var value = parseInt(this.value);
                
                if (value < 1) {
                    this.value = 1;
                    alert('Jumlah minimal adalah 1!');
                }
                
                if (value > max) {
                    this.value = max;
                    alert('Jumlah melebihi stok! Maksimal: ' + max);
                }
            });
        });

        // Client-side validation for payment
        document.querySelector('input[name="uang_diterima"]')?.addEventListener('change', function() {
            if (parseInt(this.value) <= 0) {
                this.value = '';
                alert('Jumlah uang harus lebih dari 0!');
            }
        });
    </script>
</body>
</html>
