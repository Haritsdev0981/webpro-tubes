<?php
session_start();
require_once 'includes/config.php';

$db = getDB();
$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$isLoggedIn = isset($_SESSION['user']);
$currentUser = $isLoggedIn ? $_SESSION['user'] : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teloved - Preloved Marketplace Mahasiswa</title>
    <link rel="stylesheet" href="assets/css/buyer.css">
</head>
<body>
    <!-- NAVBAR -->
    <header class="navbar">
        <div class="nav-top">
            <span class="nav-greet">Selamat Datang di Teloved!</span>
            <div class="nav-top-right">
                <?php if ($isLoggedIn): ?>
                    <span>Halo, <?= htmlspecialchars($currentUser['name']) ?></span>
                    <?php if ($currentUser['role'] === 'buyer'): ?>
                    <a href="buyer/dashboard.php">Dashboard</a>
                    <?php elseif ($currentUser['role'] === 'seller'): ?>
                    <a href="seller/dashboard.php">Dashboard</a>
                    <?php endif; ?>
                    <a href="logout.php">Logout</a>
                <?php else: ?>
                    <a href="login.php">SIGN IN</a>
                    <span>|</span>
                    <a href="#">FAQ</a>
                    <span>|</span>
                    <a href="#">BANTUAN</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="nav-main">
            <a href="index.php" class="brand">
                <span>♻️</span>
                <span class="brand-name">Teloved</span>
            </a>
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search...">
                <button onclick="searchProducts()">🔍</button>
            </div>
            <div class="nav-icons">
                <button onclick="goWishlist()" title="Wishlist">♡</button>
                <?php if ($isLoggedIn && $currentUser['role'] === 'buyer'): ?>
                <a href="buyer/orders.php" title="Orders">🛒</a>
                <?php endif; ?>
            </div>
        </div>
        <nav class="nav-bottom">
            <a href="index.php">HOME</a>
            <?php if ($isLoggedIn): ?>
            <a href="<?= $currentUser['role'] ?>/dashboard.php">DASHBOARD</a>
            <?php endif; ?>
            <a href="products.php">PRODUK</a>
            <?php if ($isLoggedIn && $currentUser['role'] === 'buyer'): ?>
            <a href="buyer/orders.php">ORDER PAGE</a>
            <?php endif; ?>
        </nav>
    </header>

    <!-- HERO -->
    <section class="hero">
        <div class="hero-content">
            <h1>Preloved Marketplace </h1>
            <p>Temukan tren pakaian dan aksesori terbaik untuk musim ini</p>
            <a href="products.php" class="btn-hero">Belanja Sekarang</a>
        </div>
    </section>

    <!-- CATEGORIES -->
    <section class="categories-section">
        <h2>Jelajahi Kategori</h2>
        <div class="categories-scroll">
            <?php foreach ($categories as $cat): ?>
            <a href="products.php?category=<?= $cat['slug'] ?>" class="cat-item">
                <div class="cat-icon"><?= $cat['icon'] ?></div>
                <span><?= htmlspecialchars($cat['name']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- TRENDING -->
    <section class="products-section">
        <div class="section-header">
            <h2>Lagi Trending</h2>
            <p>Update fashion terbaru ada di sini</p>
        </div>
        <div id="trending-products" class="products-grid">
            <div class="loading">Memuat produk...</div>
        </div>
    </section>

    <!-- TERBARU -->
    <section class="products-section">
        <div class="section-header">
            <h2>Terbaru Minggu Ini</h2>
            <p>Lihat Produk terbaru yang baru masuk minggu ini</p>
        </div>
        <div id="latest-products" class="products-grid">
            <div class="loading">Memuat produk...</div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-grid">
            <div class="footer-brand">
                <h3>Teloved</h3>
                <p>Toko online bergaya klasik dengan koleksi pilihan terbaik untukmu. Temuka gaya dan keagungan dalam setiap produk teloved</p>
                <div class="social-links">
                    <a href="#">📸</a><a href="#">🐦</a><a href="#">💼</a>
                </div>
                <button onclick="window.scrollTo(0,0)" class="back-top">Back to Top</button>
            </div>
            <div class="footer-links">
                <h4>Site Map</h4>
                <ul>
                    <li><a href="index.php">Homepage</a></li>
                    <li><a href="products.php">Produk</a></li>
                    <li><a href="#">Tentang Kami</a></li>
                    <li><a href="#">Kontak</a></li>
                </ul>
            </div>
            <div class="footer-links">
                <h4>Legal</h4>
                <ul>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Terms of service</a></li>
                    <li><a href="#">License & Credits</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2025 Teloved. Hak Cipta Dilindungi.</p>
        </div>
    </footer>

    <script>
        const IS_LOGGED_IN = <?= $isLoggedIn ? 'true' : 'false' ?>;
        const USER_ROLE = '<?= $currentUser['role'] ?? '' ?>';
        const API_KEY = '<?= $currentUser['api_key'] ?? '' ?>';

        function formatRupiah(num) {
            return 'Rp ' + Number(num).toLocaleString('id-ID');
        }

        function getProductImage(p) {
            const placeholder = 'assets/uploads/placeholder/no-image.jpeg';
            if (!p.images) return placeholder;
            try {
                const images = JSON.parse(p.images);
                if (!Array.isArray(images) || !images.length) return placeholder;
                const firstImage = images[0];
                if (!firstImage || typeof firstImage !== 'string') return placeholder;
                return `assets/uploads/products/${firstImage}`;
            } catch (err) {
                return placeholder;
            }
        }

        function renderProductCard(p, compact = false) {
            const stars = '⭐'.repeat(Math.round(p.avg_rating || 0));
            return `
                <div class="product-card" onclick="viewProduct(${p.id})">
                    <div class="product-thumb">
                        <img
                            src="${getProductImage(p)}"
                            alt="${escHtml(p.name)}"
                            onerror="this.onerror=null;this.src='assets/uploads/placeholder/no-image.jpeg';"
                            style="width:100%;height:100%;object-fit:cover;">
                        ${p.condition_type ? `<span class="condition-badge">${p.condition_type}</span>` : ''}
                    </div>
                    <div class="product-body">
                        <h3 class="product-name">${escHtml(p.name)}</h3>
                        <div class="product-price">${formatRupiah(p.price)}</div>
                        ${stars ? `<div class="product-rating">${stars} (${p.review_count || 0})</div>` : ''}
                        <div class="product-footer">
                            <button class="btn-wishlist" onclick="event.stopPropagation(); addWishlist(${p.id})" title="Tambah ke Wishlist">♡</button>
                            <button class="btn-view" onclick="event.stopPropagation(); viewProduct(${p.id})">View</button>
                        </div>
                    </div>
                </div>
            `;
        }

        function escHtml(str) {
            const d = document.createElement('div');
            d.textContent = str || '';
            return d.innerHTML;
        }

        async function loadProducts(containerId, limit = 6) {
            const el = document.getElementById(containerId);
            try {
                const res = await fetch(`api/products.php?public=1`);
                const data = await res.json();
                const products = (data.data || []).slice(0, limit);
                if (!products.length) {
                    el.innerHTML = '<div class="empty">Belum ada produk tersedia.</div>';
                    return;
                }
                el.innerHTML = products.map(p => renderProductCard(p)).join('');
            } catch (e) {
                el.innerHTML = '<div class="empty">Gagal memuat produk.</div>';
            }
        }

        function viewProduct(id) {
            window.location.href = `product-detail.php?id=${id}`;
        }

        async function addWishlist(productId) {
            if (!IS_LOGGED_IN) {
                alert('Silakan login terlebih dahulu untuk menggunakan fitur wishlist.');
                window.location.href = 'login.php';
                return;
            }
            if (USER_ROLE !== 'buyer') {
                alert('Fitur wishlist hanya untuk buyer.');
                return;
            }
            try {
                const res = await fetch('api/wishlist.php', {
                    method: 'POST',
                    headers: { 'X-API-Key': API_KEY, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ product_id: productId })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('✅ Ditambahkan ke wishlist!');
                } else {
                    showToast('ℹ️ ' + (data.error || 'Gagal'));
                }
            } catch (e) {
                showToast('Terjadi kesalahan.');
            }
        }

        function goWishlist() {
            if (!IS_LOGGED_IN) { window.location.href = 'login.php'; return; }
            window.location.href = 'buyer/wishlist.php';
        }

        function searchProducts() {
            const q = document.getElementById('searchInput').value.trim();
            if (q) window.location.href = `products.php?search=${encodeURIComponent(q)}`;
        }

        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') searchProducts();
        });

        function showToast(msg) {
            const t = document.createElement('div');
            t.className = 'toast';
            t.textContent = msg;
            document.body.appendChild(t);
            setTimeout(() => t.classList.add('show'), 10);
            setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, 2500);
        }

        loadProducts('trending-products', 3);
        loadProducts('latest-products', 6);
    </script>
</body>
</html>