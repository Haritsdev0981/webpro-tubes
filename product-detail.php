<?php
session_start();
require_once 'includes/config.php';

$db = getDB();
$productId = (int)($_GET['id'] ?? 0);
if (!$productId) { header('Location: products.php'); exit; }

$stmt = $db->prepare("
    SELECT p.*, u.name as seller_name, u.phone as seller_phone, c.name as cat_name, c.slug as cat_slug,
           COALESCE(AVG(r.rating),0) as avg_rating, COUNT(r.id) as review_count,
           pr.whatsapp, pr.university
    FROM products p
    JOIN users u ON u.id = p.seller_id
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN reviews r ON r.product_id = p.id
    LEFT JOIN profiles pr ON pr.user_id = p.seller_id
    WHERE p.id = ? AND p.status = 'active'
    GROUP BY p.id
");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: products.php');
    exit;
}

$productImage = 'assets/uploads/placeholder/no-image.jpeg';
if (!empty($product['images'])) {
    $productImages = json_decode($product['images'], true);
    if (is_array($productImages) && !empty($productImages[0]) && is_string($productImages[0])) {
        $productImage = 'assets/uploads/products/' . $productImages[0];
    }
}

$reviews = $db->prepare("SELECT r.*, u.name as buyer_name FROM reviews r JOIN users u ON u.id = r.buyer_id WHERE r.product_id = ? ORDER BY r.created_at DESC LIMIT 10");
$reviews->execute([$productId]);
$reviews = $reviews->fetchAll();

