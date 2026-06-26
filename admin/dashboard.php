<?php
session_start();
require_once '../includes/config.php';
$user = requireAuth('admin');

$db = getDB();

$totalUsers    = $db->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetchColumn();
$totalProducts = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalOrders   = $db->query("SELECT COUNT(*) FROM checkouts")->fetchColumn();
$pendingOrders = $db->query("SELECT COUNT(*) FROM checkouts WHERE status='pending'")->fetchColumn();
$recentUsers   = $db->query("SELECT * FROM users WHERE role != 'admin' ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Teloved</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include 'partials/sidebar.php'; ?>
    <div class="admin-content">
        <?php include 'partials/topbar.php'; ?>
        <main class="main-area">
            <div class="page-header">
                <h1>Dashboard</h1>
                <p>Selamat datang, <strong><?= htmlspecialchars($user['name']) ?></strong>! Kelola platform Teloved.</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-info">
                        <span class="stat-num"><?= $totalUsers ?></span>
                        <span class="stat-label">Total User</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📦</div>
                    <div class="stat-info">
                        <span class="stat-num"><?= $totalProducts ?></span>
                        <span class="stat-label">Total Produk</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🛒</div>
                    <div class="stat-info">
                        <span class="stat-num"><?= $totalOrders ?></span>
                        <span class="stat-label">Total Transaksi</span>
                    </div>
                </div>
                <div class="stat-card accent">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-info">
                        <span class="stat-num"><?= $pendingOrders ?></span>
                        <span class="stat-label">Order Pending</span>
                    </div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-head">
                    <h2>User Terbaru</h2>
                    <a href="users.php" class="btn-link">Lihat Semua →</a>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Terdaftar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $i => $u): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($u['name']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><span class="badge badge-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                            <td><span class="badge badge-<?= $u['is_active'] ? 'active' : 'inactive' ?>"><?= $u['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                            <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
