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
    <title>Wishlist - Teloved</title>
    <link rel="stylesheet" href="../assets/css/buyer.css">
</head>
<body>
    <nav class="seller-nav">
        <div class="nav-brand"><span>♻️</span><a href="../index.php">TELOVED</a></div>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="../products.php">Belanja</a>
            <a href="wishlist.php" class="active">Wishlist ❤️</a>
            <a href="orders.php">Order Saya</a>
        </div>
        <a href="../logout.php" class="nav-logout">Logout</a>
    </nav>

    <div class="buyer-page">
        <div class="page-hd">
            <div><h1>Wishlist Saya ❤️</h1><p>Produk yang kamu simpan</p></div>
        </div>
        <div id="flash-area"></div>
        <div id="wishlist-summary" class="stats-row" style="display:none; margin-bottom:20px;">
            <div class="stat-box">
                <div class="stat-icon">🛍️</div>
                <span class="stat-val" id="wishlist-count">0</span>
                <span class="stat-lbl">Jumlah Item</span>
            </div>
            <div class="stat-box accent">
                <div class="stat-icon">💰</div>
                <span class="stat-val" id="wishlist-total">Rp 0</span>
                <span class="stat-lbl">Total Harga</span>
            </div>
        </div>
        <div id="wishlist-container" class="products-grid">
            <div class="loading">Memuat wishlist...</div>
        </div>
    </div>

    <div class="modal-overlay" id="modalCheckout" style="display:none">
        <div class="modal-box">
            <div class="modal-head">
                <h3>Checkout Produk</h3>
                <button onclick="closeCheckout()">✕</button>
            </div>
            <div id="checkout-product-info" style="padding:12px;background:#f8f9fa;border-radius:8px;margin-bottom:16px;font-size:13px;"></div>
            <div class="form-group">
                <label>Jumlah</label>
                <input type="number" id="co_quantity" value="1" min="1">
            </div>
            <div class="form-group">
                <label>Alamat Pengiriman</label>
                <textarea id="co_address" rows="3" placeholder="Alamat lengkap pengiriman..."></textarea>
            </div>
            <div class="form-group">
                <label>Metode Pembayaran</label>
                <select id="co_payment">
                    <option value="transfer">Transfer Bank</option>
                    <option value="ewallet">E-Wallet (GoPay/OVO/Dana)</option>
                    <option value="cod">COD (Bayar di Tempat)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Catatan (opsional)</label>
                <textarea id="co_note" rows="2" placeholder="Catatan untuk seller..."></textarea>
            </div>
            <div class="modal-actions">
                <button class="btn-secondary" onclick="closeCheckout()">Batal</button>
                <button class="btn-primary" onclick="submitCheckout()">Checkout Sekarang</button>
            </div>
        </div>
    </div>

    <script>
        const API_KEY = '<?= htmlspecialchars($user['api_key']) ?>';
        const UPLOAD_URL = '<?= UPLOAD_URL ?>';
        const PLACEHOLDER_IMAGE = UPLOAD_URL + 'placeholder/no-image.jpeg';
        let currentProductId = null;

        function showFlash(type, msg) {
            const div = document.getElementById('flash-area');
            div.innerHTML = `<div class="flash-msg flash-${type}">${msg} <button onclick="this.parentElement.remove()">✕</button></div>`;
            setTimeout(() => div.innerHTML = '', 4000);
        }

        function escHtml(str) { const d = document.createElement('div'); d.textContent = str || ''; return d.innerHTML; }

        function formatRupiah(num) {
            return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
        }

        function getWishlistImage(images) {
            if (!images) return null;
            try {
                const parsed = typeof images === 'string' ? JSON.parse(images) : images;
                if (Array.isArray(parsed) && parsed.length) {
                    return parsed[0];
                }
            } catch (e) {
                return null;
            }
            return null;
        }

        async function loadWishlist() {
            const container = document.getElementById('wishlist-container');
            try {
                const res = await fetch('../api/wishlist.php', { headers: { 'X-API-Key': API_KEY } });
                const data = await res.json();
                if (data.error) { container.innerHTML = `<div class="empty-state">${data.error}</div>`; return; }
                const items = data.data || [];
                if (!items.length) {
                    container.innerHTML = '<div class="empty-state">Wishlist kosong. <a href="../products.php">Mulai belanja</a></div>';
                    return;
                }
                const total = items.reduce((sum, item) => sum + Number(item.price || 0), 0);
                document.getElementById('wishlist-count').textContent = items.length;
                document.getElementById('wishlist-total').textContent = formatRupiah(total);
                document.getElementById('wishlist-summary').style.display = 'grid';

                container.innerHTML = items.map(item => {
                    const imageFile = getWishlistImage(item.images);
                    const imageSrc = imageFile ? UPLOAD_URL + 'products/' + imageFile : PLACEHOLDER_IMAGE;
                    return `
                        <div class="product-card" style="cursor:default">
                            <div class="product-thumb">
                                <img
                                    src="${imageSrc}"
                                    alt="${escHtml(item.name)}"
                                    onerror="this.onerror=null;this.src='${PLACEHOLDER_IMAGE}';"
                                    style="width:100%;height:100%;object-fit:cover;" />
                                <span class="condition-badge">${escHtml(item.condition_type)}</span>
                            </div>
                            <div class="product-body">
                                <h3 class="product-name">${escHtml(item.name)}</h3>
                                <div class="product-price">${formatRupiah(item.price)}</div>
                                <div style="font-size:12px;color:#adb5bd;margin-bottom:8px">Seller: ${escHtml(item.seller_name)}</div>
                                <div class="product-footer" style="flex-direction:column;gap:6px">
                                    <button class="btn-view" style="width:100%" onclick="openCheckout(${item.product_id}, '${escHtml(item.name)}', ${item.price})">
                                        🛒 Checkout
                                    </button>
                                    <button class="btn-wishlist" style="width:100%;border-radius:6px" onclick="removeWishlist(${item.id})">
                                        🗑 Hapus dari Wishlist
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
            } catch(e) {
                container.innerHTML = '<div class="empty-state">Gagal memuat wishlist.</div>';
            }
        }

        async function removeWishlist(id) {
            if (!confirm('Hapus dari wishlist?')) return;
            try {
                const res = await fetch(`../api/wishlist.php?id=${id}`, {
                    method: 'DELETE', headers: { 'X-API-Key': API_KEY }
                });
                const data = await res.json();
                if (data.success) { showFlash('success', 'Dihapus dari wishlist.'); loadWishlist(); }
                else showFlash('error', data.error || 'Gagal.');
            } catch(e) { showFlash('error', 'Terjadi kesalahan.'); }
        }

        function openCheckout(productId, name, price) {
            currentProductId = productId;
            document.getElementById('checkout-product-info').innerHTML = `
                <strong>${escHtml(name)}</strong><br>
                <span style="color:#1B4332;font-weight:700">Rp ${Number(price).toLocaleString('id-ID')}</span>
            `;
            document.getElementById('co_quantity').value = 1;
            document.getElementById('co_address').value = '';
            document.getElementById('co_note').value = '';
            document.getElementById('modalCheckout').style.display = 'flex';
        }

        function closeCheckout() { document.getElementById('modalCheckout').style.display = 'none'; }

        async function submitCheckout() {
            const qty = parseInt(document.getElementById('co_quantity').value);
            const address = document.getElementById('co_address').value.trim();
            const payment = document.getElementById('co_payment').value;
            const note = document.getElementById('co_note').value.trim();

            if (!address) { alert('Alamat pengiriman wajib diisi.'); return; }

            try {
                const res = await fetch('../api/orders.php', {
                    method: 'POST',
                    headers: { 'X-API-Key': API_KEY, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ product_id: currentProductId, quantity: qty, shipping_address: address, payment_method: payment, note })
                });
                const data = await res.json();
                if (data.success) {
                    closeCheckout();
                    showFlash('success', `✅ Checkout berhasil! Total: Rp ${Number(data.total_price).toLocaleString('id-ID')}`);
                    setTimeout(() => window.location.href = 'orders.php', 2000);
                } else {
                    showFlash('error', data.error || 'Gagal checkout.');
                }
            } catch(e) { showFlash('error', 'Terjadi kesalahan.'); }
        }

        loadWishlist();
    </script>
</body>
</html>
