<?php
session_start();
require_once 'config.php';

// Generate CSRF Token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit();
}

// Handle Login
if (isset($_POST['username']) && isset($_POST['password'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    $stmt = $conn->prepare("SELECT id, password_hash FROM admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();
        if (password_verify($password, $admin['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header("Location: admin.php");
            exit();
        } else {
            $login_error = "Password salah.";
        }
    } else {
        $login_error = "Username tidak ditemukan.";
    }
    $stmt->close();
}

$is_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if (!is_dir('uploads')) {
    mkdir('uploads', 0777, true);
}

$pesan = "";
$pesan_kategori = "";
$active_tab = "section-produk"; // Default tab

function uploadGambar($file) {
    if ($file['error'] === 0) {
        $nama_file_asli = basename($file["name"]);
        $imageFileType = strtolower(pathinfo($nama_file_asli, PATHINFO_EXTENSION));
        $valid_extensions = array("jpg", "jpeg", "png", "gif", "webp");
        
        $check = getimagesize($file["tmp_name"]);
        if($check !== false && in_array($imageFileType, $valid_extensions)) {
            $nama_file_webp = time() . '_' . pathinfo($nama_file_asli, PATHINFO_FILENAME) . '.webp';
            $target_file = "uploads/" . $nama_file_webp;
            $mime = $check['mime'];
            $image = false;

            if ($mime == 'image/jpeg') {
                $image = imagecreatefromjpeg($file["tmp_name"]);
            } elseif ($mime == 'image/png') {
                $image = imagecreatefrompng($file["tmp_name"]);
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
            } elseif ($mime == 'image/gif') {
                $image = imagecreatefromgif($file["tmp_name"]);
            } elseif ($mime == 'image/webp') {
                $image = imagecreatefromwebp($file["tmp_name"]);
            }

            if ($image !== false) {
                if (imagewebp($image, $target_file, 80)) {
                    imagedestroy($image);
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
                    $base_url = rtrim($protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/\\') . '/';
                    return $base_url . $target_file;
                }
                imagedestroy($image);
            }
            
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

function deleteGambar($url) {
    if (strpos($url, 'uploads/') !== false) {
        $filename = basename(parse_url($url, PHP_URL_PATH));
        $filepath = 'uploads/' . $filename;
        if (file_exists($filepath) && !is_dir($filepath)) {
            unlink($filepath);
        }
    }
}

// Tambah / Edit Produk
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['simpan'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }
    $active_tab = "section-tambah";
    $id_produk = isset($_POST['id_produk']) ? (int)$_POST['id_produk'] : 0;
    $nama = trim($_POST['nama_produk']);
    $kategori = trim($_POST['kategori']);
    $harga = (float)$_POST['harga'];
    $deskripsi = trim($_POST['deskripsi']);
    $link_gambar = isset($_POST['gambar_lama']) ? $_POST['gambar_lama'] : '';

    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === 0) {
        $uploaded = uploadGambar($_FILES['gambar']);
        if ($uploaded != "") {
            if ($link_gambar != "") { deleteGambar($link_gambar); }
            $link_gambar = $uploaded;
        }
    }

    if ($id_produk > 0) {
        $stmt = $conn->prepare("UPDATE products SET nama_produk=?, kategori=?, harga=?, deskripsi=?, link_gambar=? WHERE id=?");
        $stmt->bind_param("ssdssi", $nama, $kategori, $harga, $deskripsi, $link_gambar, $id_produk);
        if ($stmt->execute()) { $pesan = "✅ Produk berhasil diperbarui!"; } else { $pesan = "❌ Error: " . $stmt->error; }
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO products (nama_produk, kategori, harga, deskripsi, link_gambar) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdss", $nama, $kategori, $harga, $deskripsi, $link_gambar);
        if ($stmt->execute()) { $pesan = "✅ Produk baru berhasil ditambahkan!"; } else { $pesan = "❌ Error: " . $stmt->error; }
        $stmt->close();
    }
}

// Hapus Produk
if (isset($_GET['hapus']) && isset($_GET['csrf_token'])) {
    if ($_GET['csrf_token'] === $_SESSION['csrf_token']) {
        $id_hapus = (int)$_GET['hapus'];
        $stmt = $conn->prepare("SELECT link_gambar FROM products WHERE id=?");
        $stmt->bind_param("i", $id_hapus);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            deleteGambar($row['link_gambar']);
        }
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM products WHERE id=?");
        $stmt->bind_param("i", $id_hapus);
        if ($stmt->execute()) {
            header("Location: admin.php"); 
            exit();
        }
        $stmt->close();
    }
}

// Tambah Kategori
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['simpan_kategori'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }
    $active_tab = "section-kategori";
    $nama_kategori = trim($_POST['nama_kategori']);
    if ($nama_kategori !== "") {
        $stmt = $conn->prepare("INSERT IGNORE INTO categories (nama_kategori) VALUES (?)");
        $stmt->bind_param("s", $nama_kategori);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $pesan_kategori = "✅ Kategori berhasil ditambahkan!";
        } else {
            $pesan_kategori = "❌ Kategori sudah ada atau gagal.";
        }
        $stmt->close();
    }
}

// Hapus Kategori via POST Dropdown
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['hapus_kategori_btn'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }
    $active_tab = "section-kategori";
    $id_kategori = isset($_POST['id_hapus_kategori']) ? (int)$_POST['id_hapus_kategori'] : 0;
    if ($id_kategori > 0) {
        $stmt = $conn->prepare("DELETE FROM categories WHERE id=?");
        $stmt->bind_param("i", $id_kategori);
        if ($stmt->execute()) {
            $pesan_kategori = "✅ Kategori berhasil dihapus!";
        } else {
            $pesan_kategori = "❌ Gagal menghapus kategori.";
        }
        $stmt->close();
    }
}

// Fetch Kategori
$kategori_result = $conn->query("SELECT * FROM categories ORDER BY id ASC");
$kategori_list = [];
if ($kategori_result && $kategori_result->num_rows > 0) {
    while($row = $kategori_result->fetch_assoc()) {
        $kategori_list[] = $row;
    }
}

// Fetch Produk
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
            --sidebar-bg: #1e293b;
            --surface: rgba(30, 41, 59, 0.9);
            --surface-border: rgba(255, 255, 255, 0.1);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --danger: #ef4444;
            --success: #10b981;
            --warning: #f59e0b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Outfit', sans-serif; }
        body { background: var(--bg-color); color: var(--text-main); height: 100vh; overflow: hidden; }
        
        .login-wrapper { display: flex; justify-content: center; align-items: center; height: 100vh; background-image: radial-gradient(circle at 50% 50%, rgba(99, 102, 241, 0.15), transparent 50%); }
        .login-card { background: var(--surface); border: 1px solid var(--surface-border); border-radius: 1.5rem; padding: 2.5rem; width: 100%; max-width: 400px; text-align: center; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
        
        .app-container { display: flex; height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 260px; background: var(--sidebar-bg); border-right: 1px solid var(--surface-border); display: flex; flex-direction: column; padding: 1.5rem; transition: transform 0.3s ease; }
        .sidebar-logo { font-size: 1.5rem; font-weight: 800; color: white; margin-bottom: 2rem; display: flex; align-items: center; gap: 10px; }
        .sidebar-logo span { color: var(--primary); }
        .menu-list { display: flex; flex-direction: column; gap: 0.5rem; flex: 1; }
        .menu-item { padding: 0.8rem 1rem; color: var(--text-muted); text-decoration: none; border-radius: 0.5rem; font-weight: 600; display: flex; align-items: center; gap: 10px; cursor: pointer; transition: all 0.3s ease; }
        .menu-item:hover { background: rgba(99, 102, 241, 0.1); color: var(--text-main); }
        .menu-item.active { background: var(--primary); color: white; }
        .menu-item.logout { color: var(--danger); margin-top: auto; }
        .menu-item.logout:hover { background: rgba(239, 68, 68, 0.1); }
        
        /* Main Content */
        .main-wrapper { flex: 1; display: flex; flex-direction: column; overflow: hidden; background-image: radial-gradient(circle at 80% 20%, rgba(99, 102, 241, 0.05), transparent 40%); }
        .top-header { height: 70px; background: rgba(30, 41, 59, 0.95); border-bottom: 1px solid var(--surface-border); display: flex; align-items: center; justify-content: space-between; padding: 0 2rem; }
        .header-title { font-weight: 600; font-size: 1.2rem; }
        
        .content-area { flex: 1; overflow-y: auto; padding: 2rem; }
        .section { display: none; animation: fadeIn 0.3s ease; }
        .section.active { display: block; }
        
        /* Components */
        .card { background: var(--surface); border: 1px solid var(--surface-border); border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.3); }
        .card-header { margin-bottom: 1.5rem; border-bottom: 1px solid var(--surface-border); padding-bottom: 1rem; }
        .card-title { font-size: 1.4rem; font-weight: 700; }
        
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; margin-bottom: 0.5rem; font-size: 0.9rem; color: var(--text-muted); font-weight: 600; }
        input[type="text"], input[type="number"], input[type="file"], textarea, select { width: 100%; padding: 0.8rem 1rem; background: rgba(15, 23, 42, 0.6); border: 1px solid var(--surface-border); border-radius: 0.5rem; color: var(--text-main); font-size: 1rem; outline: none; transition: border-color 0.3s; }
        select option { background: var(--bg-color); color: var(--text-main); }
        input:focus, textarea:focus, select:focus { border-color: var(--primary); }
        textarea { resize: vertical; min-height: 100px; }
        
        .btn { padding: 0.8rem 1.5rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; border: none; font-size: 1rem; text-align: center; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 5px 15px -5px var(--primary); }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: #dc2626; transform: translateY(-2px); box-shadow: 0 5px 15px -5px var(--danger); }
        .btn-cancel { background: rgba(255, 255, 255, 0.1); color: white; margin-top: 10px; display: none; width: 100%; }
        
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; text-align: center; font-weight: 600; }
        .alert-success { background: rgba(16, 185, 129, 0.2); border: 1px solid var(--success); color: #34d399; }
        .alert-error { background: rgba(239, 68, 68, 0.2); border: 1px solid var(--danger); color: #fca5a5; }
        
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--surface-border); }
        th { color: var(--text-muted); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; }
        tbody tr:hover { background: rgba(255, 255, 255, 0.03); }
        .img-preview { width: 50px; height: 50px; border-radius: 8px; object-fit: cover; }
        .action-btns { display: flex; gap: 0.5rem; }
        .btn-sm { padding: 0.4rem 0.8rem; font-size: 0.85rem; border-radius: 0.3rem; }
        
        /* Modal Delete */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(5px); z-index: 1000; display: none; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .modal-content { background: var(--surface); border: 1px solid var(--surface-border); border-radius: 1rem; padding: 2rem; width: 90%; max-width: 400px; text-align: center; }
        
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        
        @media (max-width: 768px) {
            .app-container { flex-direction: column; }
            .sidebar { width: 100%; border-right: none; border-bottom: 1px solid var(--surface-border); padding: 1rem; height: auto; }
            .menu-list { flex-direction: row; flex-wrap: wrap; }
            .menu-item { flex: 1; text-align: center; justify-content: center; }
        }
    </style>
