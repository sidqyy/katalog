<?php
session_start();

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit();
}

// Handle Login "Magic Button"
if (isset($_POST['magic_password'])) {
    if ($_POST['magic_password'] === 'admin123') { 
        $_SESSION['admin_logged_in'] = true;
    }
}

// Cek apakah sudah login
$is_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Konfigurasi Database
$host = "localhost";
$user = "root";
$pass = ""; 
$db   = "katalog_db";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Buat folder uploads jika belum ada
if (!is_dir('uploads')) {
    mkdir('uploads', 0777, true);
}

$pesan = "";

// Fungsi untuk menangani upload gambar (Otomatis Kompresi ke WebP)
function uploadGambar($file) {
    if ($file['error'] === 0) {
        $nama_file_asli = basename($file["name"]);
        $imageFileType = strtolower(pathinfo($nama_file_asli, PATHINFO_EXTENSION));
        $valid_extensions = array("jpg", "jpeg", "png", "gif", "webp");
        
        // Memastikan file benar-benar merupakan gambar (Mencegah fake extension / RCE shell)
        $check = getimagesize($file["tmp_name"]);
        if($check !== false && in_array($imageFileType, $valid_extensions)) {
            
            $nama_file_webp = time() . '_' . pathinfo($nama_file_asli, PATHINFO_FILENAME) . '.webp';
            $target_file = "uploads/" . $nama_file_webp;
            $mime = $check['mime'];
            $image = false;

            // Load gambar berdasarkan MIME type
            if ($mime == 'image/jpeg') {
                $image = imagecreatefromjpeg($file["tmp_name"]);
            } elseif ($mime == 'image/png') {
                $image = imagecreatefrompng($file["tmp_name"]);
                // Pertahankan transparansi PNG
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
            } elseif ($mime == 'image/gif') {
                $image = imagecreatefromgif($file["tmp_name"]);
            } elseif ($mime == 'image/webp') {
                $image = imagecreatefromwebp($file["tmp_name"]);
            }

            if ($image !== false) {
                // Simpan sebagai WEBP dengan Quality 80 (Rasio ideal ukuran kecil & gambar tajam)
                if (imagewebp($image, $target_file, 80)) {
                    imagedestroy($image);
                    
                    // URL Dinamis untuk cPanel
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
                    $base_url = rtrim($protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/\\') . '/';
                    
                    return $base_url . $target_file;
                }
                imagedestroy($image);
            }
            
            // Fallback: Jika konversi GD gagal, lakukan upload biasa
            $target_file_fallback = "uploads/" . time() . '_' . $nama_file_asli;
            if (move_uploaded_file($file["tmp_name"], $target_file_fallback)) {
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
                $base_url = rtrim($protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/\\') . '/';
                return $base_url . $target_file_fallback;
            }
        }
    }
    return "";
}

// Fungsi untuk menghapus file gambar fisik dari server
function deleteGambar($url) {
    if (strpos($url, 'uploads/') !== false) {
        $filename = basename(parse_url($url, PHP_URL_PATH));
        $filepath = 'uploads/' . $filename;
        if (file_exists($filepath) && !is_dir($filepath)) {
            unlink($filepath);
        }
    }
}

// Menangani Form Submit untuk Tambah / Edit Produk
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['simpan'])) {
    $id_produk = isset($_POST['id_produk']) ? (int)$_POST['id_produk'] : 0;
    $nama = $conn->real_escape_string($_POST['nama_produk']);
    $kategori = $conn->real_escape_string($_POST['kategori']);
    $harga = (float)$_POST['harga'];
    $deskripsi = $conn->real_escape_string($_POST['deskripsi']);
    
    // Default gambar lama
    $link_gambar = isset($_POST['gambar_lama']) ? $conn->real_escape_string($_POST['gambar_lama']) : '';

    // Jika ada file diupload (Menggantikan URL lama)
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === 0) {
        $uploaded = uploadGambar($_FILES['gambar']);
        if ($uploaded != "") {
            // Hapus gambar lama dari server jika ada
            if ($link_gambar != "") {
                deleteGambar($link_gambar);
            }
            $link_gambar = $uploaded;
        }
    }

    if ($id_produk > 0) {
        // UPDATE PRODUK
        $sql = "UPDATE products SET 
                nama_produk='$nama', kategori='$kategori', harga=$harga, deskripsi='$deskripsi', link_gambar='$link_gambar' 
                WHERE id=$id_produk";
        if ($conn->query($sql) === TRUE) {
            $pesan = "✅ Produk berhasil diperbarui!";
        } else {
            $pesan = "❌ Error: " . $conn->error;
        }
    } else {
        // INSERT PRODUK BARU
        $sql = "INSERT INTO products (nama_produk, kategori, harga, deskripsi, link_gambar) 
                VALUES ('$nama', '$kategori', $harga, '$deskripsi', '$link_gambar')";
        if ($conn->query($sql) === TRUE) {
            $pesan = "✅ Produk baru berhasil ditambahkan!";
        } else {
            $pesan = "❌ Error: " . $conn->error;
        }
    }
}

