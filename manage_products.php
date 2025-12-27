<?php
/**
 * manage_products.php - Product CRUD Management
 * MyKasir POS System
 * 
 * Full CRUD functionality for managing products.
 * All database queries use prepared statements to prevent SQL injection.
 */

// ============================================
// INITIALIZATION
// ============================================

session_start();
require_once 'config.php';
require_once 'functions.php';

// Initialize message variable
$pesan = '';
$pesan_type = '';

// Initialize edit mode variables
$edit_mode = false;
$edit_product = [
    'id' => '',
    'Nama' => '',
    'Harga' => '',
    'Stok' => ''
];

// ============================================
// PROCESS: CREATE NEW PRODUCT
// ============================================

if (isset($_POST['tambah_produk'])) {
    $nama = trim($_POST['nama'] ?? '');
    $harga = filter_input(INPUT_POST, 'harga', FILTER_VALIDATE_FLOAT);
    $stok = filter_input(INPUT_POST, 'stok', FILTER_VALIDATE_INT);
    
    // Validation
    if (empty($nama)) {
        $pesan = "Nama produk tidak boleh kosong!";
        $pesan_type = 'error';
    } elseif ($harga === false || $harga === null || $harga < 0) {
        $pesan = "Harga tidak valid! Harus berupa angka positif.";
        $pesan_type = 'error';
    } elseif ($stok === false || $stok === null || $stok < 0) {
        $pesan = "Stok tidak valid! Harus berupa angka positif.";
        $pesan_type = 'error';
    } else {
        // Check for duplicate product name
        $stmt = $conn->prepare("SELECT id FROM produk WHERE Nama = ?");
        $stmt->bind_param("s", $nama);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $pesan = "Produk dengan nama '" . sanitizeOutput($nama) . "' sudah ada!";
            $pesan_type = 'error';
        } else {
            // SECURE: Insert with prepared statement
            $stmt = $conn->prepare("INSERT INTO produk (Nama, Harga, Stok) VALUES (?, ?, ?)");
            $stmt->bind_param("sdi", $nama, $harga, $stok);
            
            if ($stmt->execute()) {
                $pesan = "Produk '" . sanitizeOutput($nama) . "' berhasil ditambahkan!";
                $pesan_type = 'success';
            } else {
                $pesan = "Gagal menambahkan produk!";
                $pesan_type = 'error';
            }
        }
        $stmt->close();
    }
}

// ============================================
// PROCESS: LOAD PRODUCT FOR EDITING
// ============================================

if (isset($_GET['edit'])) {
    $edit_id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
    
    if ($edit_id) {
        // SECURE: Fetch with prepared statement
        $stmt = $conn->prepare("SELECT id, Nama, Harga, Stok FROM produk WHERE id = ?");
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $edit_mode = true;
            $edit_product = $row;
        } else {
            $pesan = "Produk tidak ditemukan!";
            $pesan_type = 'error';
        }
        $stmt->close();
    }
}

// ============================================
// PROCESS: UPDATE PRODUCT
// ============================================

