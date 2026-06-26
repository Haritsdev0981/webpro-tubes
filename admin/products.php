<?php
session_start();
require_once '../includes/config.php';
$user = requireAuth('admin');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id) { $db->prepare("DELETE FROM products WHERE id=?")->execute([$id]); setFlash('success','Produk dihapus.'); }
    }
    if ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $status = $_POST['status'] ?? 'active';
        $db->prepare("UPDATE products SET status=? WHERE id=?")->execute([$status, $id]);
        setFlash('success','Status produk diperbarui.');
    }
    header('Location: products.php'); exit;
}

$search = trim($_GET['search'] ?? '');
$where = ''; $params = [];
if ($search) { $where = 'WHERE p.name LIKE ? OR u.name LIKE ?'; $params = ["%$search%", "%$search%"]; }

$stmt = $db->prepare("SELECT p.*, u.name as seller_name, c.name as cat_name FROM products p
    JOIN users u ON u.id = p.seller_id LEFT JOIN categories c ON c.id = p.category_id
    $where ORDER BY p.created_at DESC");
$stmt->execute($params);
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Produk - Teloved Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include 'partials/sidebar.php'; ?>
    <div class="admin-content">
        <?php include 'partials/topbar.php'; ?>
        <main class="main-area">
            <div class="page-header">
                <div><h1>Kelola Produk</h1><p>Tampilkan & moderasi semua produk di platform</p></div>
            </div>
            <form method="GET" class="filter-bar">
                <input type="text" name="search" placeholder="Cari nama produk atau seller..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn-primary">Cari</button>
                <a href="products.php" class="btn-secondary">Reset</a>
            </form>
            <div class="section-card">
                <table class="data-table">
                    <thead><tr><th>#</th><th>Produk</th><th>Seller</th><th>Kategori</th><th>Harga</th><th>Kondisi</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                        <tr><td colspan="8" class="empty-row">Tidak ada produk.</td></tr>
                        <?php else: foreach ($products as $i => $p): ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td><?= htmlspecialchars($p['name']) ?></td>
                            <td><?= htmlspecialchars($p['seller_name']) ?></td>
                            <td><?= htmlspecialchars($p['cat_name'] ?? '-') ?></td>
                            <td><?= formatRupiah($p['price']) ?></td>
                            <td><?= htmlspecialchars($p['condition_type']) ?></td>
                            <td>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <select name="status" onchange="this.form.submit()">
                                        <option value="active" <?= $p['status']==='active'?'selected':'' ?>>Aktif</option>
                                        <option value="inactive" <?= $p['status']==='inactive'?'selected':'' ?>>Nonaktif</option>
                                        <option value="sold" <?= $p['status']==='sold'?'selected':'' ?>>Terjual</option>
                                    </select>
                                </form>
                            </td>
                            <td class="action-cell">
                                <form method="POST" style="display:inline" onsubmit="return confirm('Hapus produk ini?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn-sm btn-delete">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
