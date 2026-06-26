<?php
session_start();
require_once 'includes/config.php';

$db = getDB();
$categories  = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$isLoggedIn  = isset($_SESSION['user']);
$currentUser = $isLoggedIn ? $_SESSION['user'] : null;

$searchQ   = htmlspecialchars($_GET['search'] ?? '');
$catSlug   = htmlspecialchars($_GET['category'] ?? '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produk - Teloved</title>
    <link rel="stylesheet" href="assets/css/buyer.css">
    <style>
        .products-page { max-width: 1200px; margin: 0 auto; padding: 28px 24px; }
        .products-layout { display: grid; grid-template-columns: 220px 1fr; gap: 24px; align-items: start; }

        /* Filter Sidebar */
        .filter-sidebar {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            position: sticky;
            top: 80px;
        }
        .filter-sidebar h3 { font-size: 15px; font-weight: 700; color: #212529; margin-bottom: 16px; }
        .filter-group { margin-bottom: 18px; }
        .filter-group label { font-size: 12px; font-weight: 700; color: #495057; display: block; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .filter-group input[type="number"] {
            width: 100%; padding: 7px 10px;
            border: 1.5px solid #dee2e6; border-radius: 6px;
            font-size: 13px; outline: none;
        }
        .filter-group input:focus { border-color: #1B4332; }
        .price-inputs { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
        .price-label { font-size: 10px; color: #adb5bd; margin-top: 2px; }

        .filter-group .radio-group { display: flex; flex-direction: column; gap: 6px; }
        .radio-item { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #495057; cursor: pointer; }
        .radio-item input { cursor: pointer; accent-color: #1B4332; }

        .btn-filter {
            width: 100%; padding: 9px;
            background: #FFC107; color: #212529;
            border: none; border-radius: 7px;
            font-weight: 700; font-size: 13px;
            cursor: pointer; transition: background 0.2s;
        }
        .btn-filter:hover { background: #E0A800; }
        .btn-reset {
            width: 100%; padding: 8px;
            background: transparent; color: #adb5bd;
            border: 1px solid #dee2e6; border-radius: 7px;
            font-size: 12px; cursor: pointer; margin-top: 6px;
        }

        /* Products area */
        .products-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 8px; }
        .products-header h2 { font-size: 18px; font-weight: 700; color: #212529; }
        .products-count { font-size: 13px; color: #adb5bd; }

        .products-grid-main { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 16px; }

        /* Product Card */
        .product-card-main {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .product-card-main:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.12); }

        .product-card-img {
            height: 170px;
            background: #f1f3f5;
            display: flex; align-items: center; justify-content: center;
            position: relative; overflow: hidden;
        }
        .product-card-img .img-placeholder { font-size: 50px; opacity: 0.35; }

        .product-card-body { padding: 12px; }
        .product-card-name { font-size: 14px; font-weight: 600; color: #212529; margin-bottom: 6px; line-height: 1.3; }
        .product-card-price { font-size: 15px; font-weight: 700; color: #1B4332; margin-bottom: 10px; }

        .product-card-actions { display: flex; gap: 6px; align-items: center; }
        .btn-card-wishlist {
            width: 34px; height: 32px;
            background: #f8f9fa; border: 1px solid #dee2e6;
            border-radius: 6px; cursor: pointer; font-size: 16px;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s;
        }
        .btn-card-wishlist:hover { background: #FDEDEC; border-color: #DC3545; }
        .btn-card-view {
            flex: 1; padding: 7px;
            background: #FFC107; border: none;
            border-radius: 6px; cursor: pointer;
            font-size: 12px; font-weight: 700;
            color: #212529; transition: background 0.2s;
        }
        .btn-card-view:hover { background: #E0A800; }

        /* Active category pill */
        .cat-pills { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 14px; }
        .cat-pill {
            padding: 5px 14px; border-radius: 20px;
            font-size: 12px; font-weight: 600;
            background: #f1f3f5; color: #495057;
            border: 1.5px solid #dee2e6;
            cursor: pointer; text-decoration: none;
            transition: all 0.2s;
        }
        .cat-pill.active, .cat-pill:hover { background: #1B4332; color: #fff; border-color: #1B4332; }

        /* Toast */
        .toast {
            position: fixed; bottom: 24px; right: 24px;
            background: #212529; color: #fff;
            padding: 12px 20px; border-radius: 8px;
            font-size: 14px; z-index: 500;
            opacity: 0; transform: translateY(8px);
            transition: all 0.3s; pointer-events: none;
        }
        .toast.show { opacity: 1; transform: translateY(0); }

        @media (max-width: 768px) {
            .products-layout { grid-template-columns: 1fr; }
            .filter-sidebar { position: static; }
        }
    </style>
</head>
<body>
    <!-- NAVBAR -->
    <header class="navbar">
        <div class="nav-top">
            <span class="nav-greet">Selamat Datang di Teloved!</span>
            <div class="nav-top-right">
                <?php if ($isLoggedIn): ?>
                    <span>Halo, <?= htmlspecialchars($currentUser['name']) ?></span>
                    <a href="<?= $currentUser['role'] ?>/dashboard.php">Dashboard</a>
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
                <input type="text" id="searchInput" placeholder="Cari produk..." value="<?= $searchQ ?>">
                <button onclick="applySearch()">🔍</button>
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
            <a href="products.php" class="active">PRODUK</a>
            <?php if ($isLoggedIn && $currentUser['role'] === 'buyer'): ?>
            <a href="buyer/orders.php">ORDER PAGE</a>
            <?php endif; ?>
        </nav>
    </header>

    <div class="products-page">
        <div class="products-layout">

            <!-- FILTER SIDEBAR -->
            <aside class="filter-sidebar">
                <h3>Filter Produk</h3>

                <div class="filter-group">
                    <label>Harga</label>
                    <div class="price-inputs">
                        <div>
                            <input type="number" id="minPrice" placeholder="0" min="0">
                            <div class="price-label">Rp 0</div>
                        </div>
                        <div>
                            <input type="number" id="maxPrice" placeholder="max" min="0">
                            <div class="price-label">Rp 999.999</div>
                        </div>
                    </div>
                </div>

                <div class="filter-group">
                    <label>Kondisi</label>

                    <select id="conditionSelect">
                        <option value="">Semua</option>
                        <option value="Baru">Baru</option>
                        <option value="Seperti Baru">Seperti Baru</option>
                        <option value="Bekas - Baik">Bekas - Baik</option>
                        <option value="Bekas - Cukup">Bekas - Cukup</option>
                    </select>
                </div>

                <button class="btn-filter" onclick="applyFilter()">Terapkan Filter</button>
                <button class="btn-reset" onclick="resetFilter()">Reset</button>
            </aside>

            <!-- PRODUCTS MAIN -->
            <div>
                <!-- Category Pills -->
                <div class="cat-pills">
                    <a href="products.php" class="cat-pill <?= !$catSlug ? 'active' : '' ?>">Semua</a>
                    <?php foreach ($categories as $cat): ?>
                    <a href="products.php?category=<?= $cat['slug'] ?>" class="cat-pill <?= $catSlug === $cat['slug'] ? 'active' : '' ?>">
                        <?= $cat['icon'] ?> <?= htmlspecialchars($cat['name']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>

                <div class="products-header">
                    <h2 id="products-title">
                        <?= $searchQ ? 'Hasil pencarian: "' . $searchQ . '"' : ($catSlug ? 'Kategori: ' . ucfirst($catSlug) : 'Semua Produk') ?>
                    </h2>
                    <span class="products-count" id="products-count">Memuat...</span>
                </div>

                <div id="products-grid" class="products-grid-main">
                    <div style="grid-column:1/-1;text-align:center;padding:40px;color:#adb5bd">Memuat produk...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-grid">
            <div class="footer-brand">
                <h3>Teloved</h3>
                <p>Toko online bergaya klasik dengan koleksi pilihan terbaik untukmu.</p>
                <div class="social-links"><a href="#">📸</a><a href="#">🐦</a><a href="#">💼</a></div>
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
        <div class="footer-bottom"><p>© 2025 Teloved. Hak Cipta Dilindungi.</p></div>
    </footer>

    <div class="toast" id="toast"></div>

    <script>
        const IS_LOGGED_IN  = <?= $isLoggedIn ? 'true' : 'false' ?>;
        const USER_ROLE     = '<?= $currentUser['role'] ?? '' ?>';
        const API_KEY       = '<?= $currentUser['api_key'] ?? '' ?>';
        const INIT_SEARCH   = '<?= addslashes($searchQ) ?>';
        const INIT_CATEGORY = '<?= addslashes($catSlug) ?>';

        let allProducts = [];

        function escHtml(str) {
            const d = document.createElement('div');
            d.textContent = str || '';
            return d.innerHTML;
        }

        function showToast(msg) {
            const t = document.getElementById('toast');
            t.textContent = msg;
            t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 2500);
        }

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

        async function loadProducts() {
            const params = new URLSearchParams({ public: 1 });
            if (INIT_SEARCH)   params.set('search', INIT_SEARCH);
            if (INIT_CATEGORY) params.set('category', INIT_CATEGORY);

            try {
                const res = await fetch('api/products.php?' + params.toString());
                const data = await res.json();
                allProducts = data.data || [];
                renderProducts(allProducts);
            } catch(e) {
                document.getElementById('products-grid').innerHTML =
                    '<div style="grid-column:1/-1;text-align:center;padding:40px;color:#adb5bd">Gagal memuat produk.</div>';
            }
        }

        function renderProducts(products) {
            const grid = document.getElementById('products-grid');
            const count = document.getElementById('products-count');
            count.textContent = products.length + ' produk ditemukan';

            if (!products.length) {
                grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:60px;color:#adb5bd"><div style="font-size:48px;margin-bottom:12px">🔍</div><p>Tidak ada produk ditemukan.</p></div>';
                return;
            }

            grid.innerHTML = products.map(p => `
                <div class="product-card-main">
                    <div class="product-card-img" onclick="window.location.href='product-detail.php?id=${p.id}'">
                        <img
                            src="${getProductImage(p)}"
                            alt="${escHtml(p.name)}"
                            onerror="this.onerror=null;this.src='assets/uploads/placeholder/no-image.jpeg';"
                            style="width:100%;height:100%;object-fit:cover;">
                    </div>
                    <div class="product-card-body">
                        <div class="product-card-name">${escHtml(p.name)}</div>
                        <div class="product-card-price">${formatRupiah(p.price)}</div>
                        <div class="product-card-actions">
                            <button class="btn-card-wishlist" onclick="addWishlist(${p.id})" title="Tambah Wishlist">♡</button>
                            <button class="btn-card-view" onclick="window.location.href='product-detail.php?id=${p.id}'">View</button>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function applyFilter() {
            const min  = parseFloat(document.getElementById('minPrice').value) || 0;
            const max  = parseFloat(document.getElementById('maxPrice').value) || Infinity;
            const cond = document.querySelector('input[name="condition"]:checked')?.value || '';

            const filtered = allProducts.filter(p => {
                const price = parseFloat(p.price);
                const condOk = !cond || p.condition_type === cond;
                return price >= min && price <= max && condOk;
            });
            renderProducts(filtered);
        }

        function resetFilter() {
            document.getElementById('minPrice').value = '';
            document.getElementById('maxPrice').value = '';
            document.querySelector('input[name="condition"]').checked = true;
            renderProducts(allProducts);
        }

        function applySearch() {
            const q = document.getElementById('searchInput').value.trim();
            const url = q ? `products.php?search=${encodeURIComponent(q)}` : 'products.php';
            window.location.href = url;
        }

        document.getElementById('searchInput').addEventListener('keypress', e => {
            if (e.key === 'Enter') applySearch();
        });

        async function addWishlist(productId) {
            if (!IS_LOGGED_IN) {
                showToast('Login dulu untuk menambah wishlist!');
                setTimeout(() => window.location.href = 'login.php', 1500);
                return;
            }
            if (USER_ROLE !== 'buyer') { showToast('Fitur wishlist hanya untuk buyer.'); return; }
            try {
                const res = await fetch('api/wishlist.php', {
                    method: 'POST',
                    headers: { 'X-API-Key': API_KEY, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ product_id: productId })
                });
                const data = await res.json();
                showToast(data.success ? '❤️ Ditambahkan ke wishlist!' : '⚠️ ' + (data.error || 'Gagal'));
            } catch(e) { showToast('Terjadi kesalahan.'); }
        }

        function goWishlist() {
            if (!IS_LOGGED_IN) { window.location.href = 'login.php'; return; }
            if (USER_ROLE === 'buyer') window.location.href = 'buyer/wishlist.php';
        }

        loadProducts();
    </script>
</body>
</html>