if (isset($_POST['update_produk'])) {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $nama = trim($_POST['nama'] ?? '');
    $harga = filter_input(INPUT_POST, 'harga', FILTER_VALIDATE_FLOAT);
    $stok = filter_input(INPUT_POST, 'stok', FILTER_VALIDATE_INT);
    
    // Validation
    if (!$id) {
        $pesan = "ID produk tidak valid!";
        $pesan_type = 'error';
    } elseif (empty($nama)) {
        $pesan = "Nama produk tidak boleh kosong!";
        $pesan_type = 'error';
        $edit_mode = true;
        $edit_product = ['id' => $id, 'Nama' => $nama, 'Harga' => $harga, 'Stok' => $stok];
    } elseif ($harga === false || $harga === null || $harga < 0) {
        $pesan = "Harga tidak valid! Harus berupa angka positif.";
        $pesan_type = 'error';
        $edit_mode = true;
        $edit_product = ['id' => $id, 'Nama' => $nama, 'Harga' => $harga, 'Stok' => $stok];
    } elseif ($stok === false || $stok === null || $stok < 0) {
        $pesan = "Stok tidak valid! Harus berupa angka positif.";
        $pesan_type = 'error';
        $edit_mode = true;
        $edit_product = ['id' => $id, 'Nama' => $nama, 'Harga' => $harga, 'Stok' => $stok];
    } else {
        // Check for duplicate product name (exclude current product)
        $stmt = $conn->prepare("SELECT id FROM produk WHERE Nama = ? AND id != ?");
        $stmt->bind_param("si", $nama, $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $pesan = "Produk dengan nama '" . sanitizeOutput($nama) . "' sudah ada!";
            $pesan_type = 'error';
            $edit_mode = true;
            $edit_product = ['id' => $id, 'Nama' => $nama, 'Harga' => $harga, 'Stok' => $stok];
        } else {
            // SECURE: Update with prepared statement
            $stmt = $conn->prepare("UPDATE produk SET Nama = ?, Harga = ?, Stok = ? WHERE id = ?");
            $stmt->bind_param("sdii", $nama, $harga, $stok, $id);
            
            if ($stmt->execute()) {
                $pesan = "Produk '" . sanitizeOutput($nama) . "' berhasil diperbarui!";
                $pesan_type = 'success';
            } else {
                $pesan = "Gagal memperbarui produk!";
                $pesan_type = 'error';
            }
        }
        $stmt->close();
    }
}

// ============================================
// PROCESS: DELETE PRODUCT
// ============================================

