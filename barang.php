<?php
session_start();
include 'koneksi.php';

// Cek apakah user adalah admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$success = '';$error = '';

// Proses Tambah Barang
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_barang'])) {
    $code        = trim($_POST['code']);
    $name        = trim($_POST['name']);
    $category_id = intval($_POST['category_id']);
    $quantity    = intval($_POST['total_quantity']);
    $condition   =$_POST['condition'];
    $location    = trim($_POST['location']);
    $user_id     =$_SESSION['user_id'];

    if (!empty($code) && !empty($name) && $category_id > 0 &&$quantity > 0) {
        // Cek apakah kode barang sudah ada
        $check =$conn->prepare("SELECT id FROM items WHERE code = ?");
        $check->bind_param("s", $code);
        $check->execute();$check->store_result();

        if ($check->num_rows > 0) {$error = "Kode barang '{$code}' sudah terdaftar di sistem!";
        } else {
            // Mulai transaksi
            $conn->begin_transaction();

            try {
                // 1. Insert ke tabel items (available_quantity disamakan dengan total_quantity saat pertama dibuat)
                $stmt =$conn->prepare("INSERT INTO items (category_id, code, name, total_quantity, available_quantity, `condition`, status, location) VALUES (?, ?, ?, ?, ?, ?, 'tersedia', ?)");
                $stmt->bind_param("issiiss", $category_id,$code, $name,$quantity, $quantity,$condition, $location);$stmt->execute();
                $item_id =$stmt->insert_id;
                $stmt->close();

                // 2. Catat ke tabel item_logs (Fitur 8)
                $log_desc = "Barang baru '{$name}' dengan kode '{$code}' ditambahkan sebanyak {$quantity} unit.";
                $log_stmt =$conn->prepare("INSERT INTO item_logs (item_id, user_id, action, description) VALUES (?, ?, 'tambah', ?)");
                $log_stmt->bind_param("iis", $item_id, $user_id,$log_desc);
                $log_stmt->execute();$log_stmt->close();

                $conn->commit();$success = "Data barang berhasil ditambahkan!";
            } catch (Exception $e) {
                $conn->rollback();$error = "Gagal menyimpan data: " . $e->getMessage();
            }
        }
        $check->close();
    } else {
        $error = "Semua kolom wajib diisi dengan benar!";
    }
}

// Ambil data kategori untuk pilihan form dropdown
$categories =$conn->query("SELECT * FROM categories ORDER BY name ASC");