</head>
<body>

<?php if (!$is_logged_in): ?>
    <div class="login-wrapper">
        <div class="login-card">
            <h2 style="font-size: 2rem; margin-bottom: 0.5rem;">🔒 Admin Login</h2>
            <p style="color: var(--text-muted); margin-bottom: 2rem;">Silakan masuk ke panel Anda</p>
            
            <?php if (isset($login_error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($login_error) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <input type="text" name="username" required placeholder="Username" style="text-align: center; font-size: 1.1rem;">
                </div>
                <div class="form-group">
                    <input type="password" name="password" required placeholder="Password" style="text-align: center; font-size: 1.1rem; letter-spacing: 2px;">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Login</button>
            </form>
        </div>
    </div>
<?php else: ?>

    <div class="app-container">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-logo">📦 <span>Katalog.</span></div>
            <div class="menu-list">
                <a class="menu-item <?= $active_tab == 'section-produk' ? 'active' : '' ?>" onclick="switchTab('section-produk')">📋 Daftar Produk</a>
                <a class="menu-item <?= $active_tab == 'section-tambah' ? 'active' : '' ?>" onclick="switchTab('section-tambah')">✨ Form Produk</a>
                <a class="menu-item <?= $active_tab == 'section-kategori' ? 'active' : '' ?>" onclick="switchTab('section-kategori')">📁 Kelola Kategori</a>
                
                <a href="?logout=1" class="menu-item logout">🔓 Keluar Admin</a>
            </div>
        </aside>

        <!-- Main Content Wrapper -->
        <div class="main-wrapper">
            <header class="top-header">
                <div class="header-title" id="topHeaderTitle">Daftar Produk</div>
                <div>Selamat datang, <strong>Admin</strong></div>
            </header>
            
            <main class="content-area">
                
                <!-- Section: Daftar Produk -->
                <div id="section-produk" class="section <?= $active_tab == 'section-produk' ? 'active' : '' ?>">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">📦 Semua Produk</h3>
                        </div>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Gambar</th>
                                        <th>Detail Produk</th>
                                        <th>Harga</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result->num_rows > 0): ?>
                                        <?php while($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><img src="<?= htmlspecialchars($row['link_gambar']) ?>" alt="img" class="img-preview" onerror="this.src='https://via.placeholder.com/60'"></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($row['nama_produk']) ?></strong><br>
                                                    <span style="color: var(--text-muted); font-size: 0.85rem;"><?= htmlspecialchars($row['kategori']) ?></span>
                                                </td>
                                                <td style="color: var(--success); font-weight: 600;">Rp <?= number_format($row['harga'], 0, ',', '.') ?></td>
                                                <td>
                                                    <div class="action-btns">
                                                        <button class="btn btn-sm btn-primary" style="background: rgba(99, 102, 241, 0.2); color: #818cf8;" onclick="editProduct(
                                                            <?= $row['id'] ?>, 
                                                            '<?= htmlspecialchars(addslashes($row['nama_produk'])) ?>', 
                                                            '<?= htmlspecialchars(addslashes($row['kategori'])) ?>', 
                                                            <?= $row['harga'] ?>, 
                                                            '<?= htmlspecialchars(addslashes(preg_replace("/\r|\n/", "\\n", $row['deskripsi']))) ?>',
                                                            '<?= htmlspecialchars(addslashes($row['link_gambar'])) ?>'
                                                        )">Edit</button>
                                                        <button class="btn btn-sm btn-danger" style="background: rgba(239, 68, 68, 0.2); color: #fca5a5;" onclick="showDeleteModal(<?= $row['id'] ?>)">Hapus</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" style="text-align: center; color: var(--text-muted);">Belum ada produk tersimpan.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Section: Tambah/Edit Produk -->
                <div id="section-tambah" class="section <?= $active_tab == 'section-tambah' ? 'active' : '' ?>">
                    <div class="card" style="max-width: 800px;">
                        <div class="card-header">
                            <h3 class="card-title" id="formTitle">✨ Tambah Produk Baru</h3>
                        </div>
                        
                        <?php if ($pesan != ""): ?>
                            <div class="alert <?= strpos($pesan, '✅') !== false ? 'alert-success' : 'alert-error' ?>"><?= $pesan; ?></div>
                        <?php endif; ?>

                        <form action="" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="id_produk" id="inputId">
                            <input type="hidden" name="gambar_lama" id="inputGambarLama">

                            <div class="form-group">
                                <label>Nama Produk</label>
                                <input type="text" name="nama_produk" id="inputNama" required placeholder="Mis: Jaket Parasut">
                            </div>
                            <div class="form-group">
                                <label>Kategori</label>
                                <select name="kategori" id="inputKategori" required style="cursor: pointer; appearance: none;">
                                    <option value="" disabled selected>Pilih Kategori</option>
                                    <?php foreach($kategori_list as $kat): ?>
                                        <option value="<?= htmlspecialchars($kat['nama_kategori']) ?>"><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Harga (Rp)</label>
                                <input type="number" name="harga" id="inputHarga" required placeholder="Mis: 150000">
                            </div>
                            <div class="form-group">
                                <label>Upload Gambar <span style="font-size: 0.8em; color: var(--warning);">(Abaikan jika tidak ingin mengubah gambar)</span></label>
                                <input type="file" name="gambar" accept="image/*">
                            </div>
                            <div class="form-group" id="previewGambarLamaContainer" style="display: none;">
                                <label>Gambar Lama Saat Ini:</label>
                                <input type="text" id="displayGambarLama" disabled style="background: rgba(0,0,0,0.2); color: var(--text-muted); cursor: not-allowed;">
                            </div>
                            <div class="form-group">
                                <label>Deskripsi Singkat</label>
                                <textarea name="deskripsi" id="inputDeskripsi" required placeholder="Deskripsi produk..."></textarea>
                            </div>
                            
                            <button type="submit" name="simpan" class="btn btn-primary" id="btnSubmit" style="width: 100%;">Simpan Produk</button>
                            <button type="button" class="btn btn-cancel" id="btnCancel" onclick="resetForm()">Batal Edit (Buat Baru)</button>
                        </form>
                    </div>
                </div>

                <!-- Section: Kelola Kategori -->
                <div id="section-kategori" class="section <?= $active_tab == 'section-kategori' ? 'active' : '' ?>">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                        
                        <!-- Form Tambah Kategori -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">➕ Tambah Kategori Baru</h3>
                            </div>
                            
                            <?php if (isset($pesan_kategori) && strpos($pesan_kategori, 'ditambahkan') !== false): ?>
                                <div class="alert alert-success"><?= $pesan_kategori; ?></div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <div class="form-group">
                                    <label>Nama Kategori Baru</label>
                                    <input type="text" name="nama_kategori" required placeholder="Mis: Elektronik">
                                </div>
                                <button type="submit" name="simpan_kategori" class="btn btn-primary" style="width: 100%;">Tambah Kategori</button>
                            </form>
                        </div>
                        
                        <!-- Form Hapus Kategori -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">🗑️ Hapus Kategori</h3>
                            </div>
                            
                            <?php if (isset($pesan_kategori) && strpos($pesan_kategori, 'dihapus') !== false): ?>
                                <div class="alert alert-success"><?= $pesan_kategori; ?></div>
                            <?php endif; ?>
                            <?php if (isset($pesan_kategori) && strpos($pesan_kategori, 'Gagal') !== false): ?>
                                <div class="alert alert-error"><?= $pesan_kategori; ?></div>
                            <?php endif; ?>
                            
                            <form method="POST" onsubmit="return confirm('Yakin ingin menghapus kategori ini secara permanen?')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <div class="form-group">
                                    <label>Pilih Kategori yang Ingin Dihapus</label>
                                    <select name="id_hapus_kategori" required style="cursor: pointer; appearance: none;">
                                        <option value="" disabled selected>Pilih Kategori...</option>
                                        <?php foreach($kategori_list as $kat): ?>
                                            <option value="<?= htmlspecialchars($kat['id']) ?>"><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" name="hapus_kategori_btn" class="btn btn-danger" style="width: 100%;">Hapus Kategori</button>
                            </form>
                        </div>
                        
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus Produk -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-content">
            <h3 style="margin-bottom: 1rem; color: var(--text-main);">⚠️ Konfirmasi Hapus</h3>
            <p style="color: var(--text-muted); margin-bottom: 2rem;">Apakah Anda yakin ingin menghapus produk ini? Aksi ini tidak dapat dibatalkan.</p>
            <div style="display: flex; gap: 1rem; justify-content: center;">
                <button class="btn" style="background: rgba(255,255,255,0.1); color: white;" onclick="closeDeleteModal()">Batal</button>
                <a href="#" class="btn btn-danger" id="confirmDeleteBtn" style="text-decoration: none;">Ya, Hapus!</a>
            </div>
        </div>
    </div>

    <script>
        // Fungsi Navigasi Tab / Sidebar
        function switchTab(tabId) {
            document.querySelectorAll('.section').forEach(sec => sec.classList.remove('active'));
            document.querySelectorAll('.menu-item').forEach(item => item.classList.remove('active'));
            
            document.getElementById(tabId).classList.add('active');
            document.querySelector(`[onclick="switchTab('${tabId}')"]`).classList.add('active');
            
            // Ubah Title Header
            let titles = {
                'section-produk': 'Daftar Produk',
                'section-tambah': 'Manajemen Form Produk',
                'section-kategori': 'Kelola Kategori'
            };
            document.getElementById('topHeaderTitle').innerText = titles[tabId];
        }
        
        // Panggil untuk inisialisasi judul tab aktif
        let initialTab = document.querySelector('.section.active').id;
        switchTab(initialTab);

        // Edit Produk Logic
        function editProduct(id, nama, kategori, harga, deskripsi, link_gambar) {
            switchTab('section-tambah'); // Pindah ke tab form
            
            document.getElementById('formTitle').innerHTML = '✏️ Edit Produk';
            document.getElementById('inputId').value = id;
            document.getElementById('inputNama').value = nama;
            document.getElementById('inputKategori').value = kategori;
            document.getElementById('inputHarga').value = harga;
            document.getElementById('inputDeskripsi').value = deskripsi;
            
            document.getElementById('inputGambarLama').value = link_gambar;
            document.getElementById('displayGambarLama').value = link_gambar;
            document.getElementById('previewGambarLamaContainer').style.display = 'block';
            
            document.getElementById('btnSubmit').innerHTML = 'Update Produk Data';
            document.getElementById('btnCancel').style.display = 'block';
        }

        // Reset Form Logic
        function resetForm() {
            document.getElementById('formTitle').innerHTML = '✨ Tambah Produk Baru';
            document.getElementById('inputId').value = '';
            document.getElementById('inputNama').value = '';
            document.getElementById('inputKategori').value = '';
            document.getElementById('inputHarga').value = '';
            document.getElementById('inputDeskripsi').value = '';
            document.getElementById('inputGambarLama').value = '';
            
            document.getElementById('previewGambarLamaContainer').style.display = 'none';
            document.getElementById('btnSubmit').innerHTML = 'Simpan Produk';
            document.getElementById('btnCancel').style.display = 'none';
        }
        
        // Modal Delete Logic
        function showDeleteModal(id) {
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            confirmBtn.href = '?hapus=' + id + '&csrf_token=<?= htmlspecialchars($csrf_token) ?>';
            document.getElementById('deleteModal').classList.add('active');
        }
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
    </script>
<?php endif; ?>
</body>
</html>