if (isset($_GET['delete'])) {
    $delete_id = filter_input(INPUT_GET, 'delete', FILTER_VALIDATE_INT);
    
    if ($delete_id) {
        // Get product name for confirmation message
        $stmt = $conn->prepare("SELECT Nama FROM produk WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        $stmt->close();
        
        if ($product) {
            // SECURE: Delete with prepared statement
            $stmt = $conn->prepare("DELETE FROM produk WHERE id = ?");
            $stmt->bind_param("i", $delete_id);
            
            if ($stmt->execute()) {
                $pesan = "Produk '" . sanitizeOutput($product['Nama']) . "' berhasil dihapus!";
                $pesan_type = 'success';
            } else {
                $pesan = "Gagal menghapus produk!";
                $pesan_type = 'error';
            }
            $stmt->close();
        } else {
            $pesan = "Produk tidak ditemukan!";
            $pesan_type = 'error';
        }
    }
}

// ============================================
// FETCH ALL PRODUCTS
// ============================================

$products = $conn->query("SELECT * FROM produk ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="MyKasir - Kelola Produk">
    <title>MyKasir - Kelola Produk</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Additional styles for this page */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-md, 16px);
            align-items: end;
        }
        
        .form-grid .form-group {
            margin-bottom: 0;
        }
        
        .form-actions {
            display: flex;
            gap: var(--spacing-sm, 8px);
            flex-wrap: wrap;
        }
        
        .nav-buttons {
            display: flex;
            gap: var(--spacing-sm, 8px);
            margin-bottom: var(--spacing-lg, 24px);
            flex-wrap: wrap;
        }
        
        .product-id {
            font-family: monospace;
            background-color: #f0f0f0;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        
        .action-buttons {
            display: flex;
            gap: var(--spacing-xs, 4px);
            flex-wrap: wrap;
        }
        
        .btn-edit {
            background-color: #2196f3;
            color: white;
        }
        
        .btn-edit:hover {
            background-color: #1976d2;
        }
        
        @media screen and (max-width: 576px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .form-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <h1>📦 Kelola Produk</h1>
            <p class="subtitle">Tambah, Edit, dan Hapus Produk</p>
        </header>

        <!-- Navigation -->
        <div class="nav-buttons">
            <a href="dashboard.php" class="btn btn-secondary">← Kembali ke Kasir</a>
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>

        <!-- Message Display -->
        <?php if (!empty($pesan)): ?>
            <div class="alert alert-<?php echo $pesan_type; ?>">
                <?php echo $pesan; ?>
            </div>
        <?php endif; ?>

        <!-- Add/Edit Product Form -->
        <section class="section">
            <h2><?php echo $edit_mode ? '✏️ Edit Produk' : '➕ Tambah Produk Baru'; ?></h2>
            <form method="POST" action="manage_products.php">
                <?php if ($edit_mode): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_product['id']; ?>">
                <?php endif; ?>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nama">Nama Produk *</label>
                        <input type="text" 
                               name="nama" 
                               id="nama"
                               placeholder="Contoh: Indomie Goreng"
                               value="<?php echo sanitizeOutput($edit_product['Nama']); ?>"
                               required
                               maxlength="255">
                    </div>
                    
                    <div class="form-group">
                        <label for="harga">Harga (Rp) *</label>
                        <input type="number" 
                               name="harga" 
                               id="harga"
                               placeholder="Contoh: 3500"
                               value="<?php echo $edit_product['Harga']; ?>"
                               min="0"
                               step="100"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="stok">Stok *</label>
                        <input type="number" 
                               name="stok" 
                               id="stok"
                               placeholder="Contoh: 50"
                               value="<?php echo $edit_product['Stok']; ?>"
                               min="0"
                               required>
                    </div>
                    
                    <div class="form-group form-actions">
                        <?php if ($edit_mode): ?>
                            <button type="submit" name="update_produk" class="btn btn-primary">
                                💾 Simpan Perubahan
                            </button>
                            <a href="manage_products.php" class="btn btn-secondary">Batal</a>
                        <?php else: ?>
                            <button type="submit" name="tambah_produk" class="btn btn-success">
                                ➕ Tambah Produk
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </section>

        <!-- Product List -->
        <section class="section">
            <h2>📋 Daftar Produk (<?php echo $products ? $products->num_rows : 0; ?> items)</h2>
            
            <?php if ($products && $products->num_rows > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama Produk</th>
                                <th>Harga</th>
                                <th>Stok</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $products->fetch_assoc()): ?>
                                <tr>
                                    <td data-label="ID">
                                        <span class="product-id">#<?php echo $row['id']; ?></span>
                                    </td>
                                    <td data-label="Nama"><?php echo sanitizeOutput($row['Nama']); ?></td>
                                    <td data-label="Harga"><?php echo formatRupiah($row['Harga']); ?></td>
                                    <td data-label="Stok">
                                        <span class="stock-badge <?php echo $row['Stok'] <= 5 ? 'stock-low' : ($row['Stok'] == 0 ? 'stock-empty' : 'stock-ok'); ?>">
                                            <?php echo $row['Stok']; ?>
                                        </span>
                                    </td>
                                    <td data-label="Aksi">
                                        <div class="action-buttons">
                                            <a href="?edit=<?php echo $row['id']; ?>" class="btn btn-edit btn-sm">
                                                ✏️ Edit
                                            </a>
                                            <a href="?delete=<?php echo $row['id']; ?>" 
                                               class="btn btn-danger btn-sm"
                                               onclick="return confirm('Apakah Anda yakin ingin menghapus produk \'<?php echo sanitizeOutput(addslashes($row['Nama'])); ?>\'?\n\nTindakan ini tidak dapat dibatalkan!');">
                                                🗑️ Hapus
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>📭 Belum ada produk. Tambahkan produk pertama Anda!</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Footer -->
        <footer class="footer">
            <p>&copy; <?php echo date('Y'); ?> MyKasir - Product Management</p>
        </footer>
    </div>

    <script>
        // Client-side validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const nama = document.getElementById('nama').value.trim();
            const harga = parseFloat(document.getElementById('harga').value);
            const stok = parseInt(document.getElementById('stok').value);
            
            if (nama === '') {
                e.preventDefault();
                alert('Nama produk tidak boleh kosong!');
                return;
            }
            
            if (isNaN(harga) || harga < 0) {
                e.preventDefault();
                alert('Harga harus berupa angka positif!');
                return;
            }
            
            if (isNaN(stok) || stok < 0) {
                e.preventDefault();
                alert('Stok harus berupa angka positif!');
                return;
            }
        });
        
        // Auto-focus on name field
        document.getElementById('nama').focus();
    </script>
</body>
</html>
