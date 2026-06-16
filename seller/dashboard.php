<?php
session_start();
require_once '../includes/config.php';
$user = requireAuth('seller');
$db = getDB();

$sellerId = $user['id'];
$totalProducts = $db->prepare("SELECT COUNT(*) FROM products WHERE seller_id = ?");
$totalProducts->execute([$sellerId]); $totalProducts = $totalProducts->fetchColumn();

$totalOrders = $db->prepare("SELECT COUNT(*) FROM checkouts WHERE seller_id = ?");
$totalOrders->execute([$sellerId]); $totalOrders = $totalOrders->fetchColumn();

$pendingOrders = $db->prepare("SELECT COUNT(*) FROM checkouts WHERE seller_id = ? AND status = 'pending'");
$pendingOrders->execute([$sellerId]); $pendingOrders = $pendingOrders->fetchColumn();

$revenue = $db->prepare("SELECT SUM(total_price) FROM checkouts WHERE seller_id = ? AND status = 'completed'");
$revenue->execute([$sellerId]); $revenue = $revenue->fetchColumn() ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard - Teloved</title>
    <link rel="stylesheet" href="../assets/css/seller.css">
</head>
<body>
    <nav class="seller-nav">
        <div class="nav-brand">
            <span>♻️</span>
            <a href="dashboard.php">TELOVED</a>
        </div>
        <div class="nav-links">
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="products.php">Manage Products</a>
            <a href="orders.php">Orders</a>
            <a href="profile.php">Profil</a>
        </div>
        <a href="../logout.php" class="nav-logout">Logout</a>
    </nav>

    <div class="seller-wrapper">
        <div class="welcome-card">
            <span class="welcome-badge">Seller Panel</span>
            <h1>Welcome Seller, <span class="highlight"><?= htmlspecialchars($user['name']) ?></span></h1>
            <p>Manage your products easily and grow your preloved marketplace experience with Teloved.</p>
            <a href="products.php" class="btn-primary">Manage Products</a>
        </div>

        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-icon">📦</div>
                <div class="stat-val"><?= $totalProducts ?></div>
                <div class="stat-lbl">Total Produk</div>
            </div>
            <div class="stat-box">
                <div class="stat-icon">🛒</div>
                <div class="stat-val"><?= $totalOrders ?></div>
                <div class="stat-lbl">Total Order</div>
            </div>
            <div class="stat-box accent">
                <div class="stat-icon">⏳</div>
                <div class="stat-val"><?= $pendingOrders ?></div>
                <div class="stat-lbl">Order Masuk</div>
            </div>
            <div class="stat-box green">
                <div class="stat-icon">💰</div>
                <div class="stat-val"><?= formatRupiah($revenue) ?></div>
                <div class="stat-lbl">Total Pendapatan</div>
            </div>
        </div>
    </div>
</body>
</html>
