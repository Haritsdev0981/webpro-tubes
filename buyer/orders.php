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
    <title>Order Saya - Teloved</title>
    <link rel="stylesheet" href="../assets/css/buyer.css">
</head>
<body>
    <nav class="seller-nav">
        <div class="nav-brand"><span>♻️</span><a href="../index.php">TELOVED</a></div>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="../products.php">Belanja</a>
            <a href="wishlist.php">Wishlist ❤️</a>
            <a href="orders.php" class="active">Order Saya</a>
        </div>
        <a href="../logout.php" class="nav-logout">Logout</a>
    </nav>

    <div class="buyer-page">
        <div class="page-hd">
            <div><h1>Order Saya 🛒</h1><p>Tracking semua pesanan kamu</p></div>
        </div>
        <div id="flash-area"></div>
        <div id="orders-container">
            <div class="loading">Memuat order...</div>
        </div>
    </div>

    <!-- Review Modal -->
    <div class="modal-overlay" id="modalReview" style="display:none">
        <div class="modal-box">
            <div class="modal-head">
                <h3>Beri Ulasan</h3>
                <button onclick="closeReview()">✕</button>
            </div>
            <input type="hidden" id="review_product_id">
            <input type="hidden" id="review_checkout_id">
            <div class="form-group">
                <label>Rating</label>
                <div class="star-rating" id="starRating">
                    <button type="button" onclick="setRating(1)">☆</button>
                    <button type="button" onclick="setRating(2)">☆</button>
                    <button type="button" onclick="setRating(3)">☆</button>
                    <button type="button" onclick="setRating(4)">☆</button>
                    <button type="button" onclick="setRating(5)">☆</button>
                </div>
                <input type="hidden" id="rating_val" value="0">
            </div>
            <div class="form-group">
                <label>Komentar</label>
                <textarea id="review_comment" rows="3" placeholder="Bagaimana pengalaman kamu dengan produk ini?"></textarea>
            </div>
            <div class="modal-actions">
                <button class="btn-secondary" onclick="closeReview()">Batal</button>
                <button class="btn-primary" onclick="submitReview()">Kirim Ulasan</button>
            </div>
        </div>
    </div>

    <!-- Edit Review Modal -->
<div class="modal-overlay" id="modalEditReview" style="display:none">
    <div class="modal-box">
        <div class="modal-head">
            <h3>Edit Ulasan</h3>
            <button onclick="closeEditReview()">✕</button>
        </div>
        <input type="hidden" id="edit_review_id">
        <div class="form-group">
            <label>Rating</label>
            <div class="star-rating" id="starRatingEdit"></div>
            <input type="hidden" id="edit_rating_val" value="0">
        </div>
        <div class="form-group">
            <label>Komentar</label>
            <textarea id="edit_review_comment" rows="3"></textarea>
        </div>
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:10px 14px;font-size:13px;color:#856404;margin-bottom:12px">
            ⚠️ Perhatian: ulasan hanya bisa diedit <strong>satu kali</strong>. Setelah disimpan tidak bisa diubah lagi.
        </div>
        <div class="modal-actions">
            <button class="btn-secondary" onclick="closeEditReview()">Batal</button>
            <button class="btn-primary" onclick="submitEditReview()">Simpan Perubahan</button>
        </div>
    </div>
