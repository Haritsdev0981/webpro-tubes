<?php
session_start();
require_once '../includes/config.php';
$user = requireAuth('buyer');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Teloved Buyer</title>
    <link rel="stylesheet" href="../assets/css/buyer.css">
</head>
<body>
    <nav class="seller-nav">
        <div class="nav-brand"><span>♻️</span><a href="../index.php">TELOVED</a></div>
        <div class="nav-links">
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="../products.php">Belanja</a>
            <a href="wishlist.php">Wishlist ❤️</a>
            <a href="orders.php">Order Saya</a>
            <a href="profile.php">Profil</a>
        </div>
        <a href="../logout.php" class="nav-logout">Logout</a>
    </nav>
    <div class="seller-wrapper">
        <div class="welcome-card">
            <span class="welcome-badge">Buyer Panel</span>
            <h1>Halo, <span class="highlight"><?= htmlspecialchars($user['name']) ?></span>!</h1>
            <p>Temukan barang preloved berkualitas di Teloved.</p>
            <a href="../products.php" class="btn-primary">Mulai Belanja</a>
        </div>
        <div class="stats-row">
            <div class="stat-box" onclick="location.href='wishlist.php'" style="cursor:pointer">
                <div class="stat-icon">❤️</div>
                <div class="stat-val" id="wishlist-count">...</div>
                <div class="stat-lbl">Wishlist</div>
            </div>
            <div class="stat-box" onclick="location.href='orders.php'" style="cursor:pointer">
                <div class="stat-icon">🛒</div>
                <div class="stat-val" id="order-count">...</div>
                <div class="stat-lbl">Total Order</div>
            </div>
            <div class="stat-box green" onclick="location.href='orders.php?status=completed'" style="cursor:pointer">
                <div class="stat-icon">✅</div>
                <div class="stat-val" id="completed-count">...</div>
                <div class="stat-lbl">Selesai</div>
            </div>
        </div>
    </div>
    <script>
        const API_KEY = '<?= htmlspecialchars($user['api_key']) ?>';
        async function loadStats() {
            try {
                const [wRes, oRes] = await Promise.all([
                    fetch('../api/wishlist.php', { headers: { 'X-API-Key': API_KEY } }),
                    fetch('../api/orders.php', { headers: { 'X-API-Key': API_KEY } })
                ]);
                const wData = await wRes.json();
                const oData = await oRes.json();
                document.getElementById('wishlist-count').textContent = (wData.data || []).length;
                const orders = oData.data || [];
                document.getElementById('order-count').textContent = orders.length;
                document.getElementById('completed-count').textContent = orders.filter(o => o.status === 'completed').length;
            } catch(e) {
                ['wishlist-count','order-count','completed-count'].forEach(id => document.getElementById(id).textContent = '0');
            }
        }
        loadStats();
    </script>
</body>
</html>