// Menangani Hapus Produk
if (isset($_GET['hapus'])) {
    $id_hapus = (int)$_GET['hapus'];
    
    // Ambil link gambar dan hapus file fisiknya dulu
    $res = $conn->query("SELECT link_gambar FROM products WHERE id=$id_hapus");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        deleteGambar($row['link_gambar']);
    }

    $sql_hapus = "DELETE FROM products WHERE id=$id_hapus";
    if ($conn->query($sql_hapus) === TRUE) {
        header("Location: admin.php"); 
        exit();
    }
}

// Mengambil Daftar Produk
$result = $conn->query("SELECT * FROM products ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Katalog Produk</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --bg-color: #0f172a;
            --surface: rgba(30, 41, 59, 0.7);
            --surface-border: rgba(255, 255, 255, 0.1);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --danger: #ef4444;
            --success: #10b981;
            --warning: #f59e0b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Outfit', sans-serif; }
        body {
            background: var(--bg-color);
            background-image: 
                radial-gradient(circle at 15% 50%, rgba(99, 102, 241, 0.15), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(16, 185, 129, 0.15), transparent 25%);
            color: var(--text-main);
            min-height: 100vh;
        }
        .container { max-width: 1300px; margin: 0 auto; display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; padding: 0 2rem 2rem 2rem; }
        @media (max-width: 900px) { .container { grid-template-columns: 1fr; } }
        .glass-panel {
            background: var(--surface); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--surface-border); border-radius: 1.5rem; padding: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .glass-panel:hover { transform: translateY(-5px); box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.6); }
        h2 { font-size: 1.75rem; font-weight: 700; margin-bottom: 1.5rem; background: linear-gradient(135deg, #fff, #94a3b8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; margin-bottom: 0.5rem; font-size: 0.9rem; color: var(--text-muted); font-weight: 600; }
        input[type="text"], input[type="number"], input[type="file"], textarea {
            width: 100%; padding: 0.75rem 1rem; background: rgba(15, 23, 42, 0.6); border: 1px solid var(--surface-border);
            border-radius: 0.75rem; color: var(--text-main); font-size: 1rem; outline: none; transition: all 0.3s ease;
        }
        input:focus, textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2); }
        textarea { resize: vertical; min-height: 100px; }
        button.btn-submit { width: 100%; padding: 1rem; background: linear-gradient(135deg, var(--primary), var(--primary-hover)); color: white; border: none; border-radius: 0.75rem; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; text-transform: uppercase; letter-spacing: 1px; }
        button.btn-submit:hover { box-shadow: 0 10px 20px -10px var(--primary); transform: scale(1.02); }
        button.btn-cancel { width: 100%; padding: 1rem; margin-top: 10px; background: rgba(255, 255, 255, 0.1); color: white; border: 1px solid var(--surface-border); border-radius: 0.75rem; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: none; }
        button.btn-cancel:hover { background: rgba(255, 255, 255, 0.2); }
        .alert { padding: 1rem; border-radius: 0.75rem; margin-bottom: 1.5rem; background: rgba(16, 185, 129, 0.2); border: 1px solid var(--success); color: #34d399; text-align: center; font-weight: 600; animation: fadeIn 0.5s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0 0.75rem; }
        th, td { padding: 1rem; text-align: left; }
        th { color: var(--text-muted); font-weight: 600; font-size: 0.85rem; text-transform: uppercase; border-bottom: 1px solid var(--surface-border); }
        tr.row-data { background: rgba(255, 255, 255, 0.03); transition: all 0.3s ease; }
        tr.row-data:hover { background: rgba(255, 255, 255, 0.08); transform: scale(1.01); }
        tr.row-data td:first-child { border-top-left-radius: 0.75rem; border-bottom-left-radius: 0.75rem; }
        tr.row-data td:last-child { border-top-right-radius: 0.75rem; border-bottom-right-radius: 0.75rem; }
        .img-preview { width: 60px; height: 60px; border-radius: 8px; object-fit: cover; border: 1px solid var(--surface-border); }
        .action-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn-action { padding: 0.5rem 1rem; text-decoration: none; border-radius: 0.5rem; font-size: 0.85rem; font-weight: 600; transition: all 0.3s ease; border: 1px solid transparent; cursor: pointer;}
        .btn-edit { background: rgba(245, 158, 11, 0.1); color: var(--warning); border: 1px solid var(--warning); }
        .btn-edit:hover { background: var(--warning); color: white; box-shadow: 0 5px 15px -5px var(--warning); }
        .btn-delete { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid var(--danger); }
        .btn-delete:hover { background: var(--danger); color: white; box-shadow: 0 5px 15px -5px var(--danger); }
        .price-tag { color: #34d399; font-weight: 700; }
        
        /* Modal Popup Styles */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(5px); z-index: 1000; display: none; justify-content: center; align-items: center; opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay.active { display: flex; opacity: 1; }
        .modal-content { background: var(--surface); border: 1px solid var(--surface-border); border-radius: 1.5rem; padding: 2rem; width: 90%; max-width: 400px; text-align: center; transform: scale(0.9); transition: transform 0.3s ease; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
        .modal-overlay.active .modal-content { transform: scale(1); }
        .modal-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 1rem; color: var(--text-main); }
        .modal-text { color: var(--text-muted); margin-bottom: 2rem; font-size: 1rem; }
        .modal-buttons { display: flex; gap: 1rem; justify-content: center; }
        .btn-modal { padding: 0.75rem 1.5rem; border-radius: 0.75rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; border: none; font-size: 1rem; }
        .btn-modal-cancel { background: rgba(255, 255, 255, 0.1); color: var(--text-main); }
        .btn-modal-cancel:hover { background: rgba(255, 255, 255, 0.2); }
        .btn-modal-confirm { background: var(--danger); color: white; }
        .btn-modal-confirm:hover { background: #dc2626; box-shadow: 0 10px 20px -10px var(--danger); }
    </style>
</head>
<body>

<?php if (!$is_logged_in): ?>
    <div style="display: flex; justify-content: center; align-items: center; min-height: 100vh;">
        <div class="glass-panel" style="width: 100%; max-width: 400px; text-align: center;">
            <h2 style="font-size: 2rem; margin-bottom: 0.5rem;">🔒 Admin Area</h2>
            <p style="color: var(--text-muted); margin-bottom: 2rem;">Silakan masukkan password untuk melanjutkan</p>
            
            <form method="POST">
                <div class="form-group">
                    <input type="password" name="magic_password" required placeholder="Password..." style="text-align: center; font-size: 1.2rem; letter-spacing: 2px;">
                </div>
                <button type="submit" class="btn-submit">Login</button>
            </form>
        </div>
    </div>
<?php else: ?>

    <div style="text-align: right; max-width: 1300px; margin: 0 auto; padding: 2rem 2rem 1rem 2rem;">
        <a href="?logout=1" style="color: var(--danger); text-decoration: none; font-weight: 600; padding: 8px 16px; background: rgba(239, 68, 68, 0.1); border-radius: 8px; transition: 0.3s; border: 1px solid var(--danger);">🔓 Keluar Admin</a>
    </div>

    <div class="container">
        <!-- Form Tambah/Edit Produk -->
        <div class="glass-panel" id="formPanel">
            <h2 id="formTitle">✨ Tambah Produk Baru</h2>
            
            <?php if ($pesan != ""): ?>
                <div class="alert"><?= $pesan; ?></div>
            <?php endif; ?>

            <!-- Tambahkan enctype="multipart/form-data" untuk upload file -->
            <form action="" method="POST" enctype="multipart/form-data">
                <!-- Input hidden untuk identifikasi apakah ini operasi update atau insert baru -->
                <input type="hidden" name="id_produk" id="inputId">
                <input type="hidden" name="gambar_lama" id="inputGambarLama">

                <div class="form-group">
                    <label>Nama Produk</label>
                    <input type="text" name="nama_produk" id="inputNama" required placeholder="Mis: Jaket Parasut">
                </div>
                <div class="form-group">
                    <label>Kategori</label>
                    <input type="text" name="kategori" id="inputKategori" required placeholder="Mis: Pakaian">
                </div>
                <div class="form-group">
                    <label>Harga (Rp)</label>
                    <input type="number" name="harga" id="inputHarga" required placeholder="Mis: 150000">
                </div>
                <div class="form-group">
                    <label>Upload Gambar Baru <span style="font-size: 0.8em; color: var(--warning);" id="textGambarOpsional">(Opsional jika hanya edit data)</span></label>
                    <input type="file" name="gambar" accept="image/*">
                </div>
                <div class="form-group">
                    <label>Atau Gunakan Link Gambar Lama</label>
                    <input type="text" id="displayGambarLama" disabled style="background: rgba(0,0,0,0.2); color: var(--text-muted); font-size: 0.85em; cursor: not-allowed;" placeholder="Tidak ada gambar lama">
                </div>
                <div class="form-group">
                    <label>Deskripsi Singkat</label>
                    <textarea name="deskripsi" id="inputDeskripsi" required placeholder="Tuliskan deksripsi produk yang menarik..."></textarea>
                </div>
                <button type="submit" name="simpan" class="btn-submit" id="btnSubmit">Simpan Produk</button>
                <button type="button" class="btn-cancel" id="btnCancel" onclick="resetForm()">Batal Edit</button>
            </form>
        </div>

        <!-- Daftar Produk -->
        <div class="glass-panel">
            <h2>📦 Daftar Produk Saat Ini</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Preview</th>
                            <th>Info Produk</th>
                            <th>Harga</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr class="row-data">
                                    <td>
                                        <img src="<?= htmlspecialchars($row['link_gambar']) ?>" alt="img" class="img-preview" onerror="this.src='https://via.placeholder.com/60'">
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($row['nama_produk']) ?></strong><br>
                                        <span style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($row['kategori']) ?></span>
                                    </td>
                                    <td>
                                        <span class="price-tag">Rp <?= number_format($row['harga'], 0, ',', '.') ?></span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <!-- Tombol Edit memicu JavaScript -->
                                            <button type="button" class="btn-action btn-edit" onclick="editProduct(
                                                <?= $row['id'] ?>, 
                                                '<?= htmlspecialchars(addslashes($row['nama_produk'])) ?>', 
                                                '<?= htmlspecialchars(addslashes($row['kategori'])) ?>', 
                                                <?= $row['harga'] ?>, 
                                                '<?= htmlspecialchars(addslashes(preg_replace("/\r|\n/", "\\n", $row['deskripsi']))) ?>',
                                                '<?= htmlspecialchars(addslashes($row['link_gambar'])) ?>'
                                            )">Edit</button>
                                            
                                            <button type="button" class="btn-action btn-delete" onclick="showDeleteModal(<?= $row['id'] ?>)">Hapus</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: var(--text-muted);">Belum ada produk.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function editProduct(id, nama, kategori, harga, deskripsi, link_gambar) {
            document.getElementById('formTitle').innerHTML = '✏️ Edit Produk';
            document.getElementById('inputId').value = id;
            document.getElementById('inputNama').value = nama;
            document.getElementById('inputKategori').value = kategori;
            document.getElementById('inputHarga').value = harga;
            document.getElementById('inputDeskripsi').value = deskripsi;
            
            // Simpan link gambar lama, agar tidak tertimpa kosong jika user tidak upload file baru
            document.getElementById('inputGambarLama').value = link_gambar;
            document.getElementById('displayGambarLama').value = link_gambar;
            
            // Ubah tombol submit jadi Update
            document.getElementById('btnSubmit').innerHTML = 'Update Produk';
            document.getElementById('btnCancel').style.display = 'block';
            
            // Scroll layar ke arah form
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Fungsi untuk mereset form kembali ke mode Tambah Produk Baru
        function resetForm() {
            document.getElementById('formTitle').innerHTML = '✨ Tambah Produk Baru';
            document.getElementById('inputId').value = '';
            document.getElementById('inputNama').value = '';
            document.getElementById('inputKategori').value = '';
            document.getElementById('inputHarga').value = '';
            document.getElementById('inputDeskripsi').value = '';
            document.getElementById('inputGambarLama').value = '';
            document.getElementById('displayGambarLama').value = '';
            
            document.getElementById('btnSubmit').innerHTML = 'Simpan Produk';
            document.getElementById('btnCancel').style.display = 'none';
        }
        
        // Modal Delete Logic
        function showDeleteModal(id) {
            const modal = document.getElementById('deleteModal');
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            confirmBtn.href = '?hapus=' + id;
            modal.classList.add('active');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
    </script>

    <!-- Modal Konfirmasi Hapus -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-content">
            <div class="modal-title">⚠️ Konfirmasi Hapus</div>
            <div class="modal-text">Apakah Anda yakin ingin menghapus produk ini secara permanen? Data tidak dapat dikembalikan.</div>
            <div class="modal-buttons">
                <button class="btn-modal btn-modal-cancel" onclick="closeDeleteModal()">Batal</button>
                <a href="#" class="btn-modal btn-modal-confirm" id="confirmDeleteBtn" style="text-decoration: none;">Ya, Hapus!</a>
            </div>
        </div>
    </div>

<?php endif; ?>
</body>
</html>