</div>

    <script>
        const API_KEY = '<?= htmlspecialchars($user['api_key']) ?>';

        const statusBadge = {
            pending: '⏳ Pending', confirmed: '✅ Dikonfirmasi', processing: '🔄 Diproses',
            shipped: '🚚 Dikirim', completed: '✔️ Selesai', cancelled: '❌ Dibatalkan'
        };
        const statusColor = {
            pending: '#B7950B', confirmed: '#1A5276', processing: '#1A5276',
            shipped: '#1E8449', completed: '#1E8449', cancelled: '#DC3545'
        };

        function escHtml(str) { const d = document.createElement('div'); d.textContent = str || ''; return d.innerHTML; }
        function showFlash(type, msg) {
            const div = document.getElementById('flash-area');
            div.innerHTML = `<div class="flash-msg flash-${type}">${msg} <button onclick="this.parentElement.remove()">✕</button></div>`;
            setTimeout(() => div.innerHTML = '', 4000);
        }

        async function loadOrders() {
            const container = document.getElementById('orders-container');
            try {
                const res = await fetch('../api/orders.php', { headers: { 'X-API-Key': API_KEY } });
                const data = await res.json();
                const orders = data.data || [];

                if (!orders.length) {
                    container.innerHTML = '<div class="empty">Belum ada order. <a href="../products.php">Mulai belanja</a></div>';
                    return;
                }

                container.innerHTML = orders.map(o => `
                    <div style="background:#fff;border-radius:10px;box-shadow:0 2px 12px rgba(0,0,0,0.08);padding:20px;margin-bottom:16px;border-left:4px solid ${statusColor[o.status] || '#adb5bd'}">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px">
                            <div>
                                <h3 style="font-size:16px;font-weight:700;margin-bottom:4px">${escHtml(o.product_name)}</h3>
                                <div style="font-size:13px;color:#adb5bd">Seller: ${escHtml(o.seller_name)} • Qty: ${o.quantity}</div>
                            </div>
                            <div style="text-align:right">
                                <div style="font-size:18px;font-weight:700;color:#1B4332">Rp ${Number(o.total_price).toLocaleString('id-ID')}</div>
                                <div style="font-size:12px;color:${statusColor[o.status] || '#adb5bd'};font-weight:600">${statusBadge[o.status] || o.status}</div>
                            </div>
                        </div>
                        <div style="margin-top:12px;padding-top:12px;border-top:1px solid #dee2e6;font-size:12px;color:#6c757d">
                            <div>📍 ${escHtml(o.shipping_address)}</div>
                            <div style="margin-top:4px">💳 ${escHtml(o.payment_method)} • 📅 ${new Date(o.created_at).toLocaleDateString('id-ID')}</div>
                            ${o.tracking_number ? `<div style="margin-top:4px">🚚 Resi: <strong>${escHtml(o.tracking_number)}</strong></div>` : ''}
                            ${o.seller_note ? `<div style="margin-top:4px">📝 Catatan seller: ${escHtml(o.seller_note)}</div>` : ''}
                        </div>
                        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
                            ${o.status === 'pending' ? `
                                <button class="btn-secondary" style="font-size:12px;padding:6px 12px" onclick="cancelOrder(${o.id})">❌ Batalkan</button>
                            ` : ''}
                            ${o.status === 'completed' ? (() => {
    if (!o.review) {
        return `<button class="btn-primary" style="font-size:12px;padding:6px 12px" 
                onclick="openReview(${o.product_id}, ${o.id})">⭐ Beri Ulasan</button>`;
    } else if (o.review.is_edited == 0) {
        return `<button class="btn-primary" style="font-size:12px;padding:6px 12px;background:#f59e0b" 
                onclick="openEditReview(${o.review.id}, ${o.review.rating}, \`${escHtml(o.review.comment)}\`)">✏️ Edit Ulasan</button>`;
    } else {
        return `<span style="font-size:12px;color:#6c757d;padding:6px 0;display:inline-block">
                    ✅ Sudah diulas &nbsp;<span style="color:#dc3545;font-size:11px">(tidak bisa diedit lagi)</span>
                </span>`;
    }
})() : ''}
                        </div>
                    </div>
                `).join('');
            } catch(e) {
                container.innerHTML = '<div class="empty">Gagal memuat order.</div>';
            }
        }

        async function cancelOrder(id) {
            if (!confirm('Batalkan order ini?')) return;
            try {
                const res = await fetch(`../api/orders.php?id=${id}`, {
                    method: 'PUT', headers: { 'X-API-Key': API_KEY, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ cancel: true })
                });
                const data = await res.json();
                if (data.success) { showFlash('success', 'Order dibatalkan.'); loadOrders(); }
                else showFlash('error', data.error || 'Gagal membatalkan.');
            } catch(e) { showFlash('error', 'Terjadi kesalahan.'); }
        }

        let currentRating = 0;
        function openReview(productId, checkoutId) {
            document.getElementById('review_product_id').value = productId;
            document.getElementById('review_checkout_id').value = checkoutId;
            document.getElementById('review_comment').value = '';
            setRating(0);
            document.getElementById('modalReview').style.display = 'flex';
        }
        function closeReview() { document.getElementById('modalReview').style.display = 'none'; }

        function setRating(n) {
            currentRating = n;
            document.getElementById('rating_val').value = n;
            const stars = document.querySelectorAll('#starRating button');
            stars.forEach((s, i) => { s.textContent = i < n ? '⭐' : '☆'; });
        }

        async function submitReview() {
            const productId = document.getElementById('review_product_id').value;
            const checkoutId = document.getElementById('review_checkout_id').value;
            const comment = document.getElementById('review_comment').value.trim();
            if (!currentRating) { alert('Pilih rating terlebih dahulu.'); return; }

            try {
                const res = await fetch('../api/reviews.php', {
                    method: 'POST', headers: { 'X-API-Key': API_KEY, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ product_id: productId, checkout_id: checkoutId, rating: currentRating, comment })
                });
                const data = await res.json();
                if (data.success) {
                    closeReview();
                    showFlash('success', '⭐ Ulasan berhasil dikirim!');
                    loadOrders();
                } else showFlash('error', data.error || 'Gagal mengirim ulasan.');
            } catch(e) { showFlash('error', 'Terjadi kesalahan.'); }
        }

        let currentEditRating = 0;