// Ambil seluruh data barang beserta nama kategorinya
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if (!empty($search)) {
    $stmt_items =$conn->prepare("SELECT items.*, categories.name as category_name FROM items JOIN categories ON items.category_id = categories.id WHERE items.name LIKE ? OR items.code LIKE ? ORDER BY items.id DESC");
    $searchTerm = "\%{$search}%";
    $stmt_items->bind_param("ss", $searchTerm, $searchTerm);$stmt_items->execute();
    $items =$stmt_items->get_result();
} else {
    $items =$conn->query("SELECT items.*, categories.name as category_name FROM items JOIN categories ON items.category_id = categories.id ORDER BY items.id DESC");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendataan Barang - Inventaris Lab Sains</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 flex min-h-screen">

    <!-- Sidebar Admin -->
    <aside class="w-64 bg-slate-900 text-slate-300 flex flex-col justify-between hidden md:flex">
        <div>
            <div class="p-6 border-b border-slate-800">
                <span class="text-xs uppercase tracking-wider text-indigo-400 font-semibold">Panel Admin</span>
                <h1 class="text-xl font-bold text-white">Lab Sains</h1>
            </div>
            <nav class="p-4 space-y-1">
                <a href="dashboard_admin.php" class="flex items-center gap-3 px-4 py-2.5 bg-indigo-600 text-white rounded-lg font-medium text-sm">📊 Dashboard</a>
                <a href="barang.php" class="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-800 rounded-lg text-sm transition">📦 Pendataan Barang</a>
                <a href="transaksi.php" class="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-800 rounded-lg text-sm transition">📋 Transaksi Peminjaman</a>
                <a href="riwayat_transaksi.php" class="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-800 rounded-lg text-sm transition">📜 Riwayat / Arsip</a>
                <a href="pengguna.php" class="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-800 rounded-lg text-sm transition">👥 Manajemen Pengguna</a>
                <a href="backup.php" class="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-800 rounded-lg text-sm transition">📁 Backup & Restore</a>
            </nav>
        </div>
        <div class="p-4 border-t border-slate-800">
            <a href="logout.php" class="flex items-center gap-2 px-4 py-2 bg-red-600/20 text-red-400 hover:bg-red-600 hover:text-white rounded-lg text-sm font-medium transition text-center justify-center">🚪 Keluar Sistem</a>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col">
        <!-- Top Navbar -->
        <header class="bg-white border-b border-slate-200 px-8 py-4 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-slate-800">Pendataan Barang Laboratorium</h2>
            <div class="text-sm text-slate-500">Role: <span class="font-semibold text-indigo-600 uppercase">Admin</span></div>
        </header>

        <!-- Content Body -->
        <main class="p-8 flex-1 space-y-6">

            <!-- Notifikasi Pesan -->
            <?php if (!empty($success)): ?>
                <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-lg flex items-center gap-2">
                    <span>✅</span><span><?php echo $success; ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="p-4 bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg flex items-center gap-2">
                    <span>⚠️</span><span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <!-- Form Tambah Barang & Tabel Data -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Kolom Kiri: Form Tambah Barang -->
                <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm h-fit">
                    <h3 class="font-bold text-slate-800 mb-4 pb-2 border-b border-slate-100">➕ Tambah Barang Baru</h3>
                    <form action="" method="POST" class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Kode / Barcode Barang</label>
                            <input type="text" name="code" required placeholder="Contoh: GLS-007" 
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Nama Barang</label>
                            <input type="text" name="name" required placeholder="Contoh: Gelas Ukur 50ml" 
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Kategori</label>
                            <select name="category_id" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                                <option value="">Pilih Kategori</option>
                                <?php while ($cat =$categories->fetch_assoc()): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Jumlah Total</label>
                                <input type="number" name="total_quantity" min="1" required placeholder="10" 
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Kondisi</label>
                                <select name="condition" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                                    <option value="baik">Baik</option>
                                    <option value="rusak_ringan">Rusak Ringan</option>
                                    <option value="rusak_berat">Rusak Berat</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Lokasi Penyimpanan</label>
                            <input type="text" name="location" placeholder="Contoh: Rak A-2" 
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <button type="submit" name="tambah_barang" 
                            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 rounded-lg text-sm transition shadow-sm">
                            Simpan Barang
                        </button>
                    </form>
                </div>

                <!-- Kolom Kanan: Daftar Tabel Barang -->
                <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                    <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-4">
                        <h3 class="font-bold text-slate-800">Definisi & Stok Inventaris Lab</h3>
                        <!-- Form Pencarian -->
                        <form action="" method="GET" class="flex gap-2 w-full sm:w-auto">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari nama / kode..." 
                                class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                            <button type="submit" class="bg-slate-800 text-white px-4 py-1.5 rounded-lg text-sm hover:bg-slate-700 transition">Cari</button>
                        </form>
                    </div>

                    <div class="overflow-x-auto flex-1">
                        <table class="w-full text-left text-sm text-slate-600">
                            <thead class="bg-slate-50 text-slate-700 uppercase text-xs">
                                <tr>
                                    <th class="px-4 py-3">Kode</th>
                                    <th class="px-4 py-3">Nama Barang</th>
                                    <th class="px-4 py-3">Kategori</th>
                                    <th class="px-4 py-3 text-center">Stok (Tersedia/Total)</th>
                                    <th class="px-4 py-3">Kondisi</th>
                                    <th class="px-4 py-3">Lokasi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if ($items &&$items->num_rows > 0): ?>
                                    <?php while ($row =$items->fetch_assoc()): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3 font-mono font-medium text-slate-800"><?php echo htmlspecialchars($row['code']); ?></td>
                                        <td class="px-4 py-3 font-medium text-slate-800"><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td class="px-4 py-3 text-xs"><?php echo htmlspecialchars($row['category_name']); ?></td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="font-semibold text-slate-700"><?php echo $row['available_quantity']; ?></span> / 
                                            <span class="text-slate-400"><?php echo $row['total_quantity']; ?></span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold 
                                                <?php 
                                                    if($row['condition'] == 'baik') echo 'bg-emerald-50 text-emerald-600';
                                                    elseif($row['condition'] == 'rusak_ringan') echo 'bg-amber-50 text-amber-600';
                                                    else echo 'bg-red-50 text-red-600';
                                                ?>">
                                                <?php echo ucfirst($row['condition']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-xs font-mono text-slate-500"><?php echo htmlspecialchars($row['location']); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-8 text-center text-slate-400">Belum ada data barang ditemukan.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>
</body>
</html>