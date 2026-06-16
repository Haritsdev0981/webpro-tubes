<?php
session_start();
require_once '../includes/config.php';
$user = requireAuth('admin');
$db   = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete') {
    $id = (int)$_POST['id'];
    if ($id) { $db->prepare("DELETE FROM checkouts WHERE id=?")->execute([$id]); setFlash('success','Transaksi dihapus.'); }
    header('Location: orders.php'); exit;
}

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$where  = '1=1'; $params = [];
if ($search) { $where .= " AND (p.name LIKE ? OR b.name LIKE ? OR s.name LIKE ?)"; $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]); }
if ($status) { $where .= " AND c.status = ?"; $params[] = $status; }

$stmt = $db->prepare("
    SELECT c.*, p.name as product_name, b.name as buyer_name, s.name as seller_name,
           o.status as order_status, o.tracking_number
    FROM checkouts c
    JOIN products p ON p.id = c.product_id
    JOIN users b ON b.id = c.buyer_id
    JOIN users s ON s.id = c.seller_id
    LEFT JOIN orders o ON o.checkout_id = c.id
    WHERE $where
    ORDER BY c.created_at DESC
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$statusColors = [
    'pending'=>'badge-admin','confirmed'=>'badge-buyer','processing'=>'badge-buyer',
    'shipped'=>'badge-seller','completed'=>'badge-active','cancelled'=>'badge-inactive'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Semua Transaksi - Teloved Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include 'partials/sidebar.php'; ?>
    <div class="admin-content">
        <?php include 'partials/topbar.php'; ?>
        <main class="main-area">
            <div class="page-header">
                <div><h1>Semua Transaksi</h1><p>Monitor semua aktivitas checkout di platform</p></div>
            </div>
            <form method="GET" class="filter-bar">
                <input type="text" name="search" placeholder="Cari produk, buyer, atau seller..." value="<?= htmlspecialchars($search) ?>">
                <select name="status">
                    <option value="">Semua Status</option>
                    <option value="pending" <?= $status==='pending'?'selected':'' ?>>Pending</option>
                    <option value="confirmed" <?= $status==='confirmed'?'selected':'' ?>>Confirmed</option>
                    <option value="shipped" <?= $status==='shipped'?'selected':'' ?>>Shipped</option>
                    <option value="completed" <?= $status==='completed'?'selected':'' ?>>Completed</option>
                    <option value="cancelled" <?= $status==='cancelled'?'selected':'' ?>>Cancelled</option>
                </select>
                <button type="submit" class="btn-primary">Filter</button>
                <a href="orders.php" class="btn-secondary">Reset</a>
            </form>
            <div class="section-card">
                <div class="section-head">
                    <h2>Total: <?= count($orders) ?> transaksi</h2>
                </div>
                <table class="data-table">
                    <thead>
                        <tr><th>#</th><th>Produk</th><th>Buyer</th><th>Seller</th><th>Total</th><th>Pembayaran</th><th>Status</th><th>Order Status</th><th>Tanggal</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                        <tr><td colspan="10" class="empty-row">Tidak ada transaksi.</td></tr>
                        <?php else: foreach ($orders as $i => $o): ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td><?= htmlspecialchars($o['product_name']) ?><br><small style="color:#adb5bd">Qty: <?= $o['quantity'] ?></small></td>
                            <td><?= htmlspecialchars($o['buyer_name']) ?></td>
                            <td><?= htmlspecialchars($o['seller_name']) ?></td>
                            <td><strong><?= formatRupiah($o['total_price']) ?></strong></td>
                            <td><?= ucfirst($o['payment_method']) ?></td>
                            <td><span class="badge <?= $statusColors[$o['status']] ?? 'badge-buyer' ?>"><?= ucfirst($o['status']) ?></span></td>
                            <td><?= $o['order_status'] ? '<span class="badge badge-seller">'.ucfirst($o['order_status']).'</span>' : '-' ?></td>
                            <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                            <td>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Hapus transaksi ini?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $o['id'] ?>">
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