function openEditReview(reviewId, currentRating, currentComment) {
    document.getElementById('edit_review_id').value = reviewId;
    document.getElementById('edit_review_comment').value = currentComment;
    setEditRating(currentRating);
    document.getElementById('modalEditReview').style.display = 'flex';
}
function closeEditReview() { document.getElementById('modalEditReview').style.display = 'none'; }

function setEditRating(n) {
    currentEditRating = n;
    document.getElementById('edit_rating_val').value = n;
    const container = document.getElementById('starRatingEdit');
    container.innerHTML = [1,2,3,4,5].map(i =>
        `<button type="button" onclick="setEditRating(${i})">${i <= n ? '⭐' : '☆'}</button>`
    ).join('');
}

async function submitEditReview() {
    const id = document.getElementById('edit_review_id').value;
    const comment = document.getElementById('edit_review_comment').value.trim();
    if (!currentEditRating) { alert('Pilih rating terlebih dahulu.'); return; }
    if (!confirm('Yakin ingin menyimpan? Ulasan hanya bisa diedit satu kali.')) return;

    try {
        const res = await fetch(`../api/reviews.php?id=${id}`, {
            method: 'PUT',
            headers: { 'X-API-Key': API_KEY, 'Content-Type': 'application/json' },
            body: JSON.stringify({ rating: currentEditRating, comment })
        });
        const data = await res.json();
        if (data.success) {
            closeEditReview();
            showFlash('success', '✏️ Ulasan berhasil diperbarui!');
            loadOrders();
        } else {
            // Jika sudah pernah diedit, tampilkan alert seperti di gambar
            showFlash('error', data.error || 'Gagal memperbarui ulasan.');
            closeEditReview();
            loadOrders(); // refresh agar tombol berubah jadi "tidak bisa diedit"
        }
    } catch(e) { showFlash('error', 'Terjadi kesalahan.'); }
}

        loadOrders();
    </script>

    <style>
        .star-rating { display: flex; gap: 4px; margin-top: 4px; }
        .star-rating button { background: none; border: none; font-size: 28px; cursor: pointer; padding: 0; }
        .star-rating button:hover { transform: scale(1.2); }
    </style>
</body>
</html>