$isLoggedIn  = isset($_SESSION['user']);
$currentUser = $isLoggedIn ? $_SESSION['user'] : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> - Teloved</title>
    <link rel="stylesheet" href="assets/css/buyer.css">
    <style>
        .detail-page { max-width: 1000px; margin: 0 auto; padding: 28px 24px; }
        .breadcrumb { font-size: 13px; color: #adb5bd; margin-bottom: 20px; }
        .breadcrumb a { color: #1B4332; text-decoration: none; }
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-bottom: 40px; }

        .product-image-area {
            background: #f1f3f5;
            border-radius: 12px;
            height: 380px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 80px;
        }

        .detail-info { display: flex; flex-direction: column; gap: 14px; }
        .detail-cat { font-size: 12px; color: #adb5bd; font-weight: 600; text-transform: uppercase; }
        .detail-name { font-size: 26px; font-weight: 800; color: #212529; line-height: 1.3; }
        .detail-price { font-size: 28px; font-weight: 800; color: #1B4332; }
        .detail-rating { display: flex; align-items: center; gap: 8px; font-size: 14px; color: #495057; }

        .detail-meta { display: flex; flex-direction: column; gap: 8px; }
        .meta-row { display: flex; gap: 8px; font-size: 13px; align-items: center; }
        .meta-label { color: #adb5bd; min-width: 80px; }
        .meta-value { color: #212529; font-weight: 500; }
        .condition-tag { padding: 3px 10px; background: #EAFAF1; color: #1E8449; border-radius: 12px; font-size: 12px; font-weight: 600; }

        .detail-desc { font-size: 14px; color: #495057; line-height: 1.7; background: #f8f9fa; padding: 14px; border-radius: 8px; }

        .seller-card { background: #fff; border: 1px solid #dee2e6; border-radius: 10px; padding: 16px; }
        .seller-card h4 { font-size: 13px; font-weight: 700; color: #adb5bd; text-transform: uppercase; margin-bottom: 10px; }
        .seller-name { font-size: 15px; font-weight: 700; color: #212529; }
        .seller-uni { font-size: 12px; color: #adb5bd; margin-top: 2px; }

        .action-btns { display: flex; gap: 10px; }
        .btn-wishlist-full {
            flex: 1; padding: 13px;
            background: #f8f9fa; border: 2px solid #dee2e6;
            border-radius: 8px; font-size: 14px; font-weight: 600;
            cursor: pointer; transition: all 0.2s; color: #495057;
        }
        .btn-wishlist-full:hover { background: #FDEDEC; border-color: #DC3545; color: #DC3545; }
        .btn-checkout-full {
            flex: 2; padding: 13px;
            background: #1B4332; border: none;
            border-radius: 8px; font-size: 14px; font-weight: 700;
            cursor: pointer; color: #fff; transition: background 0.2s;
        }
        .btn-checkout-full:hover { background: #2D6A4F; }

        /* Reviews */
        .reviews-section h2 { font-size: 20px; font-weight: 700; margin-bottom: 20px; }
        .review-card { background: #fff; border: 1px solid #dee2e6; border-radius: 10px; padding: 16px; margin-bottom: 12px; }
        .review-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
        .reviewer-name { font-size: 14px; font-weight: 600; color: #212529; }
        .review-date { font-size: 12px; color: #adb5bd; }
        .review-stars { font-size: 16px; margin-bottom: 6px; }
        .review-comment { font-size: 13px; color: #495057; line-height: 1.6; }

        @media (max-width: 768px) {
            .detail-grid { grid-template-columns: 1fr; }
            .product-image-area { height: 250px; }
        }
    </style>
</head>
<body>
    <header class="navbar">
        <div class="nav-top">
            <span class="nav-greet">Selamat Datang di Teloved!</span>
            <div class="nav-top-right">
                <?php if ($isLoggedIn): ?>
                    <a href="<?= $currentUser['role'] ?>/dashboard.php">Dashboard</a>
                    <a href="logout.php">Logout</a>
                <?php else: ?>
                    <a href="login.php">SIGN IN</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="nav-main">
            <a href="index.php" class="brand"><span>♻️</span><span class="brand-name">Teloved</span></a>
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Cari produk...">
                <button onclick="window.location.href='products.php?search='+document.getElementById('searchInput').value">🔍</button>
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

    <div class="detail-page">
        <div class="breadcrumb">
            <a href="index.php">Home</a> › <a href="products.php">Produk</a>
            <?php if ($product['cat_slug']): ?> › <a href="products.php?category=<?= $product['cat_slug'] ?>"><?= htmlspecialchars($product['cat_name']) ?></a><?php endif; ?>
            › <?= htmlspecialchars($product['name']) ?>
        </div>

        <div class="detail-grid">
            <!-- Image -->
            <div class="product-image-area">
                <img
                    src="<?= htmlspecialchars($productImage) ?>"
                    alt="<?= htmlspecialchars($product['name']) ?>"
                    onerror="this.onerror=null;this.src='assets/uploads/placeholder/no-image.jpeg';"
                    style="
                        width:100%;
                        height:100%;
                        object-fit:cover;
                        border-radius:12px;
                    ">
            </div>

            <!-- Info -->
            <div class="detail-info">
                <?php if ($product['cat_name']): ?>
                <div class="detail-cat"><?= htmlspecialchars($product['cat_name']) ?></div>
                <?php endif; ?>
                <h1 class="detail-name"><?= htmlspecialchars($product['name']) ?></h1>
                <div class="detail-price">Rp <?= number_format($product['price'], 0, ',', '.') ?></div>

                <div class="detail-rating">
                    <?php
                    $stars = round($product['avg_rating']);
                    echo str_repeat('⭐', $stars) . str_repeat('☆', 5 - $stars);
                    ?>
                    <span>(<?= $product['review_count'] ?> ulasan)</span>
                </div>

                <div class="detail-meta">
                    <div class="meta-row">
                        <span class="meta-label">Kondisi</span>
                        <span class="condition-tag"><?= htmlspecialchars($product['condition_type']) ?></span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Stok</span>
                        <span class="meta-value"><?= $product['stock'] ?> tersedia</span>
                    </div>
                </div>

                <div class="detail-desc"><?= nl2br(htmlspecialchars($product['description'])) ?></div>

                <div class="seller-card">
                    <h4>Penjual</h4>
                    <div class="seller-name">🏪 <?= htmlspecialchars($product['seller_name']) ?></div>
                    <?php if ($product['university']): ?>
                    <div class="seller-uni">🎓 <?= htmlspecialchars($product['university']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="action-btns">
                    <button class="btn-wishlist-full" onclick="addWishlist()">❤️ Wishlist</button>
                    <button class="btn-checkout-full" onclick="openCheckout()">🛒 Checkout</button>
                </div>
            </div>
        </div>

        <!-- Reviews -->
        <div class="reviews-section">
            <h2>Ulasan Pembeli (<?= count($reviews) ?>)</h2>
            <?php if (empty($reviews)): ?>
                <div style="text-align:center;padding:30px;color:#adb5bd;background:#f8f9fa;border-radius:10px">Belum ada ulasan untuk produk ini.</div>
            <?php else: ?>
                <?php foreach ($reviews as $r): ?>
                <div class="review-card">
                    <div class="review-header">
                        <span class="reviewer-name"><?= htmlspecialchars($r['buyer_name']) ?></span>
                        <span class="review-date"><?= date('d M Y', strtotime($r['created_at'])) ?></span>
                    </div>
                    <div class="review-stars"><?= str_repeat('⭐', $r['rating']) ?></div>
                    <div class="review-comment"><?= nl2br(htmlspecialchars($r['comment'])) ?></div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Checkout Modal -->
    <div class="modal-overlay" id="modalCheckout" style="display:none">
        <div class="modal-box">
            <div class="modal-head">
                <h3>Checkout: <?= htmlspecialchars($product['name']) ?></h3>
                <button onclick="closeCheckout()">✕</button>
            </div>
            <div style="padding:12px;background:#f8f9fa;border-radius:8px;margin-bottom:16px">
                <strong><?= htmlspecialchars($product['name']) ?></strong><br>
                <span style="color:#1B4332;font-weight:700">Rp <?= number_format($product['price'], 0, ',', '.') ?></span>
            </div>
            <div class="form-group">
                <label>Jumlah (max <?= $product['stock'] ?>)</label>
                <input type="number" id="co_qty" value="1" min="1" max="<?= $product['stock'] ?>">
            </div>
            <div class="form-group">
                <label>Alamat Pengiriman</label>
                <textarea id="co_address" rows="3" placeholder="Alamat lengkap..."></textarea>
            </div>
            <div class="form-group">
                <label>Metode Pembayaran</label>
                <select id="co_payment">
                    <option value="transfer">Transfer Bank</option>
                    <option value="ewallet">E-Wallet</option>
                    <option value="cod">COD</option>
                </select>
            </div>
            <div class="form-group">
                <label>Catatan (opsional)</label>
                <textarea id="co_note" rows="2" placeholder="Catatan untuk seller..."></textarea>
            </div>
            <div class="modal-actions">
                <button class="btn-secondary" onclick="closeCheckout()">Batal</button>
                <button class="btn-primary" onclick="submitCheckout()">Checkout</button>
            </div>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <script>
        const IS_LOGGED_IN = <?= $isLoggedIn ? 'true' : 'false' ?>;
        const USER_ROLE    = '<?= $currentUser['role'] ?? '' ?>';
        const API_KEY      = '<?= $currentUser['api_key'] ?? '' ?>';
        const PRODUCT_ID   = <?= $productId ?>;

        function showToast(msg) {
            const t = document.getElementById('toast');
            t.textContent = msg;
            t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 2500);
        }

        async function addWishlist() {
            if (!IS_LOGGED_IN) { showToast('Login dulu!'); setTimeout(() => window.location.href='login.php',1500); return; }
            if (USER_ROLE !== 'buyer') { showToast('Hanya buyer yang bisa wishlist.'); return; }
            const res = await fetch('api/wishlist.php', {
                method:'POST', headers:{'X-API-Key':API_KEY,'Content-Type':'application/json'},
                body: JSON.stringify({product_id: PRODUCT_ID})
            });
            const data = await res.json();
            showToast(data.success ? '❤️ Ditambahkan ke wishlist!' : '⚠️ '+(data.error||'Gagal'));
        }

        function openCheckout() {
            if (!IS_LOGGED_IN) { showToast('Login dulu!'); setTimeout(() => window.location.href='login.php',1500); return; }
            if (USER_ROLE !== 'buyer') { showToast('Hanya buyer yang bisa checkout.'); return; }
            document.getElementById('modalCheckout').style.display = 'flex';
        }
        function closeCheckout() { document.getElementById('modalCheckout').style.display = 'none'; }

        async function submitCheckout() {
            const qty     = parseInt(document.getElementById('co_qty').value);
            const address = document.getElementById('co_address').value.trim();
            const payment = document.getElementById('co_payment').value;
            const note    = document.getElementById('co_note').value.trim();
            if (!address) { alert('Alamat wajib diisi.'); return; }

            const res = await fetch('api/orders.php', {
                method:'POST', headers:{'X-API-Key':API_KEY,'Content-Type':'application/json'},
                body: JSON.stringify({product_id:PRODUCT_ID, quantity:qty, shipping_address:address, payment_method:payment, note})
            });
            const data = await res.json();
            if (data.success) {
                closeCheckout();
                showToast('✅ Checkout berhasil!');
                setTimeout(() => window.location.href='buyer/orders.php', 2000);
            } else {
                showToast('❌ '+(data.error||'Gagal checkout'));
            }
        }
    </script>
</body>
</html>