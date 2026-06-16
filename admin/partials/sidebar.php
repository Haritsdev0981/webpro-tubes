<nav class="admin-sidebar">
    <div class="sidebar-brand">
        <span>♻️</span>
        <span class="brand-text">TELOVED</span>
        <span class="brand-sub">Admin Panel</span>
    </div>
    <ul class="sidebar-nav">
        <li class="nav-label">MENU UTAMA</li>
        <li><a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
            <span>📊</span> Dashboard
        </a></li>
        <li><a href="users.php" class="<?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>">
            <span>👥</span> Manajemen User
        </a></li>
        <li><a href="permissions.php" class="<?= basename($_SERVER['PHP_SELF']) === 'permissions.php' ? 'active' : '' ?>">
            <span>🔐</span> Feature Permission
        </a></li>
        <li class="nav-label">KONTEN</li>
        <li><a href="products.php" class="<?= basename($_SERVER['PHP_SELF']) === 'products.php' ? 'active' : '' ?>">
            <span>📦</span> Kelola Produk
        </a></li>
        <li><a href="orders.php" class="<?= basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'active' : '' ?>">
            <span>🛒</span> Semua Transaksi
        </a></li>
        <li class="nav-label">AKUN</li>
        <li><a href="../logout.php"><span>🚪</span> Logout</a></li>
    </ul>
</nav>
